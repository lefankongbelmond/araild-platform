<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guarantee extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    public function versions() { return $this->hasMany(GuaranteeVersion::class); }
    public function currentVersion() { return $this->hasOne(GuaranteeVersion::class)->whereNull('valid_to'); }

    /**
     * Motif de refus d'une NOUVELLE souscription selon la fiche de la formule (2026-11-18), ou null.
     * Statut (seulement « Active », et « En test » dans la mutuelle de test), période de vente, âge
     * de l'adhérent, nombre de bénéficiaires. Les adhésions en cours ne sont jamais touchées.
     */
    public function subscriptionRefusal(?Member $member, string $date): ?string
    {
        $status = $this->catalog_status ?? 'active';
        $allowed = \App\Support\TestEnvironment::active() ? ['active', 'test'] : ['active'];
        if (! in_array($status, $allowed, true)) {
            return 'Cette formule n’est pas proposée à la souscription (statut : ' . (\App\Support\CatalogLabels::STATUSES[$status] ?? $status) . ').';
        }
        if (($this->sale_from && $date < (string) $this->sale_from) || ($this->sale_to && $date > (string) $this->sale_to)) {
            return 'Cette formule n’est pas commercialisée à la date d’effet choisie.';
        }
        if ($member && $member->birth_date && ($this->min_age !== null || $this->max_age !== null)) {
            $age = (int) \Illuminate\Support\Carbon::parse($member->birth_date)->diffInYears(\Illuminate\Support\Carbon::parse($date));
            if (($this->min_age !== null && $age < $this->min_age) || ($this->max_age !== null && $age > $this->max_age)) {
                return "L’âge de l’adhérent ({$age} ans) est hors des limites de la formule.";
            }
        }
        if ($member && $this->max_beneficiaries && $member->beneficiaryCount() > $this->max_beneficiaries) {
            return "La formule couvre au plus {$this->max_beneficiaries} bénéficiaire(s), adhérent compris.";
        }

        return null;
    }
}
