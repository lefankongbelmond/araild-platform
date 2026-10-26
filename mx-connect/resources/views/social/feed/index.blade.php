<x-layouts.social :title="__('mxconnect.social.feed.title')">
    {{-- ============ COMPOSER ============ --}}
    @if ($viewer)
        <form id="publier" method="POST" action="{{ route('posts.store') }}" enctype="multipart/form-data" class="card p-4 sm:p-5" data-composer>
            @csrf
            <div class="flex gap-3">
                <x-social.avatar :account="$viewer" />
                <div class="min-w-0 flex-1">
                    <label for="body" class="sr-only">{{ __('mxconnect.social.nav.publish') }}</label>
                    <textarea id="body" name="body" rows="3" maxlength="5000" class="field resize-y"
                              placeholder="{{ __('mxconnect.social.feed.composer_placeholder', ['name' => $viewer->first_name]) }}">{{ old('body') }}</textarea>
                    @error('body')<p class="mt-1 text-xs text-clay">{{ $message }}</p>@enderror

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-semibold text-ink/60">{{ __('mxconnect.social.feed.as_label') }}
                            <select name="as" class="field mt-1" data-as>
                                <option value="me">{{ $viewer->displayName() }} ({{ __('mxconnect.social.feed.as_me') }})</option>
                                @foreach ($authorOptions as $org)
                                    <option value="{{ $org->id }}" @selected(old('as') == $org->id)>{{ $org->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-xs font-semibold text-ink/60">{{ __('mxconnect.social.feed.type_label') }}
                            <select name="type" class="field mt-1" data-type>
                                @foreach (['text', 'event', 'opportunity', 'result'] as $t)
                                    <option value="{{ $t }}" @selected(old('type', 'text') === $t) @if ($t !== 'text') data-org-only @endif>{{ __('mxconnect.social.post_types.'.$t) }}</option>
                                @endforeach
                            </select>
                            @error('type')<span class="mt-1 block font-normal text-clay">{{ $message }}</span>@enderror
                        </label>
                    </div>

                    <div class="mt-3 grid gap-3 sm:grid-cols-3" data-show-type="event">
                        <label class="block text-xs font-semibold text-ink/60 sm:col-span-1">{{ __('mxconnect.social.feed.event_date') }}
                            <input type="datetime-local" name="event_starts_at" value="{{ old('event_starts_at') }}" class="field mt-1">
                            @error('event_starts_at')<span class="mt-1 block font-normal text-clay">{{ $message }}</span>@enderror
                        </label>
                        <label class="block text-xs font-semibold text-ink/60">{{ __('mxconnect.social.feed.event_location') }}
                            <input type="text" name="event_location" value="{{ old('event_location') }}" maxlength="255" class="field mt-1">
                        </label>
                        <label class="flex items-center gap-2 self-end pb-2.5 text-sm text-ink/70">
                            <input type="checkbox" name="event_online" value="1" @checked(old('event_online')) class="rounded border-ink/30 text-brand focus:ring-brand">
                            {{ __('mxconnect.social.feed.event_online') }}
                        </label>
                    </div>

                    <div class="mt-3" data-show-type="opportunity">
                        <label class="block text-xs font-semibold text-ink/60">{{ __('mxconnect.social.feed.opportunity_kind') }}
                            <select name="opportunity_kind" class="field mt-1">
                                @foreach (config('mxconnect.social.opportunity_kinds') as $kind)
                                    <option value="{{ $kind }}" @selected(old('opportunity_kind') === $kind)>{{ __('mxconnect.social.opportunity_kinds.'.$kind) }}</option>
                                @endforeach
                            </select>
                            @error('opportunity_kind')<span class="mt-1 block font-normal text-clay">{{ $message }}</span>@enderror
                        </label>
                    </div>

                    @if ($authorOptions->isNotEmpty())
                        <div class="mt-3 grid gap-3 sm:grid-cols-2" data-org-fields>
                            <label class="block text-xs font-semibold text-ink/60">{{ __('mxconnect.social.feed.project_link') }}
                                <select name="project_id" class="field mt-1">
                                    <option value="">{{ __('mxconnect.social.feed.no_project') }}</option>
                                    @foreach ($authorOptions as $org)
                                        @foreach ($org->projects as $project)
                                            <option value="{{ $project->id }}" data-org="{{ $org->id }}" @selected(old('project_id') == $project->id)>{{ $org->name }} — {{ $project->title }}</option>
                                        @endforeach
                                    @endforeach
                                </select>
                                @error('project_id')<span class="mt-1 block font-normal text-clay">{{ $message }}</span>@enderror
                            </label>
                            <label class="block text-xs font-semibold text-ink/60">{{ __('mxconnect.social.feed.visibility') }}
                                <select name="visibility" class="field mt-1">
                                    <option value="public">{{ __('mxconnect.social.feed.visibility_public') }}</option>
                                    <option value="followers" @selected(old('visibility') === 'followers')>{{ __('mxconnect.social.feed.visibility_followers') }}</option>
                                </select>
                            </label>
                        </div>
                    @endif

                    <p class="mt-2 text-xs text-ink/40" data-org-hint>{{ __('mxconnect.social.feed.org_only_hint') }}</p>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-ink/5 pt-3">
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-ink/70 hover:bg-paper">
                            <x-social.icon name="image" class="h-5 w-5 text-brand" />
                            <x-social.icon name="video" class="h-5 w-5 text-sky" />
                            {{ __('mxconnect.social.feed.media') }}
                            <input type="file" name="media[]" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/webm" class="sr-only" data-media>
                        </label>
                        <span class="text-xs text-ink/50" data-media-count></span>
                        <button class="btn-primary">{{ __('mxconnect.social.nav.publish') }}</button>
                    </div>
                    <p class="mt-1 text-[11px] text-ink/40">{{ __('mxconnect.social.feed.media_help') }}</p>
                    @foreach ($errors->get('media*') as $messages)
                        @foreach ($messages as $m)<p class="mt-1 text-xs text-clay">{{ $m }}</p>@endforeach
                    @endforeach
                </div>
            </div>
        </form>
    @else
        <div class="card flex flex-col items-start gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-ink/70">{{ __('mxconnect.social.feed.guest_cta') }}</p>
            <div class="flex gap-2">
                <a href="{{ route('login') }}" class="btn-secondary">{{ __('mxconnect.social.nav.login') }}</a>
                <a href="{{ route('register') }}" class="btn-primary">{{ __('mxconnect.social.nav.register') }}</a>
            </div>
        </div>
    @endif

    {{-- ============ TABS ============ --}}
    <nav class="mt-6 flex gap-1 overflow-x-auto border-b border-ink/10 text-sm font-semibold">
        @foreach (__('mxconnect.social.feed.tabs') as $key => $label)
            <a href="{{ route('feed', array_filter(['onglet' => $key === 'pour-vous' ? null : $key, 'tag' => $tag])) }}"
               class="whitespace-nowrap border-b-2 px-4 py-3 {{ $tab === $key ? 'border-brand text-brand-dark' : 'border-transparent text-ink/50 hover:text-navy' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if ($tag)
        <div class="mt-4 flex items-center justify-between rounded-xl bg-white px-4 py-3 text-sm ring-1 ring-ink/10">
            <span class="font-semibold text-navy">{{ __('mxconnect.social.feed.tag_filter', ['tag' => $tag]) }}</span>
            <a href="{{ route('feed', array_filter(['onglet' => $tab === 'pour-vous' ? null : $tab])) }}" class="text-xs font-semibold text-pine hover:underline">{{ __('mxconnect.social.feed.clear_filter') }}</a>
        </div>
    @endif

    {{-- ============ POSTS ============ --}}
    <div class="mt-4 space-y-5">
        @forelse ($posts as $post)
            <x-social.post-card :post="$post" :viewer="$viewer" :liked="in_array($post->id, $likedIds)" :roles="$roles" />
        @empty
            <x-social.empty icon="feed" :message="$viewer && $tab === 'pour-vous' ? __('mxconnect.social.feed.empty_for_you') : __('mxconnect.social.feed.empty')">
                <a href="{{ route('discover') }}" class="btn-secondary mt-4">{{ __('mxconnect.social.nav.discover') }}</a>
            </x-social.empty>
        @endforelse
    </div>
    <x-social.pager :paginator="$posts" />

    <x-slot:aside>
        <div class="card p-4">
            <x-social.section-title :title="__('mxconnect.social.feed.trending')" icon="hash" />
            <div class="mt-3 space-y-1">
                @forelse ($trending as $h)
                    <a href="{{ route('feed', ['tag' => $h->tag]) }}" class="flex items-center justify-between rounded-lg px-2 py-1.5 text-sm hover:bg-paper">
                        <span class="font-semibold text-pine">#{{ $h->tag }}</span>
                        <span class="text-xs text-ink/40">{{ __('mxconnect.social.feed.trending_count', ['count' => $h->recent_uses]) }}</span>
                    </a>
                @empty
                    <p class="py-2 text-sm text-ink/50">{{ __('mxconnect.social.feed.trending_empty') }}</p>
                @endforelse
            </div>
        </div>

        <div class="card p-4">
            <x-social.section-title :title="__('mxconnect.social.feed.suggestions')" :href="route('discover')" />
            <div class="mt-2 space-y-1">
                @forelse ($suggestions as $org)
                    <x-social.org-card :organization="$org" compact />
                @empty
                    <p class="py-2 text-sm text-ink/50">{{ __('mxconnect.social.home.empty_orgs') }}</p>
                @endforelse
            </div>
        </div>

        <div class="card p-4">
            <x-social.section-title :title="__('mxconnect.social.home.events')" icon="calendar" :href="route('discover', ['section' => 'evenements'])" />
            <div class="mt-3 space-y-2">
                @forelse ($upcomingEvents as $event)
                    <a href="{{ route('posts.show', $event) }}" class="flex gap-3 rounded-xl p-1.5 hover:bg-paper">
                        <span class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-brand-light text-brand-dark">
                            <span class="text-lg font-extrabold leading-none">{{ $event->event_starts_at->format('d') }}</span>
                            <span class="text-[10px] font-bold uppercase">{{ $event->event_starts_at->translatedFormat('M') }}</span>
                        </span>
                        <span class="min-w-0 text-sm">
                            <span class="line-clamp-2 font-semibold text-navy">{{ \Illuminate\Support\Str::limit((string) $event->body, 70) }}</span>
                            <span class="block truncate text-xs text-ink/50">{{ $event->organization?->name }}</span>
                        </span>
                    </a>
                @empty
                    <p class="py-2 text-sm text-ink/50">{{ __('mxconnect.social.home.empty_events') }}</p>
                @endforelse
            </div>
        </div>

        <div class="card p-4">
            <x-social.section-title :title="__('mxconnect.social.feed.opportunities')" icon="rocket" :href="route('discover', ['section' => 'opportunites'])" />
            <div class="mt-3 space-y-2">
                @forelse ($opportunities as $op)
                    <a href="{{ route('posts.show', $op) }}" class="block rounded-xl p-2 hover:bg-paper">
                        <span class="rounded-full bg-sun-light px-2 py-0.5 text-[11px] font-semibold text-sun">{{ __('mxconnect.social.opportunity_kinds.'.($op->opportunity_kind ?? 'autre')) }}</span>
                        <span class="mt-1 line-clamp-2 block text-sm font-medium text-navy">{{ \Illuminate\Support\Str::limit((string) $op->body, 90) }}</span>
                        <span class="block text-xs text-ink/50">{{ $op->organization?->name }} · {{ $op->published_at?->diffForHumans() }}</span>
                    </a>
                @empty
                    <p class="py-2 text-sm text-ink/50">{{ __('mxconnect.social.feed.empty') }}</p>
                @endforelse
            </div>
        </div>
    </x-slot:aside>

    @if ($viewer)
        <script>
          // Progressive enhancement of the composer: show only the fields relevant to
          // the chosen author/format. The server enforces the same rules regardless.
          (() => {
            const form = document.querySelector('[data-composer]');
            if (!form) return;
            const as = form.querySelector('[data-as]'), type = form.querySelector('[data-type]');
            const sync = () => {
              const isOrg = as.value !== 'me';
              type.querySelectorAll('[data-org-only]').forEach(o => o.disabled = !isOrg);
              if (!isOrg && type.selectedOptions[0]?.disabled) type.value = 'text';
              form.querySelectorAll('[data-show-type]').forEach(el => el.hidden = el.dataset.showType !== type.value);
              form.querySelectorAll('[data-org-fields]').forEach(el => el.hidden = !isOrg);
              form.querySelectorAll('option[data-org]').forEach(o => { o.hidden = o.dataset.org !== as.value; if (o.hidden && o.selected) o.parentElement.value = ''; });
              form.querySelector('[data-org-hint]').hidden = isOrg;
            };
            as.addEventListener('change', sync); type.addEventListener('change', sync); sync();
            const media = form.querySelector('[data-media]'), count = form.querySelector('[data-media-count]');
            media.addEventListener('change', () => count.textContent = media.files.length ? media.files.length + ' ✓' : '');
          })();
        </script>
    @endif
</x-layouts.social>
