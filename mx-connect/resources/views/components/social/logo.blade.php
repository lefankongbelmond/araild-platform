@props(['tagline' => true])
{{-- Brand lockup drawn in SVG from the MX-CONNECT logo: navy→green orbit, green M, navy X. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <svg class="h-10 w-10 shrink-0" viewBox="0 0 48 48" aria-hidden="true">
        <defs>
            <linearGradient id="mx-orbit" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#2E9E4F"/>
                <stop offset="1" stop-color="#0B2559"/>
            </linearGradient>
        </defs>
        <path d="M40 15A19 19 0 1 0 43 26" fill="none" stroke="url(#mx-orbit)" stroke-width="3.2" stroke-linecap="round"/>
        <circle cx="38.5" cy="9.5" r="3" fill="#2E9E4F"/>
        <path d="M13 34V15l7 10" fill="none" stroke="#2E9E4F" stroke-width="4.2" stroke-linejoin="round" stroke-linecap="round"/>
        <path d="M20 34l13-19M24 22l9 12" fill="none" stroke="#0B2559" stroke-width="4.2" stroke-linecap="round"/>
    </svg>
    <span class="leading-none">
        <span class="block text-[1.15rem] font-extrabold tracking-tight text-navy">M<span class="text-brand">X</span>-CONNECT</span>
        @if ($tagline)
            <span class="mt-1 block text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-brand-dark">{{ __('mxconnect.social.tagline') }}</span>
        @endif
    </span>
</span>
