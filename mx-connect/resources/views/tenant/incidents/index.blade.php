<x-layouts.tenant :title="__('mxconnect.incident.title')">
  <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.incident.title') }}</h1>
  <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.incident.sub') }}</p>

  <div class="mt-4 flex gap-2 text-sm">
    <a href="{{ route('incidents.index') }}" class="rounded-md px-3 py-1.5 {{ !$status ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ __('mxconnect.all') }}</a>
    @foreach (['open','investigating','resolved','closed'] as $st)
      <a href="{{ route('incidents.index', ['status'=>$st]) }}" class="rounded-md px-3 py-1.5 {{ $status===$st ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ __('mxconnect.incident.st_'.$st) }}</a>
    @endforeach
  </div>

  <div class="mt-4 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 overflow-hidden rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.incident.ref') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.incident.incident') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.incident.severity') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.status') }}</th>
        </tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($incidents as $i)
            @php $tone = ['low'=>'bg-sage/15 text-sage','medium'=>'bg-mist text-ink','high'=>'bg-clay/15 text-clay','critical'=>'bg-clay/25 text-clay'][$i->severity]; @endphp
            <tr>
              <td class="px-4 py-2 font-mono text-xs"><a href="{{ route('incidents.show',$i) }}" class="text-pine hover:underline">{{ $i->ref }}</a></td>
              <td class="px-4 py-2">{{ $i->title }}<div class="text-xs text-ink/40">{{ __('mxconnect.incident.cat_'.$i->category) }} · {{ $i->occurred_on?->format('d/m/Y') }}</div></td>
              <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs {{ $tone }}">{{ __('mxconnect.incident.sev_'.$i->severity) }}</span></td>
              <td class="px-4 py-2 text-ink/60">{{ __('mxconnect.incident.st_'.$i->status) }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.incident.empty') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <form method="POST" action="{{ route('incidents.store') }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3 self-start">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.incident.new') }}</h2>
      <input name="title" placeholder="{{ __('mxconnect.incident.field_title') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <textarea name="description" placeholder="{{ __('mxconnect.incident.field_desc') }}" rows="2" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm"></textarea>
      <select name="category" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @foreach (['operational','security','fraud','data_breach','service','other'] as $c)<option value="{{ $c }}">{{ __('mxconnect.incident.cat_'.$c) }}</option>@endforeach
      </select>
      <select name="severity" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @foreach (['low','medium','high','critical'] as $sv)<option value="{{ $sv }}" @selected($sv==='medium')>{{ __('mxconnect.incident.sev_'.$sv) }}</option>@endforeach
      </select>
      <label class="block text-xs text-ink/60">{{ __('mxconnect.incident.occurred') }}<input name="occurred_on" type="date" value="{{ date('Y-m-d') }}" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm"></label>
      <label class="block text-xs text-ink/60">{{ __('mxconnect.incident.detected') }}<input name="detected_on" type="date" class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm"></label>
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.incident.add') }}</button>
    </form>
  </div>
  <div class="mt-4">{{ $incidents->links() }}</div>
</x-layouts.tenant>
