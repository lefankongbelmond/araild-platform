<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traceability: when a post is published AS an organization, record which of its
 * managers actually published it (posts.organization_id alone doesn't say who).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('posts', function (Blueprint $t) {
            $t->foreignId('published_by_account_id')->nullable()->after('public_account_id')
                ->constrained('public_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('posts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('published_by_account_id');
        });
    }
};
