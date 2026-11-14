<?php

namespace App\Services\Tenant;

use App\Models\Tenant\CareClaim;
use App\Models\Tenant\Dependent;
use App\Models\Tenant\Dispensation;
use App\Models\Tenant\DispensationLine;
use App\Models\Tenant\Medicine;
use App\Models\Tenant\Member;
use App\Models\Tenant\MutualSetting;
use App\Models\Tenant\PrescribedMedicine;
use App\Models\Tenant\Prescription;
use App\Models\Tenant\User;
use App\Services\AccountingRefusal;
use App\Services\ClaimCaptureService;
use App\Services\FraudDetectionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * DÉLIVRANCE EN PHARMACIE (mission « Mutuelle paramétrable », L3, 2026-11-19).
 *
 * Une ordonnance est identifiée par son n° (normalisé), le bénéficiaire et sa date. Dans UNE
 * transaction, après verrouillage de l'ordonnance :
 *  - REFUS si l'ordonnance est périmée (réglage de la mutuelle, s'il existe), si une quantité délivrée
 *    dépasse la quantité prescrite, ou si l'ordonnance a déjà été délivrée en totalité (y compris ses
 *    renouvellements) : une même ordonnance ne peut pas être facturée deux fois (scénario 5) ;
 *  - lignes NON PRISES EN CHARGE (le patient les paie) : médicament exclu, hors liste positive, ou
 *    quantité au-delà du maximum par ordonnance fixé par la mutuelle ;
 *  - la prise en charge est calculée par le moteur (ClaimCaptureService), la part non prise en charge
 *    étant hors base ; les SIGNAUX (jamais des refus) : prix au-dessus du prix de référence,
 *    même médicament redélivré récemment (réglage de la mutuelle).
 * Une prestation rejetée libère les quantités de sa délivrance.
 */
class PharmacyService
{
    public function __construct(private ClaimCaptureService $capture, private FraudDetectionService $fraud) {}

