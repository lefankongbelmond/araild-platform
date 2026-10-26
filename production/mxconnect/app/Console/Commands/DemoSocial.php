<?php

namespace App\Console\Commands;

use App\Models\Central\Country;
use App\Models\Central\Currency;
use App\Models\Central\Locale;
use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use App\Models\Social\Comment;
use App\Models\Social\Follow;
use App\Models\Social\MutualProfile;
use App\Models\Social\Post;
use App\Models\Social\Reaction;
use App\Services\Social\FeedService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Données de DÉMONSTRATION du réseau social, clairement identifiées (is_demo,
 * badge « Démo », noms suffixés « (démo) ») et entièrement supprimables.
 *
 * Les organisations créées n'ont NI base de données, NI sous-domaine, NI compte
 * de gestion : elles n'existent que sur le réseau social. Statut « pending »
 * (jamais affichées comme vérifiées) et exclues des sauvegardes, migrations,
 * quotas et statistiques via Mutual::operational().
 */
class DemoSocial extends Command
{
    protected $signature = 'mxconnect:demo-social {--remove : Supprimer toutes les données de démonstration}';
    protected $description = 'Crée (ou supprime avec --remove) des données de démonstration du réseau social.';

    private const ORGS = [
        ['demo_espoir', 'Mutuelle Espoir Santé (démo)', 'mutuelle-espoir-demo', 'Abidjan', 'Lagunes', 'mutuelle',
            'La santé de proximité pour les familles des quartiers.', '#0A5FB5',
            ['Consultations', 'Maternité', 'Pharmacie', 'Hospitalisation']],
        ['demo_solidarite', 'Mutuelle Solidarité Rurale (démo)', 'solidarite-rurale-demo', 'Bouaké', 'Vallée du Bandama', 'mutuelle',
            'Protéger les familles agricoles contre les dépenses de santé.', '#4F992F',
            ['Soins de base', 'Évacuations', 'Prévention paludisme']],
        ['demo_femmes', 'Association Santé des Femmes (démo)', 'sante-des-femmes-demo', 'Yamoussoukro', 'Lacs', 'organisation',
            'Information et accompagnement en santé maternelle.', '#2D8CFF',
            ['Causeries éducatives', 'Suivi prénatal', 'Vaccination']],
        ['demo_jeunes', 'Mutuelle des Jeunes Actifs (démo)', 'jeunes-actifs-demo', 'San-Pédro', 'Bas-Sassandra', 'mutuelle',
            'Une couverture santé simple et accessible pour les 18–35 ans.', '#021F5A',
            ['Consultations', 'Optique', 'Santé mentale (orientation)']],
    ];

    private const ACCOUNTS = [
        ['Aïcha', 'Koné (démo)', 'Abidjan'], ['Jean-Marc', 'Yao (démo)', 'Bouaké'],
        ['Fatou', 'Diallo (démo)', 'Yamoussoukro'], ['Serge', 'Kouassi (démo)', 'San-Pédro'],
    ];

