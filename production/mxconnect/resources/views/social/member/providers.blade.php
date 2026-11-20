<x-layouts.social title="{{ __('Prestataires conventionnés') }}" active="mon-adhesion">
  @include('social.member._head')
  @php $types = ['hospital' => __('Hôpital'), 'clinic' => __('Clinique'), 'health_center' => __('Centre de santé'), 'pharmacy' => __('Pharmacie'), 'laboratory' => __('Laboratoire'), 'other' => __('Autre')]; @endphp
  <p class="mx-small mx-muted">Les établissements où votre mutuelle prend en charge vos soins{{ $x['restricted'] ? ' (réseau de votre formule)' : '' }}.</p>
  <form method="GET" class="mx-grid-2" style="margin-bottom:12px">
    <input class="mx-input" name="q" value="{{ $f['q'] ?? '' }}" placeholder="{{ __('Nom de l’établissement') }}" aria-label="{{ __('Nom') }}">
    <select class="mx-input" name="type" aria-label="{{ __('Type') }}"><option value="">{{ __('Tous les types') }}</option>@foreach ($types as $k => $t)<option value="{{ $k }}" @selected(($f['type'] ?? '') === $k)>{{ __($t) }}</option>@endforeach</select>
    <select class="mx-input" name="ville" aria-label="{{ __('Ville') }}"><option value="">{{ __('Toutes les villes') }}</option>@foreach ($x['cities'] as $c)<option @selected(($f['ville'] ?? '') === $c)>{{ $c }}</option>@endforeach</select>
    <div><button class="mx-btn mx-btn--soft">{{ __('Rechercher') }}</button></div>
  </form>
  @forelse ($x['items'] as $cp)
    <section class="mx-card mx-card__pad" style="margin-bottom:10px">
      <div class="mx-between" style="gap:8px;flex-wrap:wrap"><b>{{ $cp->name }}</b><span class="mx-badge mx-badge--green">{{ __('Conventionné') }}</span></div>
      <p class="mx-xs mx-muted">{{ $types[$cp->type] ?? $cp->type }}{{ $cp->locality ? ' · ' . $cp->locality : '' }}</p>
      @if ($cp->address)<p class="mx-small" style="margin:4px 0">{{ $cp->address }}</p>@endif
      @if ($cp->specialties)<p class="mx-xs mx-muted">{{ implode(', ', (array) $cp->specialties) }}</p>@endif
      @if (is_array($cp->opening_hours) && $cp->opening_hours)<p class="mx-xs mx-muted">Horaires : {{ collect($cp->opening_hours)->map(fn ($v, $k) => is_string($k) ? $k . ' ' . (is_array($v) ? implode('–', $v) : $v) : (is_array($v) ? implode(' ', $v) : $v))->implode(' · ') }}</p>@endif
      @if ($cp->phone)<a class="mx-btn mx-btn--ghost mx-btn--sm" href="tel:{{ preg_replace('/[^0-9+]/', '', $cp->phone) }}" style="margin-top:6px">Appeler {{ $cp->phone }}</a>@endif
    </section>
  @empty
    @include('social.partials.empty', ['icon' => 'search', 'title' => 'Aucun établissement', 'text' => 'Aucun établissement conventionné ne correspond à ces critères.'])
  @endforelse
</x-layouts.social>
