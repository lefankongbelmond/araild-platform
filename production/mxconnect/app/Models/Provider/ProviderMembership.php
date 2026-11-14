<?php

namespace App\Models\Provider;

use App\Models\Central\CareProvider;
use App\Support\ProviderAccess;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/** Rattachement d'une personne à un établissement : rôle + droits ajoutés / retirés. */
class ProviderMembership extends Model implements Auditable
{
    use AuditableTrait;

    protected $connection = 'central';
    protected $guarded = [];
    protected $casts = ['permissions' => 'array', 'active' => 'boolean'];

    public function account() { return $this->belongsTo(ProviderAccount::class, 'provider_account_id'); }
    public function provider() { return $this->belongsTo(CareProvider::class, 'care_provider_id'); }

    /** Droits effectifs : ceux du rôle, plus les droits ajoutés, moins les droits retirés. */
    public function effectivePermissions(): array
    {
        return ProviderAccess::effective($this->role, (array) ($this->permissions ?? []));
    }

    public function can(string $permission): bool
    {
        return $this->active && in_array($permission, $this->effectivePermissions(), true);
    }

    public function roleLabel(): string
    {
        return ProviderAccess::ROLES[$this->role] ?? $this->role;
    }
}
