<?php

namespace App\Services\Social;

use App\Enums\ProjectStatus;
use App\Models\Central\Organization;
use App\Models\Central\Project;
use App\Models\Central\ProjectIndicator;
use App\Models\Central\ProjectUpdate;
use App\Models\Central\PublicAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Projects behind "Voir le projet et les résultats". Indicators entered by the
 * organization are always stored as DECLARED (`is_verified` = false); verification
 * is a network-operator action, never available to the organization itself.
 */
class ProjectService
{
    private const EDITABLE = [
        'title', 'summary', 'description', 'objectives', 'zone', 'beneficiaries_summary',
        'status', 'progress_percent', 'start_date', 'end_date', 'partners',
    ];

    public function __construct(private OrganizationService $organizations) {}

    public function create(Organization $org, PublicAccount $actor, array $data, ?UploadedFile $cover = null): Project
    {
        $this->organizations->authorizeAdmin($org, $actor);

        return Project::create([
            ...$this->editable($data),
            'organization_id' => $org->getKey(),
            'slug' => $this->uniqueSlug($data['title']),
            'cover_path' => $cover?->store('projects/covers', 'public'),
        ])->refresh();
    }

    public function update(Project $project, PublicAccount $actor, array $data, ?UploadedFile $cover = null): Project
    {
        $this->organizations->authorizeAdmin($project->organization, $actor);

        $attributes = $this->editable($data);
        if ($cover) {
            if ($project->cover_path) {
                Storage::disk('public')->delete($project->cover_path);
            }
            $attributes['cover_path'] = $cover->store('projects/covers', 'public');
        }
        $project->update($attributes);

        return $project->refresh();
    }

    public function addIndicator(Project $project, PublicAccount $actor, array $data): ProjectIndicator
    {
        $this->organizations->authorizeAdmin($project->organization, $actor);

        return $project->indicators()->create([
            'label' => $data['label'],
            'value' => $data['value'],
            'unit' => $data['unit'] ?? null,
            'source_note' => $data['source_note'] ?? null,
            'is_verified' => false,
            'verified_by' => null,
            'verified_at' => null,
        ])->refresh();
    }

    public function addUpdate(Project $project, PublicAccount $actor, array $data): ProjectUpdate
    {
        $this->organizations->authorizeAdmin($project->organization, $actor);

        return $project->updates()->create([
            'organization_id' => $project->organization_id,
            'title' => $data['title'],
            'body' => $data['body'],
            'published_at' => now(),
        ])->refresh();
    }

    private function editable(array $data): array
    {
        $attributes = array_intersect_key($data, array_flip(self::EDITABLE));

        if (array_key_exists('status', $attributes)) {
            $attributes['status'] = ProjectStatus::from($attributes['status']);
        }
        if (array_key_exists('progress_percent', $attributes)) {
            $attributes['progress_percent'] = max(0, min(100, (int) $attributes['progress_percent']));
        }
        if (array_key_exists('partners', $attributes)) {
            $attributes['partners'] = collect(preg_split('/[\r\n,]+/', (string) $attributes['partners']))
                ->map(fn ($p) => trim($p))->filter()->unique()->take(20)->values()->all() ?: null;
        }

        return $attributes;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title) ?: 'projet', 200, '');
        if (in_array($base, config('mxconnect.social.reserved_slugs'), true)) {
            $base .= '-projet';
        }

        $slug = $base;
        $i = 2;
        while (Project::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
