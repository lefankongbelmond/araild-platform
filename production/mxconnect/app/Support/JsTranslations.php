<?php

namespace App\Support;

/**
 * Textes affichés par le JavaScript du réseau social (js/mx-social.js, js/mx-social-plus.js) —
 * multilingue M2b, 2026-11-29.
 *
 * La clé est le texte français écrit dans le script (fonction T('…') côté JS). Le gabarit social
 * envoie à la page, dans <script type="application/json" id="mx-i18n">, la traduction de chacun de
 * ces textes dans la langue affichée (rien en français). Variables : « :nom », comme côté PHP.
 * Pluriel : « :count commentaire|:count commentaires » (le script choisit selon le nombre).
 * Règle : tout nouveau texte T('…') dans ces scripts doit être ajouté ici (test de couverture).
 */
final class JsTranslations
{
    public const SOCIAL = [
        ':count réaction|:count réactions',
        ':count commentaire|:count commentaires',
        'Retirer des enregistrements', 'Enregistrer', 'Publication enregistrée', 'Retirée des enregistrements',
        'Suivi', 'Suivre', 'Vous suivez maintenant :name.', 'Vous ne suivez plus :name.',
        'Ajouter un message à votre partage (facultatif) :', 'Publication partagée sur votre profil',
        'Lien copié', 'Copiez ce lien :',
        'Chargement impossible. Vérifiez votre connexion.',
        'Merci, votre signalement a été transmis.',
        'Patientez : la vidéo est encore en cours d’envoi.',
        'La publication n’a pas pu être envoyée.',
        'Fichiers trop lourds pour être envoyés ensemble. Réduisez le nombre ou la taille des photos.',
        'Votre session a expiré. Rechargez la page : votre texte est conservé en brouillon.',
        'Trop de publications en peu de temps. Réessayez dans une minute.',
        'La publication n’a pas pu être envoyée. Votre texte est conservé.',
        'Connexion interrompue. Votre texte est conservé : réessayez.',
        'Publiez soit des photos, soit une vidéo. Retirez d’abord la vidéo.',
        '6 images maximum par publication.',
        'Image trop lourde (6 Mo max) : :file', 'Format non accepté : :file',
        'Photo :number sélectionnée', 'Retirer la photo :number',
        'Fréquents', 'Visages', 'Gestes et personnes', 'Santé et solidarité', 'Événements', 'Symboles',
        'Aucun membre ou page trouvé', 'Tapez le nom d’un membre ou d’une page…',
        'Format vidéo non accepté : utilisez une vidéo MP4, MOV ou WebM.',
        'Vidéo trop lourde : 100 Mo maximum (:size Mo).',
        'Publiez soit des photos, soit une vidéo. Retirez d’abord les photos.',
        'Un réel dure au maximum :seconds secondes.',
        'Aperçu indisponible sur cet appareil : la vidéo sera tout de même envoyée.',
        'Envoi de la vidéo… :percent %',
        'Vidéo prête (:size Mo). Ajoutez un texte et publiez.',
        'Connexion instable, nouvel essai…',
        'Envoi de la vidéo interrompu. Réessayez.', 'Envoi de la vidéo impossible.',
        'Envoi impossible.', 'Erreur :code',
        'Son activé', 'Son coupé', 'Couper le son', 'Activer le son',
        'Une erreur est survenue.', 'Choisir un emoji',
    ];

    /** Traductions à envoyer à la page (vide en français : le script garde ses textes). */
    public static function payload(): array
    {
        if (app()->getLocale() === Locales::SOURCE) {
            return [];
        }
        $out = [];
        foreach (self::SOCIAL as $key) {
            $t = __($key);
            if ($t !== $key) {
                $out[$key] = $t;
            }
        }

        return $out;
    }
}
