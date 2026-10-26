<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestion avancée : tarif conventionnel des actes, budget annuel,
 * notifications aux membres (individuelles ou en masse), suivi des cartes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('act_types', function (Blueprint $t) {
            if (! Schema::hasColumn('act_types', 'tariff_minor')) {
                $t->bigInteger('tariff_minor')->nullable()->after('category');   // tarif conventionnel (facultatif)
            }
        });

        Schema::table('members', function (Blueprint $t) {
            if (! Schema::hasColumn('members', 'card_printed_at')) {
                $t->timestamp('card_printed_at')->nullable()->after('joined_at');
            }
        });

        if (! Schema::hasTable('budgets')) {
            Schema::create('budgets', function (Blueprint $t) {
                $t->id();
                $t->unsignedSmallInteger('year')->unique();
                $t->string('label', 150);
                $t->enum('status', ['draft', 'adopted'])->default('draft');
                $t->date('adopted_on')->nullable();
                $t->unsignedBigInteger('adopted_by')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
            });
            Schema::create('budget_lines', function (Blueprint $t) {
                $t->id();
                $t->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
                $t->enum('kind', ['income', 'expense']);
                $t->foreignId('account_id')->nullable()->constrained('accounts');
                $t->string('label', 180);
                $t->bigInteger('amount_minor')->default(0);
                $t->timestamps();
                $t->unique(['budget_id', 'account_id']);
            });
        }

        if (! Schema::hasTable('member_notifications')) {
            Schema::create('member_notifications', function (Blueprint $t) {
                $t->id();
                $t->string('title', 150);
                $t->text('body');
                $t->string('audience', 30);                 // member | all | antenna | guarantee | overdue
                $t->string('audience_ref', 60)->nullable();  // id de l'adhérent, de l'antenne ou de la garantie
                $t->string('audience_label', 190);
                $t->unsignedInteger('recipients')->default(0);   // membres ciblés
                $t->unsignedInteger('in_app')->default(0);       // notifications déposées dans l'application
                $t->unsignedInteger('with_phone')->default(0);   // membres joignables par téléphone (WhatsApp / SMS)
                $t->unsignedBigInteger('sent_by')->nullable();
                $t->timestamps();
            });
            Schema::create('member_notification_recipients', function (Blueprint $t) {
                $t->id();
                $t->foreignId('member_notification_id')->constrained('member_notifications')->cascadeOnDelete();
                $t->foreignId('member_id')->constrained('members');
                $t->boolean('in_app')->default(false);
                $t->timestamps();
                $t->index('member_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_notification_recipients');
        Schema::dropIfExists('member_notifications');
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
        Schema::table('members', fn (Blueprint $t) => $t->dropColumn('card_printed_at'));
        Schema::table('act_types', fn (Blueprint $t) => $t->dropColumn('tariff_minor'));
    }
};
