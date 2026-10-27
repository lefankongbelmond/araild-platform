<?php

namespace App\Services\Tenant;

use App\Models\Central\Currency;
use App\Models\Tenant\AccountingDay;
use App\Models\Tenant\CollectionCancellation;
use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\ContributionSchedule;
use App\Models\Tenant\Member;
use App\Models\Tenant\TreasuryMovement;
use App\Services\AccountingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Encaissement des cotisations au guichet (2026-11-06).
 *
 * Un encaissement = un montant reçu d'un adhérent (espèces, banque, Mobile Money reçu hors
 * plateforme, versement groupé). Il est réparti sur ses échéances impayées, la plus ancienne
 * d'abord ; une échéance peut être réglée en plusieurs fois. Chaque encaissement :
 *  - crée UN mouvement de trésorerie (entrée « cotisation »), donc une écriture comptable
 *    quand le trésorier comptabilise ;
 *  - produit UN reçu numéroté listant les échéances réglées.
 * MX-CONNECT ne détient aucun fonds : il enregistre un argent déjà reçu par la mutuelle.
 */
class ContributionCollectionService
{
    /** Mode d'encaissement → mode de trésorerie (compte 571 caisse, 521 banque, 585 Mobile Money). */
    private const TREASURY_MODE = ['cash' => 'cash', 'group' => 'cash', 'bank' => 'bank', 'mobile_money' => 'mobile_money'];

    public function __construct(private AccountingService $accounting) {}

    /** Échéances non soldées d'un adhérent (souscriptions validées), de la plus ancienne à la plus récente. */
    public function openSchedules(Member $member): Collection
    {
        return ContributionSchedule::query()
            ->whereHas('subscription', fn ($q) => $q->where('member_id', $member->id)->where('status', 'validated'))
            ->where('status', '!=', 'paid')
            ->withSum('payments', 'amount_minor')
            // À date égale, le droit d'adhésion (« membership_fee ») passe avant la cotisation.
            ->orderBy('due_date')->when($this->hasKind(), fn ($q) => $q->orderByDesc('kind'))->orderBy('id')->get();
    }

    /**
     * @param  list<int>|null  $scheduleIds  limiter à ces échéances (sinon : toutes, la plus ancienne d'abord)
     * @return array{ref:string, receipt:string, payments:Collection, amount:int}
     */
    public function collect(Member $member, int $amountMinor, string $date, string $mode, ?string $reference, int $userId, ?array $scheduleIds = null): array
    {
        $day = Carbon::parse($date)->startOfDay();
        if ($amountMinor <= 0) {
            $this->refuse('Le montant doit être supérieur à zéro.');
        }
        if (! array_key_exists($mode, self::TREASURY_MODE)) {
            $this->refuse('Mode d’encaissement inconnu.');
        }
        if ($day->gt(now()->startOfDay())) {
            $this->refuse('La date d’encaissement ne peut pas être dans le futur.');
        }
        if ($this->accounting->isYearClosed((int) $day->year)) {
            $this->refuse("L’exercice {$day->year} est clôturé : choisissez une date dans un exercice ouvert.");
        }
        if (AccountingDay::whereDate('day', $day->toDateString())->first()?->isClosed()) {
            $this->refuse('La journée du ' . $day->format('d/m/Y') . ' est arrêtée (arrêté de caisse) : choisissez une autre date.');
        }

        $open = $this->openSchedules($member)
            ->when($scheduleIds !== null, fn ($c) => $c->whereIn('id', $scheduleIds))
            ->filter(fn (ContributionSchedule $s) => $s->remainingMinor() > 0)->values();
        $due = $open->sum(fn (ContributionSchedule $s) => $s->remainingMinor());
        if ($due === 0) {
            $this->refuse('Aucune échéance à régler pour cet adhérent. Actualisez les échéanciers pour encaisser une période à venir.');
        }
        if ($amountMinor > $due) {
            $this->refuse('Le montant dépasse le reste à payer (' . $this->currency()->format($due) . ').');
        }

        return DB::transaction(function () use ($member, $amountMinor, $day, $mode, $reference, $userId, $open) {
            $ref = 'ENC-' . $day->format('Ymd') . '-' . strtoupper(Str::random(5));
            $left = $amountMinor;
            $payments = collect();
            $bySource = [];   // montant par source de trésorerie (cotisation / droit d'adhésion)
            foreach ($open as $s) {
                if ($left === 0) {
                    break;
                }
                $remaining = $s->remainingMinor();
                $part = min($left, $remaining);
                $payments->push($s->payments()->create([
                    'amount_minor' => $part, 'paid_on' => $day->toDateString(), 'mode' => $mode,
                    'collection_ref' => $ref, 'reference' => $reference, 'recorded_by' => $userId,
                ]));
                $left -= $part;
                $source = $s->isFee() ? 'membership_fee' : 'contribution';
                $bySource[$source] = ($bySource[$source] ?? 0) + $part;
                if ($part === $remaining) {
                    $s->update(['status' => 'paid']);
                }
            }

            // Un mouvement par source : cotisations (7561) et droits d'adhésion (7011) restent distincts en comptabilité.
            foreach ($bySource as $source => $sum) {
                TreasuryMovement::create([
                    'direction' => 'inflow', 'source' => $source, 'amount_minor' => $sum,
                    'mode' => self::TREASURY_MODE[$mode], 'linked_reference' => $ref, 'moved_on' => $day->toDateString(),
                ]);
            }

            $receipt = $this->receipt($member, $ref, $payments, $amountMinor, $mode, $reference, $day);
            ContributionPayment::whereIn('id', $payments->pluck('id'))->update(['receipt_path' => $receipt]);

            return ['ref' => $ref, 'receipt' => $receipt, 'payments' => $payments, 'amount' => $amountMinor];
        });
    }

