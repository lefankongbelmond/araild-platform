<x-layouts.social :title="__('mxconnect.social.discover.title')">
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-navy-900 via-navy to-navy-700 p-6 text-white sm:p-8">
        <div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-brand/30 blur-3xl"></div>
        <h1 class="relative text-3xl font-extrabold tracking-tight sm:text-4xl">{{ __('mxconnect.social.discover.title') }}</h1>
        <p class="relative mt-2 max-w-xl text-sm text-white/75 sm:text-base">{{ __('mxconnect.social.discover.sub') }}</p>
        <form method="GET" action="{{ route('discover') }}" class="relative mt-5 flex max-w-2xl gap-2 rounded-2xl bg-white p-1.5">
            <input type="hidden" name="section" value="{{ $section }}">
            @foreach (['type', 'domaine', 'pays', 'region'] as $f)
                @if (! empty($filters[$f]))<input type="hidden" name="{{ $f }}" value="{{ $filters[$f] }}">@endif
            @endforeach
            <label class="relative flex-1">
                <span class="sr-only">{{ __('mxconnect.social.common.search') }}</span>
                <x-social.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink/40" />
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('mxconnect.social.discover.search_placeholder') }}" class="w-full rounded-xl py-2.5 pl-9 pr-2 text-sm text-ink placeholder:text-ink/40 focus:outline-none">
            </label>
            <button class="btn-primary">{{ __('mxconnect.social.common.search') }}</button>
        </form>
    </section>

    {{-- Domain shortcuts --}}
    <div class="mt-5 flex gap-2 overflow-x-auto pb-1">
        @foreach (config('mxconnect.social.domains') as $domain)
            @php($active = ($filters['domaine'] ?? null) === $domain)
            <a href="{{ route('discover', array_filter([...$filters, 'section' => $section, 'domaine' => $active ? null : $domain])) }}"
               class="flex shrink-0 items-center gap-2 rounded-xl border px-3 py-2 text-sm font-medium transition {{ $active ? 'border-brand bg-brand-light text-brand-dark' : 'border-ink/10 bg-white text-ink/70 hover:border-brand/40' }}">
                <x-social.icon :name="$domain" class="h-4 w-4 text-brand" />{{ __('mxconnect.social.domains.'.$domain) }}
            </a>
        @endforeach
    </div>

    {{-- Sections --}}
    <nav class="mt-5 flex gap-1 overflow-x-auto border-b border-ink/10 text-sm font-semibold">
        @foreach (\App\Http\Controllers\Social\DiscoverController::SECTIONS as $s)
            <a href="{{ route('discover', array_filter([...$filters, 'section' => $s])) }}"
               class="whitespace-nowrap border-b-2 px-4 py-3 {{ $section === $s ? 'border-brand text-brand-dark' : 'border-transparent text-ink/50 hover:text-navy' }}">{{ __('mxconnect.social.discover.sections.'.$s) }}</a>
        @endforeach
    </nav>

    <div class="mt-5 grid gap-6 lg:grid-cols-[230px_minmax(0,1fr)]">
        {{-- Filters --}}
        <form method="GET" action="{{ route('discover') }}" class="card h-fit space-y-4 p-4 text-sm">
            <input type="hidden" name="section" value="{{ $section }}">
            <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
            <p class="flex items-center gap-2 font-bold text-navy"><x-social.icon name="filter" class="h-4 w-4 text-brand" />{{ __('mxconnect.social.discover.filters') }}</p>
            <label class="block text-xs font-semibold text-ink/60">{{ __('mxconnect.social.discover.location') }}
                <select name="pays" class="field mt-1">
                    <option value="">{{ __('mxconnect.social.discover.all_countries') }}</option>
                    @foreach ($countries as $c)<option value="{{ $c->id }}" @selected(($filters['pays'] ?? null) == $c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </label>
            <label class="block text-xs font-semibold text-ink/60">{{ __('mxconnect.social.discover.region') }}
                <select name="region" class="field mt-1">
                    <option value="">{{ __('mxconnect.social.discover.all_regions') }}</option>
                    @foreach ($regions as $r)<option value="{{ $r->region }}" @selected(($filters['region'] ?? null) === $r->region)>{{ $r->region }} ({{ $r->total }})</option>@endforeach
                </select>
            </label>
            <label class="block text-xs font-semibold text-ink/60">{{ __('mxconnect.social.discover.structure') }}
                <select name="type" class="field mt-1">
                    <option value="">{{ __('mxconnect.social.discover.all_types') }}</option>
                    @foreach (\App\Enums\OrganizationType::cases() as $t)<option value="{{ $t->value }}" @selected(($filters['type'] ?? null) === $t->value)>{{ __('mxconnect.social.org_types.'.$t->value) }}</option>@endforeach
                </select>
            </label>
            <label class="block text-xs font-semibold text-ink/60">{{ __('mxconnect.social.discover.domain') }}
                <select name="domaine" class="field mt-1">
                    <option value="">{{ __('mxconnect.social.discover.all_domains') }}</option>
                    @foreach (config('mxconnect.social.domains') as $d)<option value="{{ $d }}" @selected(($filters['domaine'] ?? null) === $d)>{{ __('mxconnect.social.domains.'.$d) }}</option>@endforeach
                </select>
            </label>
            <div class="flex gap-2 pt-1">
                <button class="btn-primary flex-1">{{ __('mxconnect.social.common.search') }}</button>
                <a href="{{ route('discover', ['section' => $section]) }}" class="btn-secondary">{{ __('mxconnect.social.common.reset') }}</a>
            </div>

            <div class="border-t border-ink/10 pt-4">
                <p class="font-bold text-navy">{{ __('mxconnect.social.discover.key_figures') }}</p>
                <dl class="mt-2 grid grid-cols-2 gap-2">
                    <div class="rounded-xl bg-paper p-2.5"><dd class="text-lg font-extrabold text-navy">{{ $totals['organisations'] }}</dd><dt class="text-[11px] text-ink/50">{{ __('mxconnect.social.discover.sections.organisations') }}</dt></div>
                    <div class="rounded-xl bg-paper p-2.5"><dd class="text-lg font-extrabold text-navy">{{ $totals['projets'] }}</dd><dt class="text-[11px] text-ink/50">{{ __('mxconnect.social.discover.sections.projets') }}</dt></div>
                </dl>
            </div>

            @if ($regions->isNotEmpty())
                <div class="border-t border-ink/10 pt-4">
                    <p class="font-bold text-navy">{{ __('mxconnect.social.discover.top_regions') }}</p>
                    @php($max = max(1, $regions->max('total')))
                    <ul class="mt-2 space-y-2">
                        @foreach ($regions->take(5) as $r)
                            <li>
                                <a href="{{ route('discover', array_filter([...$filters, 'section' => $section, 'region' => $r->region])) }}" class="flex justify-between text-xs text-ink/70 hover:text-navy"><span>{{ $r->region }}</span><span>{{ $r->total }}</span></a>
                                <span class="mt-1 block h-1.5 overflow-hidden rounded-full bg-paper"><span class="block h-full rounded-full bg-brand" style="width: {{ round($r->total / $max * 100) }}%"></span></span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </form>

        {{-- Results --}}
        <div class="min-w-0">
            <p class="mb-3 text-sm text-ink/50">{{ __('mxconnect.social.discover.results', ['count' => $results->total()]) }}</p>
            @if ($results->isEmpty())
                <x-social.empty icon="search" :message="__('mxconnect.social.discover.empty')" />
            @elseif ($section === 'organisations')
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($results as $org)<x-social.org-card :organization="$org" />@endforeach
                </div>
            @elseif ($section === 'projets')
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($results as $project)<x-social.project-card :project="$project" />@endforeach
                </div>
            @else
                <div class="card divide-y divide-ink/5">
                    @foreach ($results as $post)
                        <a href="{{ route('posts.show', $post) }}" class="flex gap-4 p-4 transition hover:bg-paper">
                            @if ($section === 'evenements' && $post->event_starts_at)
                                <span class="flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-xl bg-brand-light text-brand-dark">
                                    <span class="text-xl font-extrabold leading-none">{{ $post->event_starts_at->format('d') }}</span>
                                    <span class="text-[10px] font-bold uppercase">{{ $post->event_starts_at->translatedFormat('M') }}</span>
                                </span>
                            @else
                                <x-social.avatar :organization="$post->organization" />
                            @endif
                            <span class="min-w-0">
                                @if ($post->opportunity_kind)<span class="rounded-full bg-sun-light px-2 py-0.5 text-[11px] font-semibold text-sun">{{ __('mxconnect.social.opportunity_kinds.'.$post->opportunity_kind) }}</span>@endif
                                <span class="mt-1 line-clamp-2 block text-sm font-semibold text-navy">{{ \Illuminate\Support\Str::limit((string) $post->body, 160) }}</span>
                                <span class="mt-1 block text-xs text-ink/50">
                                    {{ $post->organization?->name }}
                                    @if ($post->event_location) · {{ $post->event_location }}@endif
                                    @if ($post->event_starts_at) · {{ $post->event_starts_at->translatedFormat('j F Y, H:i') }}@else · {{ $post->published_at?->diffForHumans() }}@endif
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
            <x-social.pager :paginator="$results" />
        </div>
    </div>
</x-layouts.social>
