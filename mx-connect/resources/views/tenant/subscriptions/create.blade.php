<x-layouts.tenant :title="__('mxconnect.subscription.new')">
  <a href="{{ route('subscriptions.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.back') }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('mxconnect.subscription.new') }}</h1>
  <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.subscription.create_sub') }}</p>

  <form method="POST" action="{{ route('subscriptions.store') }}" class="mt-6 max-w-xl space-y-4">
    @csrf
    <div>
      <label class="block text-sm font-medium">{{ __('mxconnect.member.plural') }}</label>
      <select name="member_id" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        <option value="">—</option>
        @foreach ($members as $m)
          <option value="{{ $m->id }}" @selected(optional($selectedMember)->id==$m->id)>{{ $m->last_name }} {{ $m->first_name }} ({{ $m->member_code }})</option>
        @endforeach
      </select>
      @error('member_id')<p class="text-xs text-clay">{{ $message }}</p>@enderror
    </div>
    <div>
      <label class="block text-sm font-medium">{{ __('mxconnect.guarantee.plural') }}</label>
      <select name="guarantee_version_id" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @foreach ($versions as $v)
          <option value="{{ $v->id }}">{{ $v->guarantee->name }} — V{{ $v->version_no }} ({{ $currency->format($v->base_contribution_minor) }} / {{ $v->periodicity }})</option>
        @endforeach
      </select>
      <p class="mt-1 text-xs text-ink/40">{{ __('mxconnect.subscription.amount_note') }}</p>
    </div>
    <div>
      <label class="block text-sm font-medium">{{ __('mxconnect.subscription.effective_date') }}</label>
      <input name="effective_date" type="date" value="{{ old('effective_date', date('Y-m-d')) }}" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">
    </div>
    <div class="rounded-md bg-mist px-4 py-3 text-xs text-ink/60">{{ __('mxconnect.subscription.maker_note') }}</div>
    <button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.subscription.capture_action') }}</button>
  </form>
</x-layouts.tenant>
