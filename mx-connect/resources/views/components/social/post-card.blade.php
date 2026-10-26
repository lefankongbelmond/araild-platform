@props(['post', 'viewer' => null, 'liked' => false, 'roles' => []])
@php
    $org = $post->organization;
    $role = $org ? ($roles[$org->id] ?? null) : null;
    $isAuthor = $viewer && (int) $post->public_account_id === (int) $viewer->id;
    $canRemove = $isAuthor || in_array($role, ['owner', 'admin'], true);
    $type = $post->type->value;
    $media = $post->mediaItems();
    $permalink = route('posts.show', $post);
    $showEngage = $post->offersEngagement() && ! $role;
    $showSupport = $post->offersSupport() && ! $role;
    $showProject = $post->project_id !== null;
    $typeBadge = [
        'event' => 'bg-sky-light text-sky',
        'opportunity' => 'bg-sun-light text-sun',
        'result' => 'bg-brand-light text-brand-dark',
    ][$type] ?? null;
@endphp
<article {{ $attributes->merge(['class' => 'card overflow-hidden']) }}>
    <div class="flex items-start gap-3 p-4 sm:p-5">
        @if ($org)
            <a href="{{ route('organizations.show', $org) }}"><x-social.avatar :organization="$org" /></a>
        @else
            <x-social.avatar :account="$post->author" />
        @endif
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-1.5">
                @if ($org)
                    <a href="{{ route('organizations.show', $org) }}" class="truncate font-semibold text-navy hover:underline">{{ $org->name }}</a>
                    @if ($org->isVerified())
                        <x-social.icon name="verified" class="h-4 w-4 text-sky" title="{{ __('mxconnect.social.common.verified') }}" />
                    @endif
                @else
                    <span class="truncate font-semibold text-navy">{{ $post->author?->displayName() }}</span>
                @endif
            </div>
            <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-ink/50">
                @if ($org)<span>{{ __('mxconnect.social.org_types.'.$org->type->value) }}</span>@endif
                @if ($org?->location())<span>· {{ $org->location() }}</span>@endif
                <a href="{{ $permalink }}" class="hover:underline">@if ($org)· @endif<time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->diffForHumans() }}</time></a>
                @if ($post->visibility->value === 'followers')<span>· {{ __('mxconnect.social.feed.followers_only') }}</span>@endif
            </p>
        </div>
        @if ($typeBadge)
            <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $typeBadge }}">
                {{ $type === 'opportunity' && $post->opportunity_kind ? __('mxconnect.social.opportunity_kinds.'.$post->opportunity_kind) : __('mxconnect.social.post_types.'.$type) }}
            </span>
        @endif
        @if ($canRemove)
            <details class="relative shrink-0">
                <summary class="cursor-pointer rounded-lg p-1.5 text-ink/40 hover:bg-paper hover:text-ink" aria-label="Options"><x-social.icon name="more" /></summary>
                <form method="POST" action="{{ route('posts.destroy', $post) }}" class="card absolute right-0 z-10 mt-1 w-48 p-1" onsubmit="return confirm(@js(__('mxconnect.social.actions.confirm_delete')))">
                    @csrf @method('DELETE')
                    <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-clay hover:bg-paper"><x-social.icon name="trash" class="h-4 w-4" />{{ __('mxconnect.social.actions.delete') }}</button>
                </form>
            </details>
        @endif
    </div>

    @if ($post->body)
        <div class="px-4 pb-3 text-[15px] leading-relaxed text-ink/85 sm:px-5">{{ \App\Support\PostFormatter::render($post->body) }}</div>
    @endif

    @if ($type === 'event' && $post->event_starts_at)
        <div class="mx-4 mb-3 flex items-center gap-4 rounded-xl bg-paper p-3 sm:mx-5">
            <div class="flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-xl bg-white text-navy shadow-sm">
                <span class="text-xl font-extrabold leading-none">{{ $post->event_starts_at->format('d') }}</span>
                <span class="text-[10px] font-bold uppercase">{{ $post->event_starts_at->translatedFormat('M') }}</span>
            </div>
            <div class="min-w-0 text-sm">
                <p class="flex items-center gap-1.5 font-medium text-navy"><x-social.icon name="clock" class="h-4 w-4 text-brand" />{{ $post->event_starts_at->translatedFormat('l j F Y, H:i') }}</p>
                <p class="mt-1 flex items-center gap-1.5 text-ink/60">
                    <x-social.icon name="pin" class="h-4 w-4 text-brand" />
                    {{ collect([$post->event_location, $post->event_online ? __('mxconnect.social.feed.online') : null])->filter()->join(' · ') ?: __('mxconnect.social.feed.in_person') }}
                </p>
            </div>
        </div>
    @endif

    @if ($org && $org->domains)
        <div class="flex flex-wrap gap-1.5 px-4 pb-3 sm:px-5">
            @foreach (array_slice($org->domains, 0, 3) as $domain)
                <a href="{{ route('discover', ['domaine' => $domain]) }}" class="rounded-full bg-brand-light px-2.5 py-1 text-[11px] font-semibold text-brand-dark hover:bg-brand/20">{{ __('mxconnect.social.domains.'.$domain) }}</a>
            @endforeach
        </div>
    @endif

    @if ($media)
        @php($count = count($media))
        <div class="grid gap-1 {{ $count === 1 ? 'grid-cols-1' : 'grid-cols-2' }}">
            @foreach ($media as $i => $item)
                <div class="relative overflow-hidden bg-paper {{ $count === 3 && $i === 0 ? 'row-span-2' : '' }}">
                    @if ($item['type'] === 'video')
                        <video src="{{ $item['url'] }}" controls preload="metadata" class="h-full max-h-[480px] w-full bg-black object-contain"></video>
                    @else
                        <img src="{{ $item['url'] }}" alt="" loading="lazy" class="h-full max-h-[480px] w-full object-cover {{ $count > 1 ? 'aspect-[4/3]' : '' }}">
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($post->project)
        <a href="{{ route('projects.show', $post->project) }}" class="mx-4 mt-3 flex items-center gap-3 rounded-xl border border-ink/10 p-3 hover:border-brand sm:mx-5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-light text-sky"><x-social.icon name="folder" class="h-[18px] w-[18px]" /></span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-navy">{{ $post->project->title }}</span>
                <span class="block text-xs text-ink/50">{{ __('mxconnect.social.project_status.'.$post->project->status->value) }} · {{ $post->project->progress_percent }} %</span>
            </span>
            <x-social.icon name="arrow-right" class="h-4 w-4 text-ink/40" />
        </a>
    @endif

    <div class="flex items-center justify-between px-4 pt-3 text-xs text-ink/50 sm:px-5">
        <span class="flex items-center gap-1.5">
            @if ($post->likes_count > 0)
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-clay text-white"><x-social.icon name="heart" class="h-3 w-3" /></span>
                {{ $post->likes_count }}
            @endif
        </span>
        <a href="{{ $permalink }}#commentaires" class="hover:underline">{{ $post->comments_count }} {{ __('mxconnect.social.feed.comments_count') }}</a>
    </div>

    <div class="mt-2 grid grid-cols-3 border-t border-ink/5 px-2 text-sm font-medium text-ink/60">
        <form method="POST" action="{{ route('posts.like', $post) }}">
            @csrf
            <button class="flex w-full items-center justify-center gap-2 rounded-lg py-2.5 hover:bg-paper {{ $liked ? 'text-clay' : '' }}" aria-pressed="{{ $liked ? 'true' : 'false' }}">
                <x-social.icon name="heart" class="h-[18px] w-[18px] {{ $liked ? 'fill-current' : '' }}" />{{ __('mxconnect.social.actions.like') }}
            </button>
        </form>
        <a href="{{ $permalink }}#commentaires" class="flex items-center justify-center gap-2 rounded-lg py-2.5 hover:bg-paper"><x-social.icon name="message" class="h-[18px] w-[18px]" />{{ __('mxconnect.social.actions.comment') }}</a>
        <button type="button" data-share="{{ $permalink }}" data-copied="{{ __('mxconnect.social.actions.link_copied') }}" class="flex items-center justify-center gap-2 rounded-lg py-2.5 hover:bg-paper">
            <x-social.icon name="share" class="h-[18px] w-[18px]" /><span data-share-label>{{ __('mxconnect.social.actions.share') }}</span>
        </button>
    </div>

    @if ($showEngage || $showProject || $showSupport)
        <div class="grid gap-2 border-t border-ink/5 p-3 sm:grid-cols-3">
            @if ($showEngage)
                <a href="{{ route('organizations.engage.form', [$org, 'publication' => $post->id]) }}" class="flex items-center gap-2.5 rounded-xl bg-brand-light px-3 py-2.5 text-brand-dark transition hover:bg-brand/20">
                    <x-social.icon name="hand" class="h-5 w-5 shrink-0" />
                    <span class="leading-tight"><span class="block text-sm font-semibold">{{ __('mxconnect.social.actions.engage') }}</span><span class="block text-[11px] opacity-75">{{ __('mxconnect.social.actions.engage_sub') }}</span></span>
                </a>
            @endif
            @if ($showProject)
                <a href="{{ route('projects.show', $post->project) }}" class="flex items-center gap-2.5 rounded-xl bg-sky-light px-3 py-2.5 text-sky transition hover:bg-sky/20">
                    <x-social.icon name="chart" class="h-5 w-5 shrink-0" />
                    <span class="leading-tight"><span class="block text-sm font-semibold">{{ __('mxconnect.social.actions.view_project') }}</span><span class="block text-[11px] opacity-75">{{ __('mxconnect.social.actions.view_project_sub') }}</span></span>
                </a>
            @endif
            @if ($showSupport)
                <a href="{{ route('organizations.support.form', [$org, 'publication' => $post->id]) }}" class="flex items-center gap-2.5 rounded-xl bg-sun-light px-3 py-2.5 text-sun transition hover:bg-sun/20">
                    <x-social.icon name="gift" class="h-5 w-5 shrink-0" />
                    <span class="leading-tight"><span class="block text-sm font-semibold">{{ __('mxconnect.social.actions.support') }}</span><span class="block text-[11px] opacity-75">{{ __('mxconnect.social.actions.support_sub') }}</span></span>
                </a>
            @endif
        </div>
    @endif
</article>
