<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL — spatie/laravel-permission tables for NETWORK roles
 * (super_admin, security_admin, moderator). NetworkUser sets $connection='central',
 * so spatie reads/writes these central tables for network authorization.
 */
return new class extends Migration
{
    public function up(): void
    {
        $c = Schema::connection('central');

        $c->create('permissions', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });
        $c->create('roles', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });
        $c->create('model_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->string('model_type'); $t->unsignedBigInteger('model_id');
            $t->index(['model_id', 'model_type']);
            $t->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $t->primary(['permission_id', 'model_id', 'model_type']);
        });
        $c->create('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type'); $t->unsignedBigInteger('model_id');
            $t->index(['model_id', 'model_type']);
            $t->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $t->primary(['role_id', 'model_id', 'model_type']);
        });
        $c->create('role_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
            $t->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $t->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $t->primary(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        $c = Schema::connection('central');
        foreach (['role_has_permissions','model_has_roles','model_has_permissions','roles','permissions'] as $tbl) {
            $c->dropIfExists($tbl);
        }
    }
};
