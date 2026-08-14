<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class CareClaim extends Model implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $guarded = [];
    protected $casts = ['opened_on' => 'date'];

    public function member()        { return $this->belongsTo(Member::class); }
    public function dependent()     { return $this->belongsTo(Dependent::class); }
    public function prestations()   { return $this->hasMany(Prestation::class); }
    public function reimbursement() { return $this->hasOne(Reimbursement::class); }
    public function invoices()      { return $this->hasMany(ProviderInvoice::class); }

    /** Total mutual liability across validated prestations (minor units). */
    public function mutualTotalMinor(): int
    {
        return (int) $this->prestations()->where('status', 'validated')->sum('mutual_part_minor');
    }
}
