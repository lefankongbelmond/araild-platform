<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes (Phase 3) on the hot query paths surfaced by the actuarial,
 * treasury and arrears features. contribution_schedules(status,due_date) already
 * exists from its own migration, so it isn't repeated here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestations', function (Blueprint $t) {
            $t->index(['status', 'care_date']);          // incurred claims, monthly series
        });

        Schema::table('treasury_movements', function (Blueprint $t) {
            $t->index('moved_on');                        // daily close, dashboards
            $t->index(['direction', 'mode']);             // balance-by-mode
        });

        Schema::table('contribution_payments', function (Blueprint $t) {
            $t->index('paid_on');                         // collected contributions
        });
    }

    public function down(): void
    {
        Schema::table('prestations', fn (Blueprint $t) => $t->dropIndex(['status', 'care_date']));
        Schema::table('treasury_movements', function (Blueprint $t) {
            $t->dropIndex(['moved_on']);
            $t->dropIndex(['direction', 'mode']);
        });
        Schema::table('contribution_payments', fn (Blueprint $t) => $t->dropIndex(['paid_on']));
    }
};
