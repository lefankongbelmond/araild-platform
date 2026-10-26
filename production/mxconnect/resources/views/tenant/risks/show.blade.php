<x-layouts.tenant :title="$risk->ref">
  <a href="{{ route('risks.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.risk.title') }}</a>
  @php $band = $scoring->band($risk->score()); @endphp
  <div class="mt-2 flex items-start justify-between">
    <div><h1 class="text-2xl font-semibold tracking-tight">{{ $risk->title }}</h1>
      <div class="mt-1 text-sm text-ink/50 font-mono">{{ $risk->ref }} · {{ __('mxconnect.risk.cat_'.$risk->category) }}</div></div>
    <span class="rounded-full px-3 py-1 text-sm {{ $scoring->tone($band) }}">{{ $risk->score() }} · {{ __('mxconnect.risk.band_'.$band) }}</span>
  </div>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-4">
      <div class="rounded-lg border border-ink/10 bg-white p-4">
        <h2 class="font-medium">{{ __('mxconnect.risk.field_desc') }}</h2>
        <p class="mt-1 text-sm text-ink/70 whitespace-pre-line">{{ $risk->description ?: '—' }}</p>
      </div>
      <div class="rounded-lg border border-ink/10 bg-white p-4">
        <h2 class="font-medium">{{ __('mxconnect.risk.treatment') }}</h2>
        <p class="mt-1 text-sm text-ink/70 whitespace-pre-line">{{ $risk->treatment ?: '—' }}</p>
      </div>
    </div>

    <form method="POST" action="{{ route('risks.status', $risk) }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3 self-start">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.risk.manage') }}</h2>
      <dl class="text-sm space-y-1">
        <div class="flex justify-between"><dt class="text-ink/50">{{ __('mxconnect.risk.likelihood') }}</dt><dd>{{ $risk->likelihood }}</dd></div>
        <div class="flex justify-between"><dt class="text-ink/50">{{ __('mxconnect.risk.impact') }}</dt><dd>{{ $risk->impact }}</dd></div>
        <div class="flex justify-between"><dt class="text-ink/50">{{ __('mxconnect.risk.owner') }}</dt><dd>{{ $risk->owner_role ?: '—' }}</dd></div>
        <div class="flex justify-between"><dt class="text-ink/50">{{ __('mxconnect.risk.review') }}</dt><dd>{{ $risk->review_on?->format('d/m/Y') ?: '—' }}</dd></div>
      </dl>
      <select name="status" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @foreach (['open','mitigating','closed'] as $st)<option value="{{ $st }}" @selected($risk->status===$st)>{{ __('mxconnect.risk.st_'.$st) }}</option>@endforeach
      </select>
      <textarea name="treatment" placeholder="{{ __('mxconnect.risk.treatment') }}" rows="3" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">{{ $risk->treatment }}</textarea>
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.save') }}</button>
    </form>
  </div>
</x-layouts.tenant>
