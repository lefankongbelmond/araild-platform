<x-layouts.social :title="$organization->name">
    @php($org = $organization)

    @unless ($org->isPublished())
        <div class="mb-4 rounded-xl border border-sun/40 bg-sun-light px-4 py-3 text-sm text-ink/80">
            {{ __('mxconnect.social.org.hidden_notice', ['status' => __('mxconnect.social.org.statuses.'.$org->status->value)]) }}
        </div>
    @endunless

    {{-- ============ HEADER ============ --}}
    <section class="card overflow-hidden">
        <div class="relative h-40 bg-gradient-to-br from-navy-900 via-navy to-brand sm:h-56">
            @if ($org->coverUrl())
                <img src="{{ $org->coverUrl() }}" alt="" class="h-full w-full object-cover">
            @endif
        </div>
        <div class="px-4 pb-5 sm:px-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="-mt-12 flex items-end gap-4 sm:-mt-14">
                    <x-social.avatar :organization="$org" size="h-24 w-24 sm:h-28 sm:w-28" text="text-2xl" class="ring-4 ring-white" />
                </div>
                <div class="flex flex-wrap gap-2 sm:pb-1">
                    @if ($role)
                        @if ($isAdmin)
                            <a href="{{ route('my.organizations.inbox', $org) }}" class="btn-secondary"><x-social.icon name="inbox" class="h-4 w-4" />{{ __('mxconnect.social.org.inbox') }}</a>
                            <a href="{{ route('organizations.edit', $org) }}" class="btn-secondary"><x-social.icon name="edit" class="h-4 w-4" />{{ __('mxconnect.social.org.edit') }}</a>
                        @endif
                    @elseif ($org->isPublished())
                        @if ($following)
                            <form method="POST" action="{{ route('organizations.unfollow', $org) }}">
                                @csrf @method('DELETE')
                                <button class="btn-secondary border-brand/40 text-brand-dark"><x-social.icon name="check" class="h-4 w-4" />{{ __('mxconnect.social.actions.following') }}</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('organizations.follow', $org) }}">
                                @csrf
                                <button class="btn-primary"><x-social.icon name="plus" class="h-4 w-4" />{{ __('mxconnect.social.actions.follow') }}</button>
                            </form>
                        @endif
                        <a href="{{ route('organizations.engage.form', $org) }}" class="btn-secondary"><x-social.icon name="hand" class="h-4 w-4 text-brand" />{{ __('mxconnect.social.actions.engage') }}</a>
                        <a href="{{ route('organizations.support.form', $org) }}" class="btn-secondary"><x-social.icon name="gift" class="h-4 w-4 text-sun" />{{ __('mxconnect.social.actions.support') }}</a>
                    @endif
                </div>
            </div>

            <div class="mt-4">
                <h1 class="flex flex-wrap items-center gap-2 text-2xl font-extrabold tracking-tight text-navy sm:text-3xl">
                    {{ $org->name }}
                    @if ($org->isVerified())
                        <span class="inline-flex items-center gap-1 rounded-full bg-sky-light px-2.5 py-1 text-xs font-semibold text-sky"><x-social.icon name="verified" class="h-4 w-4" />{{ __('mxconnect.social.common.verified') }}</span>
                    @endif
                </h1>
                <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.social.org_types.'.$org->type->value) }}@if ($org->tagline) — {{ $org->tagline }}@endif</p>
                <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-ink/60">
                    @if ($org->location() || $org->country)
                        <span class="flex items-center gap-1.5"><x-social.icon name="pin" class="h-4 w-4 text-brand" />{{ collect([$org->location(), $org->country?->name])->filter()->join(', ') }}</span>
                    @endif
                    @if ($org->website)
                        <a href="{{ $org->website }}" target="_blank" rel="noopener nofollow" class="flex items-center gap-1.5 text-pine hover:underline"><x-social.icon name="globe" class="h-4 w-4" />{{ parse_url($org->website, PHP_URL_HOST) ?? $org->website }}</a>
                    @endif
                    @if ($role)
                        <span class="rounded-full bg-brand-light px-2.5 py-1 text-xs font-semibold text-brand-dark">{{ __('mxconnect.social.org.manager_badge', ['role' => __('mxconnect.social.roles.'.$role->value)]) }}</span>
                    @endif
                </div>
            </div>

            <dl class="mt-5 grid grid-cols-3 gap-3 sm:max-w-md">
                <div class="rounded-xl bg-paper p-3 text-center"><dd class="text-xl font-extrabold text-navy">{{ $org->followers_count }}</dd><dt class="text-xs text-ink/50">{{ __('mxconnect.social.common.followers') }}</dt></div>
                <div class="rounded-xl bg-paper p-3 text-center"><dd class="text-xl font-extrabold text-navy">{{ $counts['posts'] }}</dd><dt class="text-xs text-ink/50">{{ __('mxconnect.social.org.posts_count') }}</dt></div>
                <div class="rounded-xl bg-paper p-3 text-center"><dd class="text-xl font-extrabold text-navy">{{ $counts['projects'] }}</dd><dt class="text-xs text-ink/50">{{ __('mxconnect.social.org.projects_count') }}</dt></div>
            </dl>
        </div>

        <nav class="flex gap-1 overflow-x-auto border-t border-ink/10 px-2 text-sm font-semibold">
            @foreach (\App\Http\Controllers\Social\OrganizationController::TABS as $t)
                <a href="{{ route('organizations.show', [$org, 'onglet' => $t]) }}" class="whitespace-nowrap border-b-2 px-4 py-3 {{ $tab === $t ? 'border-brand text-brand-dark' : 'border-transparent text-ink/50 hover:text-navy' }}">{{ __('mxconnect.social.org.tabs.'.$t) }}</a>
            @endforeach
        </nav>
    </section>

    {{-- ============ TABS ============ --}}
    <div class="mt-6">
        @if ($tab === 'publications')
            <div class="space-y-5">
                @forelse ($posts as $post)
                    <x-social.post-card :post="$post" :viewer="$viewer" :liked="in_array($post->id, $likedIds)" :roles="$roles" />
                @empty
                    <x-social.empty icon="feed" :message="__('mxconnect.social.org.no_posts')">
                        @if ($role)<a href="{{ route('feed') }}#publier" class="btn-primary mt-4">{{ __('mxconnect.social.home.cta_publish') }}</a>@endif
                    </x-social.empty>
                @endforelse
            </div>
            <x-social.pager :paginator="$posts" />

        @elseif ($tab === 'projets')
            @if ($isAdmin)
                <div class="mb-4 flex justify-end"><a href="{{ route('projects.create', $org) }}" class="btn-primary"><x-social.icon name="plus" class="h-4 w-4" />{{ __('mxconnect.social.org.new_project') }}</a></div>
            @endif
            @if ($projects->isEmpty())
                <x-social.empty icon="folder" :message="__('mxconnect.social.org.no_projects')" />
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($projects as $project)
                        @php($project->setRelation('organization', $org))
                        <x-social.project-card :project="$project" />
                    @endforeach
                </div>
            @endif

        @else
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_300px]">
                <div class="card p-5">
                    <h2 class="text-base font-bold text-navy">{{ __('mxconnect.social.org.about') }}</h2>
                    <div class="mt-3 text-sm leading-relaxed text-ink/80">
                        @if ($org->description){{ \App\Support\PostFormatter::render($org->description) }}@else<span class="text-ink/50">{{ __('mxconnect.social.org.no_description') }}</span>@endif
                    </div>
                    @if ($org->domains)
                        <h3 class="mt-6 text-sm font-bold text-navy">{{ __('mxconnect.social.org.domains') }}</h3>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($org->domains as $d)
                                <a href="{{ route('discover', ['domaine' => $d]) }}" class="inline-flex items-center gap-1.5 rounded-full bg-brand-light px-3 py-1.5 text-xs font-semibold text-brand-dark"><x-social.icon :name="$d" class="h-3.5 w-3.5" />{{ __('mxconnect.social.domains.'.$d) }}</a>
                            @endforeach
                        </div>
                    @endif
                    @if ($org->stats)
                        <h3 class="mt-6 text-sm font-bold text-navy">{{ __('mxconnect.social.org.fields.stats') }}</h3>
                        <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            @foreach ($org->stats as $label => $value)
                                <div class="rounded-xl bg-paper p-3"><dd class="text-lg font-extrabold text-navy">{{ $value }}</dd><dt class="text-xs text-ink/50">{{ $label }}</dt></div>
                            @endforeach
                        </dl>
                        <p class="mt-2 text-[11px] text-ink/40">{{ __('mxconnect.social.common.declared') }}</p>
                    @endif
                </div>
                <div class="card h-fit space-y-3 p-5 text-sm">
                    <h2 class="text-base font-bold text-navy">{{ __('mxconnect.social.org.contact') }}</h2>
                    @if ($org->email)<a href="mailto:{{ $org->email }}" class="flex items-center gap-2 text-ink/70 hover:text-navy"><x-social.icon name="mail" class="h-4 w-4 text-brand" />{{ $org->email }}</a>@endif
                    @if ($org->phone)<a href="tel:{{ $org->phone }}" class="flex items-center gap-2 text-ink/70 hover:text-navy"><x-social.icon name="phone" class="h-4 w-4 text-brand" />{{ $org->phone }}</a>@endif
                    @if ($org->website)<a href="{{ $org->website }}" target="_blank" rel="noopener nofollow" class="flex items-center gap-2 break-all text-ink/70 hover:text-navy"><x-social.icon name="globe" class="h-4 w-4 text-brand" />{{ $org->website }}</a>@endif
                    @foreach ($org->socials ?? [] as $network => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener nofollow" class="flex items-center gap-2 capitalize text-ink/70 hover:text-navy"><x-social.icon name="link" class="h-4 w-4 text-brand" />{{ $network }}</a>
                    @endforeach
                    <p class="border-t border-ink/10 pt-3 text-xs text-ink/50">{{ __('mxconnect.social.org.member_since', ['date' => $org->created_at->translatedFormat('F Y')]) }}</p>
                </div>
            </div>
        @endif
    </div>
</x-layouts.social>
