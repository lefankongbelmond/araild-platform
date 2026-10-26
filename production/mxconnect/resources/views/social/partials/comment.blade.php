@php $viewer = auth('member')->user(); @endphp
<div class="mx-comment-wrap" id="comment-{{ $c->id }}">
  <div class="mx-comment">
    <x-mx.avatar :src="$c->mutual_id ? $c->mutual?->profile?->logoUrl() : $c->account?->avatarUrl()" :name="$c->authorName()" :org="(bool) $c->mutual_id" size="sm" />
    <div class="mx-grow">
      <div class="mx-comment__bubble"><b>{{ $c->authorName() }}</b>@if($c->mutual_id) <span class="mx-badge mx-badge--blue" style="height:18px">Mutuelle</span>@endif<p>{!! \App\Support\SocialText::render($c->body, $c->mentions) !!}</p></div>
      <div class="mx-comment__tools">
        <span>{{ $c->created_at->diffForHumans() }}</span>
        @if ($viewer && ! $c->parent_id)<button type="button" data-reply="{{ $c->id }}">Répondre</button>@endif
        @if ($viewer && $c->public_account_id === $viewer->id)
          <form method="POST" action="{{ route('social.comment.destroy', $c) }}" data-confirm="Supprimer ce commentaire ?" style="display:inline">@csrf @method('DELETE')<button>Supprimer</button></form>
        @elseif ($viewer)
          <button type="button" data-report="{{ route('social.comment.report', $c) }}">Signaler</button>
        @endif
      </div>
      @if (! $c->parent_id)
        <div class="mx-replies">
          @foreach ($c->replies as $r) @include('social.partials.comment', ['c' => $r, 'post' => $post]) @endforeach
          @if ($viewer)
            <form class="mx-comment" method="POST" action="{{ route('social.comment.store', $post) }}" data-comment-form data-reply-form="{{ $c->id }}" hidden>
              @csrf <input type="hidden" name="parent_id" value="{{ $c->id }}">
              <div class="mx-grow mx-row"><textarea class="mx-textarea" name="body" rows="1" maxlength="2000" required placeholder="Votre réponse… (@ pour mentionner)" style="min-height:38px" data-mentions data-emoji></textarea>
              <button class="mx-btn mx-btn--primary mx-btn--sm" aria-label="Répondre"><x-mx.icon name="send" :size="16"/></button></div>
            </form>
          @endif
        </div>
      @endif
    </div>
  </div>
</div>
