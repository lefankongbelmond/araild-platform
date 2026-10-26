@props(['label', 'value', 'icon' => 'chart', 'tone' => 'brand', 'hint' => null])
@php
  $g = ['brand' => 'from-brand-500 to-brand-700', 'leaf' => 'from-leaf-500 to-leaf-700', 'violet' => 'from-violet-500 to-violet-700',
        'amber' => 'from-amber-500 to-orange-600', 'red' => 'from-rose-500 to-red-700', 'teal' => 'from-teal-500 to-cyan-700'][$tone] ?? 'from-brand-500 to-brand-700';
@endphp
<div {{ $attributes->merge(['class' => 'flex items-center gap-3 rounded-2xl bg-white p-4 shadow-soft ring-1 ring-ink/[.05]']) }}>
  <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br {{ $g }} text-white"><x-tenant.icon :name="$icon" :size="20" /></span>
  <div class="min-w-0">
    <div class="text-xl font-extrabold leading-tight text-[#0B1F3A] tabular-nums">{{ $value }}</div>
    <div class="mt-0.5 text-xs font-medium leading-tight text-ink/50">{{ $label }}</div>
    @if ($hint)<div class="mt-0.5 text-[11px] text-ink/40">{{ $hint }}</div>@endif
  </div>
</div>
