<?php

namespace App\Services\Social;

use App\Models\Central\PublicAccount;
use App\Models\Social\LegalAcceptance;
use App\Models\Social\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Documents légaux versionnés et acceptations (2026-11-09).
 *
 * Règles :
 *  - seuls les documents PUBLIÉS sont demandés ; un modèle (« À FAIRE VALIDER ») n'est
 *    jamais montré comme définitif ni demandé aux utilisateurs ;
 *  - la politique de confidentialité est « portée à la connaissance » (acknowledge), pas
 *    « acceptée » ; un consentement spécifique (consent) est facultatif et retirable ;
 *  - une version est due par un compte s'il ne l'a pas encore acceptée ET (si la version
 *    exige une réacceptation OU si le compte a été créé après sa publication) : les comptes
 *    existants ne sont pas bloqués par une version qui n'exige pas de réacceptation ;
 *  - aucune case n'est jamais pré-cochée ; chaque acceptation est tracée (date, version,
 *    canal, adresse IP), sans jamais écraser l'historique.
 */
class LegalService
{
    /** Types demandés à l'inscription et dans l'écran « Conditions » (ordre d'affichage). */
    public const REQUIRED_TYPES = ['terms', 'privacy', 'community'];

    public static function ready(): bool
    {
        static $ok = null;

        return $ok ??= Schema::connection('central')->hasTable('legal_documents') && Schema::connection('central')->hasTable('legal_acceptances');
    }

    /** Version publiée la plus récente de chaque type requis. */
    public function current(): Collection
    {
        if (! self::ready()) {
            return collect();
        }

        return LegalDocument::where('status', 'published')->whereNotNull('published_at')->whereIn('type', self::REQUIRED_TYPES)
            ->orderByDesc('published_at')->orderByDesc('id')->get()->unique('type')
            ->sortBy(fn ($d) => array_search($d->type, self::REQUIRED_TYPES, true))->values();
    }

    public function currentOf(string $type): ?LegalDocument
    {
        return self::ready() ? LegalDocument::where('type', $type)->where('status', 'published')->whereNotNull('published_at')
            ->orderByDesc('published_at')->orderByDesc('id')->first() : null;
    }

    /** Documents dus par un compte (voir la règle en tête de classe). */
    public function pendingFor(?PublicAccount $account): Collection
    {
        if (! $account || ! self::ready()) {
            return collect();
        }
        $docs = $this->current();
        $done = LegalAcceptance::where('public_account_id', $account->id)->whereIn('legal_document_id', $docs->pluck('id'))
            ->whereIn('action', ['accepted', 'acknowledged'])->pluck('legal_document_id')->all();

        return $docs->reject(fn ($d) => in_array($d->id, $done, true))
            ->filter(fn ($d) => $d->requires_reacceptance || ($account->created_at && $account->created_at->gte($d->published_at)))->values();
    }

    /** Dernière acceptation de chaque document courant (pour l'écran « Conditions »). */
    public function acceptedMap(PublicAccount $account): array
    {
        return LegalAcceptance::where('public_account_id', $account->id)->whereIn('action', ['accepted', 'acknowledged'])
            ->orderBy('id')->get()->keyBy('legal_document_id')->all();
    }

