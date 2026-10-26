<?php

namespace App\Models\Social;

use App\Models\Central\Country;
use App\Models\Central\Mutual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Opportunité publiée par une page : emploi, stage, bénévolat, consultance,
 * appel d'offres, appel à partenaires… La rémunération est un texte libre dans
 * la devise choisie par l'organisation (aucune conversion).
 */
class Opportunity extends Model
{
    use SoftDeletes;

    protected $connection = 'central';
    protected $table = 'social_opportunities';
    protected $guarded = [];
    protected $casts = ['deadline_on' => 'date', 'published_at' => 'datetime', 'is_demo' => 'boolean'];

    public const CATEGORIES = [
        'emploi'            => 'Offre d’emploi',
        'stage'             => 'Stage',
        'benevolat'         => 'Bénévolat',
        'consultance'       => 'Consultance',
        'appel_offres'      => 'Appel d’offres',
        'appel_partenaires' => 'Appel à partenaires',
        'formation'         => 'Formation ou bourse',
        'autre'             => 'Autre opportunité',
    ];
    /** Catégories « emploi » au sens large (filtre rapide). */
    public const JOB_CATEGORIES = ['emploi', 'stage', 'consultance'];

    public const CONTRACTS = [
        'cdi' => 'CDI', 'cdd' => 'CDD', 'stage' => 'Stage', 'freelance' => 'Prestation / consultance',
        'benevolat' => 'Bénévolat', 'autre' => 'Autre',
    ];
    public const WORKPLACES = ['site' => 'Sur site', 'remote' => 'À distance', 'hybrid' => 'Hybride'];
    public const AVAILABILITIES = ['open' => 'Ouverte', 'filled' => 'Pourvue', 'closed' => 'Clôturée'];
    public const APPLY_MODES = ['platform' => 'Candidature sur MX-CONNECT', 'external' => 'Candidature sur un site externe'];

    public function mutual()  { return $this->belongsTo(Mutual::class, 'mutual_id'); }
    public function project() { return $this->belongsTo(Project::class, 'project_id'); }
    public function country() { return $this->belongsTo(Country::class, 'country_id'); }

    public function scopePublic(Builder $q): Builder
    {
        return $q->where('status', 'published')
            ->whereHas('mutual', fn ($m) => $m->where('status', '!=', 'suspended'));
    }

    /** Ouvertes : non pourvues/clôturées et date limite non dépassée. */
    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('availability', 'open')
            ->where(fn ($w) => $w->whereNull('deadline_on')->orWhereDate('deadline_on', '>=', now()->toDateString()));
    }

    public function isOpen(): bool
    {
        return $this->availability === 'open' && (! $this->deadline_on || $this->deadline_on->endOfDay()->isFuture());
    }

    public function isPublic(): bool { return $this->status === 'published' && $this->mutual && $this->mutual->status !== 'suspended'; }

    /** « Ouverte », « Pourvue », « Clôturée » ou « Date limite dépassée ». */
    public function availabilityLabel(): string
    {
        if ($this->availability === 'open' && ! $this->isOpen()) {
            return 'Date limite dépassée';
        }

        return self::AVAILABILITIES[$this->availability] ?? $this->availability;
    }

    public function categoryLabel(): string { return self::CATEGORIES[$this->category] ?? $this->category; }
    public function acceptsPlatform(): bool { return $this->apply_mode === 'platform'; }
    /** Une candidature (emploi, stage, consultance) ou une manifestation d'intérêt (le reste). */
    public function isJob(): bool { return in_array($this->category, self::JOB_CATEGORIES, true); }
    public function place(): string { return collect([$this->city, $this->region, $this->country?->name])->filter()->implode(', '); }
    public function getRouteKeyName(): string { return 'slug'; }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title) ?: 'opportunite', 110, '');
        do {
            $slug = $base . '-' . Str::lower(Str::random(5));
        } while (static::withTrashed()->where('slug', $slug)->exists());

        return $slug;
    }
}
