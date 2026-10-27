{{-- Sous-navigation de la comptabilité (2026-11-05) : uniquement les écrans ouverts au rôle. --}}
@php
  $links = [
    'accounting.journal'        => 'Journal',
    'accounting.entries.index'  => 'Opérations diverses',
    'accounting.balance'        => 'États comptables',
    'accounting.chart'          => 'Plan comptable',
    'accounting.closing.index'  => 'Clôture de l’exercice',
  ];
  $here = request()->route()?->getName();
  $here = in_array($here, ['accounting.ledger', 'accounting.income', 'accounting.sheet'], true) ? 'accounting.balance' : $here;
@endphp
<nav class="print:hidden flex flex-wrap items-center gap-x-4 text-sm" style="row-gap:.25rem" aria-label="Comptabilité">
  <a href="{{ route('treasury.dashboard') }}" class="text-ink/50 hover:underline">&larr; {{ __('mxconnect.treasury.title') }}</a>
  @foreach ($links as $route => $label)
    @acces($route)
      @if ($route === $here)<span class="font-semibold text-ink" aria-current="page">{{ $label }}</span>
      @else<a href="{{ route($route) }}" class="text-pine hover:underline">{{ $label }}</a>@endif
    @endacces
  @endforeach
</nav>
