<?php

namespace App\Services\Tenant;

use App\Models\Tenant\CareClaim;
use App\Models\Tenant\Dependent;
use App\Models\Tenant\Member;
use App\Models\Tenant\MutualSetting;
use App\Models\Tenant\Preauthorization;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\User;
use App\Services\AccountingRefusal;
use Illuminate\Support\Facades\DB;

/**
 * ACCORDS PRÉALABLES (mission « Mutuelle paramétrable », L3, 2026-11-19).
 *
 *  1–2. demande du prestataire (ou de la mutuelle), enregistrée ;
 *  3–5. contrôles automatiques par le moteur : droits, garantie, carence, plafonds — leur résultat
 *       est conservé avec la demande (auto_checks) ;
 *  6–7. examen par une AUTRE personne que le demandeur : administratif (administrateur, contrôleur)
 *       ou médical (médecin-conseil seulement), selon la garantie ; accord ou rejet MOTIVÉ ;
 *  8.   numéro d'accord AUT-AAAA-NNNNN attribué à l'accord ;
 *  9.   le prestataire voit la décision dans « Accords préalables » de son portail ;
 *  10.  décision tracée (auteur, date, motif, journal d'audit).
 *
 * URGENCE : soignée sans accord, elle n'est jamais bloquée ; la saisie ouvre une demande de
 * RÉGULARISATION (emergency = true) liée à la prestation, examinée ensuite.
 * Les refus métier sont des AccountingRefusal (message affiché tel quel).
 */
class PreauthorizationService
{
    public function __construct(private CoverageEngine $engine) {}

    /**
     * @param array{member:Member, dependent:?Dependent, provider_id:int, act_type_id:int, planned_on:string, estimated_minor:int, clinical_info:?string} $d
     */
    public function request(array $d, User $by, string $source): Preauthorization
    {
        $eval = $this->engine->evaluate($d['member'], $d['dependent'], $d['act_type_id'], $d['estimated_minor'], $d['planned_on'], $d['provider_id'],
            ['skip_authorization' => true]);

        return Preauthorization::create([
            'member_id' => $d['member']->id, 'dependent_id' => $d['dependent']?->id, 'provider_id' => $d['provider_id'],
            'act_type_id' => $d['act_type_id'], 'coverage_rule_id' => $eval['rule']?->id, 'guarantee_version_id' => $eval['version']?->id,
            'planned_on' => $d['planned_on'], 'estimated_minor' => $d['estimated_minor'],
            'estimated_mutual_minor' => $eval['ok'] ? $eval['mutual'] : null,
            'review_type' => $eval['rule']?->authorization_review === 'medical' ? 'medical' : 'administrative',
            'status' => 'requested', 'emergency' => false,
            'clinical_info' => ($d['clinical_info'] ?? null) ? trim($d['clinical_info']) : null,
            'auto_checks' => $this->checks($eval), 'source' => $source, 'requested_by' => $by->id,
        ]);
    }

    /** Urgence soignée sans accord : demande de régularisation liée à la prestation (jamais un refus de soins). */
    public function openEmergencyRegularisation(Prestation $p, CareClaim $claim, array $eval, User $by, string $source): Preauthorization
    {
        return Preauthorization::create([
            'member_id' => $claim->member_id, 'dependent_id' => $claim->dependent_id, 'provider_id' => $p->provider_id,
            'act_type_id' => $p->act_type_id, 'coverage_rule_id' => $eval['rule']?->id, 'guarantee_version_id' => $eval['version']?->id,
            'planned_on' => $p->care_date->toDateString(), 'estimated_minor' => $p->total_minor, 'estimated_mutual_minor' => $p->mutual_part_minor,
            'review_type' => $eval['rule']?->authorization_review === 'medical' ? 'medical' : 'administrative',
            'status' => 'requested', 'emergency' => true, 'auto_checks' => $this->checks($eval),
            'source' => $source, 'requested_by' => $by->id, 'prestation_id' => $p->id,
        ]);
    }

    /**
     * Décision : $to = approved | rejected. Jamais par le demandeur ; examen médical = médecin-conseil.
     * $approvedMinor : part mutuelle accordée au plus (null = calcul normal de la garantie).
     */
    public function decide(Preauthorization $p, User $by, string $to, ?string $note, ?int $approvedMinor = null, ?string $validUntil = null): Preauthorization
    {
        return DB::transaction(function () use ($p, $by, $to, $note, $approvedMinor, $validUntil) {
            $p = Preauthorization::whereKey($p->id)->lockForUpdate()->firstOrFail();
            if ($p->status !== 'requested') {
                throw new AccountingRefusal('Cette demande a déjà été traitée.');
            }
            if ((int) $p->requested_by === (int) $by->id) {
                throw new AccountingRefusal('Vous avez saisi cette demande : une autre personne doit décider (contrôle « 4 yeux »).');
            }
            if ($p->review_type === 'medical' && ! $by->hasRole('medical_advisor')) {
                throw new AccountingRefusal('Cette demande relève d’un examen médical : seul un médecin-conseil peut décider.');
            }
            if ($to === 'rejected' && trim((string) $note) === '') {
                throw new AccountingRefusal('Indiquez le motif du rejet : il est communiqué au prestataire.');
            }
            $data = ['status' => $to, 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note ? trim($note) : null];
            if ($to === 'approved') {
                $data['number'] = $this->nextNumber();
                $data['approved_minor'] = $approvedMinor;
                $days = MutualSetting::int('preauth_validity_days');
                $data['valid_until'] = $validUntil ?: ($days !== null ? now()->addDays($days)->toDateString() : null);
                if ($p->emergency && $p->prestation_id) {
                    $data['status'] = 'used';   // régularisation accordée : l'accord est déjà rattaché aux soins
                }
            }
            $p->update($data);
            if ($p->emergency && $p->prestation_id && $to === 'approved') {
                Prestation::whereKey($p->prestation_id)->update(['authorization_missing' => false, 'preauthorization_id' => $p->id]);
            }

            return $p;
        });
    }

    /** Le prestataire (ou la mutuelle) retire une demande pas encore examinée. */
    public function cancel(Preauthorization $p, User $by): void
    {
        if ($p->status !== 'requested' || $p->emergency) {
            throw new AccountingRefusal('Seule une demande en attente (hors urgence) peut être retirée.');
        }
        $p->update(['status' => 'cancelled', 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => 'Retirée par le demandeur.']);
    }

    /** Numéro AUT-AAAA-NNNNN, séquentiel par année (sous verrou de la transaction de décision). */
    private function nextNumber(): string
    {
        $prefix = 'AUT-' . now()->year . '-';
        $last = Preauthorization::where('number', 'like', $prefix . '%')->lockForUpdate()->orderByDesc('number')->value('number');

        return $prefix . str_pad((string) ((int) substr((string) $last, -5) + 1), 5, '0', STR_PAD_LEFT);
    }

    /** Résultat des contrôles automatiques, conservé avec la demande (sans donnée médicale). */
    private function checks(array $eval): array
    {
        return [
            'at' => now()->toDateTimeString(), 'status' => $eval['status'], 'label' => $eval['label'], 'reasons' => $eval['reasons'],
            'rule' => $eval['rule']?->label, 'rate' => $eval['rate'], 'mutual' => $eval['mutual'], 'patient' => $eval['patient'],
            'waiting_until' => $eval['waiting_until'] ?? null,
            'limits' => array_map(fn ($l) => ['label' => $l['label'], 'remaining_before' => $l['remaining_before'], 'remaining_count' => $l['remaining_count']], $eval['limits'] ?? []),
        ];
    }
}
