@php
  $c = $currency;
  $f = 'rounded-md border border-ink/15 bg-white px-3 py-2 text-sm';
  $tabs = ['overdue' => 'En retard', 'to_pay' => 'À venir', 'paid' => 'Payées'];
@endphp
<x-layouts.tenant :title="__('mxconnect.contribution.plural')">
  <x-tenant.hero icon="coins" kicker="Facturation et finances" tone="leaf">
    <x-slot:title>{{ __('mxconnect.contribution.plural') }}</x-slot:title>
    <x-slot:subtitle>Échéances des adhérents, retards et encaissements. Pour encaisser un montant partiel ou plusieurs échéances, ouvrez le relevé de l’adhérent. Chaque encaissement produit un reçu et un mouvement de trésorerie.</x-slot:subtitle>
    <x-slot:actions>
      @acces('contributions.export')<a href="{{ route('contributions.export', request()->only('status', 'q')) }}" class="rounded-md border border-ink/10 bg-white px-4 py-2 text-sm font-medium text-ink/70 hover:bg-mist">Exporter (CSV)</a>@endacces
      @if ($canRefresh)
        <form method="POST" action="{{ route('contributions.refresh') }}">@csrf<button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white">Actualiser les échéanciers</button></form>
      @endif
    </x-slot:actions>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 grid gap-3 sm:grid-cols-3">
    @foreach ($tabs as $k => $label)
      @php $t = $totals[$k]; @endphp
      <a href="{{ route('contributions.index', ['status' => $k] + request()->only('q')) }}" class="rounded-lg border bg-white p-4 {{ $status === $k ? 'border-pine' : 'border-ink/10' }}" @if ($status === $k) aria-current="page" @endif>
        <div class="text-xs font-semibold uppercase tracking-wide text-ink/50">{{ $label }}</div>
        <div class="mt-1 text-xl font-semibold tabular-nums {{ $k === 'overdue' && $t['count'] ? 'text-clay' : '' }}">{{ $c->format($k === 'paid' ? $t['paid'] : max(0, $t['due'] - $t['paid'])) }}</div>
        <div class="text-xs text-ink/55">{{ $t['count'] }} échéance(s){{ $k !== 'paid' && $t['paid'] ? ' · acomptes déduits' : '' }}</div>
      </a>
    @endforeach
  </div>

  <form method="GET" class="mt-4 flex flex-wrap items-end gap-2">
    <input type="hidden" name="status" value="{{ $status }}">
    <label class="min-w-0 text-xs text-ink/60">Adhérent (nom, code, téléphone)<input name="q" value="{{ request('q') }}" class="{{ $f }} mt-1 block" style="max-width:100%"></label>
    <button class="rounded-md bg-navy px-4 py-2 text-sm font-medium text-white">Rechercher</button>
    @if (request('q'))<a href="{{ route('contributions.index', ['status' => $status]) }}" class="px-2 py-2 text-sm text-ink/60 hover:underline">Effacer</a>@endif
  </form>

  <div class="relative mt-4 overflow-x-auto rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.member.name') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.contribution.period') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.contribution.due') }}</th>
        <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.contribution.amount') }}</th>
        <th class="px-4 py-2 font-medium text-right">{{ $status === 'paid' ? 'Payé' : 'Reste à payer' }}</th>
        <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($schedules as $sch)
          @php $m = $sch->subscription?->member; $paid = (int) $sch->payments_sum_amount_minor; $rest = max(0, $sch->due_minor - $paid); @endphp
          <tr>
            <td class="px-4 py-3">
              @if ($m)<a href="{{ route('contributions.member', $m) }}" class="font-medium hover:underline">{{ $m->last_name }} {{ $m->first_name }}</a>
                <div class="font-mono text-xs text-ink/50">{{ $m->member_code }}</div>@endif
            </td>
            <td class="px-4 py-3 whitespace-nowrap">{{ $sch->period }}</td>
            <td class="px-4 py-3 whitespace-nowrap {{ $sch->status === 'overdue' ? 'text-clay' : '' }}">{{ $sch->due_date?->format('d/m/Y') }}</td>
            <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap">{{ $c->format($sch->due_minor) }}</td>
            <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap">{{ $c->format($status === 'paid' ? $paid : $rest) }}
              @if ($status !== 'paid' && $paid > 0)<div class="text-xs text-ink/50">acompte {{ $c->format($paid) }}</div>@endif</td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              @if ($sch->status !== 'paid' && $canCollect)
                <form method="POST" action="{{ route('contributions.pay', $sch) }}" class="inline-flex items-center gap-2" onsubmit="return confirm('Encaisser {{ $c->format($rest) }} pour {{ $sch->period }} ?')">@csrf
                  <select name="mode" class="rounded-md border border-ink/15 px-2 py-1 text-xs" aria-label="Mode">
                    <option value="cash">{{ __('mxconnect.contribution.cash') }}</option>
                    <option value="group">Versement groupé</option>
                  </select>
                  <button class="text-pine hover:underline">Solder</button>
                </form>
              @elseif ($m)
                <a href="{{ route('contributions.member', $m) }}" class="text-xs text-pine hover:underline">Relevé</a>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.contribution.empty') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $schedules->links() }}</div>
</x-layouts.tenant>