    public function handle(FeedService $feed): int
    {
        if ($this->option('remove')) {
            return $this->remove();
        }
        if (MutualProfile::where('is_demo', true)->exists()) {
            $this->warn('Des données de démonstration existent déjà. Utilisez --remove pour les supprimer d’abord.');

            return self::FAILURE;
        }

        $country = Country::where('active', true)->orderBy('id')->first() ?? Country::first();
        if (! $country) {
            $this->error('Aucun pays configuré : terminez d’abord l’installation.');

            return self::FAILURE;
        }
        $currencyId = $country->default_currency_id ?? Currency::value('id');
        $localeId = $country->default_locale_id ?? Locale::value('id');

        DB::connection('central')->transaction(function () use ($country, $currencyId, $localeId) {
            $mutuals = [];
            foreach (self::ORGS as [$id, $name, $slug, $city, $region, $cat, $tagline, $color, $services]) {
                // Sans événements : aucune base de données ni domaine n'est créé.
                $m = Mutual::withoutEvents(fn () => Mutual::create([
                    'id' => $id, 'name' => $name, 'slug' => $slug, 'country_id' => $country->id,
                    'currency_id' => $currencyId, 'locale_id' => $localeId,
                    'region' => $region, 'city' => $city, 'status' => 'pending',
                ]));
                MutualProfile::create([
                    'mutual_id' => $m->id, 'category' => $cat, 'tagline' => $tagline, 'is_demo' => true,
                    'description' => "{$name} est une organisation FICTIVE créée pour la démonstration de MX Connect. "
                        . 'Elle illustre la présentation d’une mutuelle : mission, services, actualités et événements.',
                    'mission' => 'Rendre les soins accessibles grâce à la solidarité entre membres (texte de démonstration).',
                    'coverage_zone' => "{$city} et environs", 'services' => $services,
                    'phone' => null, 'email' => null, 'website' => null,
                    'cover_path' => $this->cover($id, $color),
                ]);
                $mutuals[] = $m;
            }

            $accounts = [];
            foreach (self::ACCOUNTS as $i => [$fn, $ln, $city]) {
                $accounts[] = PublicAccount::create([
                    'first_name' => $fn, 'last_name' => $ln, 'city' => $city, 'is_demo' => true,
                    'phone' => '+99900000000' . ($i + 1),            // indicatif inexistant : non joignable
                    'password' => Str::random(40),                    // connexion impossible
                    'bio' => 'Compte fictif de démonstration.',
                ]);
            }

            $samples = [
                ['campagne', 'sensibilisation', 'Campagne de sensibilisation au paludisme : distribution de moustiquaires et conseils de prévention dans nos points d’accueil tout ce mois-ci.'],
                ['evenement', 'depistage', 'Journée de dépistage gratuit de l’hypertension et du diabète, ouverte à tous. Venez avec votre carte de membre si vous en avez une.', 9, 'Centre communautaire'],
                ['annonce', null, 'Nos bureaux seront fermés lundi pour la formation annuelle du personnel. Le service d’urgence reste joignable.'],
                ['activite', 'education', 'Retour en images (texte) sur notre causerie éducative sur l’hygiène des mains avec les écoles du quartier. Merci aux bénévoles !'],
                ['evenement', 'communautaire', 'Assemblée générale ouverte : présentation du rapport d’activités et échanges avec les membres.', 20, 'Salle polyvalente'],
                ['offre', null, 'Nouvelle formule « Famille » : une cotisation unique pour couvrir jusqu’à 6 personnes. Contactez-nous pour les conditions.'],
            ];

            $n = 0;
            foreach ($mutuals as $mi => $m) {
                foreach ($samples as $si => $s) {
                    if (($mi + $si) % 2 === 1 && $si > 1) {
                        continue;   // varier le contenu d'une organisation à l'autre
                    }
                    $post = Post::create([
                        'mutual_id' => $m->id, 'type' => $s[0], 'category' => $s[1], 'body' => $s[2],
                        'event_starts_at' => isset($s[3]) ? now()->addDays($s[3] + $mi)->setTime(9, 0) : null,
                        'event_location' => isset($s[4]) ? $s[4] . ', ' . $m->city : null,
                        'visibility' => 'public', 'status' => 'published', 'is_demo' => true,
                        'published_at' => now()->subHours(3 + $n * 7),
                    ]);
                    $n++;
                    foreach ($accounts as $ai => $a) {
                        if (($ai + $n) % 3 !== 0) {
                            Reaction::create(['post_id' => $post->id, 'public_account_id' => $a->id,
                                'type' => array_keys(Post::REACTIONS)[($ai + $n) % 4]]);
                        }
                    }
                    if ($n % 3 === 0) {
                        Comment::create(['post_id' => $post->id, 'public_account_id' => $accounts[$n % 4]->id,
                            'body' => 'Merci pour l’information, je partage autour de moi.']);
                    }
                    $post->forceFill([
                        'reactions_count' => Reaction::where('post_id', $post->id)->count(),
                        'comments_count'  => Comment::where('post_id', $post->id)->count(),
                    ])->save();
                }
                foreach ($accounts as $ai => $a) {
                    if (($ai + $mi) % 2 === 0) {
                        Follow::create(['public_account_id' => $a->id, 'mutual_id' => $m->id]);
                    }
                }
            }
            Post::create([
                'public_account_id' => $accounts[0]->id, 'type' => 'texte', 'is_demo' => true,
                'body' => 'Bonne nouvelle : la journée de dépistage de samedi est ouverte à tous, même aux non-membres !',
                'visibility' => 'public', 'status' => 'published', 'published_at' => now()->subHour(),
            ]);

            // Un groupe public de démonstration, animé par la première organisation fictive.
            $group = \App\Models\Social\Group::create([
                'mutual_id' => $mutuals[0]->id, 'slug' => \App\Models\Social\Group::uniqueSlug('Mamans en bonne santé démo'),
                'name' => 'Mamans en bonne santé (démo)', 'topic' => 'education', 'visibility' => 'public', 'is_demo' => true,
                'description' => 'Groupe FICTIF de démonstration : conseils de prévention pour les mamans et les futures mamans.',
                'rules' => 'Bienveillance. Aucune information médicale personnelle. Pas de publicité.',
            ]);
            foreach (array_slice($accounts, 0, 3) as $a) {
                \App\Models\Social\GroupMember::create(['group_id' => $group->id, 'public_account_id' => $a->id, 'status' => 'active']);
            }
            foreach (['Quels aliments locaux recommandez-vous pour les enfants de moins de 5 ans ?',
                      'Rappel : la séance de vaccination du quartier a lieu jeudi matin au centre de santé.'] as $i => $body) {
                Post::create(['public_account_id' => $accounts[$i]->id, 'group_id' => $group->id, 'type' => 'texte', 'is_demo' => true,
                    'body' => $body, 'visibility' => 'public', 'status' => 'published', 'published_at' => now()->subHours(5 - $i)]);
            }
            $group->refreshCounters();
        });

        MutualProfile::where('is_demo', true)->pluck('mutual_id')->each(fn ($id) => $feed->refreshMutualCounters($id));
        cache()->forget('social:places');

        $this->info('Données de démonstration créées : ' . count(self::ORGS) . ' organisations fictives, '
            . count(self::ACCOUNTS) . ' comptes fictifs, publications marquées « Démo ».');
        $this->line('Pour tout supprimer : php artisan mxconnect:demo-social --remove (ou, sans terminal, le bouton « supprimer les données de démonstration » de la page /_installation).');

        return self::SUCCESS;
    }

