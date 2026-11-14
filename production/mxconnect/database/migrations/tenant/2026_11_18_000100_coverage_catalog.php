<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base de CHAQUE mutuelle — Catalogue des formules et garanties + moteur de prise en charge
 * (mission « Mutuelle paramétrable », livraison L1–L2, 2026-11-18). Migration ADDITIVE :
 *
 *  - service_categories : bibliothèque des catégories de soins (libellés seulement, aucun taux) ;
 *    act_types.service_category_id : rattachement d'un acte à une catégorie ;
 *  - guarantees (= FORMULES) : catégorie, population cible, âges, nombre de bénéficiaires,
 *    commercialisation, statut du catalogue (brouillon, test, active, suspendue, archivée) ;
 *  - guarantee_versions : périodicités semestrielle et personnalisée, âge « senior », renouvellement
 *    continu (carence non recomptée), auteur et note de la version ;
 *  - coverage_rules : GARANTIES d'une version (taux, ticket modérateur, catégorie de bénéficiaire,
 *    carence spécifique accident, urgence exemptée, plafonnement au tarif conventionnel) ;
 *  - coverage_limits : plafonds par acte / jour / mois / an, par bénéficiaire ou par famille, en
 *    montant et/ou en nombre d'actes, propres à une garantie ou PARTAGÉS entre plusieurs (désignées
 *    par leur rule_key, identité stable d'une version de formule à la suivante) ;
 *  - waiting_periods : délais de carence par garantie et catégorie de bénéficiaire ;
 *  - coverage_exceptions : dérogations (dispense de carence, plafond supplémentaire, acte exclu
 *    accordé), demandées puis décidées par une autre personne ;
 *  - dependents : élève, dates d'intégration et de sortie ;
 *  - prestations : garantie appliquée, montant éligible, ticket modérateur, non couvert, urgence,
 *    accident, dérogation utilisée (traçabilité du calcul).
 *
 * Les formules existantes restent valables telles quelles (taux unique, plafonds, observation) :
 * le nouveau moteur ne s'applique qu'aux versions qui reçoivent des garanties détaillées.
 */
