<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** owen-it/laravel-auditing default table. Every Auditable model writes here. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create("audits", function (Blueprint $table) {
            $table->id();
            $table->string("user_type")->nullable();
            $table->unsignedBigInteger("user_id")->nullable();
            $table->string("event");
            $table->morphs("auditable");
            $table->longText("old_values")->nullable();
            $table->longText("new_values")->nullable();
            $table->text("url")->nullable();
            $table->ipAddress("ip_address")->nullable();
            $table->string("user_agent", 1023)->nullable();
            $table->string("tags")->nullable();
            $table->timestamps();
            $table->index(["user_id", "user_type"]);
            // $table->morphs('auditable') above already creates the (auditable_type,
            // auditable_id) index — a second explicit one collides on it (duplicate
            // index name), which only surfaces when the migration actually runs.
        });
    }

    public function down(): void { Schema::dropIfExists("audits"); }
};
