<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Page d'accueil de la plateforme : demandes de démonstration (base centrale). */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('demo_requests', function (Blueprint $t) {
            $t->id();
            $t->string('name', 180);
            $t->string('organisation', 180);
            $t->string('role', 120)->nullable();
            $t->string('email', 180);
            $t->string('phone', 40)->nullable();
            $t->string('country', 120)->nullable();
            $t->string('members_range', 30)->nullable();
            $t->text('message')->nullable();
            $t->string('status', 20)->default('new');   // new | contacted | closed
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('demo_requests');
    }
};
