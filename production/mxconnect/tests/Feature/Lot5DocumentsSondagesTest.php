<?php

/*
 * Lot 5 — documents PDF publics (publications d'une page, projets) et sondages du fil.
 */

use App\Models\Central\Country;
use App\Models\Social\Document;
use App\Models\Social\Poll;
use App\Models\Social\PollVote;
use App\Models\Social\Post;
use App\Services\Social\ProjectService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('social');
    Storage::fake('social_private');
    $this->seed(\Database\Seeders\ConfigReferenceSeeder::class);
    $this->country = Country::where('active', true)->firstOrFail();
});

// --- Documents ---

it('joint un PDF public à une publication au nom d’une page, téléchargeable et compté', function () {
    $owner = account();
    $page = memberPage($owner);
    $this->actingAs($owner, 'member')->post(route('social.post.store'), [
        'body' => 'Notre rapport d’activité 2026', 'as' => $page->slug,
        'documents' => [pdf('Rapport activite 2026.pdf')], 'documents_public' => '1',
    ])->assertRedirect();

    $post = Post::latest('id')->firstOrFail();
    $doc = Document::firstOrFail();
    expect($post->type)->toBe('document')->and($doc->post_id)->toBe($post->id)->and($doc->title)->toBe('Rapport activite 2026');
    Storage::disk('social')->assertExists($doc->path);

    auth('member')->logout();
    $this->get(route('social.post', $post))->assertOk()->assertSee('Rapport activite 2026')->assertSee('PDF ·');
    $res = $this->get(route('social.document', $doc))->assertOk();
    expect($res->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($res->headers->get('Content-Security-Policy'))->toBe('sandbox');
    expect($doc->fresh()->downloads_count)->toBe(1);
    // Jamais servi par /media (pas d'affichage « en ligne » sur le domaine du site).
    $this->get('/media/' . $doc->path)->assertNotFound();
});

it('refuse les documents sur un profil personnel, un faux PDF, ou sans la confirmation « publics »', function () {
    $owner = account();
    $page = memberPage($owner);
    $this->actingAs($owner, 'member')->post(route('social.post.store'), ['body' => 'Perso', 'documents' => [pdf()], 'documents_public' => '1'])
        ->assertSessionHasErrors('documents');
    $this->post(route('social.post.store'), ['body' => 'Sans case', 'as' => $page->slug, 'documents' => [pdf()]])
        ->assertSessionHasErrors('documents_public');
    $fake = UploadedFile::fake()->createWithContent('faux.pdf', 'MZ-pas-un-pdf');
    $this->post(route('social.post.store'), ['body' => 'Faux', 'as' => $page->slug, 'documents' => [$fake], 'documents_public' => '1'])
        ->assertSessionHasErrors('documents');
    expect(Document::count())->toBe(0)->and(Post::count())->toBe(0);   // rien de publié à moitié
});

it('suit la visibilité de la publication : document d’une publication supprimée introuvable, fichier effacé', function () {
    $owner = account();
    $page = memberPage($owner);
    $this->actingAs($owner, 'member')->post(route('social.post.store'), ['body' => 'Affiche', 'as' => $page->slug, 'documents' => [pdf()], 'documents_public' => '1']);
    $post = Post::latest('id')->firstOrFail();
    $doc = Document::firstOrFail();

    $this->delete(route('social.post.destroy', $post))->assertRedirect();
    Storage::disk('social')->assertMissing($doc->path);
    $this->get(route('social.document', $doc->id))->assertNotFound();
});

it('ajoute des documents à un projet (réseau) et les montre sur la page du projet ; brouillon privé', function () {
    $owner = account();
    $page = memberPage($owner);
    $project = app(ProjectService::class)->saveProject($page, projectData(['announce' => null, 'status' => 'draft']), null, null, $owner);

    $this->actingAs($owner, 'member')->post(route('social.manage.document.store', [$page->slug, $project->id]), [
        'documents' => [pdf()], 'document_titles' => ['Rapport du district'], 'documents_public' => '1',
    ])->assertRedirect();
    $doc = Document::firstOrFail();
    expect($doc->project_id)->toBe($project->id);
    $this->get(route('social.document', $doc))->assertOk();                 // gestionnaire : brouillon accessible

    auth('member')->logout();
    $this->get(route('social.document', $doc))->assertNotFound();           // public : projet en brouillon
    $project->update(['status' => 'published']);
    $this->get(route('social.project', $project))->assertOk()->assertSee('Rapport du district');
    $this->get(route('social.document', $doc))->assertOk();

    $this->actingAs($owner, 'member')->post(route('social.manage.document.destroy', [$page->slug, $project->id, $doc->id]))->assertRedirect();
    Storage::disk('social')->assertMissing($doc->path);
});

it('efface les documents d’un projet supprimé', function () {
    $owner = account();
    $page = memberPage($owner);
    $project = app(ProjectService::class)->saveProject($page, projectData(['announce' => null]), null, null, $owner);
    $docs = app(\App\Services\Social\DocumentService::class)->store($page, [pdf()], [], ['project_id' => $project->id], $owner);
    app(ProjectService::class)->deleteProject($project->fresh());
    Storage::disk('social')->assertMissing($docs[0]->path);
    expect(Document::count())->toBe(0);
});

// --- Sondages ---

it('crée un sondage, compte un vote par personne (modifiable) et garde les résultats anonymes', function () {
    $author = account();
    $this->actingAs($author, 'member')->post(route('social.post.store'), [
        'body' => 'Quel jour pour la prochaine journée de dépistage ?', 'poll_options' => ['Samedi', 'Dimanche', '', 'samedi'], 'poll_days' => '3',
    ])->assertRedirect();
    $post = Post::latest('id')->firstOrFail();
    $poll = Poll::with('options')->firstOrFail();
    expect($poll->options->pluck('label')->all())->toBe(['Samedi', 'Dimanche'])        // vide et doublon retirés
        ->and($poll->closes_at->diffInDays(now(), true))->toBeGreaterThan(2.9);

    $a = account('Votante'); $b = account('Votant');
    $this->actingAs($a, 'member')->post(route('social.poll.vote', $post), ['option' => $poll->options[0]->id])->assertRedirect();
    $this->post(route('social.poll.vote', $post), ['option' => $poll->options[0]->id]);      // revoter le même choix : aucun effet
    $this->actingAs($b, 'member')->post(route('social.poll.vote', $post), ['option' => $poll->options[0]->id]);
    $this->post(route('social.poll.vote', $post), ['option' => $poll->options[1]->id]);      // change d'avis
    $poll->refresh()->load('options');
    expect($poll->votes_count)->toBe(2)->and($poll->options[0]->votes_count)->toBe(1)->and($poll->options[1]->votes_count)->toBe(1)
        ->and(PollVote::count())->toBe(2);

    $html = $this->actingAs($a, 'member')->get(route('social.post', $post))->assertOk()->assertSee('50 %')->assertSee('2 votes')->getContent();
    expect($html)->not->toContain($b->name);    // personne ne voit qui a voté quoi
});

it('refuse un sondage à un seul choix et les votes après la clôture', function () {
    $author = account();
    $this->actingAs($author, 'member')->post(route('social.post.store'), ['body' => 'Question ?', 'poll_options' => ['Oui']])
        ->assertSessionHasErrors('poll_options');
    expect(Post::count())->toBe(0);

    $this->post(route('social.post.store'), ['body' => 'Question ?', 'poll_options' => ['Oui', 'Non'], 'poll_days' => '1']);
    $post = Post::latest('id')->firstOrFail();
    $post->poll->update(['closes_at' => now()->subMinute()]);
    $this->actingAs(account('Tard'), 'member')->post(route('social.poll.vote', $post), ['option' => $post->poll->options()->first()->id])->assertStatus(422);
    $this->get(route('social.post', $post))->assertSee('sondage terminé');
});

it('montre les résultats aux visiteurs non connectés, avec une invitation à se connecter pour voter', function () {
    $author = account();
    $this->actingAs($author, 'member')->post(route('social.post.store'), ['body' => 'Votre avis ?', 'poll_options' => ['A', 'B']]);
    $post = Post::latest('id')->firstOrFail();
    auth('member')->logout();
    $this->get(route('social.post', $post))->assertOk()->assertSee('connectez-vous pour voter')->assertDontSee('name="option"', false);
    $this->post(route('social.poll.vote', $post), ['option' => 1])->assertRedirect();   // renvoyé vers la connexion
    expect(PollVote::count())->toBe(0);
});
