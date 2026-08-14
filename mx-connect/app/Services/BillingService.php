<?php

namespace App\Services;

use App\Models\Central\BillingInvoice;
use App\Models\Central\BillingPlan;
use App\Models\Central\BillingSubscription;
use App\Models\Central\Mutual;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * SaaS billing engine (CDC Phase 4, §32).
 *
 * Places a mutual on a plan, issues one invoice per billing period, advances the
 * period on each cycle, and flags overdue invoices. Period length follows the
 * plan interval (monthly/annual). All money is integer minor units; invoices are
 * issued in the plan's currency. The cycle is idempotent per period: a mutual is
 * never double-invoiced for the same period_start.
 */
class BillingService
{
    private const DUE_DAYS = 15;

    /** Place (or move) a mutual onto a plan, opening the first period today. */
    public function subscribe(Mutual $mutual, BillingPlan $plan, ?string $startOn = null): BillingSubscription
    {
        $start = Carbon::parse($startOn ?? now()->toDateString());

        return BillingSubscription::updateOrCreate(
            ['mutual_id' => $mutual->id],
            [
                'billing_plan_id'      => $plan->id,
                'status'               => 'active',
                'started_on'           => $start->toDateString(),
                'current_period_start' => $start->toDateString(),
                'current_period_end'   => $this->periodEnd($start, $plan->interval)->toDateString(),
            ],
        );
    }

    /** Issue the invoice for a subscription's current period (idempotent per period). */
    public function generateInvoice(BillingSubscription $subscription): ?BillingInvoice
    {
        $plan = $subscription->plan;

        // Guard: never double-invoice the same period.
        $exists = BillingInvoice::where('billing_subscription_id', $subscription->id)
            ->whereDate('period_start', $subscription->current_period_start)->exists();
        if ($exists) {
            return null;
        }

        return BillingInvoice::create([
            'number'                  => $this->number(),
            'mutual_id'               => $subscription->mutual_id,
            'billing_subscription_id' => $subscription->id,
            'amount_minor'            => (int) $plan->price_minor,
            'currency_id'             => $plan->currency_id,
            'status'                  => 'issued',
            'period_start'            => $subscription->current_period_start,
            'period_end'              => $subscription->current_period_end,
            'issued_on'               => now()->toDateString(),
            'due_on'                  => now()->addDays(self::DUE_DAYS)->toDateString(),
        ]);
    }

    /**
     * Run one billing cycle: for every active-ish subscription whose current period
     * has ended, issue that period's invoice and roll the period forward. Also flags
     * any issued invoice past its due date as overdue.
     * @return array{invoiced:int,overdue:int}
     */
    public function runCycle(?string $asOf = null): array
    {
        $today = Carbon::parse($asOf ?? now()->toDateString());
        $invoiced = 0;

        BillingSubscription::whereIn('status', ['active', 'past_due', 'trialing'])
            ->whereDate('current_period_end', '<=', $today->toDateString())
            ->with('plan')->get()
            ->each(function (BillingSubscription $sub) use (&$invoiced, $today) {
                if ($this->generateInvoice($sub)) {
                    $invoiced++;
                }
                // Roll the period forward from the period that just ended.
                $newStart = Carbon::parse($sub->current_period_end)->addDay();
                $sub->update([
                    'current_period_start' => $newStart->toDateString(),
                    'current_period_end'   => $this->periodEnd($newStart, $sub->plan->interval)->toDateString(),
                ]);
            });

        // Overdue sweep.
        $overdue = BillingInvoice::where('status', 'issued')
            ->whereDate('due_on', '<', $today->toDateString())->update(['status' => 'overdue']);

        // Reflect overdue invoices on their subscription.
        BillingSubscription::whereIn('id', BillingInvoice::where('status', 'overdue')->select('billing_subscription_id'))
            ->where('status', 'active')->update(['status' => 'past_due']);

        return ['invoiced' => $invoiced, 'overdue' => $overdue];
    }

    /** Mark an invoice paid; clears the subscription's past_due state if nothing else is overdue. */
    public function markPaid(BillingInvoice $invoice): void
    {
        $invoice->update(['status' => 'paid', 'paid_on' => now()->toDateString()]);

        $stillOverdue = BillingInvoice::where('billing_subscription_id', $invoice->billing_subscription_id)
            ->where('status', 'overdue')->exists();

        if (! $stillOverdue) {
            BillingSubscription::where('id', $invoice->billing_subscription_id)
                ->where('status', 'past_due')->update(['status' => 'active']);
        }
    }

    private function periodEnd(Carbon $start, string $interval): Carbon
    {
        return $interval === 'annual'
            ? $start->copy()->addYear()->subDay()
            : $start->copy()->addMonth()->subDay();
    }

    private function number(): string
    {
        do {
            $n = 'INV-' . now()->format('Ym') . '-' . strtoupper(Str::random(5));
        } while (BillingInvoice::where('number', $n)->exists());

        return $n;
    }
}
