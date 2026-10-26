{{-- Réels : une vidéo par écran. Rendu seul (fragment) pour le chargement continu. --}}
@php
  $viewer = auth('member')->user();
  $list = collect($first ?? null ? [$first] : [])->concat(collect($reels->items())->reject(fn ($p) => ($first ?? null) && $p->id === $first->id));
@endphp
@foreach ($list as $post)
  @php
    $video = $post->media->first(fn ($m) => $m->isVideo());
    $mine = $state['reactions'][$post->id] ?? null;
  @endphp
  @continue(! $video)
  <section class="mx-reel" id="reel-{{ $post->id }}" data-post="{{ $post->id }}" aria-label="Réel de {{ $post->authorName() }}">
    <video class="mx-reel__video" src="{{ $video->url() }}" @if($video->posterUrl()) poster="{{ $video->posterUrl() }}" @endif loop playsinline muted preload="metadata" data-reel-video></video>
    <button type="button" class="mx-reel__sound" data-reel-sound aria-label="Activer le son"><x-mx.icon name="megaphone" :size="18"/> <span>Son coupé</span></button>
    <div class="mx-reel__info">
      <a class="mx-reel__author" href="{{ $post->authorUrl() ?? '#' }}"><x-mx.avatar :src="$post->authorAvatar()" :name="$post->authorName()" :org="(bool) $post->mutual_id" size="sm" /> <b>{{ $post->authorName() }}</b></a>
      @if ($post->is_demo)<span class="mx-demo-ribbon">Démo</span>@endif
      @if ($post->body)<p>{!! \App\Support\SocialText::render($post->body, $post->mentions, 220) !!}</p>@endif
    </div>
    <div class="mx-reel__side">
      @if ($viewer)
        <button type="button" @class(['mx-reel__btn', 'is-on' => $mine]) data-react="{{ route('social.post.react', $post) }}" data-type="jaime" aria-label="J’aime"><x-mx.icon name="thumb" :size="24"/><span data-react-count>{{ $post->reactions_count ?: '' }}</span><span data-react-label hidden></span></button>
        <a class="mx-reel__btn" href="{{ route('social.post', $post) }}#commentaires" aria-label="Commentaires"><x-mx.icon name="comment" :size="24"/><span>{{ $post->comments_count ?: '' }}</span></a>
        <button type="button" class="mx-reel__btn" data-share="{{ route('social.post.share', $post) }}" aria-label="Partager"><x-mx.icon name="share" :size="24"/><span>{{ $post->shares_count ?: '' }}</span></button>
      @else
        <a class="mx-reel__btn" href="{{ route('social.login') }}" aria-label="J’aime"><x-mx.icon name="thumb" :size="24"/><span>{{ $post->reactions_count ?: '' }}</span></a>
        <a class="mx-reel__btn" href="{{ route('social.post', $post) }}#commentaires" aria-label="Commentaires"><x-mx.icon name="comment" :size="24"/><span>{{ $post->comments_count ?: '' }}</span></a>
      @endif
      <button type="button" class="mx-reel__btn" data-copy="{{ route('social.reels', ['r' => $post->id]) }}" aria-label="Copier le lien"><x-mx.icon name="link" :size="24"/></button>
      @if ($viewer && ! $post->canManage($viewer))
        <button type="button" class="mx-reel__btn" data-report="{{ route('social.post.report', $post) }}" aria-label="Signaler ce réel"><x-mx.icon name="flag" :size="22"/></button>
      @endif
    </div>
  </section>
@endforeach
@if ($reels->hasMorePages())
  <div class="mx-reel mx-reel--more" data-more-wrap>
    <a class="mx-btn mx-btn--ghost" href="{{ $reels->nextPageUrl() }}" data-more>Charger plus de réels</a>
  </div>
@endif
