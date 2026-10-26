<?php

namespace App\Services\Social;

use App\Models\Central\Mutual;
use App\Models\Central\NetworkUser;
use App\Models\Central\PublicAccount;
use App\Models\Social\Opportunity;
use App\Models\Social\Project;
use App\Models\Social\ProjectIndicator;
use App\Models\Social\ProjectUpdate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Projets (avec résultats) et opportunités publiés par une page du réseau —
 * mutuelle ou page créée par un membre. Règles :
 *  - un résultat chiffré est « déclaré » par l'organisation ; seul le réseau le « vérifie »,
 *    et toute modification du chiffre annule la vérification ;
 *  - la rémunération d'une offre est un texte libre (devise de l'organisation, aucune conversion) ;
 *  - aucune collecte de fonds : le soutien financier se règle hors plateforme.
 */
class ProjectService
{
    public function __construct(private MediaService $media, private PostService $posts, private NotificationService $notifications) {}

    // --- Règles de validation (partagées entre le réseau et le back-office) ---

    public static function projectRules(): array
    {
        $maxKb = (int) config('mxconnect.social.max_upload_kb', 6144);

        return [
            'title'            => ['required', 'string', 'min:5', 'max:180'],
            'summary'          => ['required', 'string', 'min:10', 'max:300'],
            'description'      => ['nullable', 'string', 'max:8000'],
            'objectives'       => ['nullable', 'string', 'max:3000'],
            'zone'             => ['nullable', 'string', 'max:255'],
            'region'           => ['nullable', 'string', 'max:120'],
            'city'             => ['nullable', 'string', 'max:120'],
            'phase'            => ['required', Rule::in(array_keys(Project::PHASES))],
            'starts_on'        => ['nullable', 'date'],
            'ends_on'          => ['nullable', 'date', 'after_or_equal:starts_on'],
            'seeks_volunteers' => ['nullable', 'boolean'],
            'volunteer_needs'  => ['nullable', 'string', 'max:2000', 'required_if:seeks_volunteers,1'],
            'seeks_partners'   => ['nullable', 'boolean'],
            'partner_needs'    => ['nullable', 'string', 'max:2000', 'required_if:seeks_partners,1'],
            'partners'         => ['nullable', 'string', 'max:2000'],
            'status'           => ['required', Rule::in(['published', 'draft'])],
            'cover'            => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:' . $maxKb],
            'remove_cover'     => ['nullable', 'boolean'],
            'announce'         => ['nullable', 'boolean'],
        ];
    }

    public static function projectMessages(): array
    {
        return [
            'volunteer_needs.required_if' => 'Décrivez ce que les bénévoles pourront faire.',
            'partner_needs.required_if'   => 'Décrivez le soutien recherché (technique, matériel, expertise…).',
            'ends_on.after_or_equal'      => 'La date de fin doit suivre la date de début.',
        ];
    }

