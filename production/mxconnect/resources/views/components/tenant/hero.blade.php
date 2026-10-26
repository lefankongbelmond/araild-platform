{{-- En-tête de page du back-office (même design que le module Médicaments). --}}
@props(['icon' => 'list', 'kicker' => null, 'tone' => 'leaf'])
@php
  $tones = [
    'leaf'   => 'from-leaf-500 to-leaf-700 shadow-leaf-500/30',
    'brand'  => 'from-brand-500 to-brand-700 shadow-brand-500/30',
    'violet' => 'from-violet-500 to-violet-700 shadow-violet-500/30',
    'amber'  => 'from-amber-500 to-orange-600 shadow-orange-500/30',
    'red'    => 'from-rose-500 to-red-700 shadow-red-500/30',
    'teal'   => 'from-teal-500 to-cyan-700 shadow-teal-500/30',
  ];
@endphp
<section {{ $attributes->merge(['class' => 'relative mb-1 overflow-hidden rounded-3xl bg-gradient-to-r from-white via-[#F2F8FF] to-[#E3EFFF] p-5 shadow-soft ring-1 ring-ink/[.05] sm:p-7']) }}>
  <svg class="pointer-events-none absolute -right-10 -top-10 hidden h-56 w-56 text-brand-500/[.07] md:block" viewBox="0 0 200 200" aria-hidden="true"><circle cx="100" cy="100" r="96" fill="currentColor"/><circle cx="150" cy="150" r="60" fill="currentColor"/></svg>
  <div class="relative flex flex-col gap-4 lg:flex-row lg:items-center">
    <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-gradient-to-br {{ $tones[$tone] ?? $tones['leaf'] }} text-white shadow-lg"><x-tenant.icon :name="$icon" :size="28" /></span>
    <div class="min-w-0 flex-1">
      @if ($kicker)<p class="text-[11.5px] font-semibold uppercase tracking-[.14em] text-brand-600">{{ $kicker }}</p>@endif
      <h1 class="mt-0.5 text-2xl font-extrabold tracking-tight text-[#0B1F3A] sm:text-[30px]">{{ $title }}</h1>
      @isset($subtitle)<p class="mt-1 max-w-3xl text-[14.5px] text-ink/60">{{ $subtitle }}</p>@endisset
    </div>
    @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
  </div>
</section>
