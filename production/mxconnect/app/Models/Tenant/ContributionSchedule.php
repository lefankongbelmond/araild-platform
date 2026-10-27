<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class ContributionSchedule extends Model
{
    protected $guarded = [];
    protected $casts = ['due_date' => 'date', 'due_minor' => 'integer'];

    public function subscription() { return $this->belongsTo(Subscription::class); }
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
