<x-layouts.social>
    {{-- ============ HERO ============ --}}
    <section class="relative overflow-hidden rounded-3xl bg-navy-900 text-white">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_85%_20%,rgba(46,158,79,.45),transparent_45%),radial-gradient(circle_at_10%_110%,rgba(47,111,219,.35),transparent_50%)]"></div>
        <div class="relative p-6 sm:p-10">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-white/90 ring-1 ring-white/15">
                    <span class="h-1.5 w-1.5 rounded-full bg-brand"></span>{{ __('mxconnect.social.home.badge') }}
                </span>
                <h1 class="mt-5 text-3xl font-extrabold leading-[1.1] tracking-tight sm:text-4xl 2xl:text-5xl">
                    {{ __('mxconnect.social.home.title_1') }}
                    <span class="block text-[#5FD07F]">{{ __('mxconnect.social.home.title_2') }}</span>
                </h1>
                <p class="mt-4 max-w-xl text-sm leading-relaxed text-white/75 sm:text-base">{{ __('mxconnect.social.home.sub') }}</p>

                <form action="{{ route('discover') }}" method="GET" class="mt-6 flex max-w-xl gap-2 rounded-2xl bg-white p-1.5 shadow-lg">
                    <label class="relative flex-1">
                        <span class="sr-only">{{ __('mxconnect.social.common.search') }}</span>
                        <x-social.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink/40" />
                        <input type="search" name="q" placeholder="{{ __('mxconnect.social.discover.search_placeholder') }}" class="w-full rounded-xl py-2.5 pl-9 pr-2 text-sm text-ink placeholder:text-ink/40 focus:outline-none">
                    </label>
                    <button class="btn-primary px-3" aria-label="{{ __('mxconnect.social.common.search') }}"><x-social.icon name="search" class="h-4 w-4" /></button>
                </form>

                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('discover', ['section' => 'projets']) }}" class="btn-primary">{{ __('mxconnect.social.home.cta_explore') }} <x-social.icon name="arrow-right" class="h-4 w-4" /></a>
                    @if ($viewer)
                        <a href="{{ route('feed') }}#publier" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-navy hover:bg-white/90"><x-social.icon name="edit" class="h-4 w-4" />{{ __('mxconnect.social.home.cta_publish') }}</a>
                    @else
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-navy hover:bg-white/90">{{ __('mxconnect.social.home.cta_join') }}</a>
                    @endif
                </div>
            </div>

            <dl class="mt-8 grid grid-cols-2 gap-3 md:grid-cols-4">
                @foreach ([
                    ['organizations', 'building', 'text-sky'],
                    ['members', 'users', 'text-brand'],
                    ['projects', 'folder', 'text-sun'],
                    ['events', 'calendar', 'text-[#C084FC]'],
                ] as [$key, $icon, $color])
                    <div class="flex items-center gap-3 rounded-2xl bg-white/[.07] p-3 ring-1 ring-white/10">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white {{ $color }}"><x-social.icon :name="$icon" /></span>
                        <div>
                            <dd class="text-xl font-extrabold leading-none">{{ number_format($stats[$key], 0, ',', ' ') }}</dd>
                            <dt class="mt-1 text-xs text-white/60">{{ __('mxconnect.social.home.stats_'.$key) }}</dt>
                        </div>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- ============ DOMAINS ============ --}}
    <section class="mt-8">
        <x-social.section-title :title="__('mxconnect.social.home.domains_title')" />
        <div class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4 2xl:grid-cols-7">
            @foreach ($domains as $domain => $count)
                <a href="{{ route('discover', ['domaine' => $domain]) }}" class="card flex flex-col items-center gap-2 px-2 py-4 text-center transition hover:-translate-y-0.5 hover:border-brand/40">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-light text-brand"><x-social.icon :name="$domain" class="h-6 w-6" /></span>
                    <span class="text-xs font-semibold leading-tight text-navy">{{ __('mxconnect.social.domains.'.$domain) }}</span>
                    <span class="text-[11px] text-ink/40">{{ $count }}</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- ============ FEATURED PROJECTS ============ --}}
    <section class="mt-8">
        <x-social.section-title :title="__('mxconnect.social.home.featured')" icon="star" :href="route('discover', ['section' => 'projets'])" />
        <div class="mt-3">
            @if ($featuredProjects->isEmpty())
                <x-social.empty icon="folder" :message="__('mxconnect.social.home.empty_projects')" />
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($featuredProjects as $project)
                        <x-social.project-card :project="$project" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- ============ RECENT POSTS ============ --}}
    <section class="mt-8">
        <x-social.section-title :title="__('mxconnect.social.home.recent')" icon="feed" :href="route('feed')" />
        <div class="mt-3">
            @if ($recentPosts->isEmpty())
                <x-social.empty icon="feed" :message="__('mxconnect.social.home.empty_posts')" />
            @else
                <div class="card divide-y divide-ink/5">
                    @foreach ($recentPosts as $post)
                        <a href="{{ route('posts.show', $post) }}" class="flex gap-3 p-4 transition hover:bg-paper">
                            @if ($post->organization)<x-social.avatar :organization="$post->organization" size="h-10 w-10" />@else<x-social.avatar :account="$post->author" size="h-10 w-10" />@endif
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-navy">{{ $post->organization?->name ?? $post->author?->displayName() }} <span class="font-normal text-ink/40">· {{ $post->published_at?->diffForHumans() }}</span></p>
                                <p class="mt-0.5 line-clamp-2 text-sm text-ink/70">{{ \Illuminate\Support\Str::limit((string) $post->body, 180) ?: __('mxconnect.social.post_types.'.$post->type->value) }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- ============ CALLS TO ACTION ============ --}}
    <section class="mt-8 grid gap-4 md:grid-cols-2">
        <div class="card flex flex-col p-6">
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-light text-brand"><x-social.icon name="building" /></span>
            <h2 class="mt-4 text-lg font-bold text-navy">{{ __('mxconnect.social.home.join_title') }}</h2>
            <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.social.home.join_sub') }}</p>
            <a href="{{ $viewer ? route('organizations.create') : route('register') }}" class="btn-primary mt-5 self-start">{{ __('mxconnect.social.nav.create_org') }}</a>
        </div>
        <div class="card flex flex-col p-6">
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-sky-light text-sky"><x-social.icon name="protection_sociale" /></span>
            <h2 class="mt-4 text-lg font-bold text-navy">{{ __('mxconnect.social.home.mutual_title') }}</h2>
            <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.social.home.mutual_sub') }}</p>
            <a href="{{ route('comparator') }}" class="btn-secondary mt-5 self-start">{{ __('mxconnect.social.home.mutual_cta') }}</a>
        </div>
    </section>

    <x-slot:aside>
        <div class="card p-4">
            <x-social.section-title :title="__('mxconnect.social.home.suggested')" :href="route('discover')" />
            <div class="mt-2 space-y-1">
                @forelse ($suggestedOrganizations as $org)
                    <x-social.org-card :organization="$org" compact />
                @empty
                    <p class="py-4 text-sm text-ink/50">{{ __('mxconnect.social.home.empty_orgs') }}</p>
                @endforelse
            </div>
        </div>

        <div class="card p-4">
            <x-social.section-title :title="__('mxconnect.social.home.events')" icon="calendar" :href="route('discover', ['section' => 'evenements'])" />
            <div class="mt-3 space-y-3">
                @forelse ($upcomingEvents as $event)
                    <a href="{{ route('posts.show', $event) }}" class="flex gap-3 rounded-xl p-1.5 hover:bg-paper">
                        <span class="flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-xl bg-brand-light text-brand-dark">
                            <span class="text-xl font-extrabold leading-none">{{ $event->event_starts_at->format('d') }}</span>
                            <span class="text-[10px] font-bold uppercase">{{ $event->event_starts_at->translatedFormat('M') }}</span>
                        </span>
                        <span class="min-w-0 text-sm">
                            <span class="line-clamp-2 font-semibold text-navy">{{ \Illuminate\Support\Str::limit((string) $event->body, 70) }}</span>
                            <span class="mt-0.5 block truncate text-xs text-ink/50">{{ $event->organization?->name }}@if ($event->event_location) · {{ $event->event_location }}@endif</span>
                        </span>
                    </a>
                @empty
                    <p class="py-4 text-sm text-ink/50">{{ __('mxconnect.social.home.empty_events') }}</p>
                @endforelse
            </div>
        </div>
    </x-slot:aside>
</x-layouts.social>
