<x-layouts.tenant :title="__('mxconnect.subscription.title')">
  <a href="{{ route('subscriptions.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.back') }}</a>

  @php $s = $subscription; $badge = ['captured'=>'clay','validated'=>'pine','terminated'=>'ink/40'][$s->status->value] ?? 'ink/40'; @endphp
  <div class="mt-2 flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">{{ $s->member->last_name }} {{ $s->member->first_name }}</h1>
      <p class="mt-1 text-sm text-ink/50">{{ $s->guaranteeVersion->guarantee->name }} · V{{ $s->guaranteeVersion->version_no }}</p>
    </div>
    <span class="rounded-full border border-{{ $badge }}/30 px-3 py-1 text-sm text-{{ $badge }}">{{ __('mxconnect.subscription.status_'.$s->status->value) }}</span>
  </div>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
      <div class="rounded-lg border border-ink/10 bg-white p-4">
        <dl class="grid grid-cols-2 gap-3 text-sm">
          <div><dt class="text-ink/50">{{ __('mxconnect.subscription.contribution') }}</dt><dd class="font-medium">{{ $currency->format($s->contribution_minor) }} / {{ $s->guaranteeVersion->periodicity }}</dd></div>
          <div><dt class="text-ink/50">{{ __('mxconnect.subscription.effective_date') }}</dt><dd>{{ $s->effective_date?->format('d/m/Y') }}</dd></div>
          <div><dt class="text-ink/50">{{ __('mxconnect.subscription.observation_end') }}</dt><dd>{{ $s->observation_ends_on?->format('d/m/Y') }}</dd></div>
          <div><dt class="text-ink/50">{{ __('mxconnect.subscription.beneficiaries') }}</dt><dd>{{ $s->member->beneficiaryCount() }}</dd></div>
        </dl>
      </div>

      @if ($s->status->value === 'validated')
      <div class="rounded-lg border border-ink/10 bg-white p-4">
        <div class="flex items-center justify-between">
          <h2 class="font-medium">{{ __('mxconnect.subscription.schedule') }}</h2>
          <a href="{{ route('members.card', $s->member) }}" target="_blank" class="text-sm text-pine hover:underline">{{ __('mxconnect.card.view') }}</a>
        </div>
        <table class="mt-3 w-full text-sm">
          <thead class="text-left text-ink/50"><tr><th class="py-1">{{ __('mxconnect.contribution.period') }}</th><th class="py-1">{{ __('mxconnect.contribution.due') }}</th><th class="py-1">{{ __('mxconnect.contribution.amount') }}</th><th class="py-1">{{ __('mxconnect.status') }}</th></tr></thead>
          <tbody class="divide-y divide-ink/5">
            @foreach ($s->schedules as $sch)
              @php $sb = ['to_pay'=>'ink/50','paid'=>'pine','overdue'=>'clay'][$sch->status] ?? 'ink/50'; @endphp
              <tr><td class="py-1">{{ $sch->period }}</td><td class="py-1">{{ $sch->due_date?->format('d/m/Y') }}</td><td class="py-1">{{ $currency->format($sch->due_minor) }}</td><td class="py-1 text-{{ $sb }}">{{ __('mxconnect.contribution.status_'.$sch->status) }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @endif
    </div>

    {{-- Checker action: validate (maker-checker) --}}
    @if ($s->status->value === 'captured')
    <div class="rounded-lg border border-clay/30 bg-clay/5 p-4 self-start">
      <h2 class="font-medium text-ink">{{ __('mxconnect.subscription.pending_validation') }}</h2>
      <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.subscription.validation_help') }}</p>
      @can('subscription.validate')
        <form method="POST" action="{{ route('subscriptions.validate', $s) }}" class="mt-3">
          @csrf
          <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.subscription.validate_action') }}</button>
        </form>
        <p class="mt-2 text-xs text-ink/40">{{ __('mxconnect.subscription.self_validate_note') }}</p>
      @else
        <p class="mt-3 text-xs text-ink/40">{{ __('mxconnect.subscription.checker_only') }}</p>
      @endcan
    </div>
    @endif
  </div>
</x-layouts.tenant>
