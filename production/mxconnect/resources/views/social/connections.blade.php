<x-layouts.social title="Mes connexions" active="connexions">
  <h1 style="font-size:22px">Mes connexions</h1>
  <p class="mx-small mx-muted">Les publications des organisations et des membres que vous suivez apparaissent dans « Abonnements ». Suivre une mutuelle n'est pas une adhésion.</p>
  <section class="mx-card">
    <div class="mx-card__head"><h2 class="mx-card__title">Mutuelles suivies ({{ $follows->count() }})</h2></div>
    <div class="mx-mini">
      @forelse ($follows as $f) @include('social.partials.mutual-mini', ['m' => $f->mutual]) @empty
        <div class="mx-mini__item mx-row mx-between" style="width:100%"><span class="mx-small mx-muted">Vous ne suivez encore aucune mutuelle.</span><a class="mx-btn mx-btn--primary mx-btn--sm" href="{{ route('social.mutuals') }}">Découvrir</a></div>
      @endforelse
    </div>
  </section>
  <section class="mx-card">
    <div class="mx-card__head"><h2 class="mx-card__title">Personnes suivies ({{ $people->count() }})</h2></div>
    <div class="mx-mini">
      @forelse ($people as $p)
        <div class="mx-mini__item">
          <a href="{{ route('social.user', $p) }}"><x-mx.avatar :src="$p->avatarUrl()" :name="$p->name" /></a>
          <a class="mx-grow" href="{{ route('social.user', $p) }}" style="color:inherit"><b>{{ $p->name }}</b>@if($p->city)<span class="mx-xs mx-muted">{{ $p->city }}</span>@endif</a>
          <button class="mx-btn mx-btn--ghost mx-btn--sm is-on" data-follow="{{ route('social.user.follow', $p) }}">Suivi</button>
        </div>
      @empty
        <p class="mx-mini__item mx-small mx-muted">Vous ne suivez encore aucun membre. Ouvrez le profil d’un membre puis « Suivre » pour voir ses publications dans « Abonnements ».</p>
      @endforelse
    </div>
  </section>
  @if ($followers->isNotEmpty())
    <section class="mx-card">
      <div class="mx-card__head"><h2 class="mx-card__title">Mes abonnés ({{ $followers->count() }})</h2></div>
      <div class="mx-mini">
        @foreach ($followers as $p)
          <a class="mx-mini__item" href="{{ route('social.user', $p) }}" style="color:inherit"><x-mx.avatar :src="$p->avatarUrl()" :name="$p->name" /><b class="mx-grow">{{ $p->name }}</b></a>
        @endforeach
      </div>
    </section>
  @endif
  @if ($mutes->isNotEmpty())
    <section class="mx-card">
      <div class="mx-card__head"><h2 class="mx-card__title">Masquées de mon fil</h2></div>
      <div class="mx-mini">
        @foreach ($mutes as $mu)
          <div class="mx-mini__item">
            <x-mx.avatar :src="$mu->mutual->profile?->logoUrl()" :name="$mu->mutual->name" org />
            <b class="mx-grow">{{ $mu->mutual->name }}</b>
            <form method="POST" action="{{ route('social.mute', $mu->mutual->slug) }}">@csrf<button class="mx-btn mx-btn--ghost mx-btn--sm">Réafficher</button></form>
          </div>
        @endforeach
      </div>
    </section>
  @endif
</x-layouts.social>
