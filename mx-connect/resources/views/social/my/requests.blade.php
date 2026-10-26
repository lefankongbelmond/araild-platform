<x-layouts.social :title="__('mxconnect.social.requests.my_title')">
    <h1 class="text-2xl font-extrabold tracking-tight text-navy">{{ __('mxconnect.social.requests.my_title') }}</h1>

    @foreach ([
        ['engagement', $engagements, __('mxconnect.social.requests.engagements'), 'hand', 'contribution_types', 'contribution_type'],
        ['support', $offers, __('mxconnect.social.requests.offers'), 'gift', 'support_types', 'support_type'],
    ] as [$kind, $items, $heading, $icon, $labels, $typeField])
        <section class="mt-6">
            <h2 class="flex items-center gap-2 text-base font-bold text-navy"><x-social.icon :name="$icon" class="h-5 w-5 text-brand" />{{ $heading }}</h2>
            <div class="mt-3 space-y-3">
                @forelse ($items as $item)
                    <article class="card p-4 sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <a href="{{ route('organizations.show', $item->organization) }}" class="flex items-center gap-3">
                                <x-social.avatar :organization="$item->organization" size="h-10 w-10" />
                                <span>
                                    <span class="block font-semibold text-navy">{{ $item->organization->name }}</span>
                                    <span class="block text-xs text-ink/50">{{ __('mxconnect.social.'.$labels.'.'.$item->{$typeField}->value) }} · {{ __('mxconnect.social.requests.sent_on', ['date' => $item->created_at->translatedFormat('j F Y')]) }}</span>
                                </span>
                            </a>
                            <x-social.status-badge :status="$item->status" />
                        </div>
                        @if ($item->project)
                            <p class="mt-3 text-sm"><a href="{{ route('projects.show', $item->project) }}" class="font-medium text-pine hover:underline">{{ $item->project->title }}</a></p>
                        @endif
                        @if ($item->message)<p class="mt-2 text-sm text-ink/70">{{ $item->message }}</p>@endif
                        @if ($item->response_note)
                            <div class="mt-3 rounded-xl bg-paper px-3 py-2.5 text-sm">
                                <p class="text-xs font-semibold text-ink/50">{{ __('mxconnect.social.requests.response') }}</p>
                                <p class="mt-0.5 text-ink/80">{{ $item->response_note }}</p>
                            </div>
                        @endif
                        @if (in_array($item->status->value, ['envoyee', 'en_cours'], true))
                            <form method="POST" action="{{ route('my.requests.withdraw', [$kind, $item->id]) }}" class="mt-3" onsubmit="return confirm(@js(__('mxconnect.social.requests.withdraw').' ?'))">
                                @csrf
                                <button class="text-xs font-semibold text-clay hover:underline">{{ __('mxconnect.social.requests.withdraw') }}</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <x-social.empty :icon="$icon" :message="__('mxconnect.social.requests.none')">
                        <a href="{{ route('discover') }}" class="btn-secondary mt-4">{{ __('mxconnect.social.nav.discover') }}</a>
                    </x-social.empty>
                @endforelse
            </div>
        </section>
    @endforeach
</x-layouts.social>
