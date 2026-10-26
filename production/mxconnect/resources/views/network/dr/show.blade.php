<x-layouts.network :title="$test->ref">
  <a href="{{ route('network.dr.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.dr.title') }}</a>
  @php $v = $assessment->verdict($test);
    $tone = ['clean'=>'bg-sage/15 text-sage','degraded'=>'bg-clay/15 text-clay','failed'=>'bg-clay/25 text-clay','incomplete'=>'bg-mist text-ink'][$v]; @endphp
  <div class="mt-2 flex items-start justify-between">
    <div><h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.dr.type_'.$test->type) }}</h1>
      <div class="mt-1 text-sm text-ink/50 font-mono">{{ $test->ref }} · {{ $test->scope }}</div></div>
    <span class="rounded-full px-3 py-1 text-sm {{ $tone }}">{{ __('mxconnect.dr.v_'.$v) }}</span>
  </div>

  <div class="mt-6 grid gap-4 md:grid-cols-2">
    <div class="rounded-lg border border-ink/10 bg-white p-4">
      <h2 class="font-medium">RTO — {{ __('mxconnect.dr.rto_label') }}</h2>
      <div class="mt-2 flex items-baseline gap-2">
        <span class="text-2xl font-semibold {{ $assessment->rtoMet($test) ? 'text-sage' : 'text-clay' }}">{{ $test->rto_actual_minutes ?? '—' }}</span>
        <span class="text-sm text-ink/50">/ {{ $test->rto_target_minutes ?? '—' }} {{ __('mxconnect.dr.minutes') }}</span>
      </div>
    </div>
    <div class="rounded-lg border border-ink/10 bg-white p-4">
      <h2 class="font-medium">RPO — {{ __('mxconnect.dr.rpo_label') }}</h2>
      <div class="mt-2 flex items-baseline gap-2">
        <span class="text-2xl font-semibold {{ $assessment->rpoMet($test) ? 'text-sage' : 'text-clay' }}">{{ $test->rpo_actual_minutes ?? '—' }}</span>
        <span class="text-sm text-ink/50">/ {{ $test->rpo_target_minutes ?? '—' }} {{ __('mxconnect.dr.minutes') }}</span>
      </div>
    </div>
  </div>

  <div class="mt-4 rounded-lg border border-ink/10 bg-white p-4">
    <dl class="grid gap-2 sm:grid-cols-2 text-sm">
      <div class="flex justify-between"><dt class="text-ink/50">{{ __('mxconnect.dr.date') }}</dt><dd>{{ $test->performed_on?->format('d/m/Y') }}</dd></div>
      <div class="flex justify-between"><dt class="text-ink/50">{{ __('mxconnect.dr.outcome') }}</dt><dd>{{ __('mxconnect.dr.o_'.$test->outcome) }}</dd></div>
      <div class="flex justify-between"><dt class="text-ink/50">{{ __('mxconnect.dr.by') }}</dt><dd>{{ $test->performed_by ?: '—' }}</dd></div>
    </dl>
    <h2 class="mt-4 font-medium">{{ __('mxconnect.dr.findings') }}</h2>
    <p class="mt-1 text-sm text-ink/70 whitespace-pre-line">{{ $test->findings ?: '—' }}</p>
  </div>
</x-layouts.network>
