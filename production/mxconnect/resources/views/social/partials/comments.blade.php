@php
  $viewer = auth('member')->user();
  $comments = $post->comments()->whereNull('parent_id')->where('status', 'visible')
      ->where(fn ($w) => $w->whereNull('public_account_id')->orWhereIn('public_account_id', \App\Models\Central\PublicAccount::whereNull('suspended_at')->select('id')))
      ->with(['account', 'mutual.profile', 'mentions.mutual', 'replies.account', 'replies.mutual.profile', 'replies.mentions.mutual'])->orderBy('created_at')->limit(100)->get();
@endphp
<section class="mx-comments" id="commentaires" aria-label="Commentaires">
  @if ($viewer)
    <form class="mx-comment" method="POST" action="{{ route('social.comment.store', $post) }}" data-comment-form>
      @csrf
      <x-mx.avatar :src="$viewer->avatarUrl()" :name="$viewer->name" size="sm" />
      <div class="mx-grow mx-row">
        <label class="mx-sr" for="c-{{ $post->id }}">Votre commentaire</label>
        <textarea id="c-{{ $post->id }}" class="mx-textarea" name="body" rows="1" maxlength="2000" required placeholder="Écrire un commentaire… (@ pour mentionner)" style="min-height:40px" data-mentions data-emoji></textarea>
        <button class="mx-btn mx-btn--primary mx-btn--sm" aria-label="Publier le commentaire"><x-mx.icon name="send" :size="16"/></button>
      </div>
    </form>
  @else
    <p class="mx-small"><a href="{{ route('social.login') }}">Connectez-vous</a> pour commenter.</p>
  @endif
  <div class="mx-stack" data-comment-list style="gap:10px">
    @forelse ($comments as $c)
      @include('social.partials.comment', ['c' => $c, 'post' => $post])
    @empty
      <p class="mx-small mx-muted" data-no-comment>Aucun commentaire pour l'instant.</p>
    @endforelse
  </div>
</section>
