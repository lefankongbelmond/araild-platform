@props(['organization', 'compact' => false])
@php($org = $organization)
@if ($compact)
    <a href="{{ route('organizations.show', $org) }}" {{ $attributes->merge(['class' => 'flex items-center gap-3 rounded-xl p-2 transition hover:bg-paper']) }}>
        <x-social.avatar :organization="$org" size="h-10 w-10" />
        <span class="min-w-0 flex-1">
            <span class="flex items-center gap-1 truncate text-sm font-semibold text-navy">
                {{ $org->name }}
                @if ($org->isVerified())<x-social.icon name="verified" class="h-3.5 w-3.5 shrink-0 text-sky" />@endif
            </span>
            <span class="block truncate text-xs text-ink/50">{{ __('mxconnect.social.org_types.'.$org->type->value) }}@if ($org->location()) · {{ $org->location() }}@endif</span>
        </span>
        <x-social.icon name="arrow-right" class="h-4 w-4 shrink-0 text-ink/30" />
    </a>
@else
    <a href="{{ route('organizations.show', $org) }}" {{ $attributes->merge(['class' => 'card group flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-lg']) }}>
        <div class="relative h-24 bg-gradient-to-br from-navy to-brand">
            @if ($org->coverUrl())
                <img src="{{ $org->coverUrl() }}" alt="" loading="lazy" class="h-full w-full object-cover">
            @endif
            <x-social.avatar :organization="$org" size="h-14 w-14" text="text-base" class="absolute -bottom-6 left-4 ring-4 ring-white" />
        </div>
        <div class="flex flex-1 flex-col p-4 pt-8">
            <p class="flex items-center gap-1 font-semibold text-navy group-hover:underline">
                <span class="truncate">{{ $org->name }}</span>
                @if ($org->isVerified())<x-social.icon name="verified" class="h-4 w-4 shrink-0 text-sky" />@endif
            </p>
            <p class="text-xs text-ink/50">{{ __('mxconnect.social.org_types.'.$org->type->value) }}</p>
            @if ($org->tagline)<p class="mt-2 line-clamp-2 text-sm text-ink/70">{{ $org->tagline }}</p>@endif
            <div class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 pt-3 text-xs text-ink/50">
                @if ($org->location())<span class="flex items-center gap-1"><x-social.icon name="pin" class="h-3.5 w-3.5" />{{ $org->location() }}</span>@endif
                <span class="flex items-center gap-1"><x-social.icon name="users" class="h-3.5 w-3.5" />{{ $org->followers_count }} {{ __('mxconnect.social.common.followers') }}</span>
            </div>
            @if ($org->domains)
                <div class="mt-3 flex flex-wrap gap-1">
                    @foreach (array_slice($org->domains, 0, 2) as $d)
                        <span class="rounded-full bg-brand-light px-2 py-0.5 text-[11px] font-semibold text-brand-dark">{{ __('mxconnect.social.domains.'.$d) }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </a>
@endif
