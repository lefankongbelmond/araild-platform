<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\MemberStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Illuminate\Notifications\Notifiable;

class Member extends Model implements Auditable
{
    use Notifiable, HasFactory, SoftDeletes, AuditableTrait;

    protected static function newFactory()
    {
        return \Database\Factories\Tenant\MemberFactory::new();
    }

    protected $guarded = [];
    protected $casts = [
        'birth_date' => 'date',
        'joined_at'  => 'date',
        'status'     => MemberStatus::class,
        'id_document' => 'encrypted',   // sensitive
    ];

    public function dependents()    { return $this->hasMany(Dependent::class); }
    public function group()         { return $this->belongsTo(MemberGroup::class, 'member_group_id'); }
    public function subscriptions() { return $this->hasMany(Subscription::class); }
    public function careClaims()   { return $this->hasMany(CareClaim::class); }

    /** Active beneficiaries = the member + active dependents. */
    public function beneficiaryCount(): int
    {
        return 1 + $this->dependents()->where('status', 'active')->count();
    }
}
