<x-layouts.social :title="__('mxconnect.social.requests.inbox_title')">
    @php
        $engage = $tab === 'engagement';
        $labels = $engage ? 'contribution_types' : 'support_types';
        $typeField = $engage ? 'contribution_type' : 'support_type';
    @endphp

    <a href="{{ route('organizations.show', $organization) }}" class="inline-flex items-center gap-1 text-sm font-medium text-ink/50 hover:text-navy">&larr; {{ $organization->name }}</a>
    <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-navy">{{ __('mxconnect.social.requests.inbox_title') }}</h1>

    <nav class="mt-5 flex gap-1 border-b border-ink/10 text-sm font-semibold">
        @foreach (['engagement' => __('mxconnect.social.requests.tab_engagement'), 'support' => __('mxconnect.social.requests.tab_support')] as $key => $label)
            <a href="{{ route('my.organizations.inbox', [$organization, 'tab' => $key]) }}" class="border-b-2 px-4 py-3 {{ $tab === $key ? 'border-brand text-brand-dark' : 'border-transparent text-ink/50 hover:text-navy' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
        <a href="{{ route('my.organizations.inbox', [$organization, 'tab' => $tab]) }}" class="rounded-full px-3 py-1.5 {{ ! $status ? 'bg-navy text-white' : 'bg-white text-ink/60 ring-1 ring-ink/10' }}">{{ __('mxconnect.social.requests.all_statuses') }}</a>
        @foreach (\App\Enums\EngagementStatus::cases() as $s)
            <a href="{{ route('my.organizations.inbox', [$organization, 'tab' => $tab, 'statut' => $s->value]) }}" class="rounded-full px-3 py-1.5 {{ $status === $s ? 'bg-navy text-white' : 'bg-white text-ink/60 ring-1 ring-ink/10' }}">{{ __('mxconnect.social.request_status.'.$s->value) }}</a>
        @endforeach
    </div>

    <div class="mt-5 space-y-3">
        @forelse ($items as $item)
            @php($transitions = \App\Services\Social\RequestService::allowedTransitions($item->status))
            <article class="card p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <x-social.avatar :account="$item->requester" size="h-10 w-10" />
                        <div>
                            <p class="font-semibold text-navy">{{ $item->requester->displayName() }}</p>
                            <p class="text-xs text-ink/50">{{ __('mxconnect.social.'.$labels.'.'.$item->{$typeField}->value) }} · {{ $item->created_at->translatedFormat('j F Y, H:i') }}</p>
                        </div>
                    </div>
                    <x-social.status-badge :status="$item->status" />
                </div>

                @if ($item->project || $item->post)
                    <p class="mt-3 text-sm text-ink/60">
                        @if ($item->project)<a href="{{ route('projects.show', $item->project) }}" class="font-medium text-pine hover:underline">{{ $item->project->title }}</a>@endif
                        @if ($item->post) · <a href="{{ route('posts.show', $item->post) }}" class="hover:underline">{{ \Illuminate\Support\Str::limit((string) $item->post->body, 80) }}</a>@endif
                    </p>
                @endif
                @if ($item->message)<p class="mt-2 whitespace-pre-line text-sm text-ink/80">{{ $item->message }}</p>@endif

                <div class="mt-3 grid gap-2 rounded-xl bg-paper px-3 py-2.5 text-sm sm:grid-cols-2">
                    <p class="text-xs font-semibold text-ink/50 sm:col-span-2">{{ __('mxconnect.social.requests.contact') }}</p>
                    @if (! $engage && $item->contact_info)
                        @foreach ($item->contact_info as $value)<span class="text-ink/80">{{ $value }}</span>@endforeach
                    @endif
                    @if ($item->status->value === 'acceptee')
                        <a href="tel:{{ $item->requester->phone }}" class="flex items-center gap-1.5 text-ink/80"><x-social.icon name="phone" class="h-4 w-4 text-brand" />{{ $item->requester->phone }}</a>
                        @if ($item->requester->email)<a href="mailto:{{ $item->requester->email }}" class="flex items-center gap-1.5 text-ink/80"><x-social.icon name="mail" class="h-4 w-4 text-brand" />{{ $item->requester->email }}</a>@endif
                    @else
                        <span class="text-xs text-ink/50">{{ __('mxconnect.social.requests.contact_hidden') }}</span>
                    @endif
                </div>

                @if ($item->response_note)
                    <p class="mt-3 text-xs text-ink/50">{{ __('mxconnect.social.requests.note') }} : <span class="text-ink/80">{{ $item->response_note }}</span></p>
                @endif

                @if ($transitions)
                    <form method="POST" action="{{ route('my.organizations.respond', [$organization, $tab, $item->id]) }}" class="mt-4 grid gap-2 border-t border-ink/5 pt-4 sm:grid-cols-[200px_minmax(0,1fr)_auto] sm:items-end">
                        @csrf
                        <label class="block text-xs font-semibold text-ink/60">{{ __('mxconnect.social.requests.change_status') }}
                            <select name="status" class="field mt-1">
                                @foreach ($transitions as $t)<option value="{{ $t->value }}">{{ __('mxconnect.social.request_status.'.$t->value) }}</option>@endforeach
                            </select>
                        </label>
                        <label class="block text-xs font-semibold text-ink/60">{{ __('mxconnect.social.requests.note') }}
                            <input name="note" maxlength="2000" class="field mt-1">
                        </label>
                        <button class="btn-primary">{{ __('mxconnect.social.requests.apply') }}</button>
                    </form>
                @else
                    <p class="mt-3 text-xs text-ink/40">{{ __('mxconnect.social.requests.final') }}</p>
                @endif
            </article>
        @empty
            <x-social.empty icon="inbox" :message="__('mxconnect.social.requests.inbox_empty')" />
        @endforelse
    </div>
    <x-social.pager :paginator="$items" />
</x-layouts.social>
