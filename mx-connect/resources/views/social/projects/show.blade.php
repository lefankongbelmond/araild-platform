<x-layouts.social :title="$project->title">
    @php($statusStyle = ['planifie' => 'bg-sky-light text-sky', 'en_cours' => 'bg-sun-light text-sun', 'termine' => 'bg-brand-light text-brand-dark'][$project->status->value])

    <a href="{{ route('organizations.show', [$organization, 'onglet' => 'projets']) }}" class="mb-4 inline-flex items-center gap-1 text-sm font-medium text-ink/50 hover:text-navy">&larr; {{ $organization->name }}</a>

    {{-- ============ HEADER ============ --}}
    <section class="card overflow-hidden">
        <div class="relative h-44 bg-gradient-to-br from-navy-900 via-navy-700 to-brand sm:h-60">
            @if ($project->coverUrl())<img src="{{ $project->coverUrl() }}" alt="" class="h-full w-full object-cover">@endif
            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-navy-900/90 to-transparent p-5 text-white sm:p-6">
                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusStyle }}">{{ __('mxconnect.social.project_status.'.$project->status->value) }}</span>
                <h1 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $project->title }}</h1>
                @if ($project->summary)<p class="mt-1 max-w-2xl text-sm text-white/80">{{ $project->summary }}</p>@endif
            </div>
        </div>
        <div class="grid gap-4 p-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:p-6">
            <a href="{{ route('organizations.show', $organization) }}" class="flex items-center gap-3">
                <x-social.avatar :organization="$organization" />
                <span>
                    <span class="block text-xs text-ink/50">{{ __('mxconnect.social.project.carried_by') }}</span>
                    <span class="flex items-center gap-1 font-semibold text-navy">{{ $organization->name }}@if ($organization->isVerified())<x-social.icon name="verified" class="h-4 w-4 text-sky" />@endif</span>
                </span>
            </a>
            <div class="flex flex-wrap gap-2">
                @if ($isAdmin)
                    <a href="{{ route('projects.edit', $project) }}" class="btn-secondary"><x-social.icon name="edit" class="h-4 w-4" />{{ __('mxconnect.social.project.edit') }}</a>
                @elseif (! $isManager)
                    <a href="{{ route('organizations.engage.form', [$organization, 'projet' => $project->id]) }}" class="btn-primary"><x-social.icon name="hand" class="h-4 w-4" />{{ __('mxconnect.social.actions.engage') }}</a>
                    <a href="{{ route('organizations.support.form', [$organization, 'projet' => $project->id]) }}" class="btn-secondary"><x-social.icon name="gift" class="h-4 w-4 text-sun" />{{ __('mxconnect.social.actions.support') }}</a>
                @endif
                <button type="button" data-share="{{ route('projects.show', $project) }}" data-copied="{{ __('mxconnect.social.actions.link_copied') }}" class="btn-secondary"><x-social.icon name="share" class="h-4 w-4" /><span data-share-label>{{ __('mxconnect.social.actions.share') }}</span></button>
            </div>
        </div>
        <dl class="grid gap-px border-t border-ink/10 bg-ink/5 sm:grid-cols-4">
            <div class="bg-white p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ __('mxconnect.social.project.progress') }}</dt>
                <dd class="mt-1 text-xl font-extrabold text-navy">{{ $project->progress_percent }} %</dd>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-paper"><div class="h-full rounded-full bg-brand" style="width: {{ $project->progress_percent }}%"></div></div>
            </div>
            <div class="bg-white p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ __('mxconnect.social.project.zone') }}</dt>
                <dd class="mt-1 text-sm font-medium text-navy">{{ $project->zone ?: __('mxconnect.social.project.not_set') }}</dd>
            </div>
            <div class="bg-white p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ __('mxconnect.social.project.period') }}</dt>
                <dd class="mt-1 text-sm font-medium text-navy">
                    @if ($project->start_date || $project->end_date)
                        {{ $project->start_date?->translatedFormat('M Y') ?? '…' }} → {{ $project->end_date?->translatedFormat('M Y') ?? '…' }}
                    @else
                        {{ __('mxconnect.social.project.not_set') }}
                    @endif
                </dd>
            </div>
            <div class="bg-white p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ __('mxconnect.social.project.beneficiaries') }}</dt>
                <dd class="mt-1 text-sm font-medium text-navy">{{ $project->beneficiaries_summary ?: __('mxconnect.social.project.not_set') }}</dd>
            </div>
        </dl>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-6">
            @if ($project->description || $project->objectives)
                <section class="card space-y-5 p-5 sm:p-6">
                    @if ($project->description)
                        <div><h2 class="text-base font-bold text-navy">{{ __('mxconnect.social.project.context') }}</h2><div class="mt-2 text-sm leading-relaxed text-ink/80">{{ \App\Support\PostFormatter::render($project->description) }}</div></div>
                    @endif
                    @if ($project->objectives)
                        <div><h2 class="flex items-center gap-2 text-base font-bold text-navy"><x-social.icon name="target" class="h-5 w-5 text-brand" />{{ __('mxconnect.social.project.objectives') }}</h2><div class="mt-2 text-sm leading-relaxed text-ink/80">{{ \App\Support\PostFormatter::render($project->objectives) }}</div></div>
                    @endif
                </section>
            @endif

            {{-- ============ RESULTS (declared vs verified) ============ --}}
            <section id="resultats" class="card p-5 sm:p-6">
                <h2 class="flex items-center gap-2 text-base font-bold text-navy"><x-social.icon name="chart" class="h-5 w-5 text-brand" />{{ __('mxconnect.social.project.results') }}</h2>
                <p class="mt-1 text-xs text-ink/50">{{ __('mxconnect.social.project.results_note') }}</p>
                @if ($indicators->isEmpty())
                    <p class="mt-4 text-sm text-ink/50">{{ __('mxconnect.social.project.no_results') }}</p>
                @else
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($indicators as $indicator)
                            <div class="rounded-xl border border-ink/10 p-4">
                                <p class="text-2xl font-extrabold text-navy">{{ $indicator->value }}@if ($indicator->unit) <span class="text-base font-semibold text-ink/50">{{ $indicator->unit }}</span>@endif</p>
                                <p class="mt-0.5 text-sm text-ink/70">{{ $indicator->label }}</p>
                                <p class="mt-2 flex flex-wrap items-center gap-2 text-[11px]">
                                    @if ($indicator->is_verified)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-sky-light px-2 py-0.5 font-semibold text-sky"><x-social.icon name="verified" class="h-3.5 w-3.5" />{{ __('mxconnect.social.project.verified') }}</span>
                                    @else
                                        <span class="rounded-full bg-paper px-2 py-0.5 font-semibold text-ink/60">{{ __('mxconnect.social.project.declared') }}</span>
                                    @endif
                                    @if ($indicator->source_note)<span class="text-ink/40">{{ __('mxconnect.social.project.source') }} : {{ $indicator->source_note }}</span>@endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($isAdmin)
                    <details class="mt-5 rounded-xl border border-dashed border-ink/15 p-4" @if ($errors->hasAny(['label', 'value'])) open @endif>
                        <summary class="cursor-pointer text-sm font-semibold text-pine">+ {{ __('mxconnect.social.project.add_indicator') }}</summary>
                        <form method="POST" action="{{ route('projects.indicators.store', $project) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                            @csrf
                            <label class="block text-xs font-semibold text-ink/60">{{ __('mxconnect.social.project.fields.label') }}<input name="label" required maxlength="200" value="{{ old('label') }}" class="field mt-1"></label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="block text-xs font-semibold text-ink/60">{{ __('mxconnect.social.project.fields.value') }}<input name="value" required maxlength="100" value="{{ old('value') }}" class="field mt-1"></label>
                                <label class="block text-xs font-semibold text-ink/60">{{ __('mxconnect.social.project.fields.unit') }}<input name="unit" maxlength="40" value="{{ old('unit') }}" class="field mt-1"></label>
                            </div>
                            <label class="block text-xs font-semibold text-ink/60 sm:col-span-2">{{ __('mxconnect.social.project.fields.source') }}<input name="source_note" maxlength="255" value="{{ old('source_note') }}" class="field mt-1"></label>
                            <div class="sm:col-span-2"><button class="btn-primary">{{ __('mxconnect.social.common.save') }}</button></div>
                        </form>
                    </details>
                @endif
            </section>

            {{-- ============ UPDATES ============ --}}
            <section id="actualites" class="card p-5 sm:p-6">
                <h2 class="flex items-center gap-2 text-base font-bold text-navy"><x-social.icon name="clock" class="h-5 w-5 text-brand" />{{ __('mxconnect.social.project.updates') }}</h2>
                @if ($isAdmin)
                    <details class="mt-4 rounded-xl border border-dashed border-ink/15 p-4" @if ($errors->hasAny(['title', 'body'])) open @endif>
                        <summary class="cursor-pointer text-sm font-semibold text-pine">+ {{ __('mxconnect.social.project.add_update') }}</summary>
                        <form method="POST" action="{{ route('projects.updates.store', $project) }}" class="mt-3 space-y-3">
                            @csrf
                            <label class="block text-xs font-semibold text-ink/60">{{ __('mxconnect.social.project.fields.update_title') }}<input name="title" required maxlength="200" value="{{ old('title') }}" class="field mt-1"></label>
                            <label class="block text-xs font-semibold text-ink/60">{{ __('mxconnect.social.project.fields.update_body') }}<textarea name="body" rows="3" required maxlength="5000" class="field mt-1">{{ old('body') }}</textarea></label>
                            <button class="btn-primary">{{ __('mxconnect.social.nav.publish') }}</button>
                        </form>
                    </details>
                @endif
                <ol class="mt-4 space-y-5 border-l-2 border-brand/20 pl-5">
                    @forelse ($updates as $update)
                        <li class="relative">
                            <span class="absolute -left-[27px] top-1 h-3 w-3 rounded-full bg-brand ring-4 ring-white"></span>
                            <p class="text-xs text-ink/40">{{ $update->published_at?->translatedFormat('j F Y') }}</p>
                            <p class="font-semibold text-navy">{{ $update->title }}</p>
                            <div class="mt-1 text-sm text-ink/75">{{ \App\Support\PostFormatter::render($update->body) }}</div>
                        </li>
                    @empty
                        <li class="list-none text-sm text-ink/50">{{ __('mxconnect.social.project.no_updates') }}</li>
                    @endforelse
                </ol>
            </section>
        </div>

        <aside class="space-y-6">
            @if ($project->partners)
                <section class="card p-5">
                    <h2 class="text-base font-bold text-navy">{{ __('mxconnect.social.project.partners') }}</h2>
                    <ul class="mt-3 space-y-2 text-sm text-ink/75">
                        @foreach ($project->partners as $partner)<li class="flex items-center gap-2"><x-social.icon name="check" class="h-4 w-4 text-brand" />{{ $partner }}</li>@endforeach
                    </ul>
                </section>
            @endif
            <section class="card p-5">
                <h2 class="text-base font-bold text-navy">{{ __('mxconnect.social.project.related_posts') }}</h2>
                <div class="mt-3 space-y-2">
                    @forelse ($relatedPosts as $post)
                        <a href="{{ route('posts.show', $post) }}" class="block rounded-xl p-2 text-sm hover:bg-paper">
                            <span class="line-clamp-2 text-ink/80">{{ \Illuminate\Support\Str::limit((string) $post->body, 120) ?: __('mxconnect.social.post_types.'.$post->type->value) }}</span>
                            <span class="mt-0.5 block text-xs text-ink/40">{{ $post->published_at?->diffForHumans() }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-ink/50">{{ __('mxconnect.social.home.empty_posts') }}</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</x-layouts.social>
