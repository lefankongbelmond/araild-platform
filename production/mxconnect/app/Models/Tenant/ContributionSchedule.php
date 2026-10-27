<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class ContributionSchedule extends Model
{
    protected $guarded = [];
    protected $casts = ['due_date' => 'date', 'due_minor' => 'integer'];

    /** Période d'un droit d'adhésion (2026-11-07). */
    public const FEE_PERIOD = 'ADH';

    public function subscription() { return $this->belongsTo(Subscription::class); }

    public function isFee(): bool
    {
        return ($this->kind ?? 'contribution') === 'membership_fee';
    }

    /** Libellé lisible : « Droit d'adhésion » ou la période (« 2026-03 », « 2026-T2 »…). */
    public function label(): string
    {
        return $this->isFee() ? 'Droit d’adhésion' : (string) $this->period;
    }
    public function payments()     { return $this->hasMany(ContributionPayment::class, 'schedule_id'); }

    /** Déjà encaissé (unités mineures) : utilise payments_sum_amount_minor si chargé par withSum(). */
    public function paidMinor(): int
    {
        // withSum() renvoie null quand il n'y a aucun paiement : l'attribut chargé fait foi.
        return (int) (array_key_exists('payments_sum_amount_minor', $this->attributes)
            ? $this->attributes['payments_sum_amount_minor'] : $this->payments()->sum('amount_minor'));
    }

    public function remainingMinor(): int
    {
        return max(0, $this->due_minor - $this->paidMinor());
    }
}
