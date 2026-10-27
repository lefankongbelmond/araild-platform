<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class ContributionPayment extends Model
{
    protected $guarded = [];
    protected $casts = ['paid_on' => 'date', 'amount_minor' => 'integer'];

    /** Modes d'encaissement (2026-11-06) ; Mobile Money = reçu hors plateforme, sur le compte de la mutuelle. */
    public const MODES = [
        'cash'         => 'Espèces',
        'bank'         => 'Banque (virement, dépôt)',
        'mobile_money' => 'Mobile Money',
        'group'        => 'Versement groupé',
    ];

    public function schedule() { return $this->belongsTo(ContributionSchedule::class, 'schedule_id'); }
    public function recordedBy() { return $this->belongsTo(User::class, 'recorded_by'); }
}
