@php $f = 'h-11 rounded-xl border border-ink/10 bg-white px-3 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100'; @endphp
<x-layouts.tenant title="Cartes de membre">
  <x-tenant.hero icon="card" kicker="Gestion des adhérents" tone="brand">
    <x-slot:title>Cartes de membre</x-slot:title>
    <x-slot:subtitle>Suivi des cartes : droits ouverts, cartes à imprimer ou déjà remises. Impression individuelle ou par planche A4 (10 cartes). Le QR code porte un jeton signé, vérifiable par les formations sanitaires, sans aucune donnée médicale.</x-slot:subtitle>
  </x-tenant.hero>

  <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-tenant.kpi label="Adhérents avec droits ouverts" :value="number_format($stats['eligible'], 0, ',', ' ')" icon="shield" tone="leaf" />
    <x-tenant.kpi label="Cartes à imprimer" :value="number_format($stats['to_print'], 0, ',', ' ')" icon="printer" tone="amber" />
    <x-tenant.kpi label="Cartes imprimées" :value="number_format($stats['printed'], 0, ',', ' ')" icon="card" tone="brand" />
    <x-tenant.kpi label="Adhérents non actifs" :value="number_format($stats['blocked'], 0, ',', ' ')" icon="alert" tone="red" />
  </div>

  <x-tenant.panel class="mt-5" title="Adhérents" icon="users">
    <x-slot:aside><span class="text-ink/50">{{ $members->total() }} résultat(s)</span></x-slot:aside>
    <form method="GET" class="grid gap-2.5 px-5 pt-4 sm:grid-cols-[minmax(0,1.5fr)_1fr_1fr_auto] sm:px-6" role="search">
      <input name="q" value="{{ $filters['q'] }}" placeholder="Nom ou code adhérent…" class="{{ $f }} w-full bg-paper" aria-label="Rechercher">
      <select name="etat" class="{{ $f }}" aria-label="État de la carte">
        @foreach (['' => 'Tous les adhérents', 'a-imprimer' => 'Cartes à imprimer', 'imprimees' => 'Cartes imprimées', 'sans-droits' => 'Sans droits ouverts'] as $k => $l)<option value="{{ $k }}" @selected($filters['state'] === $k)>{{ $l }}</option>@endforeach
      </select>
      <select name="antenne" class="{{ $f }}" aria-label="Antenne"><option value="">Toutes les antennes</option>@foreach ($antennas as $a)<option value="{{ $a->id }}" @selected($filters['antenna'] === $a->id)>{{ $a->name }}</option>@endforeach</select>
      <button class="h-11 rounded-xl bg-[#0B1F3A] px-5 text-sm font-semibold text-white">Filtrer</button>
    </form>

    <form method="GET" action="{{ route('cards.batch') }}" target="_blank" id="batch">
      <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="bg-[#EEF4FC] text-left text-[12.5px] font-semibold text-[#0B1F3A]/80">
            <th class="w-10 py-3 pl-6"><input type="checkbox" data-all aria-label="Tout sélectionner"></th>
            <th class="px-3 py-3">Adhérent</th><th class="px-3 py-3">Garantie</th><th class="px-3 py-3">Droits</th><th class="px-3 py-3">Carte</th><th class="py-3 pl-3 pr-6 text-right">Actions</th>
          </tr></thead>
          <tbody class="divide-y divide-ink/[.06]">
            @forelse ($members as $m)
              @php $sub = $m->subscriptions->first(); $ok = $sub && $m->status->value === 'active'; @endphp
              <tr>
                <td class="py-3 pl-6">@if($ok)<input type="checkbox" name="ids[]" value="{{ $m->id }}" data-item aria-label="Sélectionner {{ $m->last_name }}">@endif</td>
                <td class="px-3 py-3"><div class="font-semibold text-[#0B1F3A]">{{ $m->last_name }} {{ $m->first_name }}</div><div class="font-mono text-xs text-ink/50">{{ $m->member_code }}</div></td>
                <td class="px-3 py-3 text-ink/70">{{ $sub?->guaranteeVersion?->guarantee?->name ?? '—' }}@if($sub)<div class="text-xs text-ink/45">depuis le {{ $sub->effective_date?->format('d/m/Y') }}</div>@endif</td>
                <td class="px-3 py-3">@if($ok)<span class="rounded-full bg-leaf-50 px-2.5 py-1 text-xs font-bold text-leaf-700 ring-1 ring-leaf-100">Ouverts</span>@else<span class="rounded-full bg-clay/5 px-2.5 py-1 text-xs font-bold text-clay ring-1 ring-clay/20">{{ $m->status->value !== 'active' ? __('mxconnect.member.status_' . $m->status->value) : 'Sans souscription validée' }}</span>@endif</td>
                <td class="px-3 py-3 text-xs">@if($m->card_printed_at)<span class="font-semibold text-brand-700">Imprimée le {{ $m->card_printed_at->format('d/m/Y') }}</span>@elseif($ok)<span class="font-semibold text-amber-700">À imprimer</span>@else<span class="text-ink/40">—</span>@endif</td>
                <td class="py-3 pl-3 pr-6 text-right">
                  @if ($ok)<a href="{{ route('members.card', $m) }}" target="_blank" class="inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-xs font-semibold text-brand-600 hover:bg-brand-50"><x-tenant.icon name="card" :size="15"/> Voir la carte</a>@endif
                  <a href="{{ route('member-notifications.index', ['audience' => 'member', 'membre' => $m->member_code]) }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-xs font-semibold text-ink/60 hover:bg-paper"><x-tenant.icon name="send" :size="15"/> Notifier</a>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-ink/50">Aucun adhérent pour ces critères.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-ink/[.06] px-5 py-4 sm:px-6">
        <button class="inline-flex h-11 items-center gap-2 rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 px-5 text-sm font-bold text-white shadow-lg shadow-leaf-500/25 disabled:opacity-50" data-print disabled><x-tenant.icon name="printer" :size="17"/> Imprimer la sélection (<span data-n>0</span>)</button>
        <div>{{ $members->links() }}</div>
      </div>
    </form>
  </x-tenant.panel>

  <x-slot:scripts>
    <script>
      (function () {
        var items = function () { return Array.prototype.slice.call(document.querySelectorAll('[data-item]')); };
        var sync = function () { var n = items().filter(function (i) { return i.checked; }).length; document.querySelector('[data-n]').textContent = n; document.querySelector('[data-print]').disabled = !n; };
        var all = document.querySelector('[data-all]'); if (all) all.addEventListener('change', function () { items().forEach(function (i) { i.checked = all.checked; }); sync(); });
        document.addEventListener('change', function (e) { if (e.target.matches('[data-item]')) sync(); });
      })();
    </script>
  </x-slot:scripts>
</x-layouts.tenant>