    /**
     * @param array{reference:string, prescriber:string, prescribed_on:string, renewals_allowed:int} $rx
     * @param array<int,array{medicine_id:int, quantity_prescribed:int, quantity_dispensed:int, unit_price_minor:int}> $lines
     * @return array{ok:bool, reasons:array, prestation:?\App\Models\Tenant\Prestation, dispensation:?Dispensation, excluded:int, total:int}
     */
    public function dispense(Member $member, ?Dependent $dependent, int $providerId, int $actTypeId, array $rx, array $lines, User $by, string $source = 'provider', bool $emergency = false): array
    {
        $today = now()->toDateString();
        $validity = MutualSetting::int('prescription_validity_days');
        if ($rx['prescribed_on'] > $today) {
            throw new AccountingRefusal('La date de l’ordonnance ne peut pas être dans le futur.');
        }
        if ($validity !== null && now()->startOfDay()->gt(\Illuminate\Support\Carbon::parse($rx['prescribed_on'])->addDays($validity))) {
            throw new AccountingRefusal("Ordonnance périmée : la mutuelle accepte les ordonnances de moins de {$validity} jours.");
        }
        if (! $lines) {
            throw new AccountingRefusal('Indiquez au moins un médicament délivré.');
        }

        $outcome = ['reasons' => []];
        try {
            return DB::transaction(function () use ($member, $dependent, $providerId, $actTypeId, $rx, $lines, $by, $source, $emergency, $today, &$outcome) {
                $prescription = $this->lockPrescription($member, $dependent, $providerId, $rx);
                $cycles = 1 + (int) $prescription->renewals_allowed;
                $medicines = Medicine::withTrashed()->whereIn('id', array_column($lines, 'medicine_id'))->get()->keyBy('id');

                $total = 0;
                $excluded = 0;
                $rows = [];
                foreach ($lines as $l) {
                    $m = $medicines[$l['medicine_id']] ?? null;
                    if (! $m) {
                        throw new AccountingRefusal('Médicament inconnu.');
                    }
                    $qp = (int) $l['quantity_prescribed'];
                    $qd = (int) $l['quantity_dispensed'];
                    if ($qp < 1 || $qd < 1) {
                        throw new AccountingRefusal("Quantités invalides pour {$m->label}.");
                    }
                    if ($qd > $qp) {
                        throw new AccountingRefusal("{$m->label} : la quantité délivrée ({$qd}) dépasse la quantité prescrite ({$qp}).");
                    }
                    $already = $this->alreadyDispensed($prescription->id, $m->id);
                    if ($already + $qd > $qp * $cycles) {
                        throw new AccountingRefusal($already >= $qp * $cycles
                            ? "Ordonnance déjà utilisée : {$m->label} a déjà été délivré en totalité pour cette ordonnance" . ($cycles > 1 ? ' (renouvellements compris).' : '.')
                            : "{$m->label} : il reste " . ($qp * $cycles - $already) . ' unité(s) à délivrer sur cette ordonnance.');
                    }
                    $unit = max(0, (int) $l['unit_price_minor']);
                    $amount = $unit * $qd;
                    $reason = $m->excluded ? 'excluded' : (! $m->eligible ? 'not_listed' : null);
                    $lineExcluded = $reason ? $amount : 0;
                    if (! $reason && $m->max_quantity !== null && $qd > $m->max_quantity) {
                        $lineExcluded = $unit * ($qd - $m->max_quantity);   // au-delà du maximum par ordonnance
                    }
                    $total += $amount;
                    $excluded += $lineExcluded;
                    $rows[] = ['medicine_id' => $m->id, 'quantity_prescribed' => $qp, 'quantity_dispensed' => $qd, 'unit_price_minor' => $unit,
                        'amount_minor' => $amount, 'eligible' => $reason === null, 'reason' => $reason,
                        'above_reference' => $m->reference_price_minor > 0 && $unit > $m->reference_price_minor];
                }
                if ($total <= 0) {
                    throw new AccountingRefusal('Indiquez le prix des médicaments délivrés.');
                }

                $claim = CareClaim::create(['member_id' => $member->id, 'dependent_id' => $dependent?->id, 'opened_on' => $today, 'status' => 'open']);
                $res = $this->capture->capture($claim, [
                    'act_type_id' => $actTypeId, 'provider_id' => $providerId, 'care_date' => $today,
                    'total_minor' => $total, 'excluded_minor' => $excluded, 'emergency' => $emergency,
                ], $by, $source);
                if (! $res['ok']) {
                    $outcome = $res;
                    throw new \RuntimeException('pharmacy_refused');
                }
                $p = $res['prestation'];
                $disp = Dispensation::create(['prescription_id' => $prescription->id, 'provider_id' => $providerId, 'prestation_id' => $p->id,
                    'dispensed_on' => $today, 'total_minor' => $total, 'excluded_minor' => $excluded, 'captured_by' => $by->id]);
                foreach ($rows as $r) {
                    DispensationLine::create($r + ['dispensation_id' => $disp->id]);
                    PrescribedMedicine::create(['prestation_id' => $p->id, 'medicine_id' => $r['medicine_id'], 'quantity' => $r['quantity_dispensed'], 'amount_minor' => $r['amount_minor']]);
                }
                $this->signals($p, $member, $dependent, $rows, $disp->id);

                return ['ok' => true, 'reasons' => [], 'prestation' => $p, 'dispensation' => $disp, 'excluded' => $excluded, 'total' => $total];
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'pharmacy_refused') {
                throw $e;
            }

            return ['ok' => false, 'reasons' => $outcome['reasons'] ?? [], 'prestation' => null, 'dispensation' => null, 'excluded' => 0, 'total' => 0,
                'status' => $outcome['status'] ?? null];
        }
    }

    /** Ordonnance existante (verrouillée) ou créée ; deux pharmacies simultanées ne la dupliquent jamais. */
    private function lockPrescription(Member $member, ?Dependent $dependent, int $providerId, array $rx): Prescription
    {
        $key = Prescription::keyFor($rx['reference'], $member->id, $dependent?->id, $rx['prescribed_on']);
        $found = Prescription::where('reference_key', $key)->lockForUpdate()->first();
        if ($found) {
            return $found;
        }
        try {
            DB::transaction(fn () => Prescription::create([
                'member_id' => $member->id, 'dependent_id' => $dependent?->id, 'reference' => trim($rx['reference']), 'reference_key' => $key,
                'prescriber' => trim($rx['prescriber']), 'prescribed_on' => $rx['prescribed_on'],
                'renewals_allowed' => max(0, min(12, (int) ($rx['renewals_allowed'] ?? 0))), 'first_provider_id' => $providerId,
            ]));
        } catch (UniqueConstraintViolationException) {
            // créée au même instant par une autre saisie : on la relit ci-dessous, sous verrou
        }

        return Prescription::where('reference_key', $key)->lockForUpdate()->firstOrFail();
    }

    /** Quantité déjà délivrée d'un médicament sur une ordonnance (prestations non rejetées). */
    public function alreadyDispensed(int $prescriptionId, int $medicineId): int
    {
        return (int) DispensationLine::where('medicine_id', $medicineId)
            ->whereHas('dispensation', fn ($q) => $q->where('prescription_id', $prescriptionId)
                ->whereHas('prestation', fn ($p) => $p->where('status', '!=', 'rejected')))
            ->sum('quantity_dispensed');
    }

    private function signals($p, Member $member, ?Dependent $dependent, array $rows, int $dispensationId): void
    {
        if (collect($rows)->contains('above_reference', true)) {
            $this->fraud->signal($p, 'financial', 'watch', 'above_reference_price');
        }
        $days = MutualSetting::int('dispensation_watch_days');
        if ($days === null) {
            return;
        }
        $recent = DispensationLine::whereIn('medicine_id', array_column($rows, 'medicine_id'))
            ->whereHas('dispensation', fn ($q) => $q->where('id', '!=', $dispensationId)
                ->whereDate('dispensed_on', '>=', now()->subDays($days)->toDateString())
                ->whereHas('prescription', fn ($r) => $r->where('member_id', $member->id)
                    ->when($dependent, fn ($w) => $w->where('dependent_id', $dependent->id), fn ($w) => $w->whereNull('dependent_id')))
                ->whereHas('prestation', fn ($x) => $x->where('status', '!=', 'rejected')))
            ->exists();
        if ($recent) {
            $this->fraud->signal($p, 'overconsumption', 'watch', 'close_redispensing');
        }
    }
}
