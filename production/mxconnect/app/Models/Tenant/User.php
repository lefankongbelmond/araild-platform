<?php

namespace App\Models\Tenant;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Fortify\TwoFactorAuthenticatable;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Spatie\Permission\Traits\HasRoles;

/**
 * Internal user of a mutual. Lives in the TENANT database (default connection
 * once tenancy is initialized), so spatie uses the tenant permission tables.
 */
class User extends Authenticatable implements Auditable
{
    use SoftDeletes, HasRoles, TwoFactorAuthenticatable, AuditableTrait;

    protected $guard_name = 'web';

    protected $guarded = [];
    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];
    protected $casts = [
        'password' => 'hashed',
        'active'   => 'boolean',
        'two_factor_confirmed_at' => 'datetime',
        'must_change_password' => 'boolean',
        'last_login_at' => 'datetime',
        'care_provider_id' => 'integer',
    ];

    /** Prestataire de soins représenté (comptes au rôle « provider », registre central). */
    public function careProvider(): ?\App\Models\Central\CareProvider
    {
        return $this->care_provider_id ? \App\Models\Central\CareProvider::on('central')->find($this->care_provider_id) : null;
    }

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
