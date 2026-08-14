<x-layouts.tenant :title="__('mxconnect.risk.title')">
  <h1 class="text-2xl font-semibold tracking-tight">{{ __('mxconnect.risk.title') }}</h1>
  <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.risk.sub') }}</p>

  <div class="mt-4 flex gap-2 text-sm">
    <a href="{{ route('risks.index') }}" class="rounded-md px-3 py-1.5 {{ !$status ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ __('mxconnect.all') }}</a>
    @foreach (['open','mitigating','closed'] as $st)
      <a href="{{ route('risks.index', ['status'=>$st]) }}" class="rounded-md px-3 py-1.5 {{ $status===$st ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ __('mxconnect.risk.st_'.$st) }}</a>
    @endforeach
  </div>

  <div class="mt-4 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 overflow-hidden rounded-lg border border-ink/10 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-mist text-left text-ink/60"><tr>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.risk.ref') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.risk.risk') }}</th>
          <th class="px-4 py-2 font-medium text-center">{{ __('mxconnect.risk.score') }}</th>
          <th class="px-4 py-2 font-medium">{{ __('mxconnect.status') }}</th>
        </tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($risks as $r)
            @php $band = $scoring->band($r->score()); @endphp
            <tr>
              <td class="px-4 py-2 font-mono text-xs"><a href="{{ route('risks.show',$r) }}" class="text-pine hover:underline">{{ $r->ref }}</a></td>
              <td class="px-4 py-2">{{ $r->title }}<div class="text-xs text-ink/40">{{ __('mxconnect.risk.cat_'.$r->category) }}</div></td>
              <td class="px-4 py-2 text-center"><span class="rounded-full px-2 py-0.5 text-xs {{ $scoring->tone($band) }}">{{ $r->score() }} · {{ __('mxconnect.risk.band_'.$band) }}</span></td>
              <td class="px-4 py-2 text-ink/60">{{ __('mxconnect.risk.st_'.$r->status) }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="px-4 py-10 text-center text-ink/40">{{ __('mxconnect.risk.empty') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <form method="POST" action="{{ route('risks.store') }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3 self-start">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.risk.new') }}</h2>
      <input name="title" placeholder="{{ __('mxconnect.risk.field_title') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <textarea name="description" placeholder="{{ __('mxconnect.risk.field_desc') }}" rows="2" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm"></textarea>
      <select name="category" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        @foreach (['strategic','operational','financial','compliance','security','other'] as $c)<option value="{{ $c }}">{{ __('mxconnect.risk.cat_'.$c) }}</option>@endforeach
      </select>
      <div class="flex gap-2">
        <label class="flex-1 text-xs text-ink/60">{{ __('mxconnect.risk.likelihood') }}
          <select name="likelihood" class="mt-1 w-full rounded-md border-ink/20 px-2 py-2 text-sm">@for($i=1;$i<=5;$i++)<option value="{{ $i }}">{{ $i }}</option>@endfor</select></label>
        <label class="flex-1 text-xs text-ink/60">{{ __('mxconnect.risk.impact') }}
          <select name="impact" class="mt-1 w-full rounded-md border-ink/20 px-2 py-2 text-sm">@for($i=1;$i<=5;$i++)<option value="{{ $i }}">{{ $i }}</option>@endfor</select></label>
      </div>
      <input name="owner_role" placeholder="{{ __('mxconnect.risk.owner') }}" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <input name="review_on" type="date" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.risk.add') }}</button>
    </form>
  </div>
  <div class="mt-4">{{ $risks->links() }}</div>
</x-layouts.tenant>
