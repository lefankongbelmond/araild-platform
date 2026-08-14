<x-layouts.tenant :title="__('mxconnect.contribution.plural')">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.contribution.plural') }}</h1>
      <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.contribution.sub') }}</p>
    </div>
    @if ($arrears > 0)<span class="rounded-full bg-clay/10 px-3 py-1 text-sm text-clay">{{ $arrears }} {{ __('mxconnect.contribution.overdue_count') }}</span>@endif
  </div>

  <div class="mt-6 flex gap-2 text-sm">
    @foreach (['overdue','to_pay','paid'] as $tab)
      <a href="{{ route('contributions.index', ['status' => $tab]) }}"
         class="rounded-md px-3 py-1.5 {{ $status === $tab ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">
        {{ __('mxconnect.contribution.status_'.$tab) }}
      </a>
    @endforeach
  </div>

  <div class="mt-4 overflow-hidden rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.member.name') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.contribution.period') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.contribution.due') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.contribution.amount') }}</th>
        <th class="px-4 py-2"></th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($schedules as $sch)
          <tr>
            <td class="px-4 py-3 font-medium">{{ $sch->subscription?->member?->last_name }} {{ $sch->subscription?->member?->first_name }}</td>
            <td class="px-4 py-3">{{ $sch->period }}</td>
            <td class="px-4 py-3 {{ $sch->status === 'overdue' ? 'text-clay' : '' }}">{{ $sch->due_date?->format('d/m/Y') }}</td>
            <td class="px-4 py-3">{{ $currency->format($sch->due_minor) }}</td>
            <td class="px-4 py-3 text-right">
              @if ($sch->status !== 'paid')
                {{-- Cash/group here; Mobile Money is initiated from the member area (Module 8) --}}
                <form method="POST" action="{{ route('contributions.pay', $sch) }}" class="inline-flex items-center gap-2">
                  @csrf
                  <select name="mode" class="rounded-md border-ink/20 px-2 py-1 text-xs">
                    <option value="cash">{{ __('mxconnect.contribution.cash') }}</option>
                    <option value="group">{{ __('mxconnect.contribution.group') }}</option>
                  </select>
                  <button class="text-pine hover:underline">{{ __('mxconnect.contribution.record') }}</button>
                </form>
              @else
                <span class="text-xs text-pine">{{ __('mxconnect.contribution.status_paid') }}</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.contribution.empty') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $schedules->links() }}</div>
</x-layouts.tenant>
