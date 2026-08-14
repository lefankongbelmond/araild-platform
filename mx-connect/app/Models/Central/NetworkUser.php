<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Fortify\TwoFactorAuthenticatable;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Spatie\Permission\Traits\HasRoles;

/**
 * Network-level user (super_admin, security_admin, moderator).
 * Lives in the CENTRAL database. spatie reads the central permission tables
 * because $connection is 'central'.
 */
class NetworkUser extends Authenticatable implements Auditable
{
    use HasFactory, SoftDeletes, HasRoles, TwoFactorAuthenticatable, AuditableTrait;

    protected static function newFactory()
    {
        return \Database\Factories\Central\NetworkUserFactory::new();
    }

    protected $connection = 'central';
    protected $guard_name = 'network';

    protected $guarded = [];
    protected $hidden = ['password', 'remember_token', 'mfa_secret'];
    protected $casts = [
        'password'    => 'hashed',
        'mfa_secret'  => 'encrypted',
        'mfa_enabled' => 'boolean',
        'active'      => 'boolean',
    ];

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
