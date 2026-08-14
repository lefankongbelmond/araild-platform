<x-layouts.tenant :title="__('mxconnect.treasury.movements')">
  <a href="{{ route('treasury.dashboard') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.treasury.title') }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('mxconnect.treasury.movements') }}</h1>

  <div class="mt-4 flex gap-2 text-sm">
    <a href="{{ route('treasury.movements') }}" class="rounded-md px-3 py-1.5 {{ ! $direction ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ __('mxconnect.treasury.all') }}</a>
    @foreach (['inflow','outflow'] as $d)
      <a href="{{ route('treasury.movements', ['direction' => $d]) }}" class="rounded-md px-3 py-1.5 {{ $direction === $d ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ __('mxconnect.treasury.'.$d) }}</a>
    @endforeach
  </div>

  <div class="mt-4 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 overflow-hidden rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.treasury.date') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.treasury.source') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.treasury.mode') }}</th>
          <th class="px-4 py-2 font-medium text-right">{{ __('mxconnect.treasury.amount') }}</th>
        </tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($movements as $m)
            <tr>
              <td class="px-4 py-2">{{ $m->moved_on?->format('d/m/Y') }}</td>
              <td class="px-4 py-2">{{ __('mxconnect.treasury.src_'.$m->source) }}</td>
              <td class="px-4 py-2 text-ink/60">{{ __('mxconnect.treasury.mode_'.$m->mode) }}</td>
              <td class="px-4 py-2 text-right {{ $m->direction === 'inflow' ? 'text-sage' : 'text-clay' }}">{{ $m->direction === 'inflow' ? '+' : '−' }}{{ $currency->format($m->amount_minor) }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.treasury.empty') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if (auth()->user()?->hasRole('treasurer_accountant'))
    <form method="POST" action="{{ route('treasury.movements.store') }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3 self-start">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.treasury.manual') }}</h2>
      <select name="direction" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        <option value="inflow">{{ __('mxconnect.treasury.inflow') }}</option>
        <option value="outflow">{{ __('mxconnect.treasury.outflow') }}</option>
      </select>
      <select name="source" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @foreach (['contribution','membership_fee','provider_invoice','reimbursement','other'] as $src)
          <option value="{{ $src }}">{{ __('mxconnect.treasury.src_'.$src) }}</option>
        @endforeach
      </select>
      <select name="mode" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @foreach (['cash','bank','mobile_money'] as $mode)
          <option value="{{ $mode }}">{{ __('mxconnect.treasury.mode_'.$mode) }}</option>
        @endforeach
      </select>
      <input name="amount" type="number" step="any" min="0" placeholder="{{ __('mxconnect.treasury.amount') }} ({{ $currency->symbol }})" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <input name="moved_on" type="date" value="{{ date('Y-m-d') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <input name="reference" placeholder="{{ __('mxconnect.treasury.reference') }}" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.treasury.add') }}</button>
    </form>
    @endif
  </div>
  <div class="mt-4">{{ $movements->links() }}</div>
</x-layouts.tenant>
