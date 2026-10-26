@props(['icon' => 'star', 'message'])
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-ink/15 bg-white/60 px-6 py-10 text-center']) }}>
    <span class="mx-auto inline-flex h-11 w-11 items-center justify-center rounded-full bg-mist text-brand">
        <x-social.icon :name="$icon" />
    </span>
    <p class="mt-3 text-sm text-ink/60">{{ $message }}</p>
    {{ $slot }}
</div>
