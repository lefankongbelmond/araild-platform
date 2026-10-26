@php
  /** @var \App\Models\Social\Post $post */
  $detail = $detail ?? false;
  // Partage sans commentaire : on affiche la publication d'origine, précédée d'un
  // bandeau « X a partagé », au lieu d'une carte imbriquée dans une autre.
  $sponsor = $sponsor ?? null;
  $isRepost = ! $sponsor && ! $detail && empty($repostOf) && $post->shared_post_id && blank($post->body) && $post->media->isEmpty()
      && $post->shared && ! $post->shared->trashed() && $post->shared->status === 'published'
      && $post->shared->visibility === 'public' && ! ($post->shared->group_id && $post->shared->group?->isPrivate());
@endphp
@if ($isRepost)
  @include('social.partials.post', ['post' => $post->shared, 'repostOf' => $post, 'state' => $state ?? [], 'detail' => false, 'inGroup' => $inGroup ?? false])
@else
@php
  $viewer = auth('member')->user();
  $repostOf = $repostOf ?? null;
  $mine = $state['reactions'][$post->id] ?? null;
  $saved = isset($state['saved'][$post->id]);
  $isOwner = $post->canManage($viewer);
  $isDemo = $post->is_demo || ($post->mutual?->profile?->is_demo ?? false);
  $inGroup = $inGroup ?? false;
  $ownRepost = $repostOf && $viewer && $repostOf->public_account_id === $viewer->id && ! $repostOf->mutual_id;
