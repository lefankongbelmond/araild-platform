{{-- Onglets du fil (une ligne, défilement horizontal sur téléphone). $current : clé active. --}}
@php
  $tabsList = array_filter([
    'fil'           => ['Pour vous', route('social.home')],
    'abonnements'   => auth('member')->check() ? ['Abonnements', route('social.following')] : null,
    'toutes'        => ['Toutes', route('social.all')],
    'organisations' => ['Organisations', route('social.mutuals', ['categorie' => 'organisations'])],
    'mutuelles'     => ['Mutuelles de santé', route('social.mutuals', ['categorie' => 'mutuelle'])],
    'explorer'      => ['Explorer', route('social.explore')],
  ]);
@endphp
<nav class="mx-feedtabs" aria-label="Choisir le fil" data-feedtabs>
  @foreach ($tabsList as $key => [$label, $url])
    <a href="{{ $url }}" @class(['is-active' => ($current ?? null) === $key]) @if(($current ?? null) === $key) aria-current="page" @endif>{{ $label }}</a>
  @endforeach
</nav>
