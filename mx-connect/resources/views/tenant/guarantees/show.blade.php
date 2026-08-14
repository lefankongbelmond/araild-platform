<x-layouts.tenant :title="$guarantee->name">
  <a href="{{ route('guarantees.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.back') }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ $guarantee->name }} <span class="text-sm text-ink/40">{{ $guarantee->code }}</span></h1>
  <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.guarantee.history_sub') }}</p>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    {{-- Version history: past versions stay valid for claims dated within their window --}}
    <div class="lg:col-span-2 space-y-3">
      @foreach ($guarantee->versions as $v)
        <div class="rounded-lg border {{ is_null($v->valid_to) ? 'border-pine/40 bg-pine/5' : 'border-ink/10 bg-white' }} p-4">
          <div class="flex items-center justify-between">
            <div class="font-medium">V{{ $v->version_no }}
              @if (is_null($v->valid_to))<span class="ml-2 rounded-full bg-pine px-2 py-0.5 text-xs text-white">{{ __('mxconnect.guarantee.current') }}</span>@endif
              @if ($v->locked)<span class="ml-2 rounded-full bg-ink/60 px-2 py-0.5 text-xs text-white">{{ __('mxconnect.guarantee.locked') }}</span>@endif
            </div>
            <div class="text-xs text-ink/50">{{ $v->valid_from?->format('d/m/Y') }} → {{ $v->valid_to?->format('d/m/Y') ?? '…' }}</div>
          </div>
          <div class="mt-2 grid grid-cols-3 gap-2 text-sm text-ink/70">
            <div>{{ __('mxconnect.guarantee.contribution') }}: <b>{{ $currency->format($v->base_contribution_minor) }}</b> / {{ $v->periodicity }}</div>
            <div>{{ __('mxconnect.guarantee.coverage') }}: <b>{{ $v->coverage_rate }}%</b></div>
            <div>{{ __('mxconnect.guarantee.copay') }}: <b>{{ $v->copay_rate }}%</b></div>
            <div>{{ __('mxconnect.guarantee.observation') }}: <b>{{ $v->observation_days }} {{ __('mxconnect.guarantee.days') }}</b></div>
          </div>
        </div>
      @endforeach
    </div>

    @can('manage-parameters')
    {{-- Roll a new version: closes the current one, opens the successor --}}
    <form method="POST" action="{{ route('guarantees.version', $guarantee) }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3 self-start">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.guarantee.new_version') }}</h2>
      <p class="text-xs text-ink/50">{{ __('mxconnect.guarantee.new_version_note') }}</p>
      <input name="base_contribution" type="number" step="any" min="0" placeholder="{{ __('mxconnect.guarantee.contribution') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <select name="periodicity" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        <option value="monthly">{{ __('mxconnect.periodicity.monthly') }}</option>
        <option value="quarterly">{{ __('mxconnect.periodicity.quarterly') }}</option>
        <option value="annual">{{ __('mxconnect.periodicity.annual') }}</option>
      </select>
      <div class="flex gap-2">
        <input name="coverage_rate" type="number" step="any" min="0" max="100" placeholder="{{ __('mxconnect.guarantee.coverage') }} %" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        <input name="copay_rate" type="number" step="any" min="0" max="100" placeholder="{{ __('mxconnect.guarantee.copay') }} %" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      </div>
      <input name="membership_fee" type="number" step="any" min="0" value="0" placeholder="{{ __('mxconnect.guarantee.membership_fee') }}" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <input name="observation_days" type="number" min="0" value="0" placeholder="{{ __('mxconnect.guarantee.observation') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <label class="block text-sm">{{ __('mxconnect.guarantee.effective_from') }}
        <input name="effective_from" type="date" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm"></label>
      @error('effective_from')<p class="text-xs text-clay">{{ $message }}</p>@enderror
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.guarantee.roll') }}</button>
    </form>
    @endcan
  </div>
</x-layouts.tenant>