@endphp
<article @class(['mx-card', 'mx-post', 'mx-post--sponsored' => $sponsor]) @unless($sponsor) id="post-{{ $repostOf?->id ?? $post->id }}" @endunless data-post="{{ $post->id }}">
  @if ($repostOf)
    <div class="mx-repost">
      <x-mx.icon name="repost" :size="14"/>
      <span>@if($repostOf->authorUrl())<a href="{{ $repostOf->authorUrl() }}">{{ $repostOf->authorName() }}</a>@else<b>{{ $repostOf->authorName() }}</b>@endif a partagé · <span title="{{ $repostOf->published_at?->format('d/m/Y H:i') }}">{{ $repostOf->published_at?->diffForHumans(null, true, true) }}</span></span>
    </div>
  @endif
  <header class="mx-post__head">
    @if ($post->authorUrl())<a href="{{ $post->authorUrl() }}">@endif
      <x-mx.avatar :src="$post->authorAvatar()" :name="$post->authorName()" :org="(bool) $post->mutual_id" />
    @if ($post->authorUrl())</a>@endif
    <div class="mx-grow">
      <div class="mx-row" style="gap:6px;flex-wrap:wrap">
        <a class="mx-post__author" href="{{ $post->authorUrl() ?? '#' }}">{{ $post->authorName() }}</a>
        @if ($post->mutual?->isVerified())<span class="mx-verified" title="Organisation vérifiée par l’administration du réseau"><x-mx.icon name="verified" :size="16"/></span>@endif
        @if ($post->isReel())<a class="mx-badge mx-badge--reel" href="{{ route('social.reels', ['r' => $post->id]) }}"><x-mx.icon name="play" :size="12"/> Réel</a>@endif
        @if (! in_array($post->type, ['texte', 'reel'], true))<span class="mx-badge mx-badge--blue">{{ $post->typeLabel() }}</span>@endif
        @if ($isDemo)<span class="mx-demo-ribbon" title="Contenu fictif de démonstration">Démo</span>@endif
        @if ($post->group_id && $post->group && empty($inGroup))<span class="mx-xs mx-muted">dans <a href="{{ route('social.group', $post->group) }}">{{ $post->group->name }}</a>@if($post->group->isPrivate()) <x-mx.icon name="shield" :size="12"/>@endif</span>@endif
      </div>
      <div class="mx-post__meta">
        @if ($sponsor)
          <span class="mx-sponsored" title="Publication mise en avant par un annonceur">Sponsorisé</span>
        @else
        <a href="{{ route('social.post', $post) }}" class="mx-muted" title="{{ $post->published_at?->format('d/m/Y H:i') }}">{{ $post->published_at?->diffForHumans(null, true, true) }}</a>
        @endif
        @if ($post->visibility === 'public' && ! $post->group_id)<span class="mx-post__vis" title="Publication publique">· <x-mx.icon name="globe" :size="13"/></span>@endif
        @if ($post->category && isset(\App\Models\Social\Post::ACTIVITY_CATEGORIES[$post->category]))<span>· {{ \App\Models\Social\Post::ACTIVITY_CATEGORIES[$post->category] }}</span>@endif
        @if ($post->visibility === 'followers')<span title="Visible par les abonnés">· Abonnés</span>@endif
      </div>
    </div>
    <details class="mx-menu">
      <summary class="mx-iconbtn" style="width:36px;height:36px;border:0;background:none" aria-label="Options de la publication"><x-mx.icon name="more" :size="18"/></summary>
      <div class="mx-menu__list">
        <button type="button" data-copy="{{ route('social.post', $post) }}"><x-mx.icon name="link" :size="18"/> Copier le lien</button>
        @if ($viewer)
          <button type="button" data-save="{{ route('social.post.save', $post) }}"><x-mx.icon name="bookmark" :size="18"/> <span>{{ $saved ? 'Retirer des enregistrements' : 'Enregistrer' }}</span></button>
          @if ($post->mutual_id)
            <form method="POST" action="{{ route('social.mute', $post->mutual->slug) }}">@csrf<button><x-mx.icon name="eye-off" :size="18"/> Masquer {{ \Illuminate\Support\Str::limit($post->mutual->name, 24) }} de mon fil</button></form>
          @endif
          @if ($ownRepost)
            <form method="POST" action="{{ route('social.post.destroy', $repostOf) }}" data-confirm="Retirer ce partage de votre profil ? La publication d’origine n’est pas supprimée.">@csrf @method('DELETE')<button class="is-danger"><x-mx.icon name="trash" :size="18"/> Retirer mon partage</button></form>
          @endif
          @if ($isOwner && $post->status === 'published' && $post->visibility === 'public' && ! $post->group_id && \App\Services\Social\SponsorService::enabled())
            <a href="{{ route('social.sponsor.create', $post) }}"><x-mx.icon name="megaphone" :size="18"/> Sponsoriser cette publication</a>
          @endif
          @if ($isOwner)
            <form method="POST" action="{{ route('social.post.destroy', $post) }}" data-confirm="Supprimer définitivement cette publication ?">@csrf @method('DELETE')<button class="is-danger"><x-mx.icon name="trash" :size="18"/> Supprimer</button></form>
          @else
            <button type="button" class="is-danger" data-report="{{ route('social.post.report', $post) }}"><x-mx.icon name="flag" :size="18"/> Signaler</button>
          @endif
        @else
          <a href="{{ route('social.login') }}"><x-mx.icon name="login" :size="18"/> Se connecter pour interagir</a>
        @endif
      </div>
    </details>
  </header>

  @if ($post->body)
    <div class="mx-post__body">{!! \App\Support\SocialText::render($post->body, $post->mentions, $detail ? null : 320) !!}@if(!$detail && mb_strlen($post->body) > 320) <a class="mx-more-link" href="{{ route('social.post', $post) }}">Voir plus</a>@endif</div>
  @endif

  @if ($post->type === 'evenement' && $post->event_starts_at)
    <div class="mx-post__event">
      <div class="mx-cal"><b>{{ $post->event_starts_at->format('d') }}</b><span>{{ $post->event_starts_at->translatedFormat('M Y') }}</span></div>
      <div class="mx-small"><b>{{ $post->event_starts_at->translatedFormat('l j F Y \à H\hi') }}</b>@if ($post->event_location)<br><span class="mx-muted"><x-mx.icon name="pin" :size="14"/> {{ $post->event_location }}</span>@endif</div>
    </div>
  @endif

  @if ($post->link_url && $post->link_title)
    <a class="mx-linkcard" href="{{ $post->link_url }}" target="_blank" rel="noopener nofollow ugc">
      @if ($post->link_image_path)<img src="{{ \App\Services\Social\MediaService::url($post->link_image_path) }}" alt="" loading="lazy">@endif
      <span class="mx-linkcard__txt">
        <span class="mx-linkcard__site">{{ \Illuminate\Support\Str::upper($post->link_site ?: parse_url($post->link_url, PHP_URL_HOST)) }}</span>
        <b>{{ $post->link_title }}</b>
        @if ($post->link_description)<span class="mx-linkcard__desc">{{ \Illuminate\Support\Str::limit($post->link_description, 160) }}</span>@endif
      </span>
    </a>
  @elseif ($post->link_url && ! str_contains((string) $post->body, preg_replace('#^https?://(www\.)?#', '', $post->link_url)))
    <div style="padding:0 16px 12px"><a href="{{ $post->link_url }}" target="_blank" rel="noopener nofollow ugc" class="mx-btn mx-btn--ghost mx-btn--sm"><x-mx.icon name="link" :size="16"/> {{ \Illuminate\Support\Str::limit(preg_replace('#^https?://#', '', $post->link_url), 48) }}</a></div>
  @endif

  @php $video = $post->media->first(fn ($m) => $m->isVideo()); @endphp
  @if ($video)
    <div @class(['mx-video', 'mx-video--reel' => $post->isReel() || ($video->height && $video->width && $video->height > $video->width)])>
      <video src="{{ $video->url() }}" @if($video->posterUrl()) poster="{{ $video->posterUrl() }}" @endif controls playsinline muted preload="metadata" data-autoplay
             @if($video->width) width="{{ $video->width }}" height="{{ $video->height }}" @endif aria-label="Vidéo publiée par {{ $post->authorName() }}"></video>
      @if ($post->isReel())<a class="mx-video__open" href="{{ route('social.reels', ['r' => $post->id]) }}"><x-mx.icon name="play" :size="14"/> Ouvrir dans Réels</a>@endif
    </div>
  @elseif ($post->media->isNotEmpty())
    @php $n = $post->media->count(); $shown = $post->media->take(4); @endphp
    <div class="mx-media mx-media--{{ min($n, 4) }}">
      @foreach ($shown as $i => $m)
        <a href="{{ $m->url() }}" target="_blank" rel="noopener"><img src="{{ $n === 1 ? $m->url() : $m->thumbUrl() }}" loading="lazy" alt="Image {{ $i + 1 }} de la publication" @if($m->width) width="{{ $m->width }}" height="{{ $m->height }}" @endif>@if ($i === 3 && $n > 4)<span class="mx-more">+{{ $n - 4 }}</span>@endif</a>
      @endforeach
    </div>
  @endif

  @if ($post->shared_post_id)
    <div class="mx-shared">
      @if ($post->shared && ! $post->shared->trashed() && $post->shared->status === 'published')
        @include('social.partials.post-embed', ['p' => $post->shared])
      @else
        <p class="mx-card__pad mx-small mx-muted">Cette publication n'est plus disponible.</p>
      @endif
    </div>
  @endif

  @if ($sponsor)
    <a class="mx-sponsor-cta" href="{{ route('social.sponsor.click', $sponsor->id) }}" rel="nofollow sponsored">
      <span>{{ $post->mutual?->name ?? $post->authorName() }}</span>
      <b>{{ \App\Models\Social\Sponsorship::CTA[$sponsor->cta_label] ?? 'En savoir plus' }}</b>
    </a>
  @endif

  <div class="mx-post__stats">
    <span data-react-count>{{ $post->reactions_count ? $post->reactions_count . ' réaction' . ($post->reactions_count > 1 ? 's' : '') : '' }}</span>
    <span><a class="mx-muted" href="{{ route('social.post', $post) }}#commentaires" data-comment-count>{{ $post->comments_count }} commentaire{{ $post->comments_count > 1 ? 's' : '' }}</a> · {{ $post->shares_count }} partage{{ $post->shares_count > 1 ? 's' : '' }}</span>
  </div>
  <div class="mx-post__actions">
    @if ($viewer)
      <div class="mx-reactbar">
        <button type="button" @class(['mx-act', 'is-on' => $mine]) data-react="{{ route('social.post.react', $post) }}" data-type="jaime" style="width:100%"><x-mx.icon name="thumb" :size="18"/> <span data-react-label>{{ $mine ? \App\Models\Social\Post::REACTIONS[$mine] : 'J’aime' }}</span></button>
        <div class="mx-reactbar__pick" role="group" aria-label="Choisir une réaction">
          @foreach (\App\Models\Social\Post::REACTIONS as $k => $v)<button type="button" data-react="{{ route('social.post.react', $post) }}" data-type="{{ $k }}">{{ $v }}</button>@endforeach
        </div>
      </div>
      <a class="mx-act" href="{{ route('social.post', $post) }}#commentaires"><x-mx.icon name="comment" :size="18"/> <span>Commenter</span></a>
      @if (! ($post->group_id && $post->group?->isPrivate()))
      <button type="button" class="mx-act" data-share="{{ route('social.post.share', $post) }}"><x-mx.icon name="share" :size="18"/> <span>Partager</span></button>
      @endif
      <button type="button" @class(['mx-act', 'is-on' => $saved]) data-save="{{ route('social.post.save', $post) }}"><x-mx.icon name="bookmark" :size="18"/> <span>Enregistrer</span></button>
    @else
      <a class="mx-act" href="{{ route('social.login') }}"><x-mx.icon name="thumb" :size="18"/> <span>J’aime</span></a>
      <a class="mx-act" href="{{ route('social.post', $post) }}#commentaires"><x-mx.icon name="comment" :size="18"/> <span>Commenter</span></a>
      <a class="mx-act" href="{{ route('social.login') }}"><x-mx.icon name="share" :size="18"/> <span>Partager</span></a>
      <a class="mx-act" href="{{ route('social.login') }}"><x-mx.icon name="bookmark" :size="18"/> <span>Enregistrer</span></a>
    @endif
  </div>

  @if ($detail)
    @include('social.partials.comments', ['post' => $post])
  @endif
</article>
@endif
