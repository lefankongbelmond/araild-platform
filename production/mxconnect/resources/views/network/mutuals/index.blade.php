@php
  $tones = ['pending' => 'bg-amber-50 text-amber-800 ring-amber-200', 'approved' => 'bg-leaf-50 text-leaf-700 ring-leaf-100', 'suspended' => 'bg-clay/10 text-clay ring-clay/20'];
  $labels = ['pending' => 'En attente d’agrément', 'approved' => 'Agréée', 'suspended' => 'Suspendue'];
  $total = $counts->sum();
  $f = 'h-10 rounded-xl border border-ink/10 bg-white px-3 text-sm outline-none focus:border-pine focus:ring-4 focus:ring-pine/10';
@endphp
<x-layouts.network title="Mutuelles">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-xs font-semibold uppercase tracking-[.14em] text-leaf-600">Réseau MX-CONNECT</p>
      <h1 class="mt-1 text-2xl font-bold tracking-tight text-navy sm:text-3xl">Mutuelles</h1>
      <p class="mt-1 max-w-2xl text-sm text-ink/60">Créez, agréez, suspendez ou réactivez les mutuelles du réseau. Chaque mutuelle dispose de son propre espace et de ses propres données.</p>
    </div>
    @can('create', \App\Models\Central\Mutual::class)
      <a href="{{ route('network.mutuals.create') }}" class="inline-flex h-11 items-center gap-2 rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 px-5 text-sm font-bold text-white shadow-lg shadow-leaf-500/25">+ Nouvelle mutuelle</a>
    @endcan
  </div>

  <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach ([[null, 'Total', $total, 'text-navy'], ['approved', 'Agréées', $counts['approved'] ?? 0, 'text-leaf-700'], ['pending', 'En attente', $counts['pending'] ?? 0, 'text-amber-700'], ['suspended', 'Suspendues', $counts['suspended'] ?? 0, 'text-clay']] as [$k, $l, $n, $c])
      <a href="{{ route('network.mutuals.index', array_filter(['statut' => $k])) }}" @class(['rounded-2xl bg-white p-4 shadow-sm ring-1 transition hover:ring-pine/30', 'ring-pine/40' => $status === $k, 'ring-ink/[.06]' => $status !== $k])>
        <div class="text-xs font-semibold uppercase tracking-wide text-ink/50">{{ $l }}</div>
        <div class="mt-1 text-3xl font-bold tabular-nums {{ $c }}">{{ $n }}</div>
      </a>
    @endforeach
  </div>

  <form method="GET" class="mt-5 flex flex-wrap items-center gap-2" role="search">
    <label class="sr-only" for="mq">Rechercher</label>
    <input id="mq" name="q" value="{{ request('q') }}" placeholder="Nom, sous-domaine ou ville…" class="{{ $f }} w-full sm:w-auto sm:min-w-0 sm:flex-1">
    <label class="sr-only" for="ms">Statut</label>
    <select id="ms" name="statut" class="{{ $f }}"><option value="">Tous les statuts</option>@foreach ($labels as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach</select>
    <label class="sr-only" for="mp">Pays</label>
    <select id="mp" name="pays" class="{{ $f }}"><option value="">Tous les pays</option>@foreach ($countries as $c)<option value="{{ $c->id }}" @selected((int) request('pays') === $c->id)>{{ $c->name }}</option>@endforeach</select>
    <button class="h-10 rounded-xl bg-navy px-4 text-sm font-semibold text-white">Filtrer</button>
  </form>

  <div class="mt-4 space-y-3">
    @forelse ($mutuals as $m)
      @php $domain = $m->domains->first()?->domain; @endphp
      <article class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-ink/[.06] sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <a href="{{ route('network.mutuals.show', $m) }}" class="text-lg font-bold text-navy hover:underline">{{ $m->name }}</a>
              <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $tones[$m->status] ?? 'bg-mist text-ink ring-ink/10' }}">{{ $labels[$m->status] ?? $m->status }}</span>
            </div>
            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink/60">
              <span>{{ $m->country?->name ?? '—' }}@if ($m->city) · {{ $m->city }}@endif</span>
              <span>{{ $m->currency?->code }}</span>
              @if ($domain)<span class="break-all font-mono text-xs leading-5">{{ $domain }}</span>@endif
              <span class="text-xs leading-5">{{ \App\Support\TenantStorage::label($m) }}</span>
            </div>
            @if ($m->status === 'suspended' && $m->suspension_reason)<p class="mt-2 text-sm text-clay">Motif : {{ $m->suspension_reason }}</p>@endif
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('network.mutuals.show', $m) }}" class="inline-flex h-9 items-center rounded-lg border border-ink/10 px-3 text-sm font-semibold text-navy hover:bg-mist">Fiche</a>
            @can('approve', $m)
              <form method="POST" action="{{ route('network.mutuals.approve', $m) }}">@csrf<button class="h-9 rounded-lg bg-leaf-600 px-3 text-sm font-semibold text-white">Agréer</button></form>
            @endcan
            @can('reactivate', $m)
              <form method="POST" action="{{ route('network.mutuals.reactivate', $m) }}" onsubmit="return confirm('Réactiver {{ addslashes($m->name) }} ? Son espace sera de nouveau ouvert.')">@csrf<button class="h-9 rounded-lg bg-leaf-600 px-3 text-sm font-semibold text-white">Réactiver</button></form>
            @endcan
            @can('suspend', $m)
              <a href="{{ route('network.mutuals.show', $m) }}#suspendre" class="inline-flex h-9 items-center rounded-lg px-3 text-sm font-semibold text-clay hover:bg-clay/10">Suspendre…</a>
            @endcan
          </div>
        </div>
      </article>
    @empty
      <div class="rounded-2xl bg-white p-10 text-center text-sm text-ink/50 ring-1 ring-ink/[.06]">Aucune mutuelle ne correspond à ces critères.</div>
    @endforelse
  </div>
  <div class="mt-4">{{ $mutuals->links() }}</div>
</x-layouts.network>
