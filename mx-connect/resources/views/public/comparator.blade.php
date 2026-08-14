<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ __('mxconnect.comparator.title') }} — MX-CONNECT</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{ink:'#0B1F3A',pine:'#123C78',navy:'#0B2559',sage:'#2FAE53',green:'#3AA655',clay:'#C0392B',mist:'#EAF6EE',paper:'#F7FAF8'}}}}</script>
</head>
<body class="bg-paper text-ink">
  <header class="border-b border-ink/10 bg-white">
    <div class="mx-auto max-w-6xl px-5 h-16 flex items-center gap-2">
      <a href="/" class="flex items-center gap-2"><span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-navy to-sage text-white text-xs font-bold">MX</span><span class="font-semibold text-lg">MX-<span class="text-sage">CONNECT</span></span></a>
      <span class="ml-2 text-ink/50">{{ __('mxconnect.comparator.title') }}</span>
      <a href="{{ route('directory') }}" class="ml-auto text-sm text-pine hover:underline">{{ __('mxconnect.directory.title') }} →</a>
    </div>
  </header>

  <main class="mx-auto max-w-6xl px-5 py-10">
    <h1 class="text-3xl font-semibold tracking-tight">{{ __('mxconnect.comparator.heading') }}</h1>
    <p class="mt-2 text-ink/60">{{ __('mxconnect.comparator.sub') }}</p>

    <form method="GET" class="mt-6">
      <select name="country" onchange="this.form.submit()" class="rounded-md border-ink/20 bg-white px-3 py-2 text-sm">
        <option value="">{{ __('mxconnect.directory.all_countries') }}</option>
        @foreach ($countries as $c)<option value="{{ $c->id }}" @selected($countryId==$c->id)>{{ $c->name }}</option>@endforeach
      </select>
    </form>

    @if ($offers->isEmpty())
      <p class="mt-10 text-ink/40">{{ __('mxconnect.comparator.empty') }}</p>
    @else
      {{-- Comparison table --}}
      <div class="mt-6 overflow-x-auto rounded-lg border border-ink/10 bg-white">
        <table class="w-full text-sm">
          <thead class="bg-mist text-left text-ink/60"><tr>
            <th class="px-4 py-3 font-medium">{{ __('mxconnect.comparator.mutual') }}</th>
            <th class="px-4 py-3 font-medium text-right">{{ __('mxconnect.comparator.monthly') }}</th>
            <th class="px-4 py-3 font-medium text-right">{{ __('mxconnect.comparator.coverage') }}</th>
            <th class="px-4 py-3 font-medium text-right">{{ __('mxconnect.comparator.membership') }}</th>
            <th class="px-4 py-3 font-medium text-right">{{ __('mxconnect.comparator.guarantees') }}</th>
          </tr></thead>
          <tbody class="divide-y divide-ink/5">
            @foreach ($offers as $o)
              @php $cur = $o->currency; @endphp
              <tr>
                <td class="px-4 py-3">
                  <div class="font-medium">{{ $o->mutual->name }}</div>
                  <div class="text-xs text-ink/40">{{ $o->mutual->country?->name }}</div>
                </td>
                <td class="px-4 py-3 text-right">
                  @if ($o->monthly_contribution_min_minor !== null)
                    {{ $cur ? $cur->format($o->monthly_contribution_min_minor) : $o->monthly_contribution_min_minor }}
                    @if ($o->monthly_contribution_max_minor > $o->monthly_contribution_min_minor)
                      – {{ $cur ? $cur->format($o->monthly_contribution_max_minor) : $o->monthly_contribution_max_minor }}
                    @endif
                  @else — @endif
                </td>
                <td class="px-4 py-3 text-right">{{ $o->coverage_min !== null ? rtrim(rtrim(number_format($o->coverage_min,2),'0'),'.').'–'.rtrim(rtrim(number_format($o->coverage_max,2),'0'),'.').'%' : '—' }}</td>
                <td class="px-4 py-3 text-right">{{ $o->membership_fee_min_minor !== null && $cur ? $cur->format($o->membership_fee_min_minor) : '—' }}</td>
                <td class="px-4 py-3 text-right">{{ $o->guarantees_count }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      {{-- Offer cards with highlights --}}
      <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($offers as $o)
          <div class="rounded-xl border border-ink/10 bg-white p-5">
            <div class="text-lg font-semibold">{{ $o->mutual->name }}</div>
            <div class="mt-1 text-sm text-ink/50">{{ $o->mutual->country?->name }}</div>
            <div class="mt-3 text-2xl font-semibold text-pine">
              {{ $o->currency && $o->monthly_contribution_min_minor !== null ? $o->currency->format($o->monthly_contribution_min_minor) : '—' }}
              <span class="text-sm font-normal text-ink/50">/{{ __('mxconnect.comparator.month') }}</span>
            </div>
            @if ($o->highlights)
              <ul class="mt-3 space-y-1 text-sm text-ink/70">
                @foreach ($o->highlights as $h)<li class="flex gap-2"><span class="text-sage">✓</span>{{ $h }}</li>@endforeach
              </ul>
            @endif
          </div>
        @endforeach
      </div>
      <p class="mt-6 text-xs text-ink/40">{{ __('mxconnect.comparator.note') }}</p>
    @endif
  </main>

  <footer class="border-t border-ink/10 py-6 text-center text-xs text-ink/40">MX-CONNECT</footer>
</body>
</html>
