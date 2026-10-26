<x-layouts.social :title="__('mxconnect.social.my.notifications_title')">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold tracking-tight text-navy">{{ __('mxconnect.social.my.notifications_title') }}</h1>
        @if ($notifications->contains(fn ($n) => $n->read_at === null))
            <form method="POST" action="{{ route('my.notifications.read') }}">
                @csrf
                <button class="btn-secondary"><x-social.icon name="check" class="h-4 w-4" />{{ __('mxconnect.social.my.mark_read') }}</button>
            </form>
        @endif
    </div>

    <div class="card mt-6 divide-y divide-ink/5">
        @forelse ($notifications as $n)
            @php
                $d = $n->data;
                $types = ($d['kind'] ?? null) === 'support' ? 'support_types' : 'contribution_types';
                $text = ($d['event'] ?? null) === 'request_received'
                    ? __('mxconnect.social.my.notif_received', ['requester' => $d['requester_name'], 'type' => __('mxconnect.social.'.$types.'.'.$d['type']), 'org' => $d['organization_name']])
                    : __('mxconnect.social.my.notif_status', ['org' => $d['organization_name'], 'status' => __('mxconnect.social.request_status.'.($d['status'] ?? 'envoyee'))]);
            @endphp
            <a href="{{ $d['url'] ?? route('my.requests') }}" class="flex gap-3 p-4 transition hover:bg-paper {{ $n->read_at ? '' : 'bg-brand-light/50' }}">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ ($d['kind'] ?? '') === 'support' ? 'bg-sun-light text-sun' : 'bg-brand-light text-brand' }}">
                    <x-social.icon :name="($d['kind'] ?? '') === 'support' ? 'gift' : 'hand'" class="h-5 w-5" />
                </span>
                <span class="min-w-0">
                    <span class="block text-sm text-ink/85">{{ $text }}</span>
                    <span class="mt-0.5 block text-xs text-ink/40">{{ $n->created_at->diffForHumans() }}</span>
                </span>
                @unless ($n->read_at)<span class="ml-auto mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-brand"></span>@endunless
            </a>
        @empty
            <div class="p-8"><x-social.empty icon="bell" :message="__('mxconnect.social.my.notifications_empty')" class="border-0" /></div>
        @endforelse
    </div>
    <x-social.pager :paginator="$notifications" />
</x-layouts.social>
