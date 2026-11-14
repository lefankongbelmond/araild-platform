<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base de CHAQUE mutuelle — mission « Mutuelle paramétrable », livraison L3 (2026-11-19). ADDITIVE :
 *
 *  - rôle « medical_advisor » (médecin-conseil) ajouté aux rôles de la mutuelle ;
 *  - coverage_rules : accord préalable exigé (examen administratif ou médical, au-delà d'un montant) ;
 *  - preauthorizations : demandes d'accord préalable (numéro attribué à l'accord, décision tracée,
 *    urgence régularisée a posteriori) ; prestations.preauthorization_id / authorization_missing ;
 *  - medicines : liste positive, exclusion, générique, quantité maximale par ordonnance ;
 *  - prescriptions, dispensations, dispensation_lines : ordonnances et délivrances en pharmacie
 *    (contrôle des doubles facturations, des quantités et des renouvellements) ;
 *  - mutual_settings : réglages de la mutuelle (politique pharmaceutique), clé → valeur.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles') && ! DB::table('roles')->where('name', 'medical_advisor')->where('guard_name', 'web')->exists()) {
            DB::table('roles')->insert(['name' => 'medical_advisor', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            try {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable) {
                // cache indisponible : il expirera de lui-même
            }
        }

        if (! Schema::hasColumn('coverage_rules', 'requires_authorization')) {
            Schema::table('coverage_rules', function (Blueprint $t) {
                $t->boolean('requires_authorization')->default(false);
                $t->string('authorization_review', 14)->default('administrative');   // administrative | medical
                $t->bigInteger('authorization_above_minor')->nullable();             // accord exigé au-delà de ce montant
            });
        }

        if (! Schema::hasTable('preauthorizations')) {
            Schema::create('preauthorizations', function (Blueprint $t) {
                $t->id();
                $t->string('number', 24)->nullable()->unique();      // attribué à l'accord : AUT-AAAA-NNNNN
                $t->unsignedBigInteger('member_id')->index();
                $t->unsignedBigInteger('dependent_id')->nullable();
                $t->unsignedBigInteger('provider_id')->index();      // établissement (registre central)
                $t->unsignedBigInteger('act_type_id');
                $t->unsignedBigInteger('coverage_rule_id')->nullable();
                $t->unsignedBigInteger('guarantee_version_id')->nullable();
                $t->date('planned_on');
                $t->bigInteger('estimated_minor');
                $t->bigInteger('estimated_mutual_minor')->nullable();
                $t->bigInteger('approved_minor')->nullable();        // part mutuelle accordée au plus
                $t->string('review_type', 14)->default('administrative');
                $t->string('status', 12)->default('requested');      // requested|approved|rejected|used|expired|cancelled
                $t->boolean('emergency')->default(false);            // urgence : régularisation a posteriori
                $t->text('clinical_info')->nullable();               // DONNÉE DE SANTÉ : personnes habilitées seulement
                $t->json('auto_checks')->nullable();
                $t->string('source', 10)->default('provider');       // provider | mutual
                $t->unsignedBigInteger('requested_by');
                $t->unsignedBigInteger('decided_by')->nullable();
                $t->timestamp('decided_at')->nullable();
                $t->text('decision_note')->nullable();
                $t->date('valid_until')->nullable();
                $t->unsignedBigInteger('prestation_id')->nullable()->index();
                $t->timestamps();
                $t->index(['member_id', 'status']);
            });
        }
        if (! Schema::hasColumn('prestations', 'preauthorization_id')) {
            Schema::table('prestations', function (Blueprint $t) {
                $t->unsignedBigInteger('preauthorization_id')->nullable()->index();
                $t->boolean('authorization_missing')->default(false);   // urgence soignée sans accord : à régulariser
            });
        }

        if (! Schema::hasColumn('medicines', 'eligible')) {
            Schema::table('medicines', function (Blueprint $t) {
                $t->boolean('eligible')->default(true);            // liste positive de la mutuelle
                $t->boolean('excluded')->default(false);           // exclu de la prise en charge
                $t->boolean('generic')->default(false);
                $t->unsignedInteger('max_quantity')->nullable();   // par ordonnance (délivrance)
            });
        }

        if (! Schema::hasTable('prescriptions')) {
            Schema::create('prescriptions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('member_id')->index();
                $t->unsignedBigInteger('dependent_id')->nullable();
                $t->string('reference', 60);                       // n° porté sur l'ordonnance
                $t->char('reference_key', 64)->unique();           // empreinte : n° normalisé + bénéficiaire + date
                $t->string('prescriber', 160);
                $t->date('prescribed_on');
                $t->unsignedTinyInteger('renewals_allowed')->default(0);
                $t->unsignedBigInteger('first_provider_id');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('dispensations')) {
            Schema::create('dispensations', function (Blueprint $t) {
                $t->id();
                $t->foreignId('prescription_id')->constrained('prescriptions');
                $t->unsignedBigInteger('provider_id')->index();
                $t->unsignedBigInteger('prestation_id')->nullable()->index();
                $t->date('dispensed_on');
                $t->bigInteger('total_minor');
                $t->bigInteger('excluded_minor')->default(0);
                $t->unsignedBigInteger('captured_by');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('dispensation_lines')) {
            Schema::create('dispensation_lines', function (Blueprint $t) {
                $t->id();
                $t->foreignId('dispensation_id')->constrained('dispensations')->cascadeOnDelete();
                $t->unsignedBigInteger('medicine_id')->index();
                $t->unsignedInteger('quantity_prescribed');
                $t->unsignedInteger('quantity_dispensed');
                $t->bigInteger('unit_price_minor');
                $t->bigInteger('amount_minor');
                $t->boolean('eligible')->default(true);
                $t->string('reason', 40)->nullable();              // excluded | not_listed
                $t->boolean('above_reference')->default(false);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('mutual_settings')) {
            Schema::create('mutual_settings', function (Blueprint $t) {
                $t->id();
                $t->string('key', 60)->unique();
                $t->text('value')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['dispensation_lines', 'dispensations', 'prescriptions', 'preauthorizations', 'mutual_settings'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
        $drop = function (string $table, array $cols) {
            $cols = array_values(array_filter($cols, fn ($c) => Schema::hasColumn($table, $c)));
            if ($cols) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($cols));
            }
        };
        $drop('medicines', ['eligible', 'excluded', 'generic', 'max_quantity']);
        $drop('prestations', ['preauthorization_id', 'authorization_missing']);
        $drop('coverage_rules', ['requires_authorization', 'authorization_review', 'authorization_above_minor']);
        // Le rôle medical_advisor est conservé (des comptes peuvent l'avoir).
    }
};
