<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Member social feed (CDC Phase 5) — tenant-scoped. Posts by members, comments,
 * and one like per member per post. Moderation via the post status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $t->text('body');
            $t->enum('status', ['published', 'hidden'])->default('published');
            $t->unsignedInteger('likes_count')->default(0);
            $t->unsignedInteger('comments_count')->default(0);
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });

        Schema::create('feed_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('feed_post_id')->constrained('feed_posts')->cascadeOnDelete();
            $t->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $t->text('body');
            $t->timestamps();
            $t->index(['feed_post_id', 'created_at']);
        });

        Schema::create('feed_post_likes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('feed_post_id')->constrained('feed_posts')->cascadeOnDelete();
            $t->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['feed_post_id', 'member_id']);   // one like per member per post
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_post_likes');
        Schema::dropIfExists('feed_comments');
        Schema::dropIfExists('feed_posts');
    }
};
