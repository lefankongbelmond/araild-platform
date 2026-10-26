<x-layouts.tenant :title="__('mxconnect.claim.title')">
  <a href="{{ route('claims.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.back') }}</a>

  <div class="mt-2 flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">{{ $claim->member->last_name }} {{ $claim->member->first_name }}</h1>
      <p class="mt-1 text-sm text-ink/50">{{ __('mxconnect.claim.opened_on') }} {{ $claim->opened_on?->format('d/m/Y') }} · {{ $claim->reason }}</p>
    </div>
    <span class="rounded-full border px-3 py-1 text-sm {{ $claim->status === 'open' ? 'border-pine/30 text-pine' : 'border-ink/20 text-ink/50' }}">{{ __('mxconnect.claim.status_'.$claim->status) }}</span>
  </div>

  {{-- Rights block feedback (prestation capture refused) --}}
  @if (session('rights_block'))
    <div class="mt-4 rounded-md border border-clay/40 bg-clay/5 px-4 py-3 text-sm text-clay">
      <b>{{ __('mxconnect.prestation.rights_blocked') }}</b>
      <ul class="mt-1 list-disc pl-5">
        @foreach (session('rights_block') as $r)<li>{{ __('mxconnect.rights.'.$r) }}</li>@endforeach
      </ul>
    </div>
  @endif

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    {{-- Prestations --}}
    <div class="lg:col-span-2 space-y-6">
      <div class="rounded-lg border border-ink/10 bg-white p-4">
        <h2 class="font-medium">{{ __('mxconnect.claim.prestations') }}</h2>
        <table class="mt-3 w-full text-sm">
          <thead class="text-left text-ink/50"><tr>
            <th class="py-1">{{ __('mxconnect.prestation.act') }}</th>
            <th class="py-1">{{ __('mxconnect.prestation.provider') }}</th>
            <th class="py-1">{{ __('mxconnect.prestation.date') }}</th>
            <th class="py-1">{{ __('mxconnect.prestation.total') }}</th>
            <th class="py-1">{{ __('mxconnect.prestation.mutual_part') }}</th>
            <th class="py-1">{{ __('mxconnect.status') }}</th>
            <th class="py-1"></th>
          </tr></thead>
          <tbody class="divide-y divide-ink/5">
            @forelse ($claim->prestations as $p)
              @php $pb = ['captured'=>'clay','validated'=>'pine','rejected'=>'ink/40','controlled'=>'sage'][$p->status] ?? 'ink/50'; @endphp
              <tr>
                <td class="py-2">{{ $p->actType?->label }}
                  @if (($alerts[$p->id] ?? collect())->isNotEmpty())
                    <span class="ml-1 rounded bg-clay/10 px-1 text-[10px] text-clay">⚠ {{ $alerts[$p->id]->count() }}</span>
                  @endif
                </td>
                <td class="py-2 text-ink/60">{{ $providers[$p->provider_id] ?? '#'.$p->provider_id }}</td>
                <td class="py-2">{{ $p->care_date?->format('d/m/Y') }}</td>
                <td class="py-2">{{ $currency->format($p->total_minor) }}</td>
                <td class="py-2 font-medium">{{ $currency->format($p->mutual_part_minor) }}</td>
                <td class="py-2 text-{{ $pb }}">{{ __('mxconnect.prestation.status_'.$p->status) }}</td>
                <td class="py-2 text-right">
                  @if ($p->status === 'captured' && auth()->user()?->hasAnyRole(['controller_validator','mutual_admin']))
                    <form method="POST" action="{{ route('claims.prestations.validate', [$claim, $p]) }}" class="inline">@csrf<button class="text-xs text-pine hover:underline">{{ __('mxconnect.prestation.validate') }}</button></form>
                    <form method="POST" action="{{ route('claims.prestations.reject', [$claim, $p]) }}" class="inline">@csrf<button class="ml-2 text-xs text-clay hover:underline">{{ __('mxconnect.prestation.reject') }}</button></form>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="py-6 text-center text-ink/40">{{ __('mxconnect.prestation.empty') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- Add prestation --}}
      @if ($claim->status === 'open' && auth()->user()?->hasAnyRole(['benefits_manager','controller_validator','mutual_admin']))
      <form method="POST" action="{{ route('claims.prestations.store', $claim) }}" class="rounded-lg border border-ink/10 bg-white p-4">
        @csrf
        <h2 class="font-medium">{{ __('mxconnect.prestation.add') }}</h2>
        <div class="mt-3 grid grid-cols-2 gap-3">
          <select name="act_type_id" required class="rounded-md border-ink/20 px-3 py-2 text-sm">
            <option value="">{{ __('mxconnect.prestation.act') }}…</option>
            @foreach ($actTypes as $a)<option value="{{ $a->id }}">{{ $a->label }}</option>@endforeach
          </select>
          <select name="provider_id" required class="rounded-md border-ink/20 px-3 py-2 text-sm">
            <option value="">{{ __('mxconnect.prestation.provider') }}…</option>
            @foreach ($careProviders as $cp)<option value="{{ $cp->id }}">{{ $cp->name }}</option>@endforeach
          </select>
          <input name="care_date" type="date" required class="rounded-md border-ink/20 px-3 py-2 text-sm">
          <input name="total" type="number" step="any" min="0" placeholder="{{ __('mxconnect.prestation.total') }} ({{ $currency->symbol }})" required class="rounded-md border-ink/20 px-3 py-2 text-sm">
          <select name="channel" required class="col-span-2 rounded-md border-ink/20 px-3 py-2 text-sm">
            <option value="reimbursement">{{ __('mxconnect.prestation.reimbursement') }}</option>
            <option value="tiers_payant">{{ __('mxconnect.prestation.tiers_payant') }}</option>
          </select>
        </div>
        <button class="mt-3 rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.prestation.add') }}</button>
      </form>
      @endif
    </div>

    {{-- Settlement panel --}}
    <div class="space-y-4 self-start">
      <div class="rounded-lg border border-ink/10 bg-white p-4">
        <h2 class="font-medium">{{ __('mxconnect.settlement.title') }}</h2>
        <p class="mt-2 text-sm text-ink/60">{{ __('mxconnect.settlement.mutual_liability') }}</p>
        <p class="text-2xl font-semibold text-pine">{{ $currency->format($claim->mutualTotalMinor()) }}</p>

        @if ($claim->mutualTotalMinor() > 0)
          {{-- Reimbursement path --}}
          @if (! $claim->reimbursement)
            @if (auth()->user()?->hasAnyRole(['controller_validator','mutual_admin','treasurer_accountant']))
            <form method="POST" action="{{ route('claims.reimbursement.request', $claim) }}" class="mt-3">@csrf
              <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.settlement.request_reimbursement') }}</button>
            </form>
            @endif
          @else
            <div class="mt-3 rounded-md bg-mist px-3 py-2 text-sm">
              {{ __('mxconnect.settlement.reimbursement') }}: <b>{{ __('mxconnect.settlement.status_'.$claim->reimbursement->status) }}</b>
              @if ($claim->reimbursement->status !== 'paid' && auth()->user()?->hasRole('treasurer_accountant'))
                <form method="POST" action="{{ route('claims.reimbursement.pay', [$claim, $claim->reimbursement]) }}" class="mt-2">@csrf
                  <button class="w-full rounded-md bg-pine px-3 py-1.5 text-xs font-medium text-white">{{ __('mxconnect.settlement.pay') }}</button>
                </form>
              @endif
            </div>
          @endif

          {{-- Tiers payant path --}}
          @if (auth()->user()?->hasAnyRole(['benefits_manager','controller_validator','mutual_admin']))
          <form method="POST" action="{{ route('claims.invoice.create', $claim) }}" class="mt-4 border-t border-ink/10 pt-3">@csrf
            <select name="provider_id" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
              <option value="">{{ __('mxconnect.prestation.provider') }}…</option>
              @foreach ($careProviders as $cp)<option value="{{ $cp->id }}">{{ $cp->name }}</option>@endforeach
            </select>
            <button class="mt-2 w-full rounded-md border border-pine px-4 py-2 text-sm font-medium text-pine hover:bg-pine hover:text-white transition">{{ __('mxconnect.settlement.create_invoice') }}</button>
          </form>
          @endif

          @foreach ($claim->invoices as $inv)
            <div class="mt-2 rounded-md bg-mist px-3 py-2 text-sm">
              {{ __('mxconnect.settlement.invoice') }} #{{ $inv->id }}: <b>{{ __('mxconnect.settlement.status_'.$inv->status) }}</b> — {{ $currency->format($inv->amount_minor) }}
              @if ($inv->status !== 'paid' && auth()->user()?->hasRole('treasurer_accountant'))
                <form method="POST" action="{{ route('claims.invoice.pay', [$claim, $inv]) }}" class="mt-1">@csrf<button class="w-full rounded-md bg-pine px-3 py-1.5 text-xs font-medium text-white">{{ __('mxconnect.settlement.pay') }}</button></form>
              @endif
            </div>
          @endforeach
        @endif
      </div>

      @if ($claim->status === 'open' && auth()->user()?->hasAnyRole(['controller_validator','mutual_admin']))
      <form method="POST" action="{{ route('claims.close', $claim) }}">@csrf
        <button class="w-full rounded-md border border-ink/20 px-4 py-2 text-sm text-ink/60 hover:bg-ink/5">{{ __('mxconnect.claim.close') }}</button>
      </form>
      @endif
    </div>
  </div>
</x-layouts.tenant>
