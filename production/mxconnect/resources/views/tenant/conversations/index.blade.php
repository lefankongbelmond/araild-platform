<x-layouts.tenant title="Messagerie">
  <x-tenant.hero icon="message" kicker="Réseau social" tone="brand">
    <x-slot:title>Messagerie</x-slot:title>
    <x-slot:subtitle>Messages privés envoyés par les membres du réseau depuis votre page publique. (Les messages des visiteurs non inscrits sont dans <a class="text-pine underline" href="{{ route('social-presence.messages') }}">Réseau social › Messages</a>.)</x-slot:subtitle>
  </x-tenant.hero>
  <div class="mt-6 flex gap-2 text-sm">
    @foreach ([null => 'Ouvertes', 'non-lus' => 'Non lues', 'clos' => 'Closes'] as $k => $l)
      <a href="{{ route('conversations.index', array_filter(['filtre' => $k])) }}" class="rounded-md px-3 py-1.5 {{ $filter === ($k ?: null) ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ $l }}</a>
    @endforeach
  </div>
  <div class="mt-4 divide-y divide-ink/5 overflow-hidden rounded-lg border border-ink/10 bg-white">
    @forelse ($items as $c)
      <a href="{{ route('conversations.show', $c->id) }}" class="flex items-start justify-between gap-4 px-4 py-3 text-sm hover:bg-mist {{ $c->mutual_unread ? 'bg-mist/60' : '' }}">
        <div class="min-w-0"><b>{{ $c->account?->name }}</b> · {{ $c->subject }} @if($c->mutual_unread)<span class="ml-1 rounded-full bg-clay px-2 text-xs text-white">{{ $c->mutual_unread }}</span>@endif
          <div class="truncate text-ink/55">{{ \Illuminate\Support\Str::limit($c->lastMessage?->body, 120) }}</div></div>
        <span class="whitespace-nowrap text-xs text-ink/45">{{ $c->last_message_at?->format('d/m/Y H:i') }}</span>
      </a>
    @empty
      <p class="px-4 py-10 text-center text-sm text-ink/45">Aucune conversation.</p>
    @endforelse
  </div>
  <div class="mt-4">{{ $items->links() }}</div>
</x-layouts.tenant>
