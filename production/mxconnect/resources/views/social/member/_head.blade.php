{{-- En-tête du portail adhérent : mutuelle, adhérent, onglets (2026-11-20). --}}
@php
  $tabs = [
    ['dashboard', 'social.member.dashboard', 'Accueil'], ['guarantees', 'social.member.guarantees', 'Mes garanties'],
    ['dependents', 'social.member.dependents', 'Bénéficiaires'], ['prestations', 'social.member.prestations', 'Prestations'],
    ['contributions', 'social.member.contributions', 'Cotisations'], ['providers', 'social.member.providers', 'Prestataires'],
    ['complaints', 'social.member.complaints', 'Réclamations'], ['profile', 'social.member.profile', 'Mon profil'],
  ];
  $current = in_array($tab, ['prestation'], true) ? 'prestations' : ($tab === 'complaint' ? 'complaints' : $tab);
@endphp
<div class="mx-card" style="margin-bottom:14px">
  <div class="mx-card__pad mx-row" style="gap:12px;flex-wrap:wrap">
    <x-mx.avatar :name="$mutual->name" org />
    <div class="mx-grow" style="min-width:0">
      <b>{{ $mutual->name }}</b>
      <span class="mx-xs mx-muted" style="display:block">{{ mb_strtoupper($m->last_name) }} {{ $m->first_name }} · n° {{ $m->member_code }}</span>
    </div>
    <a class="mx-btn mx-btn--soft mx-btn--sm" href="{{ route('social.member.card', $mutual->slug) }}">Ma carte</a>
  </div>
  <nav class="mx-tabs" aria-label="Mon adhésion">
    @foreach ($tabs as [$k, $r, $l])<a href="{{ route($r, $mutual->slug) }}" @class(['is-active' => $current === $k])>{{ $l }}</a>@endforeach
  </nav>
</div>
@if (($links ?? collect())->count() > 1)
  <p class="mx-xs mx-muted" style="margin:-6px 0 12px">Autres adhésions : @foreach ($links as $l)@if ($l->mutual->slug !== $mutual->slug)<a href="{{ route('social.member.dashboard', $l->mutual->slug) }}">{{ $l->mutual->name }}</a> @endif @endforeach</p>
@endif
@if ($errors->any())<div class="mx-alert mx-alert--err" role="alert">{{ $errors->first() }}</div>@endif
