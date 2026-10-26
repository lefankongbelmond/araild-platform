@props(['project'])
@php
    $statusStyle = [
        'planifie' => 'bg-sky-light text-sky',
        'en_cours' => 'bg-sun-light text-sun',
        'termine' => 'bg-brand-light text-brand-dark',
    ][$project->status->value];
@endphp
<a href="{{ route('projects.show', $project) }}" {{ $attributes->merge(['class' => 'card group flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-lg']) }}>
    <div class="relative h-36 bg-gradient-to-br from-navy-700 to-brand">
        @if ($project->coverUrl())
            <img src="{{ $project->coverUrl() }}" alt="" loading="lazy" class="h-full w-full object-cover">
        @else
            <x-social.icon name="folder" class="absolute inset-0 m-auto h-10 w-10 text-white/40" />
        @endif
        <span class="absolute left-3 top-3 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusStyle }}">{{ __('mxconnect.social.project_status.'.$project->status->value) }}</span>
    </div>
    <div class="flex flex-1 flex-col p-4">
        <p class="line-clamp-2 font-semibold leading-snug text-navy group-hover:underline">{{ $project->title }}</p>
        @if ($project->summary)<p class="mt-1 line-clamp-2 text-sm text-ink/60">{{ $project->summary }}</p>@endif
        <div class="mt-auto pt-3">
            <div class="flex items-center gap-2 text-xs text-ink/50">
                <x-social.avatar :organization="$project->organization" size="h-6 w-6" text="text-[9px]" />
                <span class="truncate">{{ $project->organization->name }}</span>
            </div>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-paper">
                <div class="h-full rounded-full bg-brand" style="width: {{ $project->progress_percent }}%"></div>
            </div>
            <p class="mt-1 text-right text-[11px] font-medium text-ink/50">{{ $project->progress_percent }} %</p>
        </div>
    </div>
</a>
