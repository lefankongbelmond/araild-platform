<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A mutual = a tenant. Backed by its own database (database-per-tenant).
 * Country / currency / locale / payment providers are all FK references — nothing hard-coded.
 */
class Mutual extends BaseTenant implements TenantWithDatabase
{
    use HasFactory, HasDatabase, HasDomains;

    protected static function newFactory()
    {
        return \Database\Factories\Central\MutualFactory::new();
    }

    protected $table = 'mutuals';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'approved_at' => 'date',
        'data' => 'array',
    ];

    /** Columns that are real relational fields (not folded into stancl's json `data`). */
    public static function getCustomColumns(): array
    {
        return [
            'id', 'name', 'slug', 'country_id', 'currency_id', 'locale_id',
            'region', 'city', 'logo_path', 'status', 'approved_at', 'approved_by',
        ];
    }

    /** Colonne `mutual_id` (et non `tenant_id`) dans la table domains. */
    public function domains()
    {
        return $this->hasMany(Domain::class, 'mutual_id');
    }

    /** Vitrine publique (réseau social). */
    public function profile()
    {
        return $this->hasOne(\App\Models\Social\MutualProfile::class, 'mutual_id');
    }

    /** Mutuelles réelles (exclut les organisations de démonstration, sans base tenant). */
    public function scopeOperational($query)
    {
        if (! \Illuminate\Support\Facades\Schema::connection('central')->hasTable('mutual_profiles')) {
            return $query;   // avant la migration du réseau social
        }

        // Exclut aussi les pages créées par des membres (aucune base de données).
        $hasOwner = \Illuminate\Support\Facades\Schema::connection('central')->hasColumn('mutual_profiles', 'owner_account_id');

        return $query->whereNotIn('id', \App\Models\Social\MutualProfile::where(fn ($w) => $hasOwner
            ? $w->where('is_demo', true)->orWhereNotNull('owner_account_id')
            : $w->where('is_demo', true))->select('mutual_id'));
    }

    /** Page créée par un membre (le profil a un propriétaire). */
    public function isMemberPage(): bool
    {
        return (bool) $this->profile?->owner_account_id;
    }

    public function isVerified(): bool
    {
        return $this->status === 'approved';
    }

    public function country()  { return $this->belongsTo(Country::class); }
    public function currency() { return $this->belongsTo(Currency::class); }
    public function locale()   { return $this->belongsTo(Locale::class); }

    public function paymentConfigs(): HasMany
    {
        return $this->hasMany(MutualPaymentConfig::class, 'mutual_id');
    }
}
