<x-layouts.tenant :title="$incident->ref">
  <a href="{{ route('incidents.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.incident.title') }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ $incident->title }}</h1>
  <div class="mt-1 text-sm text-ink/50 font-mono">{{ $incident->ref }} · {{ __('mxconnect.incident.cat_'.$incident->category) }} · {{ __('mxconnect.incident.sev_'.$incident->severity) }}</div>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-4">
      <div class="rounded-lg border border-ink/10 bg-white p-4">
        <h2 class="font-medium">{{ __('mxconnect.incident.field_desc') }}</h2>
        <p class="mt-1 text-sm text-ink/70 whitespace-pre-line">{{ $incident->description ?: '—' }}</p>
      </div>
      <div class="rounded-lg border border-ink/10 bg-white p-4">
        <h2 class="font-medium">{{ __('mxconnect.incident.resolution') }}</h2>
        <p class="mt-1 text-sm text-ink/70 whitespace-pre-line">{{ $incident->resolution ?: '—' }}</p>
        @if ($incident->resolved_on)<p class="mt-1 text-xs text-ink/40">{{ __('mxconnect.incident.resolved_on') }} {{ $incident->resolved_on->format('d/m/Y') }}</p>@endif
      </div>
    </div>

    <form method="POST" action="{{ route('incidents.status', $incident) }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3 self-start">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.incident.manage') }}</h2>
      <dl class="text-sm space-y-1">
        <div class="flex justify-between"><dt class="text-ink/50">{{ __('mxconnect.incident.occurred') }}</dt><dd>{{ $incident->occurred_on?->format('d/m/Y') }}</dd></div>
        <div class="flex justify-between"><dt class="text-ink/50">{{ __('mxconnect.incident.detected') }}</dt><dd>{{ $incident->detected_on?->format('d/m/Y') ?: '—' }}</dd></div>
      </dl>
      <select name="status" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @foreach (['open','investigating','resolved','closed'] as $st)<option value="{{ $st }}" @selected($incident->status===$st)>{{ __('mxconnect.incident.st_'.$st) }}</option>@endforeach
      </select>
      <textarea name="resolution" placeholder="{{ __('mxconnect.incident.resolution') }}" rows="3" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">{{ $incident->resolution }}</textarea>
      <p class="text-xs text-ink/40">{{ __('mxconnect.incident.resolution_hint') }}</p>
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.save') }}</button>
    </form>
  </div>
</x-layouts.tenant>
