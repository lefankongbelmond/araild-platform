<x-layouts.social :title="__('mxconnect.social.my.organizations_title')">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold tracking-tight text-navy">{{ __('mxconnect.social.my.organizations_title') }}</h1>
        <a href="{{ route('organizations.create') }}" class="btn-primary"><x-social.icon name="plus" class="h-4 w-4" />{{ __('mxconnect.social.nav.create_org') }}</a>
    </div>

    <div class="mt-6 space-y-3">
        @forelse ($organizations as $org)
            @php($role = $org->pivot->role)
            <article class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:p-5">
                <a href="{{ route('organizations.show', $org) }}" class="flex min-w-0 flex-1 items-center gap-3">
                    <x-social.avatar :organization="$org" />
                    <span class="min-w-0">
                        <span class="block truncate font-semibold text-navy">{{ $org->name }}</span>
                        <span class="block text-xs text-ink/50">{{ __('mxconnect.social.my.role') }} : {{ __('mxconnect.social.roles.'.$role) }} · {{ __('mxconnect.social.org.statuses.'.$org->status->value) }}</span>
                    </span>
                </a>
                @if (in_array($role, ['owner', 'admin'], true))
                    @php($open = $org->open_engagements + $org->open_offers)
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('my.organizations.inbox', $org) }}" class="btn-secondary relative">
                            <x-social.icon name="inbox" class="h-4 w-4" />{{ __('mxconnect.social.org.inbox') }}
                            @if ($open > 0)<span class="rounded-full bg-clay px-1.5 text-[11px] font-bold text-white">{{ $open }}</span>@endif
                        </a>
                        <a href="{{ route('projects.create', $org) }}" class="btn-secondary"><x-social.icon name="folder" class="h-4 w-4" />{{ __('mxconnect.social.org.new_project') }}</a>
                        <a href="{{ route('organizations.edit', $org) }}" class="btn-secondary"><x-social.icon name="edit" class="h-4 w-4" />{{ __('mxconnect.social.org.edit') }}</a>
                    </div>
                @endif
            </article>
        @empty
            <x-social.empty icon="building" :message="__('mxconnect.social.my.organizations_empty')">
                <a href="{{ route('organizations.create') }}" class="btn-primary mt-4">{{ __('mxconnect.social.nav.create_org') }}</a>
            </x-social.empty>
        @endforelse
    </div>
</x-layouts.social>
