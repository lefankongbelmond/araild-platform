<?php

use App\Enums\OrganizationManagerRole;
use App\Enums\OrganizationStatus;
use App\Enums\PostType;
use App\Models\Central\Hashtag;
use App\Models\Central\Post;
use App\Models\Central\PostComment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/SocialHelpers.php';

/** Lot 2 — public feed: authorship, visibility, interactions, output escaping. */

it('publishes a personal post, extracts hashtags and shows it in the feed', function () {
    $me = socialAccount();

    $this->actingAs($me, 'member')->post(route('posts.store'), [
        'as' => 'me', 'type' => 'text', 'body' => 'Merci à tous ! #SantéCommunautaire #Jeunesse',
    ])->assertRedirect(route('feed'));

    $post = Post::firstOrFail();
    expect($post->public_account_id)->toBe($me->id)
        ->and($post->organization_id)->toBeNull()
        ->and($post->hashtags->pluck('tag')->all())->toBe(['santécommunautaire', 'jeunesse']);

    $this->get(route('feed'))->assertOk()->assertSee('Merci à tous');
    $this->get(route('feed', ['tag' => 'jeunesse']))->assertOk()->assertSee('Merci à tous');
    $this->get(route('feed', ['tag' => 'autre']))->assertOk()->assertDontSee('Merci à tous');
    expect(Hashtag::where('tag', 'jeunesse')->value('uses_count'))->toBe(1);
});

it('reserves events, opportunities and results to organizations', function () {
    $me = socialAccount();

    $this->actingAs($me, 'member')->post(route('posts.store'), [
        'as' => 'me', 'type' => 'opportunity', 'opportunity_kind' => 'appel_benevoles', 'body' => 'Besoin de bras',
    ])->assertSessionHasErrors('type');

    expect(Post::count())->toBe(0);
});

it('refuses to publish as an organization the account does not manage', function () {
    $org = socialOrg(socialAccount());
    $intruder = socialAccount('Intruder');

    $this->actingAs($intruder, 'member')->post(route('posts.store'), [
        'as' => (string) $org->id, 'type' => 'text', 'body' => 'Faux message',
    ])->assertForbidden();

    expect(Post::count())->toBe(0);
});

it('lets an editor publish as the organization and records who published', function () {
    $org = socialOrg(socialAccount());
    $editor = socialAccount('Editor');
    addManager($org, $editor, OrganizationManagerRole::Editor);

    $this->actingAs($editor, 'member')->post(route('posts.store'), [
        'as' => (string) $org->id, 'type' => 'event', 'body' => 'Journée de consultation gratuite',
        'event_starts_at' => now()->addWeek()->format('Y-m-d H:i'), 'event_location' => 'Bafoussam',
    ])->assertRedirect();

    $post = Post::firstOrFail();
    expect($post->organization_id)->toBe($org->id)
        ->and($post->public_account_id)->toBeNull()
        ->and($post->published_by_account_id)->toBe($editor->id)
        ->and($post->type)->toBe(PostType::Event);
});

it('stores uploaded photos and marks the post as a photo post', function () {
    Storage::fake('public');
    $me = socialAccount();

    $this->actingAs($me, 'member')->post(route('posts.store'), [
        'as' => 'me', 'type' => 'text', 'media' => [UploadedFile::fake()->image('plantation.jpg', 800, 600)],
    ])->assertRedirect();

    $post = Post::firstOrFail();
    expect($post->type)->toBe(PostType::Photo)->and($post->media)->toHaveCount(1);
    Storage::disk('public')->assertExists($post->media[0]['path']);
});

it('limits followers-only posts to followers and managers', function () {
    $owner = socialAccount();
    $org = socialOrg($owner);
    $post = orgPost($org, $owner, ['body' => 'Réservé aux abonnés', 'visibility' => 'followers']);
    $follower = socialAccount('Follower');
    $org->followers()->attach($follower->id);
    $stranger = socialAccount('Stranger');

    $this->get(route('posts.show', $post))->assertNotFound();
    $this->actingAs($stranger, 'member')->get(route('posts.show', $post))->assertNotFound();
    $this->actingAs($stranger, 'member')->post(route('posts.like', $post))->assertNotFound();
    $this->actingAs($follower, 'member')->get(route('posts.show', $post))->assertOk()->assertSee('Réservé aux abonnés');
    $this->actingAs($owner, 'member')->get(route('feed', ['onglet' => 'organisations']))->assertSee('Réservé aux abonnés');
});

it('hides the posts of a suspended organization', function () {
    $owner = socialAccount();
    $org = socialOrg($owner);
    $post = orgPost($org, $owner, ['body' => 'Message de l’organisation suspendue']);
    $org->update(['status' => OrganizationStatus::Suspended]);

    $this->get(route('feed'))->assertDontSee('Message de l’organisation suspendue', false);
    $this->get(route('posts.show', $post))->assertNotFound();
});

it('toggles likes and keeps the counter consistent', function () {
    $owner = socialAccount();
    $post = orgPost(socialOrg($owner), $owner);
    $fan = socialAccount('Fan');

    $this->actingAs($fan, 'member')->postJson(route('posts.like', $post))->assertJson(['liked' => true, 'likes_count' => 1]);
    $this->actingAs($fan, 'member')->postJson(route('posts.like', $post))->assertJson(['liked' => false, 'likes_count' => 0]);
});

it('comments, replies and lets only the author or an org admin remove a comment', function () {
    $owner = socialAccount();
    $post = orgPost(socialOrg($owner), $owner);
    $writer = socialAccount('Writer');
    $stranger = socialAccount('Stranger');

    $this->actingAs($writer, 'member')->post(route('posts.comments.store', $post), ['body' => 'Bravo !'])->assertRedirect();
    $comment = PostComment::firstOrFail();
    $this->actingAs($stranger, 'member')->post(route('posts.comments.store', $post), ['body' => 'Merci', 'parent_id' => $comment->id]);
    expect($post->refresh()->comments_count)->toBe(2);

    $this->actingAs($stranger, 'member')->delete(route('comments.destroy', $comment))->assertForbidden();
    $this->actingAs($owner, 'member')->delete(route('comments.destroy', $comment))->assertRedirect();
    expect($post->refresh()->comments_count)->toBe(1);
});

it('escapes user content while still linking hashtags and urls', function () {
    $me = socialAccount();
    $this->actingAs($me, 'member')->post(route('posts.store'), [
        'as' => 'me', 'type' => 'text', 'body' => '<script>alert(1)</script> voir https://example.org/#ancre #Santé',
    ]);

    $html = $this->get(route('posts.show', Post::firstOrFail()))->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and($html)->toContain('href="https://example.org/#ancre"')
        ->and($html)->toContain('tag=sant%C3%A9');
});

it('lets the author remove their post, and nobody else', function () {
    $me = socialAccount();
    $this->actingAs($me, 'member')->post(route('posts.store'), ['as' => 'me', 'type' => 'text', 'body' => 'À supprimer']);
    $post = Post::firstOrFail();

    $this->actingAs(socialAccount('Other'), 'member')->delete(route('posts.destroy', $post))->assertForbidden();
    $this->actingAs($me, 'member')->delete(route('posts.destroy', $post))->assertRedirect();

    expect(Post::count())->toBe(0)->and(Post::withTrashed()->first()->status->value)->toBe('removed');
});
