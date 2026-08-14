<?php

use App\Events\FeedPostCreated;
use App\Models\Tenant\FeedPost;
use App\Models\Tenant\Member;
use App\Services\FeedService;
use Illuminate\Support\Facades\Event;

/** Module 21 — member social feed (tenant-scoped). */

beforeEach(function () {
    $this->svc = app(FeedService::class);
    $this->member = Member::factory()->create(['first_name' => 'Awa', 'last_name' => 'Njoya']);
    $this->other  = Member::factory()->create(['first_name' => 'Paul', 'last_name' => 'Biya']);
});

it('creates a post and broadcasts it', function () {
    Event::fake([FeedPostCreated::class]);

    $post = $this->svc->createPost($this->member, '  Bonjour la mutuelle  ');

    expect($post->body)->toBe('Bonjour la mutuelle')      // trimmed
        ->and($post->status)->toBe('published');
    Event::assertDispatched(FeedPostCreated::class, fn ($e) => $e->post->id === $post->id && $e->authorName === 'Awa Njoya');
})->group('tenant');

it('toggles a like idempotently and counts distinct members', function () {
    $post = $this->svc->createPost($this->member, 'Hello');

    $a = $this->svc->toggleLike($this->member, $post);
    expect($a)->toMatchArray(['liked' => true, 'likes_count' => 1]);

    // Same member likes again -> unlike, back to 0.
    $b = $this->svc->toggleLike($this->member, $post);
    expect($b)->toMatchArray(['liked' => false, 'likes_count' => 0]);

    // Two different members -> count 2.
    $this->svc->toggleLike($this->member, $post);
    $c = $this->svc->toggleLike($this->other, $post);
    expect($c['likes_count'])->toBe(2);
})->group('tenant');

it('adds comments and keeps the counter in sync', function () {
    $post = $this->svc->createPost($this->member, 'Question');

    $this->svc->addComment($this->other, $post, 'Réponse 1');
    $this->svc->addComment($this->member, $post, 'Réponse 2');

    expect($post->fresh()->comments_count)->toBe(2);
})->group('tenant');

it('lists published posts newest first with the caller like state', function () {
    $p1 = $this->svc->createPost($this->member, 'Premier');
    $p2 = $this->svc->createPost($this->member, 'Second');
    $this->svc->toggleLike($this->other, $p1);

    $feed = $this->svc->feed(30, $this->other->id);

    expect($feed[0]['id'])->toBe($p2->id)         // newest first
        ->and($feed[1]['id'])->toBe($p1->id)
        ->and($feed[1]['liked'])->toBeTrue()       // other liked p1
        ->and($feed[0]['liked'])->toBeFalse();
})->group('tenant');

it('broadcasts on the tenant-scoped private feed channel', function () {
    $post = $this->svc->createPost($this->member, 'Diffusion');
    $event = new FeedPostCreated($post, 'mut_demo', 'Awa Njoya');

    expect($event->broadcastOn()->name)->toBe('private-tenant.mut_demo.feed')
        ->and($event->broadcastAs())->toBe('feed.post.created')
        ->and($event->broadcastWith())->toHaveKeys(['id', 'author', 'body', 'created_at']);
})->group('tenant');
