<x-layouts.network :title="__('mxconnect.billing.plans')">
  <div class="flex items-center justify-between">
    <div><h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.billing.plans') }}</h1>
      <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.billing.plans_sub') }}</p></div>
    <a href="{{ route('network.billing.index') }}" class="text-sm text-pine hover:underline">{{ __('mxconnect.billing.title') }} →</a>
  </div>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 overflow-hidden rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.billing.plan') }}</th>
          <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.billing.price') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.billing.interval') }}</th>
          <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.billing.max_members') }}</th>
          <th class="px-4 py-2 font-medium"></th>
        </tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($plans as $p)
            <tr class="{{ $p->active ? '' : 'opacity-50' }}">
              <td class="px-4 py-2">{{ $p->name }}<div class="text-xs text-ink/40 font-mono">{{ $p->code }}</div></td>
              <td class="px-4 py-2 text-right font-medium">{{ $p->currency ? $p->currency->format($p->price_minor) : $p->price_minor }}</td>
              <td class="px-4 py-2 text-ink/60">{{ __('mxconnect.billing.int_'.$p->interval) }}</td>
              <td class="px-4 py-2 text-right">{{ $p->max_members ?? '∞' }}</td>
              <td class="px-4 py-2 text-right">
                <form method="POST" action="{{ route('network.billing.plans.toggle', $p) }}">@csrf
                  <button class="text-xs text-pine hover:underline">{{ $p->active ? __('mxconnect.billing.deactivate') : __('mxconnect.billing.activate') }}</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.billing.no_plans') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <form method="POST" action="{{ route('network.billing.plans.store') }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3 self-start">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.billing.new_plan') }}</h2>
      <input name="code" placeholder="{{ __('mxconnect.billing.code') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <input name="name" placeholder="{{ __('mxconnect.billing.name') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <div class="flex gap-2">
        <input name="price" type="number" step="any" min="0" placeholder="{{ __('mxconnect.billing.price') }}" required class="flex-1 rounded-md border-ink/20 px-3 py-2 text-sm">
        <select name="currency_id" required class="rounded-md border-ink/20 px-3 py-2 text-sm">
          @foreach ($currencies as $c)<option value="{{ $c->id }}">{{ $c->code }}</option>@endforeach
        </select>
      </div>
      <select name="interval" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        <option value="monthly">{{ __('mxconnect.billing.int_monthly') }}</option>
        <option value="annual">{{ __('mxconnect.billing.int_annual') }}</option>
      </select>
      <input name="max_members" type="number" min="0" placeholder="{{ __('mxconnect.billing.max_members') }}" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.billing.add_plan') }}</button>
    </form>
  </div>
</x-layouts.network>
