<x-layouts.social :title="$account->name" :active="$isSelf ? 'profil' : null">
  <section class="mx-card" style="overflow:hidden">
    <div @class(['mx-cover', 'has-img' => $account->cover_path]) @if($account->cover_path && ! $private) style="background-image:url('{{ $account->coverUrl() }}')" @endif></div>
    <div class="mx-profilehead">
      <div class="mx-profilehead__top">
        <x-mx.avatar :src="$account->avatarUrl()" :name="$account->name" size="lg" />
        <div class="mx-profilehead__actions">
          @if ($isSelf)
            <a class="mx-btn mx-btn--ghost" href="{{ route('social.settings') }}"><x-mx.icon name="edit" :size="16"/> Modifier le profil</a>
          @elseif (auth('member')->check())
            @unless ($private)
              <button class="mx-btn mx-btn--primary" data-follow="{{ route('social.user.follow', $account) }}" @class(['is-on' => $isFollowing])>{{ $isFollowing ? 'Suivi' : 'Suivre' }}</button>
            @endunless
            <details class="mx-menu">
              <summary class="mx-btn mx-btn--ghost" aria-label="Plus d'options"><x-mx.icon name="more" :size="16"/></summary>
              <div class="mx-menu__list">
                <button type="button" data-copy="{{ route('social.user', $account) }}"><x-mx.icon name="link" :size="18"/> Copier le lien du profil</button>
                <button type="button" class="is-danger" data-report="{{ route('social.user.report', $account) }}"><x-mx.icon name="flag" :size="18"/> Signaler ce profil</button>
              </div>
            </details>
          @else
            @unless ($private)<a class="mx-btn mx-btn--primary" href="{{ route('social.login') }}">Suivre</a>@endunless
          @endif
        </div>
      </div>
      <div>
        <h1 style="font-size:24px">{{ $account->name }} @if($account->is_demo)<span class="mx-demo-ribbon">Démo</span>@endif</h1>
        @unless ($private)
          @if ($account->city)<p class="mx-small mx-muted"><x-mx.icon name="pin" :size="14"/> {{ $account->city }}</p>@endif
          @if ($account->bio)<p style="margin-top:8px">{{ $account->bio }}</p>@endif
        @endunless
      </div>
      @if ($private)
        <p class="mx-small mx-muted"><x-mx.icon name="shield" :size="14"/> Ce profil est privé.</p>
      @else
        <div class="mx-stats">
          <span><b>{{ $postsCount }}</b> publication(s)</span>
          <span><b data-followers="{{ route('social.user.follow', $account) }}">{{ $followersCount }}</b> abonné(s)</span>
          <span><b>{{ $followingCount }}</b> abonnement(s)</span>
          <span><b>{{ $followed->count() }}</b> organisation(s) suivie(s)</span>
        </div>
        @if (! empty($account->interests))
          <div class="mx-chips" style="flex-wrap:wrap">@foreach ($account->interests as $i)@isset($interests[$i])<span class="mx-chip">{{ $interests[$i] }}</span>@endisset @endforeach</div>
        @endif
      @endif
      @if ($isSelf && $account->profile_visibility === 'private')
        <p class="mx-alert mx-alert--info">Votre profil est privé : les autres membres ne voient que votre nom.</p>
      @endif
    </div>
  </section>

  @unless ($private)
    @if ($isSelf)@include('social.partials.composer')@endif
    @if ($posts->isEmpty())
      @include('social.partials.empty', ['icon' => 'list', 'title' => 'Aucune publication', 'text' => $isSelf ? 'Partagez une information utile à la communauté.' : $account->first_name . ' n’a encore rien publié.'])
    @else
      <div class="mx-stack">@include('social.partials.feed')</div>
    @endif
  @endunless

  <x-slot:right>
    @unless ($private)
      <section class="mx-card">
        <div class="mx-card__head"><h2 class="mx-card__title">Mutuelles suivies</h2></div>
        <div class="mx-mini">
          @forelse ($followed as $m) @include('social.partials.mutual-mini', ['m' => $m]) @empty <p class="mx-mini__item mx-small mx-muted">Aucune pour le moment.</p> @endforelse
        </div>
      </section>
    @endunless
    @include('social.partials.sidebar')
  </x-slot:right>
</x-layouts.social>
