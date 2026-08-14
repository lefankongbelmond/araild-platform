<x-layouts.tenant :title="__('mxconnect.guarantee.new')">
  <a href="{{ route('guarantees.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.back') }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('mxconnect.guarantee.new') }}</h1>

  <form method="POST" action="{{ route('guarantees.store') }}" class="mt-6 max-w-2xl space-y-4">
    @csrf
    <div class="grid grid-cols-2 gap-4">
      <div><label class="block text-sm font-medium">{{ __('mxconnect.guarantee.code') }}</label>
        <input name="code" value="{{ old('code') }}" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @error('code')<p class="text-xs text-clay">{{ $message }}</p>@enderror</div>
      <div><label class="block text-sm font-medium">{{ __('mxconnect.guarantee.name') }}</label>
        <input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm"></div>
    </div>
    <div><label class="block text-sm font-medium">{{ __('mxconnect.guarantee.description') }}</label>
      <textarea name="description" rows="2" class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">{{ old('description') }}</textarea></div>

    <div class="rounded-lg border border-ink/10 bg-white p-4">
      <h2 class="font-medium">{{ __('mxconnect.guarantee.first_version') }}</h2>
      <div class="mt-3 grid grid-cols-2 gap-4">
        <div><label class="block text-sm">{{ __('mxconnect.guarantee.contribution') }} ({{ $currency->symbol }})</label>
          <input name="base_contribution" type="number" step="any" min="0" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm"></div>
        <div><label class="block text-sm">{{ __('mxconnect.guarantee.periodicity') }}</label>
          <select name="periodicity" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">
            <option value="monthly">{{ __('mxconnect.periodicity.monthly') }}</option>
            <option value="quarterly">{{ __('mxconnect.periodicity.quarterly') }}</option>
            <option value="annual">{{ __('mxconnect.periodicity.annual') }}</option>
          </select></div>
        <div><label class="block text-sm">{{ __('mxconnect.guarantee.coverage') }} (%)</label>
          <input name="coverage_rate" type="number" step="any" min="0" max="100" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm"></div>
        <div><label class="block text-sm">{{ __('mxconnect.guarantee.copay') }} (%)</label>
          <input name="copay_rate" type="number" step="any" min="0" max="100" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm"></div>
        <div><label class="block text-sm">{{ __('mxconnect.guarantee.membership_fee') }} ({{ $currency->symbol }})</label>
          <input name="membership_fee" type="number" step="any" min="0" value="0" class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm"></div>
        <div><label class="block text-sm">{{ __('mxconnect.guarantee.observation') }} ({{ __('mxconnect.guarantee.days') }})</label>
          <input name="observation_days" type="number" min="0" value="0" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm"></div>
      </div>
    </div>
    <button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.guarantee.create_action') }}</button>
  </form>
</x-layouts.tenant>
