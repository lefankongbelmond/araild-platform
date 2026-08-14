<x-layouts.tenant :title="__('mxconnect.claim.new')">
  <a href="{{ route('claims.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.back') }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('mxconnect.claim.new') }}</h1>

  <form method="POST" action="{{ route('claims.store') }}" class="mt-6 max-w-xl space-y-4">
    @csrf
    <div>
      <label class="block text-sm font-medium">{{ __('mxconnect.member.plural') }}</label>
      <select name="member_id" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        <option value="">—</option>
        @foreach ($members as $m)<option value="{{ $m->id }}">{{ $m->last_name }} {{ $m->first_name }} ({{ $m->member_code }})</option>@endforeach
      </select>
      @error('member_id')<p class="text-xs text-clay">{{ $message }}</p>@enderror
    </div>
    <div>
      <label class="block text-sm font-medium">{{ __('mxconnect.claim.opened_on') }}</label>
      <input name="opened_on" type="date" value="{{ old('opened_on', date('Y-m-d')) }}" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">
    </div>
    <div>
      <label class="block text-sm font-medium">{{ __('mxconnect.claim.reason') }}</label>
      <input name="reason" value="{{ old('reason') }}" class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">
    </div>
    <button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.claim.open_action') }}</button>
  </form>
</x-layouts.tenant>
