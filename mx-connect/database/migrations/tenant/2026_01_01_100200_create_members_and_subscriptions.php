<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT schema — enrollment: members, dependents, groups, subscriptions
 * (maker-checker), amendments, suspensions, departures, radiations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_groups', function (Blueprint $t) {
            $t->id();
            $t->string('name', 180);
            $t->enum('type', ['company', 'association', 'cooperative']);
            $t->string('contact', 120)->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('members', function (Blueprint $t) {
            $t->id();
            $t->string('member_code', 40)->unique();
            $t->foreignId('antenna_id')->nullable()->constrained('antennas');
            $t->foreignId('member_group_id')->nullable()->constrained('member_groups');
            $t->string('first_name', 120);
            $t->string('last_name', 120);
            $t->date('birth_date');
            $t->enum('sex', ['M', 'F']);
            $t->string('phone', 20)->nullable();
            $t->text('id_document')->nullable();            // encrypted (strong anti-duplicate key)
            $t->string('address', 255)->nullable();
            $t->enum('status', ['active', 'suspended', 'left', 'revoked'])->default('active');
            $t->date('joined_at');
            $t->timestamps();
            $t->softDeletes();
            $t->index(['last_name', 'first_name', 'birth_date']); // duplicate detection
            $t->index('phone');
        });

        Schema::create('dependents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $t->string('first_name', 120);
            $t->string('last_name', 120);
            $t->date('birth_date');
            $t->enum('sex', ['M', 'F']);
            $t->enum('relationship', ['spouse', 'child', 'ascendant', 'other']);
            $t->enum('status', ['active', 'removed'])->default('active');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('member_id')->nullable()->constrained('members');
            $t->foreignId('member_group_id')->nullable()->constrained('member_groups');
            $t->foreignId('guarantee_version_id')->constrained('guarantee_versions');
            $t->bigInteger('contribution_minor');
            $t->date('effective_date');
            $t->date('observation_ends_on')->nullable();
            $t->enum('status', ['captured', 'validated', 'terminated'])->default('captured');
            $t->unsignedBigInteger('captured_by');          // FK users (tenant)
            $t->unsignedBigInteger('validated_by')->nullable(); // must differ (maker-checker)
            $t->timestamp('validated_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('amendments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $t->enum('type', ['add_beneficiary', 'remove_beneficiary', 'change']);
            $t->bigInteger('new_contribution_minor')->nullable();
            $t->date('effective_date');
            $t->timestamps();
        });

        Schema::create('suspensions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->string('reason', 255)->nullable();
            $t->enum('status', ['captured', 'validated'])->default('captured');
            $t->unsignedBigInteger('captured_by');
            $t->unsignedBigInteger('validated_by')->nullable();
            $t->timestamps();
        });

        Schema::create('departures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $t->string('reason', 255)->nullable();
            $t->date('departure_date');
            $t->enum('status', ['captured', 'validated'])->default('captured');
            $t->unsignedBigInteger('captured_by');
            $t->unsignedBigInteger('validated_by')->nullable();
            $t->timestamps();
        });

        Schema::create('radiations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('member_group_id')->constrained('member_groups')->cascadeOnDelete();
            $t->string('reason', 255)->nullable();
            $t->date('radiation_date');
            $t->enum('status', ['captured', 'validated'])->default('captured');
            $t->unsignedBigInteger('captured_by');
            $t->unsignedBigInteger('validated_by')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['radiations','departures','suspensions','amendments','subscriptions','dependents','members','member_groups'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
