<x-layouts.tenant :title="__('mxconnect.claim.plural')">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.claim.plural') }}</h1>
      <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.claim.sub') }}</p>
    </div>
    @if (auth()->user()?->hasAnyRole(['benefits_manager','controller_validator','mutual_admin']))
      <a href="{{ route('claims.create') }}" class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">+ {{ __('mxconnect.claim.new') }}</a>
    @endif
  </div>

  <div class="mt-6 flex gap-2 text-sm">
    @foreach (['open','closed'] as $tab)
      <a href="{{ route('claims.index', ['status' => $tab]) }}"
         class="rounded-md px-3 py-1.5 {{ $status === $tab ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">
        {{ __('mxconnect.claim.status_'.$tab) }}
      </a>
    @endforeach
  </div>

  <div class="mt-4 overflow-hidden rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.member.name') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.claim.opened_on') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.claim.reason') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('mxconnect.claim.prestations') }}</th>
        <th class="px-4 py-2"></th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($claims as $c)
          <tr>
            <td class="px-4 py-3 font-medium">{{ $c->member?->last_name }} {{ $c->member?->first_name }}</td>
            <td class="px-4 py-3">{{ $c->opened_on?->format('d/m/Y') }}</td>
            <td class="px-4 py-3 text-ink/60">{{ $c->reason ?: '—' }}</td>
            <td class="px-4 py-3">{{ $c->prestations_count }}</td>
            <td class="px-4 py-3 text-right"><a href="{{ route('claims.show', $c) }}" class="text-pine hover:underline">{{ __('mxconnect.member.open') }}</a></td>
          </tr>
        @empty
          <tr><td colspan="5" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.claim.empty') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $claims->links() }}</div>
</x-layouts.tenant>
