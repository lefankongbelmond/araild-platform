<x-layouts.tenant :title="__('mxconnect.import.title')">
  <a href="{{ route('members.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.member.plural') }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('mxconnect.import.title') }}</h1>
  <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.import.sub') }}</p>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <form method="POST" action="{{ route('members.import') }}" enctype="multipart/form-data" class="rounded-lg border border-ink/10 bg-white p-5 space-y-3 self-start">
      @csrf
      <div>
        <label class="block text-sm font-medium">{{ __('mxconnect.import.file') }}</label>
        <input type="file" name="file" accept=".csv,text/csv" required class="mt-1 w-full text-sm">
        @error('file')<p class="mt-1 text-sm text-clay">{{ $message }}</p>@enderror
      </div>
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.import.run') }}</button>
      <a href="{{ route('members.import.template') }}" class="block text-center text-sm text-pine hover:underline">{{ __('mxconnect.import.download_template') }}</a>
      <p class="text-xs text-ink/50">{{ __('mxconnect.import.columns') }}: <span class="font-mono">{{ implode(', ', \App\Services\MemberImportService::COLUMNS) }}</span></p>
    </form>

    <div class="lg:col-span-2">
      @if ($report)
        <div class="rounded-lg border border-ink/10 bg-white p-4">
          <div class="flex gap-6 text-sm">
            <div><span class="text-2xl font-semibold text-sage">{{ $report['created'] }}</span><br>{{ __('mxconnect.import.created') }}</div>
            <div><span class="text-2xl font-semibold text-clay">{{ $report['skipped'] }}</span><br>{{ __('mxconnect.import.skipped') }}</div>
          </div>
        </div>
        <div class="mt-4 overflow-hidden rounded-lg border border-ink/10 bg-white">
          <table class="w-full text-sm">
            <thead class="bg-mist text-left text-ink/60"><tr>
              <th class="px-4 py-2 font-medium">{{ __('mxconnect.import.line') }}</th>
              <th class="px-4 py-2 font-medium">{{ __('mxconnect.import.result') }}</th>
              <th class="px-4 py-2 font-medium">{{ __('mxconnect.import.detail') }}</th>
            </tr></thead>
            <tbody class="divide-y divide-ink/5">
              @foreach ($report['rows'] as $r)
                <tr>
                  <td class="px-4 py-2 font-mono">{{ $r['line'] }}</td>
                  <td class="px-4 py-2">
                    @php $tone = ['created'=>'bg-sage/15 text-sage','created_warn'=>'bg-clay/15 text-clay','duplicate'=>'bg-clay/15 text-clay','invalid'=>'bg-ink/10 text-ink'][$r['display']] ?? 'bg-mist'; @endphp
                    <span class="rounded-full px-2 py-0.5 text-xs {{ $tone }}">{{ __('mxconnect.import.st_'.$r['display']) }}</span>
                    @if ($r['code'])<span class="ml-1 font-mono text-xs text-ink/50">{{ $r['code'] }}</span>@endif
                  </td>
                  <td class="px-4 py-2 text-ink/60">{{ $r['message'] }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="rounded-lg border border-dashed border-ink/20 p-10 text-center text-ink/40">{{ __('mxconnect.import.no_report') }}</div>
      @endif
    </div>
  </div>
</x-layouts.tenant>
