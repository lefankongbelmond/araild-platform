<x-layouts.tenant :title="__('mxconnect.member.plural')">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.member.plural') }}</h1>
      <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.member.sub') }}</p>
    </div>
    @if (auth()->user()?->hasAnyRole(['enrollment_agent','mutual_admin']))
      <a href="{{ route('members.import.form') }}" class="rounded-md border border-ink/20 px-4 py-2 text-sm font-medium text-ink hover:bg-ink/5 transition">{{ __('mxconnect.import.title') }}</a>
      <a href="{{ route('members.create') }}" class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">+ {{ __('mxconnect.member.new') }}</a>
    @endif
  </div>

  <form method="GET" class="mt-6">
    <input name="q" value="{{ $q }}" placeholder="{{ __('mxconnect.member.search') }}"
           class="w-full max-w-md rounded-md border-ink/20 bg-white px-3 py-2 text-sm focus:border-pine focus:ring-pine">
  </form>

  <div class="mt-4 overflow-hidden rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.member.code') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.member.name') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.member.phone') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.member.dependents') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.status') }}</th>
        <th class="px-4 py-2"></th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($members as $m)
          @php $badge = ['active'=>'pine','suspended'=>'clay','left'=>'ink/40','revoked'=>'ink/40'][$m->status->value] ?? 'ink/40'; @endphp
          <tr>
            <td class="px-4 py-3 font-mono text-xs">{{ $m->member_code }}</td>
            <td class="px-4 py-3 font-medium">{{ $m->last_name }} {{ $m->first_name }}</td>
            <td class="px-4 py-3">{{ $m->phone ?: '—' }}</td>
            <td class="px-4 py-3">{{ $m->dependents_count }}</td>
            <td class="px-4 py-3"><span class="rounded-full border border-{{ $badge }}/30 px-2 py-0.5 text-xs text-{{ $badge }}">{{ __('mxconnect.member.status_'.$m->status->value) }}</span></td>
            <td class="px-4 py-3 text-right"><a href="{{ route('members.show', $m) }}" class="text-pine hover:underline">{{ __('mxconnect.member.open') }}</a></td>
          </tr>
        @empty
          <tr><td colspan="6" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.member.empty') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-4">{{ $members->links() }}</div>
</x-layouts.tenant>
