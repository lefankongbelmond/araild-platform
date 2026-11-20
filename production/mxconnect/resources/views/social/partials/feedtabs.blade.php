{{-- Onglets du fil (une ligne, défilement horizontal sur téléphone). $current : clé active. --}}
@php
  $tabsList = array_filter([
    'fil'           => [__('Pour vous'), route('social.home')],
    'abonnements'   => auth('member')->check() ? [__('Abonnements'), route('social.following')] : null,
    'toutes'        => [__('Toutes'), route('social.all')],
    'organisations' => [__('Organisations'), route('social.mutuals', ['categorie' => 'organisations'])],
    'mutuelles'     => [__('Mutuelles de santé'), route('social.mutuals', ['categorie' => 'mutuelle'])],
    'explorer'      => [__('Explorer'), route('social.explore')],
  ]);
@endphp
<nav class="mx-feedtabs" aria-label="{{ __('Choisir le fil') }}" data-feedtabs>
  @foreach ($tabsList as $key => [$label, $url])
    <a href="{{ $url }}" @class(['is-active' => ($current ?? null) === $key]) @if(($current ?? null) === $key) aria-current="page" @endif>{{ $label }}</a>
  @endforeach
</nav>
