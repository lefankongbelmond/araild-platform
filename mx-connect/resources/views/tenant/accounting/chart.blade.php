<x-layouts.tenant :title="__('mxconnect.accounting.chart')">
  <a href="{{ route('treasury.dashboard') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.treasury.title') }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('mxconnect.accounting.chart') }}</h1>
  <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.accounting.chart_sub') }}</p>

  <div class="mt-6 space-y-4">
    @foreach ($accounts as $class => $lines)
      <div class="rounded-lg border border-ink/10 bg-white p-4">
        <h2 class="font-medium">{{ __('mxconnect.accounting.class') }} {{ $class }}</h2>
        <table class="mt-2 w-full text-sm">
          <tbody class="divide-y divide-ink/5">
            @foreach ($lines as $a)
              <tr><td class="py-1.5 font-mono w-24">{{ $a->number }}</td><td class="py-1.5">{{ $a->label }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endforeach
  </div>
</x-layouts.tenant>
