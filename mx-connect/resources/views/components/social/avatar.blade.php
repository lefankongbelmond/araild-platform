@props(['organization' => null, 'account' => null, 'size' => 'h-11 w-11', 'text' => 'text-sm'])
@php
    $url = $organization?->logoUrl();
    $initials = $organization ? $organization->initials() : ($account?->initials() ?? '?');
    $label = $organization?->name ?? $account?->displayName() ?? '';
    $shape = $organization ? 'rounded-xl' : 'rounded-full';
    $palette = $organization ? 'bg-gradient-to-br from-navy to-brand text-white' : 'bg-brand-light text-brand-dark';
@endphp
@if ($url)
    <img src="{{ $url }}" alt="{{ $label }}" {{ $attributes->merge(['class' => "$size $shape shrink-0 object-cover ring-1 ring-ink/10 bg-white"]) }}>
@else
    <span aria-hidden="true" {{ $attributes->merge(['class' => "$size $shape $palette $text shrink-0 inline-flex items-center justify-center font-bold"]) }}>{{ $initials }}</span>
@endif
