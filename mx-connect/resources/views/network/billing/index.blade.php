<x-layouts.network :title="__('mxconnect.billing.title')">
  <div class="flex items-center justify-between">
    <div><h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.billing.title') }}</h1>
      <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.billing.sub') }}</p></div>
    <a href="{{ route('network.billing.plans') }}" class="text-sm text-pine hover:underline">{{ __('mxconnect.billing.plans') }} →</a>
  </div>

  {{-- Assign a plan to an unsubscribed mutual --}}
  @if ($unsubscribed->isNotEmpty() && $plans->isNotEmpty())
    <form method="POST" action="{{ route('network.billing.subscribe') }}" class="mt-6 rounded-lg border border-ink/10 bg-white p-4 flex flex-wrap items-end gap-3">
      @csrf
      <div><label class="block text-xs text-ink/60">{{ __('mxconnect.billing.mutual') }}</label>
        <select name="mutual_id" required class="mt-1 rounded-md border-ink/20 px-3 py-2 text-sm">
          @foreach ($unsubscribed as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
        </select></div>
      <div><label class="block text-xs text-ink/60">{{ __('mxconnect.billing.plan') }}</label>
        <select name="billing_plan_id" required class="mt-1 rounded-md border-ink/20 px-3 py-2 text-sm">
          @foreach ($plans as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
        </select></div>
      <button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.billing.assign') }}</button>
    </form>
  @endif

  {{-- Subscriptions --}}
  <h2 class="mt-8 font-medium">{{ __('mxconnect.billing.subscriptions') }}</h2>
  <div class="mt-2 overflow-hidden rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.billing.mutual') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.billing.plan') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.status') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.billing.period_end') }}</th>
        <th class="px-4 py-2 font-medium text-right"></th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($subscriptions as $s)
          @php $tone = ['active'=>'text-sage','past_due'=>'text-clay','suspended'=>'text-clay','cancelled'=>'text-ink/40','trialing'=>'text-ink'][$s->status] ?? ''; @endphp
          <tr>
            <td class="px-4 py-2">{{ $s->mutual?->name }}</td>
            <td class="px-4 py-2">{{ $s->plan?->name }}<div class="text-xs text-ink/40">{{ $s->plan?->currency?->format($s->plan->price_minor) }} / {{ __('mxconnect.billing.int_'.$s->plan?->interval) }}</div></td>
            <td class="px-4 py-2 {{ $tone }}">{{ __('mxconnect.billing.st_'.$s->status) }}</td>
            <td class="px-4 py-2 text-ink/60">{{ $s->current_period_end?->format('d/m/Y') }}</td>
            <td class="px-4 py-2 text-right">
              <form method="POST" action="{{ route('network.billing.invoice', $s) }}">@csrf
                <button class="text-xs text-pine hover:underline">{{ __('mxconnect.billing.generate_invoice') }}</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.billing.no_subs') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Invoices --}}
  <h2 class="mt-8 font-medium">{{ __('mxconnect.billing.invoices') }}</h2>
  <div class="mt-2 overflow-hidden rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.billing.number') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.billing.mutual') }}</th>
        <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.billing.amount') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.billing.due') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.status') }}</th>
        <th class="px-4 py-2 font-medium text-right"></th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($invoices as $inv)
          @php $tone = ['paid'=>'bg-sage/15 text-sage','issued'=>'bg-mist text-ink','overdue'=>'bg-clay/20 text-clay','void'=>'bg-ink/10 text-ink/50'][$inv->status]; @endphp
          <tr>
            <td class="px-4 py-2 font-mono text-xs">{{ $inv->number }}</td>
            <td class="px-4 py-2">{{ $inv->mutual?->name }}</td>
            <td class="px-4 py-2 text-right font-medium">{{ $inv->currency ? $inv->currency->format($inv->amount_minor) : $inv->amount_minor }}</td>
            <td class="px-4 py-2 text-ink/60">{{ $inv->due_on?->format('d/m/Y') }}</td>
            <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs {{ $tone }}">{{ __('mxconnect.billing.st_'.$inv->status) }}</span></td>
            <td class="px-4 py-2 text-right">
              @if (! in_array($inv->status, ['paid','void']))
                <form method="POST" action="{{ route('network.billing.paid', $inv) }}">@csrf
                  <button class="text-xs text-pine hover:underline">{{ __('mxconnect.billing.mark_paid') }}</button>
                </form>
              @else <span class="text-xs text-ink/30">—</span> @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.billing.no_invoices') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</x-layouts.network>
