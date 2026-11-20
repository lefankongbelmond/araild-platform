{{-- En-tête du portail adhérent : mutuelle, adhérent, onglets (2026-11-20). --}}
@php
  $tabs = [
    ['dashboard', 'social.member.dashboard', __('Accueil')], ['guarantees', 'social.member.guarantees', __('Mes garanties')],
    ['dependents', 'social.member.dependents', __('Bénéficiaires')], ['prestations', 'social.member.prestations', __('Prestations')],
    ['contributions', 'social.member.contributions', __('Cotisations')], ['providers', 'social.member.providers', __('Prestataires')],
    ['complaints', 'social.member.complaints', __('Réclamations')], ['profile', 'social.member.profile', __('Mon profil')],
  ];
  $current = in_array($tab, ['prestation'], true) ? 'prestations' : ($tab === 'complaint' ? 'complaints' : $tab);
@endphp
<div class="mx-card" style="margin-bottom:14px">
  <div class="mx-card__pad mx-row" style="gap:12px;flex-wrap:wrap">
    <x-mx.avatar :name="$mutual->name" org />
    <div class="mx-grow" style="min-width:0">
      <b>{{ $mutual->name }}</b>
      <span class="mx-xs mx-muted" style="display:block">{{ __(':last_name :name · n° :reference', ['last_name' => mb_strtoupper($m->last_name), 'name' => $m->first_name, 'reference' => $m->member_code]) }}</span>
    </div>
    <a class="mx-btn mx-btn--soft mx-btn--sm" href="{{ route('social.member.card', $mutual->slug) }}">{{ __('Ma carte') }}</a>
  </div>
  <nav class="mx-tabs" aria-label="{{ __('Mon adhésion') }}">
    @foreach ($tabs as [$k, $r, $l])<a href="{{ route($r, $mutual->slug) }}" @class(['is-active' => $current === $k])>{{ $l }}</a>@endforeach
  </nav>
</div>
@if (($links ?? collect())->count() > 1)
  <p class="mx-xs mx-muted" style="margin:-6px 0 12px">{{ __('Autres adhésions :') }} @foreach ($links as $l)@if ($l->mutual->slug !== $mutual->slug)<a href="{{ route('social.member.dashboard', $l->mutual->slug) }}">{{ $l->mutual->name }}</a> @endif @endforeach</p>
@endif
@if ($errors->any())<div class="mx-alert mx-alert--err" role="alert">{{ $errors->first() }}</div>@endif