    // ------------------------------------------------------------------------------------
    // Annulation d'un encaissement saisi par erreur (2026-11-07) : demandée par le trésorier
    // avec un motif, validée par une AUTRE personne (administrateur ou contrôleur). À la
    // validation : paiements annulés (gardés en base), échéances rouvertes, mouvement de
    // trésorerie inverse daté du jour (contre-passation en comptabilité), reçu marqué « ANNULÉ ».
    // ------------------------------------------------------------------------------------

    public function requestCancellation(string $collectionRef, string $reason, int $userId): CollectionCancellation
    {
        $payments = ContributionPayment::where('collection_ref', $collectionRef)->with('schedule.subscription')->get();
        if ($payments->isEmpty()) {
            $this->refuse('Encaissement introuvable ou déjà annulé.');
        }
        if (CollectionCancellation::where('collection_ref', $collectionRef)->where('status', 'pending')->exists()) {
            $this->refuse('Une demande d’annulation de cet encaissement attend déjà une décision.');
        }

        return CollectionCancellation::create([
            'collection_ref' => $collectionRef, 'member_id' => $payments->first()->schedule->subscription->member_id,
            'amount_minor' => (int) $payments->sum('amount_minor'), 'reason' => $reason, 'requested_by' => $userId,
        ]);
    }

    public function approveCancellation(CollectionCancellation $c, int $userId): void
    {
        $this->assertDecidable($c, $userId);
        $today = now()->startOfDay();
        if ($this->accounting->isYearClosed((int) $today->year)) {
            $this->refuse("L’exercice {$today->year} est clôturé.");
        }
        if (AccountingDay::whereDate('day', $today->toDateString())->first()?->isClosed()) {
            $this->refuse('La journée d’aujourd’hui est arrêtée (arrêté de caisse) : l’annulation sera possible demain.');
        }

        DB::transaction(function () use ($c, $userId, $today) {
            $payments = ContributionPayment::where('collection_ref', $c->collection_ref)->with('schedule')->get();
            $movements = TreasuryMovement::where('linked_reference', $c->collection_ref)->where('direction', 'inflow')->get();
            foreach ($payments as $p) {
                $p->delete();
            }
            // Échéances rouvertes : à payer, ou en retard si leur date est passée.
            foreach ($payments->pluck('schedule')->filter()->unique('id') as $s) {
                $s->refresh();
                if ($s->remainingMinor() > 0) {
                    $s->update(['status' => $s->due_date->lt($today) ? 'overdue' : 'to_pay']);
                }
            }
            foreach ($movements as $m) {
                TreasuryMovement::create([
                    'direction' => 'outflow', 'source' => $m->source, 'amount_minor' => $m->amount_minor, 'mode' => $m->mode,
                    'linked_reference' => 'ANN-' . $c->collection_ref, 'moved_on' => $today->toDateString(),
                ]);
            }
            $c->update(['status' => 'approved', 'decided_by' => $userId, 'decided_at' => now()]);
        });
    }

    public function rejectCancellation(CollectionCancellation $c, int $userId, string $note): void
    {
        $this->assertDecidable($c, $userId);
        $c->update(['status' => 'rejected', 'decided_by' => $userId, 'decided_at' => now(), 'decision_note' => $note]);
    }

    private function assertDecidable(CollectionCancellation $c, int $userId): void
    {
        if ($c->status !== 'pending') {
            $this->refuse('Cette demande a déjà été décidée.');
        }
        if ((int) $c->requested_by === $userId) {
            $this->refuse('Vous avez demandé cette annulation : une autre personne doit décider (contrôle « 4 yeux »).');
        }
    }

    private ?bool $hasKind = null;

    private function hasKind(): bool
    {
        return $this->hasKind ??= \Illuminate\Support\Facades\Schema::hasColumn('contribution_schedules', 'kind');
    }

    /** Reçu unique de l'encaissement (HTML imprimable, disque privé de la mutuelle). */
    private function receipt(Member $member, string $ref, Collection $payments, int $amount, string $mode, ?string $reference, Carbon $day): string
    {
        $payments->each->loadMissing('schedule');
        $html = View::make('tenant.contributions.collection-receipt', [
            'member' => $member, 'number' => $ref, 'payments' => $payments, 'amount' => $amount,
            'mode' => ContributionPayment::MODES[$mode] ?? $mode, 'reference' => $reference, 'day' => $day,
            'currency' => $this->currency(), 'mutual' => tenant('name'),
            'balance' => $this->openSchedules($member)->sum(fn ($s) => max(0, $s->due_minor - (int) $s->payments_sum_amount_minor)),
        ])->render();
        $path = "receipts/{$ref}.html";
        Storage::disk('local')->put($path, $html);

        return $path;
    }

    private function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id')) ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }

    private function refuse(string $message): never
    {
        throw new \App\Services\AccountingRefusal($message);
    }
}
