@props(['title', 'href' => null, 'icon' => null])
<div {{ $attributes->merge(['class' => 'flex items-center justify-between gap-3']) }}>
    <h2 class="flex items-center gap-2 text-base font-bold text-navy">
        @if ($icon)<x-social.icon :name="$icon" class="h-5 w-5 text-brand" />@endif
        {{ $title }}
    </h2>
    @if ($href)
        <a href="{{ $href }}" class="inline-flex items-center gap-1 text-xs font-semibold text-pine hover:underline">
            {{ __('mxconnect.social.common.see_all') }} <x-social.icon name="arrow-right" class="h-3.5 w-3.5" />
        </a>
    @endif
</div>
