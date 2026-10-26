@props(['status'])
@php
    $value = $status instanceof \BackedEnum ? $status->value : $status;
    $style = [
        'envoyee' => 'bg-sky-light text-sky',
        'en_cours' => 'bg-sun-light text-sun',
        'acceptee' => 'bg-brand-light text-brand-dark',
        'refusee' => 'bg-clay/10 text-clay',
        'cloturee' => 'bg-paper text-ink/50',
    ][$value] ?? 'bg-paper text-ink/50';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-[11px] font-semibold $style"]) }}>{{ __('mxconnect.social.request_status.'.$value) }}</span>
