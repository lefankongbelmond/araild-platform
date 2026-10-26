<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT — internal users of the mutual + RBAC tables (spatie).
 * Runs inside each mutual's database. Roles here are the MUTUAL roles
 * (mutual_admin, enrollment_agent, controller_validator, ...).
 * Runs BEFORE parameters (100100) so the seeder can assign roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('first_name', 120);
            $t->string('last_name', 120);
            $t->string('email', 180)->unique();
            $t->string('phone', 20)->nullable();
            $t->foreignId('antenna_id')->nullable();
            $t->string('password');
            $t->text('two_factor_secret')->nullable();     // encrypted (Fortify)
            $t->text('two_factor_recovery_codes')->nullable();
            $t->timestamp('two_factor_confirmed_at')->nullable();
            $t->boolean('active')->default(true);
            $t->rememberToken();
            $t->timestamps();
            $t->softDeletes();
        });

        // --- spatie/laravel-permission standard tables (tenant connection = default) ---
        Schema::create('permissions', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });
        Schema::create('model_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->string('model_type'); $t->unsignedBigInteger('model_id');
            $t->index(['model_id', 'model_type']);
            $t->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $t->primary(['permission_id', 'model_id', 'model_type']);
        });
        Schema::create('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type'); $t->unsignedBigInteger('model_id');
            $t->index(['model_id', 'model_type']);
            $t->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $t->primary(['role_id', 'model_id', 'model_type']);
        });
        Schema::create('role_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
            $t->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $t->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $t->primary(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        foreach (['role_has_permissions','model_has_roles','model_has_permissions','roles','permissions','users'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
