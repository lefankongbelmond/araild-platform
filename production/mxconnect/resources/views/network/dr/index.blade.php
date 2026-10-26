<x-layouts.network :title="__('mxconnect.dr.title')">
  <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.dr.title') }}</h1>
  <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.dr.sub') }}</p>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 overflow-hidden rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.dr.ref') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.dr.drill') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.dr.date') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.dr.verdict') }}</th>
        </tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($tests as $t)
            @php $v = $assessment->verdict($t);
              $tone = ['clean'=>'bg-sage/15 text-sage','degraded'=>'bg-clay/15 text-clay','failed'=>'bg-clay/25 text-clay','incomplete'=>'bg-mist text-ink'][$v]; @endphp
            <tr>
              <td class="px-4 py-2 font-mono text-xs"><a href="{{ route('network.dr.show',$t) }}" class="text-pine hover:underline">{{ $t->ref }}</a></td>
              <td class="px-4 py-2">{{ __('mxconnect.dr.type_'.$t->type) }}<div class="text-xs text-ink/40">{{ $t->scope }}</div></td>
              <td class="px-4 py-2 text-ink/60">{{ $t->performed_on?->format('d/m/Y') }}</td>
              <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs {{ $tone }}">{{ __('mxconnect.dr.v_'.$v) }}</span></td>
            </tr>
          @empty
            <tr><td colspan="4" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.dr.empty') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <form method="POST" action="{{ route('network.dr.store') }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3 self-start">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.dr.new') }}</h2>
      <select name="type" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @foreach (['backup_restore','failover','full_dr','tabletop'] as $ty)<option value="{{ $ty }}">{{ __('mxconnect.dr.type_'.$ty) }}</option>@endforeach
      </select>
      <input name="scope" placeholder="{{ __('mxconnect.dr.scope') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <label class="block text-xs text-ink/60">{{ __('mxconnect.dr.date') }}<input name="performed_on" type="date" value="{{ date('Y-m-d') }}" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm"></label>
      <div class="flex gap-2">
        <label class="flex-1 text-xs text-ink/60">RTO {{ __('mxconnect.dr.target') }}<input name="rto_target_minutes" type="number" min="0" value="{{ $defaults['default_rto_minutes'] }}" class="mt-1 w-full rounded-md border-ink/20 px-2 py-2 text-sm"></label>
        <label class="flex-1 text-xs text-ink/60">RTO {{ __('mxconnect.dr.actual') }}<input name="rto_actual_minutes" type="number" min="0" class="mt-1 w-full rounded-md border-ink/20 px-2 py-2 text-sm"></label>
      </div>
      <div class="flex gap-2">
        <label class="flex-1 text-xs text-ink/60">RPO {{ __('mxconnect.dr.target') }}<input name="rpo_target_minutes" type="number" min="0" value="{{ $defaults['default_rpo_minutes'] }}" class="mt-1 w-full rounded-md border-ink/20 px-2 py-2 text-sm"></label>
        <label class="flex-1 text-xs text-ink/60">RPO {{ __('mxconnect.dr.actual') }}<input name="rpo_actual_minutes" type="number" min="0" class="mt-1 w-full rounded-md border-ink/20 px-2 py-2 text-sm"></label>
      </div>
      <select name="outcome" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @foreach (['success','partial','failed'] as $o)<option value="{{ $o }}">{{ __('mxconnect.dr.o_'.$o) }}</option>@endforeach
      </select>
      <input name="performed_by" placeholder="{{ __('mxconnect.dr.by') }}" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <textarea name="findings" placeholder="{{ __('mxconnect.dr.findings') }}" rows="2" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm"></textarea>
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.dr.add') }}</button>
    </form>
  </div>
  <div class="mt-4">{{ $tests->links() }}</div>
</x-layouts.network>
