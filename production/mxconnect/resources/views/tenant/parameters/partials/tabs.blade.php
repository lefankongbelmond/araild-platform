<nav class="mt-5 flex gap-1 overflow-x-auto rounded-2xl bg-white p-1.5 shadow-soft ring-1 ring-ink/[.05]" aria-label="Paramètres">
  @foreach (['guarantees.index' => 'Garanties', 'antennas.index' => 'Antennes', 'act-types.index' => 'Actes', 'medicines.index' => 'Médicaments'] as $r => $l)
    @if (Route::has($r))
      <a href="{{ route($r) }}" @class(['whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-semibold transition', 'bg-leaf-50 text-leaf-700 ring-1 ring-leaf-100' => request()->routeIs($r), 'text-ink/60 hover:bg-paper hover:text-ink' => ! request()->routeIs($r)])>{{ $l }}</a>
    @endif
  @endforeach
  @can('manage-parameters')<a href="{{ route('catalog.import', 'actes') }}" @class(['ml-auto whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-semibold transition', 'bg-leaf-50 text-leaf-700 ring-1 ring-leaf-100' => request()->routeIs('catalog.*'), 'text-brand-600 hover:bg-brand-50' => ! request()->routeIs('catalog.*')])>Listes de référence</a>@endcan
</nav>
