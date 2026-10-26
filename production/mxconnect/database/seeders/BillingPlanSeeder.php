<?php

namespace Database\Seeders;

use App\Models\Central\BillingPlan;
use App\Models\Central\Currency;
use Illuminate\Database\Seeder;

/** Starter platform plans. Amounts are indicative; adjust from the UI. */
class BillingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $xaf = Currency::on('central')->where('code', 'XAF')->first();
        $ccy = $xaf?->id;
        $unit = $xaf && $xaf->minor_unit === 0 ? 1 : 100; // XAF has no minor unit

        $plans = [
            ['code' => 'starter', 'name' => 'Starter', 'price' => 15000,  'interval' => 'monthly', 'max_members' => 500],
            ['code' => 'growth',  'name' => 'Growth',  'price' => 40000,  'interval' => 'monthly', 'max_members' => 2000],
            ['code' => 'scale',   'name' => 'Scale',   'price' => 90000,  'interval' => 'monthly', 'max_members' => null],
        ];

        foreach ($plans as $p) {
            BillingPlan::updateOrCreate(
                ['code' => $p['code']],
                [
                    'name' => $p['name'],
                    'price_minor' => $p['price'] * $unit,
                    'currency_id' => $ccy,
                    'interval' => $p['interval'],
                    'max_members' => $p['max_members'],
                    'active' => true,
                ],
            );
        }
    }
}
