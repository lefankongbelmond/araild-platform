<x-layouts.tenant :title="$complaint->ref">
  <a href="{{ route('complaints.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.complaint.title') }}</a>
  <div class="mt-2 flex items-start justify-between">
    <div><h1 class="text-2xl font-semibold tracking-tight">{{ $complaint->subject }}</h1>
      <div class="mt-1 text-sm text-ink/50 font-mono">{{ $complaint->ref }} · {{ __('mxconnect.complaint.ch_'.$complaint->channel) }}</div></div>
    @if ($complaint->isOverdue())<span class="rounded-full bg-clay/15 px-3 py-1 text-sm text-clay">{{ __('mxconnect.complaint.overdue') }}</span>@endif
  </div>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-4">
      <div class="rounded-lg border border-ink/10 bg-white p-4">
        <h2 class="font-medium">{{ __('mxconnect.complaint.field_desc') }}</h2>
        <p class="mt-1 text-sm text-ink/70 whitespace-pre-line">{{ $complaint->description ?: '—' }}</p>
      </div>
      <div class="rounded-lg border border-ink/10 bg-white p-4">
        <h2 class="font-medium">{{ __('mxconnect.complaint.resolution') }}</h2>
        <p class="mt-1 text-sm text-ink/70 whitespace-pre-line">{{ $complaint->resolution ?: '—' }}</p>
        @if ($complaint->resolved_on)<p class="mt-1 text-xs text-ink/40">{{ __('mxconnect.complaint.resolved_on') }} {{ $complaint->resolved_on->format('d/m/Y') }}</p>@endif
      </div>
    </div>

    <form method="POST" action="{{ route('complaints.status', $complaint) }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3 self-start">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.complaint.manage') }}</h2>
      <dl class="text-sm space-y-1">
        <div class="flex justify-between"><dt class="text-ink/50">{{ __('mxconnect.complaint.member') }}</dt><dd>{{ $complaint->member?->member_code ?? __('mxconnect.complaint.anonymous') }}</dd></div>
        <div class="flex justify-between"><dt class="text-ink/50">{{ __('mxconnect.complaint.received') }}</dt><dd>{{ $complaint->received_on?->format('d/m/Y') }}</dd></div>
        <div class="flex justify-between"><dt class="text-ink/50">{{ __('mxconnect.complaint.due') }}</dt><dd class="{{ $complaint->isOverdue() ? 'text-clay' : '' }}">{{ $complaint->due_on?->format('d/m/Y') ?: '—' }}</dd></div>
      </dl>
      <select name="status" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @foreach (['received','in_progress','resolved','closed'] as $st)<option value="{{ $st }}" @selected($complaint->status===$st)>{{ __('mxconnect.complaint.st_'.$st) }}</option>@endforeach
      </select>
      <textarea name="resolution" placeholder="{{ __('mxconnect.complaint.resolution') }}" rows="3" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">{{ $complaint->resolution }}</textarea>
      <p class="text-xs text-ink/40">{{ __('mxconnect.complaint.resolution_hint') }}</p>
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.save') }}</button>
    </form>
  </div>
</x-layouts.tenant>