    public static function indicatorRules(): array
    {
        return [
            'label'       => ['required', 'string', 'max:160'],
            'value'       => ['required', 'numeric', 'min:0', 'max:99999999999999'],
            'target'      => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'unit'        => ['nullable', 'string', 'max:40'],
            'period'      => ['nullable', 'string', 'max:60'],
            'source_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function opportunityRules(): array
    {
        return [
            'category'           => ['required', Rule::in(array_keys(Opportunity::CATEGORIES))],
            'title'              => ['required', 'string', 'min:5', 'max:180'],
            'description'        => ['required', 'string', 'min:20', 'max:8000'],
            'requirements'       => ['nullable', 'string', 'max:4000'],
            'contract_type'      => ['nullable', Rule::in(array_keys(Opportunity::CONTRACTS))],
            'workplace'          => ['nullable', Rule::in(array_keys(Opportunity::WORKPLACES))],
            'region'             => ['nullable', 'string', 'max:120'],
            'city'               => ['nullable', 'string', 'max:120'],
            'country_id'         => ['nullable', 'integer', 'exists:central.countries,id'],
            'remuneration'       => ['nullable', 'string', 'max:120'],
            'positions'          => ['nullable', 'integer', 'min:1', 'max:5000'],
            'deadline_on'        => ['nullable', 'date'],
            'apply_mode'         => ['required', Rule::in(array_keys(Opportunity::APPLY_MODES))],
            'apply_url'          => ['nullable', 'url:https,http', 'max:255', 'required_if:apply_mode,external'],
            'apply_instructions' => ['nullable', 'string', 'max:500'],
            'project_id'         => ['nullable', 'integer'],
            'status'             => ['required', Rule::in(['published', 'draft'])],
            'announce'           => ['nullable', 'boolean'],
        ];
    }

    public static function opportunityMessages(): array
    {
        return ['apply_url.required_if' => 'Indiquez l’adresse du site où postuler.'];
    }

    // --- Projets ---

    /** Crée ou met à jour un projet. $data provient de projectRules(). */
    public function saveProject(Mutual $page, array $data, ?UploadedFile $cover, ?Project $project = null,
                                ?PublicAccount $by = null, ?int $tenantUserId = null, ?string $authorLabel = null): Project
    {
        $fields = collect($data)->only(['title', 'summary', 'description', 'objectives', 'zone', 'region', 'city', 'phase',
            'starts_on', 'ends_on', 'volunteer_needs', 'partner_needs', 'partners', 'status'])->all();
        $fields['seeks_volunteers'] = ! empty($data['seeks_volunteers']);
        $fields['seeks_partners'] = ! empty($data['seeks_partners']);
        if (! $fields['seeks_volunteers']) { $fields['volunteer_needs'] = null; }
        if (! $fields['seeks_partners']) { $fields['partner_needs'] = null; }

        $isNew = ! $project;
        $wasPublished = $project && $project->published_at;
        $oldCover = $project?->cover_path;
        $newCover = null;
        if ($cover) {
            $img = $this->media->storeImage($cover, "social/projects/{$page->id}", 1600);
            $this->media->delete($img['thumb_path']);
            $newCover = $img['path'];
        }

        try {
            if ($isNew) {
                $project = Project::create($fields + [
                    'mutual_id' => $page->id, 'slug' => Project::uniqueSlug($data['title']),
                    'created_by_account_id' => $by?->id, 'tenant_user_id' => $tenantUserId,
                    'is_demo' => (bool) $page->profile?->is_demo,
                ]);
            } else {
                // Un projet masqué par la modération le reste : la page ne peut pas lever ce statut.
                if ($project->status === 'hidden') {
                    unset($fields['status']);
                }
                $project->fill($fields);
            }
            if ($newCover) {
                $project->cover_path = $newCover;
            } elseif (! empty($data['remove_cover'])) {
                $project->cover_path = null;
            }
            if ($project->status === 'published' && ! $project->published_at) {
                $project->published_at = now();
            }
            $project->save();
        } catch (\Throwable $e) {
            $this->media->delete($newCover);
            throw $e;
        }
        if ($oldCover && $oldCover !== $project->cover_path) {
            $this->media->delete($oldCover);
        }

        // Première publication : annonce facultative dans le fil, avec le bouton « Voir le projet et les résultats ».
        if (! $wasPublished && $project->status === 'published' && ! empty($data['announce'])) {
            $this->announce($page, $by, $tenantUserId, $authorLabel, 'activite',
                "Nouveau projet : {$project->title}\n\n{$project->summary}", ['project_id' => $project->id]);
        }

        return $project;
    }

    public function deleteProject(Project $project): void
    {
        $cover = $project->cover_path;
        $project->update(['cover_path' => null]);
        $project->delete();
        $this->media->delete($cover);
    }

    /** Ajoute ou modifie un résultat. Un chiffre modifié perd sa vérification. */
    public function saveIndicator(Project $project, array $data, ?ProjectIndicator $indicator = null): ProjectIndicator
    {
        $fields = collect($data)->only(['label', 'value', 'target', 'unit', 'period', 'source_note'])->all();
        if (! $indicator) {
            if ($project->indicators()->count() >= 20) {
                throw \Illuminate\Validation\ValidationException::withMessages(['label' => '20 résultats maximum par projet.']);
            }

            return ProjectIndicator::create($fields + ['project_id' => $project->id, 'position' => (int) $project->indicators()->max('position') + 1]);
        }

        $indicator->fill($fields);
        if ($indicator->isVerified() && $indicator->isDirty(['label', 'value', 'target', 'unit', 'period'])) {
            $indicator->forceFill(['verified_at' => null, 'verified_by' => null, 'verification_note' => null]);
        }
        $indicator->save();

        return $indicator;
    }

    public function addUpdate(Project $project, string $title, string $body, bool $announce, Mutual $page,
                              ?PublicAccount $by = null, ?int $tenantUserId = null, ?string $authorLabel = null): ProjectUpdate
    {
        $update = ProjectUpdate::create([
            'project_id' => $project->id, 'title' => trim($title), 'body' => trim($body),
            'created_by_account_id' => $by?->id, 'tenant_user_id' => $tenantUserId, 'author_label' => $authorLabel,
        ]);
        if ($announce && $project->status === 'published') {
            $this->announce($page, $by, $tenantUserId, $authorLabel, 'activite',
                "{$project->title} — {$update->title}\n\n" . \Illuminate\Support\Str::limit($update->body, 600), ['project_id' => $project->id]);
        }

        return $update;
    }

    /** Vérification d'un résultat par l'administration du réseau (jamais par la page elle-même). */
    public function verifyIndicator(ProjectIndicator $indicator, NetworkUser $by, ?string $note): void
    {
        $indicator->forceFill(['verified_at' => now(), 'verified_by' => $by->id, 'verification_note' => $note ? trim($note) : null])->save();
        $project = $indicator->project;
        foreach (PageService::managerIds($project->mutual_id) as $id) {
            $this->notifications->notify($id, 'resultat', "Résultat vérifié : {$indicator->label}",
                "L’administration du réseau a vérifié ce résultat du projet {$project->title}.", route('social.project', $project));
        }
    }

    public function unverifyIndicator(ProjectIndicator $indicator): void
    {
        $indicator->forceFill(['verified_at' => null, 'verified_by' => null, 'verification_note' => null])->save();
    }

    // --- Opportunités ---

    public function saveOpportunity(Mutual $page, array $data, ?Opportunity $opportunity = null,
                                    ?PublicAccount $by = null, ?int $tenantUserId = null, ?string $authorLabel = null): Opportunity
    {
        $fields = collect($data)->only(['category', 'title', 'description', 'requirements', 'contract_type', 'workplace',
            'region', 'city', 'country_id', 'remuneration', 'positions', 'deadline_on', 'apply_mode', 'apply_url',
            'apply_instructions', 'status'])->all();
        if (($fields['apply_mode'] ?? 'platform') === 'platform') {
            $fields['apply_url'] = null;
        }
        // Le projet lié doit appartenir à la même page.
        $fields['project_id'] = ! empty($data['project_id'])
            ? Project::where('mutual_id', $page->id)->whereKey($data['project_id'])->value('id')
            : null;

        $wasPublished = $opportunity && $opportunity->published_at;
        if (! $opportunity) {
            $opportunity = new Opportunity([
                'mutual_id' => $page->id, 'slug' => Opportunity::uniqueSlug($data['title']),
                'availability' => 'open', 'requests_count' => 0,   // valeurs par défaut aussi sur l'objet, pas seulement en base
                'created_by_account_id' => $by?->id, 'tenant_user_id' => $tenantUserId,
                'is_demo' => (bool) $page->profile?->is_demo,
            ]);
        } elseif ($opportunity->status === 'hidden') {
            unset($fields['status']);
        }
        $opportunity->fill($fields);
        if ($opportunity->status === 'published' && ! $opportunity->published_at) {
            $opportunity->published_at = now();
        }
        $opportunity->save();

        if (! $wasPublished && $opportunity->status === 'published' && ! empty($data['announce'])) {
            $lines = [$opportunity->categoryLabel() . ' : ' . $opportunity->title];
            if ($place = $opportunity->place()) { $lines[] = $place; }
            if ($opportunity->deadline_on) { $lines[] = 'Date limite : ' . $opportunity->deadline_on->format('d/m/Y'); }
            $this->announce($page, $by, $tenantUserId, $authorLabel, 'annonce', implode("\n", $lines), ['opportunity_id' => $opportunity->id]);
        }

        return $opportunity;
    }

    public function setAvailability(Opportunity $opportunity, string $availability): void
    {
        abort_unless(array_key_exists($availability, Opportunity::AVAILABILITIES), 422);
        $opportunity->update(['availability' => $availability]);
    }

    /** Annonce dans le fil (publication ordinaire de la page, avec un lien vers le projet ou l'offre). */
    private function announce(Mutual $page, ?PublicAccount $by, ?int $tenantUserId, ?string $authorLabel, string $type, string $body, array $link): void
    {
        DB::connection('central')->transaction(function () use ($page, $by, $tenantUserId, $authorLabel, $type, $body, $link) {
            $post = $this->posts->create(['body' => $body, 'type' => $type], [], $by, $page, $tenantUserId, $authorLabel);
            $post->forceFill($link)->save();
        });
    }
}
