<x-layouts.tenant :title="$member->last_name.' '.$member->first_name">
  <a href="{{ route('members.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.back') }}</a>

  <div class="mt-2 flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">{{ $member->last_name }} {{ $member->first_name }}</h1>
      <p class="mt-1 text-sm text-ink/50 font-mono">{{ $member->member_code }}</p>
    </div>
    @php $badge = ['active'=>'pine','suspended'=>'clay','left'=>'ink/40','revoked'=>'ink/40'][$member->status->value] ?? 'ink/40'; @endphp
    <span class="rounded-full border border-{{ $badge }}/30 px-3 py-1 text-sm text-{{ $badge }}">{{ __('mxconnect.member.status_'.$member->status->value) }}</span>
  </div>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    {{-- Identity + lifecycle --}}
    <div class="lg:col-span-2 space-y-6">
      <div class="rounded-lg border border-ink/10 bg-white p-4">
        <h2 class="font-medium">{{ __('mxconnect.member.identity') }}</h2>
        <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
          <div><dt class="text-ink/50">{{ __('mxconnect.member.birth_date') }}</dt><dd>{{ $member->birth_date?->format('d/m/Y') }}</dd></div>
          <div><dt class="text-ink/50">{{ __('mxconnect.member.sex') }}</dt><dd>{{ $member->sex }}</dd></div>
          <div><dt class="text-ink/50">{{ __('mxconnect.member.phone') }}</dt><dd>{{ $member->phone ?: '—' }}</dd></div>
          <div><dt class="text-ink/50">{{ __('mxconnect.member.group') }}</dt><dd>{{ $member->group?->name ?: '—' }}</dd></div>
          <div class="col-span-2"><dt class="text-ink/50">{{ __('mxconnect.member.address') }}</dt><dd>{{ $member->address ?: '—' }}</dd></div>
        </dl>
        @if (auth()->user()?->hasAnyRole(['enrollment_agent','mutual_admin']))
        <div class="mt-4 flex gap-3">
          @if ($member->status->value === 'active')
            <form method="POST" action="{{ route('members.suspend', $member) }}">@csrf<button class="text-sm text-clay hover:underline">{{ __('mxconnect.member.suspend') }}</button></form>
          @elseif ($member->status->value === 'suspended')
            <form method="POST" action="{{ route('members.reactivate', $member) }}">@csrf<button class="text-sm text-pine hover:underline">{{ __('mxconnect.member.reactivate') }}</button></form>
          @endif
        </div>
        @endif
      </div>

      {{-- Dependents --}}
      <div class="rounded-lg border border-ink/10 bg-white p-4">
        <h2 class="font-medium">{{ __('mxconnect.member.dependents') }} ({{ $member->dependents->where('status','active')->count() }})</h2>
        <table class="mt-3 w-full text-sm">
          <tbody class="divide-y divide-ink/5">
            @forelse ($member->dependents as $d)
              <tr class="{{ $d->status === 'removed' ? 'opacity-40' : '' }}">
                <td class="py-2">{{ $d->last_name }} {{ $d->first_name }}</td>
                <td class="py-2 text-ink/50">{{ __('mxconnect.relationship.'.$d->relationship) }}</td>
                <td class="py-2 text-ink/50">{{ $d->birth_date?->format('d/m/Y') }}</td>
                <td class="py-2 text-right">
                  @if ($d->status !== 'removed' && auth()->user()?->hasAnyRole(['enrollment_agent','mutual_admin']))
                    <form method="POST" action="{{ route('members.dependents.remove', [$member, $d]) }}">@csrf @method('DELETE')
                      <button class="text-xs text-clay hover:underline">{{ __('mxconnect.member.remove') }}</button></form>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td class="py-4 text-center text-ink/40">{{ __('mxconnect.member.no_dependents') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Add dependent --}}
    @if (auth()->user()?->hasAnyRole(['enrollment_agent','mutual_admin']))
    <form method="POST" action="{{ route('members.dependents.add', $member) }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3 self-start">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.member.add_dependent') }}</h2>
      <input name="last_name" placeholder="{{ __('mxconnect.member.last_name') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <input name="first_name" placeholder="{{ __('mxconnect.member.first_name') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <input name="birth_date" type="date" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <select name="sex" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        <option value="M">{{ __('mxconnect.member.male') }}</option><option value="F">{{ __('mxconnect.member.female') }}</option>
      </select>
      <select name="relationship" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        <option value="spouse">{{ __('mxconnect.relationship.spouse') }}</option>
        <option value="child">{{ __('mxconnect.relationship.child') }}</option>
        <option value="ascendant">{{ __('mxconnect.relationship.ascendant') }}</option>
        <option value="other">{{ __('mxconnect.relationship.other') }}</option>
      </select>
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.member.add_dependent') }}</button>
    </form>
    @endif
  </div>
</x-layouts.tenant>
