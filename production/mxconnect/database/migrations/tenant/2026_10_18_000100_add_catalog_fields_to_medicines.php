<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue des médicaments : catégorie, présentation, fournisseur, devise du
 * prix de référence (NULL = devise de la mutuelle) et description.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $t) {
            if (! Schema::hasColumn('medicines', 'category')) {
                $t->string('category', 40)->nullable()->after('label');
                $t->string('presentation', 120)->nullable()->after('category');
                $t->string('supplier', 120)->nullable()->after('presentation');
                $t->string('currency_code', 3)->nullable()->after('reference_price_minor');
                $t->text('description')->nullable()->after('currency_code');
                $t->index('category');
                $t->index('supplier');
            }
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $t) {
            $t->dropIndex(['category']);
            $t->dropIndex(['supplier']);
            $t->dropColumn(['category', 'presentation', 'supplier', 'currency_code', 'description']);
        });
    }
};
