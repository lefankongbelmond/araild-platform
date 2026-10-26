@props(['title' => null, 'icon' => null])
<section {{ $attributes->merge(['class' => 'rounded-3xl bg-white shadow-soft ring-1 ring-ink/[.05]']) }}>
  @if ($title)
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5 sm:px-6">
      <h2 class="flex items-center gap-2.5 text-lg font-bold text-[#0B1F3A]">@if($icon)<span class="grid h-8 w-8 place-items-center rounded-full bg-leaf-500 text-white"><x-tenant.icon :name="$icon" :size="16"/></span>@endif {{ $title }}</h2>
      @isset($aside)<div class="flex flex-wrap items-center gap-2 text-sm">{{ $aside }}</div>@endisset
    </div>
  @endif
  {{ $slot }}
</section>
