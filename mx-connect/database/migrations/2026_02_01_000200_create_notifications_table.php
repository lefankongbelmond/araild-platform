<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app (database channel) notifications for public accounts on the central
 * domain — e.g. an organization receiving a "Je m'engage" request, or a requester
 * learning their request was accepted. Standard Laravel notifications schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('notifications');
    }
};
