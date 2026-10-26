<?php

namespace App\Models\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Projet publié par une page du réseau (mutuelle ou page créée par un membre).
 * Les résultats chiffrés sont DÉCLARÉS par l'organisation ; seul le réseau peut
 * les marquer « vérifiés » (voir ProjectIndicator).
 */
class Project extends Model
{
    use SoftDeletes;

    protected $connection = 'central';
    protected $table = 'social_projects';
    protected $guarded = [];
    protected $casts = [
        'starts_on' => 'date', 'ends_on' => 'date', 'published_at' => 'datetime',
        'seeks_volunteers' => 'boolean', 'seeks_partners' => 'boolean', 'is_demo' => 'boolean',
    ];

    public const PHASES = ['planned' => 'En préparation', 'ongoing' => 'En cours', 'completed' => 'Terminé'];
    public const STATUSES = ['published' => 'Publié', 'draft' => 'Brouillon', 'hidden' => 'Masqué par la modération'];

    public function mutual()     { return $this->belongsTo(Mutual::class, 'mutual_id'); }
    public function creator()    { return $this->belongsTo(PublicAccount::class, 'created_by_account_id'); }
    public function indicators() { return $this->hasMany(ProjectIndicator::class, 'project_id')->orderBy('position')->orderBy('id'); }
    public function updates()    { return $this->hasMany(ProjectUpdate::class, 'project_id')->latest(); }
    public function opportunities() { return $this->hasMany(Opportunity::class, 'project_id'); }

    /** Projets visibles publiquement (page non suspendue). */
    public function scopePublic(Builder $q): Builder
    {
        return $q->where('status', 'published')
            ->whereHas('mutual', fn ($m) => $m->where('status', '!=', 'suspended'));
    }

    public function phaseLabel(): string { return self::PHASES[$this->phase] ?? $this->phase; }
    public function coverUrl(): ?string  { return \App\Services\Social\MediaService::url($this->cover_path); }
    public function isPublic(): bool     { return $this->status === 'published' && $this->mutual && $this->mutual->status !== 'suspended'; }
    public function getRouteKeyName(): string { return 'slug'; }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title) ?: 'projet', 110, '');
        do {
            $slug = $base . '-' . Str::lower(Str::random(5));
        } while (static::withTrashed()->where('slug', $slug)->exists());

        return $slug;
    }
}