    /** Enregistre l'acceptation / la prise de connaissance de documents. */
    public function record(PublicAccount $account, iterable $documents, Request $request, ?string $channel = null): void
    {
        foreach ($documents as $d) {
            LegalAcceptance::create([
                'public_account_id' => $account->id, 'legal_document_id' => $d->id, 'type' => $d->type, 'version' => $d->version,
                'action' => $d->action === 'acknowledge' ? 'acknowledged' : 'accepted',
                'channel' => $channel ?? self::channel($request),
                'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ]);
        }
    }

    /** « android » quand la demande vient de l'application (formulaires du parcours /app), sinon « web ». */
    public static function channel(Request $request): string
    {
        return $request->input('app') === '1' || $request->is('app', 'app/*') ? 'android' : 'web';
    }

    /**
     * Mise en forme sûre d'un texte légal : « ## Titre », listes « - », paragraphes.
     * Tout est échappé ; aucun HTML saisi n'est interprété.
     */
    public static function render(string $body): string
    {
        $html = ''; $list = false;
        foreach (preg_split('/\R/', $body) as $line) {
            $t = trim($line);
            if (str_starts_with($t, '- ')) {
                if (! $list) { $html .= '<ul class="mx-legal__list">'; $list = true; }
                $html .= '<li>' . e(mb_substr($t, 2)) . '</li>';
                continue;
            }
            if ($list) { $html .= '</ul>'; $list = false; }
            if ($t === '') {
                continue;
            }
            $html .= str_starts_with($t, '## ') ? '<h2>' . e(mb_substr($t, 3)) . '</h2>' : '<p>' . e($t) . '</p>';
        }

        return $html . ($list ? '</ul>' : '');
    }

    /**
     * Textes de départ (idempotent) :
     *  - Politique de confidentialité 1.0 et Règles communautaires 1.0 : PUBLIÉES, reprises
     *    mot pour mot des pages déjà en ligne (/confidentialite, /a-propos) ;
     *  - Conditions d'utilisation 1.0 et Politique de confidentialité 2.0 complète :
     *    MODÈLES en brouillon, avec les informations manquantes signalées, à faire valider
     *    puis publier depuis l'espace super-admin.
     */
    public function seedDefaults(): void
    {
        if (! self::ready()) {
            return;
        }
        $now = now();
        foreach (self::defaults() as $d) {
            if (! LegalDocument::where('type', $d['type'])->where('version', $d['version'])->exists()) {
                LegalDocument::create($d + ['published_at' => $d['status'] === 'published' ? $now : null]);
            }
        }
    }

    public static function defaults(): array
    {
        $todo = '[À COMPLÉTER PAR L’EXPLOITANT]';

        return [
            ['type' => 'privacy', 'version' => '1.0', 'action' => 'acknowledge', 'status' => 'published', 'is_template' => false,
                'title' => 'Politique de confidentialité',
                'summary' => 'Résumé des règles appliquées par la plateforme (texte en vigueur sur le site).',
                'body' => implode("\n", [
                    'Résumé des règles appliquées par la plateforme. Le texte juridique complet est fourni par l’exploitant du réseau.',
                    '## Données collectées',
                    '- Compte : nom, prénom, téléphone, e-mail facultatif, mot de passe (stocké sous forme chiffrée irréversible).',
                    '- Profil facultatif : photo, ville, présentation, centres d’intérêt, visibilité choisie.',
                    '- Activité : publications, commentaires, réactions, enregistrements, abonnements, signalements.',
                    '- Messages de contact et messages privés : visibles uniquement par vous et la mutuelle concernée.',
                    '- Demandes d’adhésion en ligne : identité, coordonnées, composition du foyer et pièces jointes que vous choisissez d’envoyer. Elles sont transmises uniquement à la mutuelle choisie ; les pièces sont conservées hors de l’espace public du site et ne sont lisibles que par vous et les agents habilités de cette mutuelle.',
                    '## Ce que nous ne collectons pas sur le réseau social',
                    'Aucune donnée médicale n’est demandée. Les dossiers d’adhérents, cotisations et prestations restent dans l’espace de gestion sécurisé de chaque mutuelle, séparé du réseau public, et ne sont jamais publiés.',
                    '## Vos choix',
                    '- Rendre votre profil privé, modifier ou retirer votre photo (Paramètres).',
                    '- Supprimer vos publications et commentaires à tout moment.',
                    '- Ne plus suivre ou masquer une mutuelle.',
                    '- Supprimer vous-même votre compte et vos données du réseau social (Paramètres → Supprimer mon compte).',
                    '## Photos',
                    'Les images publiées sont réencodées par le serveur : les métadonnées (position GPS, appareil…) sont supprimées.',
                ])],
            ['type' => 'community', 'version' => '1.0', 'action' => 'accept', 'status' => 'published', 'is_template' => false,
                'title' => 'Règles communautaires',
                'summary' => 'Les règles de la communauté MX-CONNECT (texte en vigueur sur le site).',
                'body' => implode("\n", [
                    '## Règles de la communauté',
                    '- Pas d’information médicale personnelle (la vôtre ou celle d’autrui) dans les publications et commentaires.',
                    '- Respect, bienveillance : pas de propos haineux, de harcèlement ni de spam.',
                    '- Pas de fausses informations de santé ; citez des sources fiables.',
                    '- Signalez tout contenu problématique avec le bouton « Signaler ».',
                    '## Suivre n’est pas adhérer',
                    'Suivre une mutuelle permet seulement de voir ses publications. L’adhésion (devenir bénéficiaire) est une démarche distincte, réalisée auprès de la mutuelle.',
                    '## Organisations vérifiées',
                    'Le badge de vérification signale une organisation dont l’inscription a été contrôlée et validée par l’administration du réseau MX-CONNECT (procédure d’agrément), et non simplement déclarée par l’organisation. Les contenus marqués « Démo » sont fictifs et servent uniquement à la démonstration.',
                ])],
            ['type' => 'terms', 'version' => '1.0', 'action' => 'accept', 'status' => 'draft', 'is_template' => true,
                'title' => 'Conditions d’utilisation',
                'summary' => 'MODÈLE — À FAIRE VALIDER par l’exploitant et un juriste avant publication.',
                'body' => implode("\n", [
                    'MODÈLE — À FAIRE VALIDER. Ce texte n’est pas en vigueur tant qu’il n’est pas publié par l’administration du réseau.',
                    '## 1. Objet',
                    'Les présentes conditions encadrent l’utilisation du réseau social MX-CONNECT (site et application Android), édité par ' . $todo . ' (raison sociale, forme juridique, numéro d’immatriculation, adresse).',
                    '## 2. Accès et compte',
                    '- L’inscription est gratuite. Elle requiert un nom, un prénom et un e-mail ou un numéro de téléphone.',
                    '- Âge minimum : ' . $todo . '.',
                    '- Vous êtes responsable de la confidentialité de votre mot de passe.',
                    '## 3. Ce que permet le service',
                    '- Suivre des mutuelles et des organisations, publier, commenter, réagir, partager.',
                    '- Proposer un engagement (« Je m’engage ») ou un soutien (« Soutenir ou devenir partenaire ») à un projet ; MX-CONNECT transmet la demande à l’organisation et ne détient aucun fonds.',
                    '- Échanger par messages avec une mutuelle.',
                    '## 4. Règles de conduite',
                    'Les Règles communautaires s’appliquent. Aucune information médicale personnelle ne doit être publiée.',
                    '## 5. Contenus',
                    'Vous restez titulaire de vos contenus. Vous autorisez leur affichage sur le réseau selon la visibilité choisie. ' . $todo . ' (licence, durée, retrait).',
                    '## 6. Modération et suspension',
                    'Les contenus signalés peuvent être masqués ; un compte peut être suspendu en cas de manquement, avec indication du motif.',
                    '## 7. Responsabilité',
                    $todo . ' (limites de responsabilité de l’éditeur et des organisations).',
                    '## 8. Suppression du compte',
                    'Vous pouvez supprimer votre compte à tout moment depuis Paramètres → Supprimer mon compte.',
                    '## 9. Modification des conditions',
                    'Toute nouvelle version est publiée avec sa date. Si elle exige votre accord, il vous sera demandé à la connexion.',
                    '## 10. Droit applicable et contact',
                    $todo . ' (droit applicable, juridiction, adresse de contact).',
                ])],
            ['type' => 'privacy', 'version' => '2.0', 'action' => 'acknowledge', 'status' => 'draft', 'is_template' => true, 'requires_reacceptance' => true,
                'title' => 'Politique de confidentialité',
                'summary' => 'MODÈLE COMPLET — À FAIRE VALIDER : identité du responsable, durées, sous-traitants, droits.',
                'body' => implode("\n", [
                    'MODÈLE — À FAIRE VALIDER. La version en vigueur reste la version 1.0 tant que celle-ci n’est pas publiée.',
                    '## Responsable du traitement',
                    $todo . ' (raison sociale, adresse, contact ; délégué à la protection des données s’il y en a un).',
                    '## Données traitées et finalités',
                    '- Compte (nom, prénom, e-mail et/ou téléphone, mot de passe chiffré) : création et sécurité du compte.',
                    '- Profil et activité (publications, commentaires, réactions, abonnements, signalements) : fonctionnement du réseau social.',
                    '- Messages et demandes (engagement, soutien, candidature, adhésion) : transmission à l’organisation choisie uniquement.',
                    '- Journal technique (adresse IP, navigateur, date) : sécurité, prévention des abus, preuve des acceptations.',
                    '- Aucune donnée médicale n’est collectée sur le réseau social.',
                    '## Base légale',
                    $todo . ' (exécution du service, intérêt légitime, consentement pour les traitements facultatifs — à confirmer selon la loi applicable, par exemple la loi camerounaise n° 2024-017 relative à la protection des données à caractère personnel).',
                    '## Durées de conservation',
                    $todo . ' (compte actif, après suppression, journaux techniques, pièces d’adhésion).',
                    '## Destinataires et sous-traitants',
                    '- Hébergement : ' . $todo . ' (hébergeur, pays).',
                    '- Envoi des e-mails : ' . $todo . '.',
                    '- Les organisations ne reçoivent que les demandes qui leur sont adressées.',
                    '## Transferts hors du pays',
                    $todo . '.',
                    '## Vos droits',
                    'Accès, rectification, suppression (directement depuis Paramètres), opposition, retrait d’un consentement facultatif à tout moment. Contact : ' . $todo . '. Réclamation auprès de l’autorité de protection des données compétente : ' . $todo . '.',
                    '## Application Android',
                    'L’application n’accède ni à vos contacts, ni à votre position, ni à vos SMS. Les photos ne sont lues que lorsque vous choisissez un fichier à publier.',
                ])],
        ];
    }
}
