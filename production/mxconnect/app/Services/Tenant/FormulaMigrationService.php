<?php

namespace App\Services\Tenant;

use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\FormulaMigration;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\User;
use App\Services\AccountingRefusal;
use App\Services\ScheduleGenerationService;
use App\Support\MemberNotifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CHANGEMENT DE FORMULE CONTRÔLÉ (mission « Mutuelle paramétrable », Partie 5.2 ; 2026-11-23).
 *
 * Fait passer un groupe d'adhérents d'une formule à une autre à une date d'effet, sans jamais
 * modifier le passé :
 *  - demande motivée, validée par une AUTRE personne (« 4 yeux ») ; date d'effet aujourd'hui ou plus tard ;
 *  - appliquée à la date d'effet (tâche de nuit, ou bouton une fois la date atteinte) ;
 *  - pour chaque adhérent : l'ancienne souscription reste « validée » et reçoit une date de fin
 *    (veille de la date d'effet) — les soins antérieurs restent couverts par l'ancienne formule,
 *    les prestations déjà enregistrées ne changent pas ; une nouvelle souscription validée
 *    commence à la date d'effet, cotisation = cotisation de base de la formule cible × bénéficiaires ;
 *  - ancienneté conservée (option) : les délais de carence repartent de l'ancienne date de début ;
 *  - échéances de l'ancienne formule dues à partir de la date d'effet et non réglées : retirées
 *    (elles étaient seulement appelées) ; si l'une est déjà réglée, l'adhérent est ÉCARTÉ (motif
 *    affiché) : l'encaissement doit d'abord être annulé au guichet. Aucun paiement n'est déplacé.
 *  - chaque adhérent est traité dans sa propre transaction, sous verrou ; les écarts sont listés.
 */
class FormulaMigrationService
{
    public function __construct(private ScheduleGenerationService $schedules) {}

    /**
     * Adhérents concernés à la date d'effet : éligibles et écartés (avec le motif).
     *
     * @param  array<int>|null  $memberIds  null = tous les adhérents de la formule source
     * @return array{eligible: array<int, array>, skipped: array<int, array>, version: ?GuaranteeVersion}
     */
    public function candidates(int $sourceId, int $targetId, string $date, ?array $memberIds = null): array
    {
        $target = Guarantee::find($targetId);
        $version = $target ? GuaranteeVersion::applicableOn($targetId, $date) : null;
        $versionIds = GuaranteeVersion::where('guarantee_id', $sourceId)->pluck('id');
        $subs = Subscription::with(['member', 'guaranteeVersion'])->where('status', 'validated')->whereIn('guarantee_version_id', $versionIds)
            ->whereDate('effective_date', '<=', $date)->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date))
            ->when($memberIds !== null, fn ($q) => $q->whereIn('member_id', $memberIds ?: [0]))
            ->orderBy('member_id')->get()->unique('member_id');

        $eligible = $skipped = [];
        foreach ($subs as $sub) {
            $m = $sub->member;
            if (! $m) {
                continue;
            }
            $row = ['member_id' => $m->id, 'code' => $m->member_code, 'name' => trim($m->last_name . ' ' . $m->first_name), 'subscription_id' => $sub->id,
                'old_minor' => (int) $sub->contribution_minor, 'new_minor' => $version ? (int) $version->base_contribution_minor * max(1, $m->beneficiaryCount()) : null];
            $reason = $this->refusal($m, $sub, $target, $version, $date);
            if ($reason) {
                $skipped[] = $row + ['reason' => $reason];
            } else {
                $eligible[] = $row + ['removed' => $this->futureSchedules($sub, $date)->count()];
            }
        }

        return ['eligible' => $eligible, 'skipped' => $skipped, 'version' => $version];
    }

    public function request(array $data, User $by): FormulaMigration
    {
        $date = (string) $data['effective_date'];
        if ((int) $data['source_guarantee_id'] === (int) $data['target_guarantee_id']) {
            throw new AccountingRefusal('Choisissez une formule cible différente de la formule actuelle.');
        }
        if ($date < now()->toDateString()) {
            throw new AccountingRefusal('La date d’effet ne peut pas être passée : un changement de formule ne modifie jamais les droits déjà ouverts.');
        }
        $c = $this->candidates((int) $data['source_guarantee_id'], (int) $data['target_guarantee_id'], $date, $data['member_ids'] ?? null);
        if (! $c['version']) {
            throw new AccountingRefusal('La formule cible n’a pas de version en vigueur à la date d’effet.');
        }
        if (! $c['eligible']) {
            throw new AccountingRefusal('Aucun adhérent ne peut changer de formule à cette date (voir les motifs dans l’aperçu).');
        }

        return DB::transaction(function () use ($data, $date, $by) {
            $year = Carbon::parse($date)->year;
            $n = FormulaMigration::where('reference', 'like', "MIG-{$year}-%")->lockForUpdate()->count() + 1;

            return FormulaMigration::create([
                'reference' => sprintf('MIG-%d-%03d', $year, $n),
                'source_guarantee_id' => (int) $data['source_guarantee_id'], 'target_guarantee_id' => (int) $data['target_guarantee_id'],
                'effective_date' => $date, 'keep_seniority' => (bool) ($data['keep_seniority'] ?? false),
                'member_ids' => $data['member_ids'] ?? null, 'note' => trim((string) $data['note']), 'status' => 'requested', 'requested_by' => $by->id,
            ]);
        });
    }

    /** Décision « 4 yeux » : jamais par la personne qui a demandé. Validée et date atteinte → appliquée tout de suite. */
    public function decide(FormulaMigration $mig, bool $approve, ?string $note, User $by): FormulaMigration
    {
        if ($mig->status !== 'requested') {
            throw new AccountingRefusal('Cette demande a déjà été traitée.');
        }
        if ((int) $mig->requested_by === (int) $by->id) {
            throw new AccountingRefusal('Une autre personne doit décider : vous avez fait cette demande.');
        }
        if (! $approve && mb_strlen(trim((string) $note)) < 5) {
            throw new AccountingRefusal('Indiquez le motif du refus.');
        }
        $mig->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note ? trim($note) : null]);
        if ($approve && $mig->effective_date->toDateString() <= now()->toDateString()) {
            $this->apply($mig->fresh());
        }

        return $mig->fresh();
    }

    public function cancel(FormulaMigration $mig, User $by): void
    {
        if ($mig->status !== 'requested' && $mig->status !== 'approved') {
            throw new AccountingRefusal('Cette demande ne peut plus être retirée.');
        }
        $mig->update(['status' => 'cancelled', 'decision_note' => trim(($mig->decision_note ? $mig->decision_note . ' — ' : '') . 'Retirée par ' . $by->first_name . ' ' . $by->last_name)]);
    }

    /** Applique une demande validée dont la date d'effet est atteinte. */
    public function apply(FormulaMigration $mig): FormulaMigration
    {
        $date = $mig->effective_date->toDateString();
        if ($mig->status !== 'approved') {
            throw new AccountingRefusal('Seule une demande validée peut être appliquée.');
        }
        if ($date > now()->toDateString()) {
            throw new AccountingRefusal('La date d’effet n’est pas encore atteinte : la demande sera appliquée ce jour-là.');
        }
        // Une seule application, même en cas de double clic ou de tâche de nuit simultanée.
        $claimed = FormulaMigration::whereKey($mig->id)->where('status', 'approved')->update(['status' => 'applied', 'applied_at' => now()]);
        if (! $claimed) {
            throw new AccountingRefusal('Cette demande est déjà en cours d’application ou appliquée.');
        }

        $c = $this->candidates($mig->source_guarantee_id, $mig->target_guarantee_id, $date, $mig->member_ids);
        $migrated = [];
        $skipped = $c['skipped'];
        foreach ($c['eligible'] as $row) {
            try {
                $migrated[] = DB::transaction(fn () => $this->migrateOne($mig, $row, $c['version']));
            } catch (AccountingRefusal $e) {
                $skipped[] = $row + ['reason' => $e->getMessage()];
            }
        }
        $mig->update(['result' => ['migrated' => $migrated, 'skipped' => array_values($skipped), 'target_version_id' => $c['version']?->id]]);

        foreach ($migrated as $row) {
            MemberNotifier::notify($row['member_id'], 'Changement de formule',
                'Votre mutuelle vous fait passer à la formule « ' . ($mig->target?->name ?? '') . ' » à partir du ' . $mig->effective_date->format('d/m/Y') . '.', 'garanties');
        }

        return $mig->fresh();
    }

    /** Demandes validées dont la date d'effet est atteinte (tâche de nuit). */
    public function applyDue(): int
    {
        $n = 0;
        foreach (FormulaMigration::where('status', 'approved')->whereDate('effective_date', '<=', now()->toDateString())->get() as $mig) {
            try {
                $this->apply($mig);
                $n++;
            } catch (AccountingRefusal) {
                // déjà appliquée par ailleurs
            }
        }

        return $n;
    }

    private function migrateOne(FormulaMigration $mig, array $row, GuaranteeVersion $version): array
    {
        $date = $mig->effective_date->toDateString();
        $member = Member::whereKey($row['member_id'])->lockForUpdate()->firstOrFail();
        $old = Subscription::with('guaranteeVersion')->whereKey($row['subscription_id'])->lockForUpdate()->firstOrFail();
        // Contrôles refaits sous verrou (rien n'a pu changer entre l'aperçu et l'application).
        if ($reason = $this->refusal($member, $old, $mig->target, $version, $date)) {
            throw new AccountingRefusal($reason);
        }
        $removed = 0;
        foreach ($this->futureSchedules($old, $date)->get() as $s) {
            $s->delete();
            $removed++;
        }
        $old->update(['ends_on' => Carbon::parse($date)->subDay()->toDateString()]);
        $new = Subscription::create([
            'member_id' => $member->id, 'member_group_id' => $old->member_group_id, 'guarantee_version_id' => $version->id,
            'contribution_minor' => (int) $version->base_contribution_minor * max(1, $member->beneficiaryCount()),
            'effective_date' => $date,
            'observation_ends_on' => $mig->keep_seniority ? $date : Carbon::parse($date)->addDays((int) $version->observation_days)->toDateString(),
            'waiting_from' => $mig->keep_seniority ? ($old->waiting_from ?? $old->effective_date)?->toDateString() : null,
            'migrated_from_id' => $old->id, 'status' => 'validated',
            'captured_by' => $mig->requested_by, 'validated_by' => $mig->decided_by, 'validated_at' => now(),
        ]);
        $this->schedules->extend($new->load('guaranteeVersion'));

        return ['member_id' => $member->id, 'code' => $row['code'], 'name' => $row['name'], 'old_subscription_id' => $old->id,
            'new_subscription_id' => $new->id, 'old_minor' => (int) $old->contribution_minor, 'new_minor' => (int) $new->contribution_minor, 'removed' => $removed];
    }

    private function refusal(Member $m, Subscription $sub, ?Guarantee $target, ?GuaranteeVersion $version, string $date): ?string
    {
        $status = $m->status instanceof \BackedEnum ? $m->status->value : (string) $m->status;
        if ($status !== 'active') {
            return 'Adhérent non actif (statut : ' . $status . ').';
        }
        if (! $target || ! $version) {
            return 'La formule cible n’a pas de version en vigueur à la date d’effet.';
        }
        if ($m->subscriptions()->where('status', 'captured')->exists()) {
            return 'Une souscription saisie attend sa validation.';
        }
        if ($m->subscriptions()->where('status', 'validated')->whereKeyNot($sub->id)->whereDate('effective_date', '>', $sub->effective_date)->exists()) {
            return 'Une autre souscription plus récente existe déjà.';
        }
        if ($refusal = $target->subscriptionRefusal($m, $date)) {
            return $refusal;
        }
        $paid = $this->futureSchedules($sub, $date, true)->exists();
        if ($paid) {
            return 'Une échéance de l’ancienne formule, due à partir de la date d’effet, est réglée ou porte un encaissement (même annulé) : à régulariser au guichet avant le changement.';
        }

        return null;
    }

    /** Échéances de l'ancienne souscription dues à partir de la date d'effet ($paid : réglées, même en partie ; sinon : non réglées). */
    private function futureSchedules(Subscription $sub, string $date, bool $paid = false)
    {
        $q = ContributionSchedule::where('subscription_id', $sub->id)->whereDate('due_date', '>=', $date);
        $withPayment = ContributionPayment::withTrashed()->select('schedule_id');   // encaissements annulés compris

        return $paid
            ? $q->where(fn ($w) => $w->where('status', 'paid')->orWhereIn('id', $withPayment))
            : $q->where('status', '!=', 'paid')->whereNotIn('id', $withPayment);
    }
}