    private function remove(): int
    {
        $ids = MutualProfile::where('is_demo', true)->pluck('mutual_id');
        DB::connection('central')->transaction(function () use ($ids) {
            Post::withTrashed()->where('is_demo', true)->get()->each(fn ($p) => $p->media()->delete());
            Post::withTrashed()->where('is_demo', true)->forceDelete();
            PublicAccount::where('is_demo', true)->delete();   // cascade : réactions, abonnements, commentaires
            // Sans événements : surtout ne pas tenter de supprimer une base de données.
            Mutual::withoutEvents(fn () => Mutual::whereIn('id', $ids)->get()->each->delete());
        });
        Storage::disk('social')->deleteDirectory('social/demo');
        cache()->forget('social:places');
        $this->info("Données de démonstration supprimées ({$ids->count()} organisation(s)).");

        return self::SUCCESS;
    }

    /** Bannière dégradée générée localement (aucune image externe). */
    private function cover(string $id, string $hex): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $w = 1200; $h = 400;
        $im = imagecreatetruecolor($w, $h);
        for ($x = 0; $x < $w; $x++) {
            $t = $x / $w;
            $c = imagecolorallocate($im, (int) (2 + ($r - 2) * $t), (int) (31 + ($g - 31) * $t), (int) (90 + ($b - 90) * $t));
            imageline($im, $x, 0, $x, $h, $c);
        }
        $white = imagecolorallocatealpha($im, 255, 255, 255, 110);
        for ($i = 0; $i < 6; $i++) {
            imagefilledellipse($im, 900 + $i * 40, 80 + $i * 30, 260 - $i * 30, 260 - $i * 30, $white);
        }
        $webp = \App\Services\Social\MediaService::webpSupported();
        ob_start(); $webp ? imagewebp($im, null, 80) : imagejpeg($im, null, 80); $data = ob_get_clean(); imagedestroy($im);
        $path = "social/demo/{$id}-cover." . ($webp ? 'webp' : 'jpg');
        Storage::disk('social')->put($path, $data);

        return $path;
    }
}
