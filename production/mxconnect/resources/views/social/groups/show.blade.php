@php $viewer = auth('member')->user(); $composerAction = route('social.group.post', $group); @endphp
<x-layouts.social :title="$group->name" active="groupes">
  <section class="mx-card" style="overflow:hidden">
    <div @class(['mx-cover', 'has-img' => $group->cover_path]) style="height:150px;@if($group->cover_path)background-image:url('{{ $group->coverUrl() }}')@endif"></div>
    <div class="mx-card__pad mx-stack" style="gap:10px">
      <div class="mx-row mx-between mx-row--wrap">
        <div>
          <h1 style="font-size:22px">{{ $group->name }} @if($group->is_demo)<span class="mx-demo-ribbon">Démo</span>@endif</h1>
          <p class="mx-small mx-muted">{{ $group->isPrivate() ? 'Groupe privé' : 'Groupe public' }} · {{ $group->members_count }} membre(s) · animé par <a href="{{ route('social.mutual', $group->mutual->slug) }}">{{ $group->mutual->name }}</a></p>
        </div>
        <div class="mx-row">
          @if ($group->status !== 'active')<span class="mx-badge">Archivé</span>
          @elseif ($membership?->status === 'active')
            <form method="POST" action="{{ route('social.group.leave', $group) }}" data-confirm="Quitter ce groupe ?">@csrf<button class="mx-btn mx-btn--ghost mx-btn--sm">Quitter le groupe</button></form>
          @elseif ($membership?->status === 'pending')
            <span class="mx-badge mx-badge--warn">Demande en attente</span>
            <form method="POST" action="{{ route('social.group.leave', $group) }}">@csrf<button class="mx-btn mx-btn--ghost mx-btn--sm">Annuler</button></form>
          @elseif ($viewer)
            <form method="POST" action="{{ route('social.group.join', $group) }}">@csrf<button class="mx-btn mx-btn--primary">{{ $group->isPrivate() ? 'Demander à rejoindre' : 'Rejoindre le groupe' }}</button></form>
          @else
            <a class="mx-btn mx-btn--primary" href="{{ route('social.login') }}">Se connecter pour rejoindre</a>
          @endif
        </div>
      </div>
    </div>
    <nav class="mx-tabs" aria-label="Sections du groupe">
      @foreach (['publications' => 'Publications', 'a-propos' => 'À propos', 'membres' => 'Membres'] as $k => $v)
        <a href="{{ route('social.group', [$group, 'onglet' => $k === 'publications' ? null : $k]) }}" @class(['is-active' => $tab === $k])>{{ $v }}</a>
      @endforeach
    </nav>
  </section>

  @if ($tab === 'a-propos')
    <section class="mx-card mx-card__pad mx-stack">
      <h2 class="mx-card__title">À propos</h2>
      <p>{!! nl2br(e($group->description ?: 'Pas encore de description.')) !!}</p>
      @if ($group->topic && isset(\App\Models\Social\Post::ACTIVITY_CATEGORIES[$group->topic]))<p class="mx-small">Thème : {{ \App\Models\Social\Post::ACTIVITY_CATEGORIES[$group->topic] }}</p>@endif
      <h3 style="font-size:15px">Règles du groupe</h3>
      <p class="mx-small">{!! nl2br(e($group->rules ?: 'Respect, bienveillance, aucune information médicale personnelle, pas de publicité.')) !!}</p>
      <p class="mx-xs mx-muted">Ce groupe est modéré par {{ $group->mutual->name }}. Utilisez « Signaler » sur un contenu problématique.</p>
    </section>
  @elseif ($tab === 'membres')
    <section class="mx-card">
      <div class="mx-card__head"><h2 class="mx-card__title">Membres</h2></div>
      @if ($members === null)
        <p class="mx-card__pad mx-small mx-muted">La liste des membres est visible uniquement par les membres du groupe.</p>
      @else
        <div class="mx-mini">
          @foreach ($members as $gm)
            @if ($gm->account)
              <a class="mx-mini__item" href="{{ route('social.user', $gm->account) }}" style="color:inherit"><x-mx.avatar :src="$gm->account->avatarUrl()" :name="$gm->account->name" size="sm" /><b class="mx-grow">{{ $gm->account->name }}</b></a>
            @endif
          @endforeach
        </div>
      @endif
    </section>
  @else
    @if (! $canRead)
      @include('social.partials.empty', ['icon' => 'shield', 'title' => 'Groupe privé', 'text' => 'Seuls les membres voient les publications. Demandez à rejoindre le groupe : la mutuelle validera votre demande.'])
    @else
      @if ($isMember && $group->status === 'active')@include('social.partials.composer')@endif
      @if ($posts->isEmpty())
        @include('social.partials.empty', ['icon' => 'comment', 'title' => 'Aucune publication', 'text' => $isMember ? 'Lancez la première discussion du groupe.' : 'Rejoignez le groupe pour participer.'])
      @else
        <div class="mx-stack">@include('social.partials.feed')</div>
      @endif
    @endif
  @endif
</x-layouts.social>
