<x-layouts.social title="Mes pages" active="pages">
  <section class="mx-card mx-card__pad mx-stack">
    <div class="mx-row mx-row--wrap" style="justify-content:space-between;gap:10px">
      <div>
        <h1 style="font-size:22px">Mes pages</h1>
        <p class="mx-small mx-muted">Créez la page de votre association, de votre établissement de santé ou de votre organisation : les membres peuvent la suivre et voir ses publications.</p>
      </div>
      @if ($pages->count() < $max)
        <a class="mx-btn mx-btn--primary" href="{{ route('social.pages.create') }}"><x-mx.icon name="plus" :size="16"/> Créer une page</a>
      @endif
    </div>
  </section>

  @forelse ($pages as $m)
    <article class="mx-card mx-card__pad mx-row" style="gap:14px;align-items:center">
      <a href="{{ route('social.mutual', $m->slug) }}"><x-mx.avatar :src="$m->profile?->logoUrl()" :name="$m->name" org size="lg" /></a>
      <div class="mx-grow" style="min-width:0">
        <a href="{{ route('social.mutual', $m->slug) }}" style="color:inherit"><b style="font-size:17px">{{ $m->name }}</b></a>
        <p class="mx-small mx-muted">{{ \App\Models\Social\MutualProfile::CATEGORIES[$m->profile?->category] ?? 'Organisation' }}@if($m->city) · {{ $m->city }}@endif</p>
        <p class="mx-xs mx-muted">{{ number_format((int) $m->profile?->followers_count, 0, ',', ' ') }} abonné(s) · {{ number_format((int) $m->profile?->posts_count, 0, ',', ' ') }} publication(s)
          @if ($m->status === 'suspended') · <b style="color:var(--mx-red,#c62828)">Suspendue par la modération</b>@endif</p>
      </div>
      <div class="mx-row mx-row--wrap" style="gap:6px">
        <a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.mutual', $m->slug) }}">Voir</a>
        <a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.pages.edit', $m->slug) }}"><x-mx.icon name="edit" :size="14"/> Modifier</a>
      </div>
    </article>
  @empty
    @include('social.partials.empty', ['icon' => 'building', 'title' => 'Vous ne gérez aucune page', 'text' => 'Créez une page pour faire connaître votre organisation et publier en son nom.'])
  @endforelse

  <p class="mx-xs mx-muted" style="padding:0 6px">Une page n’est pas une mutuelle gérée sur MX-CONNECT : elle ne reçoit pas de demandes d’adhésion. Le badge « vérifié » est réservé aux organisations validées par l’administration du réseau. {{ $max }} pages maximum par compte.</p>
</x-layouts.social>