return new class extends Migration
{
    private const LIBRARY = [
        ['consultation', 'Consultation médicale', 'consultation'],
        ['consultation_specialist', 'Consultation spécialisée', 'consultation'],
        ['hospitalization', 'Hospitalisation', 'hospital'],
        ['pharmacy', 'Pharmacie', 'pharmacy'],
        ['generic_drugs', 'Médicaments génériques', 'pharmacy'],
        ['lab_tests', 'Analyses médicales', 'lab'],
        ['lab_exams', 'Examens de laboratoire', 'lab'],
        ['imaging', 'Imagerie médicale', 'imaging'],
        ['radiology', 'Radiologie', 'imaging'],
        ['ultrasound', 'Échographie', 'imaging'],
        ['ct_scan', 'Scanner', 'imaging'],
        ['mri', 'IRM', 'imaging'],
        ['minor_surgery', 'Petite chirurgie', 'surgery'],
        ['major_surgery', 'Grande chirurgie', 'surgery'],
        ['delivery_simple', 'Accouchement simple', 'maternity'],
        ['delivery_complicated', 'Accouchement compliqué', 'maternity'],
        ['cesarean', 'Césarienne', 'maternity'],
        ['prenatal', 'Consultation prénatale', 'maternity'],
        ['maternity_hospital', 'Hospitalisation liée à la maternité', 'maternity'],
        ['dental', 'Soins dentaires', 'dental'],
        ['optical', 'Soins ophtalmologiques', 'optical'],
        ['emergency', 'Urgences', 'emergency'],
        ['transport', 'Transport sanitaire', 'transport'],
        ['other', 'Autres prestations', 'other'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('service_categories')) {
            Schema::create('service_categories', function (Blueprint $t) {
                $t->id();
                $t->string('code', 40)->unique();
                $t->string('label', 120);
                $t->string('family', 20)->default('other');
                $t->unsignedSmallInteger('sort')->default(0);
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
            foreach (self::LIBRARY as $i => [$code, $label, $family]) {
                DB::table('service_categories')->insert(['code' => $code, 'label' => $label, 'family' => $family, 'sort' => ($i + 1) * 10,
                    'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        if (! Schema::hasColumn('act_types', 'service_category_id')) {
            Schema::table('act_types', fn (Blueprint $t) => $t->unsignedBigInteger('service_category_id')->nullable()->index());
        }

        if (! Schema::hasColumn('guarantees', 'catalog_status')) {
            Schema::table('guarantees', function (Blueprint $t) {
                $t->string('catalog_status', 12)->default('active');
                $t->string('category', 60)->nullable();
                $t->string('target_population', 255)->nullable();
                $t->unsignedTinyInteger('min_age')->nullable();
                $t->unsignedTinyInteger('max_age')->nullable();
                $t->unsignedSmallInteger('max_beneficiaries')->nullable();
                $t->date('sale_from')->nullable();
                $t->date('sale_to')->nullable();
                $t->boolean('is_example')->default(false);
            });
        }

        if (! Schema::hasColumn('guarantee_versions', 'period_months')) {
            // Périodicités semestrielle et personnalisée (nom de table entouré par la grammaire :
            // compatible avec les mutuelles à tables préfixées).
            $table = DB::connection()->getQueryGrammar()->wrapTable('guarantee_versions');
            DB::statement("ALTER TABLE {$table} MODIFY periodicity ENUM('monthly','quarterly','semiannual','annual','custom') NOT NULL");
            Schema::table('guarantee_versions', function (Blueprint $t) {
                $t->unsignedTinyInteger('period_months')->nullable();
                $t->unsignedTinyInteger('senior_age')->nullable();
                $t->boolean('renewal_waiver')->default(false);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->string('change_note', 255)->nullable();
            });
        }

        if (! Schema::hasTable('coverage_rules')) {
            Schema::create('coverage_rules', function (Blueprint $t) {
                $t->id();
                $t->foreignId('guarantee_version_id')->constrained('guarantee_versions')->cascadeOnDelete();
                // Identité STABLE de la garantie d'une version à l'autre : les consommations de plafond
                // d'une année ne repartent pas de zéro quand la formule change de version.
                $t->char('rule_key', 26)->index();
                $t->unsignedBigInteger('service_category_id')->nullable();   // null = toutes les prestations
                $t->string('label', 120);
                $t->text('description')->nullable();
                $t->string('beneficiary_category', 16)->default('all');
                $t->decimal('coverage_rate', 5, 2);
                $t->decimal('copay_rate', 5, 2)->nullable();
                $t->unsignedSmallInteger('accident_waiting_days')->nullable();
                $t->boolean('emergency_exempt')->default(true);
                $t->boolean('tariff_cap')->default(false);
                $t->text('conditions')->nullable();
                $t->boolean('active')->default(true);
                $t->unsignedSmallInteger('sort')->default(0);
                $t->timestamps();
                $t->index(['guarantee_version_id', 'service_category_id']);
            });
        }
        if (! Schema::hasTable('coverage_limits')) {
            Schema::create('coverage_limits', function (Blueprint $t) {
                $t->id();
                $t->foreignId('guarantee_version_id')->constrained('guarantee_versions')->cascadeOnDelete();
                $t->string('label', 120)->nullable();
                $t->json('rule_keys')->nullable();         // null = toutes les garanties de la formule ; plusieurs = plafond PARTAGÉ
                $t->string('scope', 8);                    // act | day | month | year
                $t->string('holder', 12)->default('beneficiary');   // beneficiary | family
                $t->bigInteger('amount_minor')->nullable();
                $t->unsignedInteger('max_count')->nullable();
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('waiting_periods')) {
            Schema::create('waiting_periods', function (Blueprint $t) {
                $t->id();
                $t->foreignId('guarantee_version_id')->constrained('guarantee_versions')->cascadeOnDelete();
                $t->char('rule_key', 26)->nullable();       // null = toutes les garanties
                $t->string('beneficiary_category', 16)->default('all');
                $t->unsignedSmallInteger('days');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('coverage_exceptions')) {
            Schema::create('coverage_exceptions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('member_id')->index();
                $t->unsignedBigInteger('dependent_id')->nullable();
                $t->string('kind', 20);                    // waiting_waiver | limit_extra | exclusion_override
                $t->unsignedBigInteger('service_category_id')->nullable();
                $t->unsignedBigInteger('act_type_id')->nullable();
                $t->bigInteger('amount_minor')->nullable();
                $t->bigInteger('used_minor')->default(0);
                $t->date('valid_from');
                $t->date('valid_to')->nullable();
                $t->string('reason', 160);
                $t->text('justification');
                $t->string('status', 10)->default('requested');   // requested | approved | rejected | revoked
                $t->unsignedBigInteger('requested_by');
                $t->unsignedBigInteger('decided_by')->nullable();
                $t->timestamp('decided_at')->nullable();
                $t->text('decision_note')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasColumn('dependents', 'is_student')) {
            Schema::table('dependents', function (Blueprint $t) {
                $t->boolean('is_student')->default(false);
                $t->date('joined_on')->nullable();
                $t->date('left_on')->nullable();
            });
        }
        if (! Schema::hasColumn('prestations', 'coverage_rule_id')) {
            Schema::table('prestations', function (Blueprint $t) {
                $t->unsignedBigInteger('coverage_rule_id')->nullable()->index();
                $t->bigInteger('eligible_minor')->nullable();
                $t->bigInteger('copay_minor')->nullable();
                $t->bigInteger('non_covered_minor')->nullable();
                $t->boolean('emergency')->default(false);
                $t->boolean('accident')->default(false);
                $t->unsignedBigInteger('coverage_exception_id')->nullable();
            });
        }
        if (! Schema::hasColumn('prestations', 'exception_used_minor')) {
            // Part prise sur un plafond supplémentaire accordé par dérogation : rendue si la
            // prestation est rejetée.
            Schema::table('prestations', fn (Blueprint $t) => $t->bigInteger('exception_used_minor')->default(0));
        }
    }

    public function down(): void
    {
        $cols = array_values(array_filter(['coverage_rule_id', 'eligible_minor', 'copay_minor', 'non_covered_minor', 'emergency', 'accident',
            'coverage_exception_id', 'exception_used_minor'], fn ($c) => Schema::hasColumn('prestations', $c)));
        if ($cols) {
            Schema::table('prestations', fn (Blueprint $t) => $t->dropColumn($cols));
        }
        if (Schema::hasColumn('dependents', 'is_student')) {
            Schema::table('dependents', fn (Blueprint $t) => $t->dropColumn(['is_student', 'joined_on', 'left_on']));
        }
        foreach (['coverage_exceptions', 'waiting_periods', 'coverage_limits', 'coverage_rules'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
        if (Schema::hasColumn('guarantee_versions', 'period_months')) {
            Schema::table('guarantee_versions', fn (Blueprint $t) => $t->dropColumn(['period_months', 'senior_age', 'renewal_waiver', 'created_by', 'change_note']));
        }
        if (Schema::hasColumn('guarantees', 'catalog_status')) {
            Schema::table('guarantees', fn (Blueprint $t) => $t->dropColumn(['catalog_status', 'category', 'target_population', 'min_age', 'max_age', 'max_beneficiaries', 'sale_from', 'sale_to', 'is_example']));
        }
        if (Schema::hasColumn('act_types', 'service_category_id')) {
            Schema::table('act_types', fn (Blueprint $t) => $t->dropColumn('service_category_id'));
        }
        Schema::dropIfExists('service_categories');
        // L'énumération « periodicity » élargie est conservée (des versions peuvent l'utiliser).
    }
};
