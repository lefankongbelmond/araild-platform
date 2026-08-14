<x-layouts.tenant :title="__('mxconnect.member.new')">
  <a href="{{ route('members.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.back') }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('mxconnect.member.new') }}</h1>

  {{-- Strong duplicate: hard block, cannot proceed --}}
  @if (session('duplicate_block'))
    <div class="mt-4 rounded-md border border-clay/40 bg-clay/5 px-4 py-3 text-sm text-clay">
      <b>{{ __('mxconnect.member.dup_block_title') }}</b>
      <p class="mt-1">{{ __('mxconnect.member.dup_block_help') }}</p>
      <ul class="mt-1 list-disc pl-5">
        @foreach (\App\Models\Tenant\Member::whereIn('id', session('duplicate_block'))->get() as $dup)
          <li><a class="underline" href="{{ route('members.show', $dup) }}">{{ $dup->member_code }} — {{ $dup->last_name }} {{ $dup->first_name }}</a></li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Weak duplicate: warn, agent must tick to confirm --}}
  @if (session('duplicate_warn'))
    <div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">
      <b>{{ __('mxconnect.member.dup_warn_title') }}</b>
      <p class="mt-1">{{ __('mxconnect.member.dup_warn_help') }}</p>
      <ul class="mt-1 list-disc pl-5">
        @foreach (\App\Models\Tenant\Member::whereIn('id', session('duplicate_warn'))->get() as $dup)
          <li><a class="underline" href="{{ route('members.show', $dup) }}" target="_blank">{{ $dup->member_code }} — {{ $dup->last_name }} {{ $dup->first_name }}</a></li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('members.store') }}" class="mt-6 max-w-2xl space-y-4">
    @csrf
    <div class="grid grid-cols-2 gap-4">
      <div><label class="block text-sm font-medium">{{ __('mxconnect.member.last_name') }}</label>
        <input name="last_name" value="{{ old('last_name') }}" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @error('last_name')<p class="text-xs text-clay">{{ $message }}</p>@enderror</div>
      <div><label class="block text-sm font-medium">{{ __('mxconnect.member.first_name') }}</label>
        <input name="first_name" value="{{ old('first_name') }}" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @error('first_name')<p class="text-xs text-clay">{{ $message }}</p>@enderror</div>
      <div><label class="block text-sm font-medium">{{ __('mxconnect.member.birth_date') }}</label>
        <input name="birth_date" type="date" value="{{ old('birth_date') }}" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @error('birth_date')<p class="text-xs text-clay">{{ $message }}</p>@enderror</div>
      <div><label class="block text-sm font-medium">{{ __('mxconnect.member.sex') }}</label>
        <select name="sex" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">
          <option value="M" @selected(old('sex')=='M')>{{ __('mxconnect.member.male') }}</option>
          <option value="F" @selected(old('sex')=='F')>{{ __('mxconnect.member.female') }}</option>
        </select></div>
      <div><label class="block text-sm font-medium">{{ __('mxconnect.member.phone') }}</label>
        <input name="phone" value="{{ old('phone') }}" placeholder="+237…" class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm"></div>
      <div><label class="block text-sm font-medium">{{ __('mxconnect.member.id_document') }}</label>
        <input name="id_document" value="{{ old('id_document') }}" class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        <p class="mt-1 text-xs text-ink/40">{{ __('mxconnect.member.id_help') }}</p></div>
      <div><label class="block text-sm font-medium">{{ __('mxconnect.param.antennas') }}</label>
        <select name="antenna_id" class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">
          <option value="">—</option>
          @foreach ($antennas as $a)<option value="{{ $a->id }}" @selected(old('antenna_id')==$a->id)>{{ $a->name }}</option>@endforeach
        </select></div>
      <div><label class="block text-sm font-medium">{{ __('mxconnect.member.group') }}</label>
        <select name="member_group_id" class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm">
          <option value="">—</option>
          @foreach ($groups as $g)<option value="{{ $g->id }}" @selected(old('member_group_id')==$g->id)>{{ $g->name }}</option>@endforeach
        </select></div>
      <div class="col-span-2"><label class="block text-sm font-medium">{{ __('mxconnect.member.address') }}</label>
        <input name="address" value="{{ old('address') }}" class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm"></div>
    </div>

    @if (session('duplicate_warn'))
      <label class="flex items-center gap-2 text-sm text-clay">
        <input type="checkbox" name="confirm_duplicate" value="1" required class="rounded border-clay/40 text-clay">
        {{ __('mxconnect.member.dup_confirm') }}
      </label>
    @endif

    <button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.member.create_action') }}</button>
  </form>
</x-layouts.tenant>
