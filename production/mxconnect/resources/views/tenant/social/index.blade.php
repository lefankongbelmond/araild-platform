@php
  $central = rtrim(config('app.url'), '/');
  $labels = ['published' => [__('En ligne'), 'sage'], 'draft' => [__('Brouillon'), 'ink/50'], 'archived' => [__('Archivée'), 'ink/50'], 'hidden' => [__('Masquée par la modération'), 'clay']];
@endphp
<x-layouts.tenant title="{{ __('Réseau social') }}">
  <x-tenant.hero icon="globe" kicker="Réseau social" tone="brand">
    <x-slot:title>{{ __('Réseau social') }}</x-slot:title>
    <x-slot:subtitle>{{ __('Votre page publique sur MX-CONNECT, vos publications et les messages reçus.') }}</x-slot:subtitle>
    <x-slot:actions>

    <div class="flex flex-wrap gap-2 text-sm">
      <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="rounded-md border border-ink/15 bg-white px-3 py-2 hover:border-pine">{{ __('Voir la page publique ↗') }}</a>
      <a href="{{ route('social-presence.profile') }}" class="rounded-md border border-ink/15 bg-white px-3 py-2 hover:border-pine">{{ __('Modifier la page') }}</a>
      <a href="{{ route('social-groups.index') }}" class="rounded-md border border-ink/15 bg-white px-3 py-2 hover:border-pine">{{ __('Groupes') }}</a>
      <a href="{{ route('social-presence.messages') }}" class="rounded-md border border-ink/15 bg-white px-3 py-2 hover:border-pine">{{ __('Messages') }} @if($stats['new_contact'])<span class="ml-1 rounded-full bg-clay px-2 text-xs text-white">{{ $stats['new_contact'] }}</span>@endif</a>
      <a href="{{ route('social-presence.create') }}" class="rounded-md bg-pine px-4 py-2 font-medium text-white hover:bg-navy">{{ __('+ Nouvelle publication') }}</a>
    </div>
    </x-slot:actions>
  </x-tenant.hero>

  @if (! $profile || (! $profile->description && ! $profile->logo_path))
    <div class="mt-5 rounded-lg border border-sage/30 bg-mist px-4 py-3 text-sm">{{ __('Complétez votre page publique (logo, présentation, coordonnées) pour être mieux trouvé par le public.') }} <a class="font-medium text-pine underline" href="{{ route('social-presence.profile') }}">{{ __('Compléter maintenant') }}</a></div>
  @endif

  <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    @foreach ([['Abonnés', $stats['followers']], ['Publications en ligne', $stats['published']], ['Publiées (30 j)', $stats['posts_30d']], ['Réactions', $stats['reactions']], ['Commentaires', $stats['comments']], ['Partages', $stats['shares']]] as [$l, $v])
      <div class="rounded-lg border border-ink/10 bg-white p-4"><div class="text-2xl font-semibold">{{ number_format($v, 0, ',', ' ') }}</div><div class="text-xs text-ink/55">{{ $l }}</div></div>
    @endforeach
  </div>
  <p class="mt-2 text-xs text-ink/45">{{ __('Chiffres réels calculés sur vos publications en ligne.') }}</p>

  <div class="mt-6 flex flex-wrap gap-2 text-sm">
    <a href="{{ route('social-presence.index') }}" class="rounded-md px-3 py-1.5 {{ ! $status ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ __('Toutes') }}</a>
    @foreach ($labels as $k => [$l])
      <a href="{{ route('social-presence.index', ['statut' => $k]) }}" class="rounded-md px-3 py-1.5 {{ $status === $k ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ $k === 'hidden' ? __('Masquées') : $l }}</a>
    @endforeach
  </div>

  <div class="mt-4 overflow-hidden rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('Publication') }}</th><th class="px-4 py-2 font-medium">{{ __('Type') }}</th><th class="px-4 py-2 font-medium">{{ __('Statut') }}</th>
        <th class="px-4 py-2 font-medium text-right">{{ __('Interactions') }}</th><th class="px-4 py-2"></th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($posts as $p)
          @php [$sl, $sc] = $labels[$p->status] ?? [$p->status, 'ink/50']; @endphp
          <tr class="align-top">
            <td class="px-4 py-3">
              <div class="flex gap-3">
                @if ($p->media->isNotEmpty())<img src="{{ $central . $p->media->first()->thumbUrl() }}" alt="" class="h-12 w-12 flex-none rounded object-cover">@endif
                <div><div class="line-clamp-2">{{ \Illuminate\Support\Str::limit($p->body ?: __('(images seules)'), 140) }}</div>
                  <div class="mt-1 text-xs text-ink/45">{{ $p->published_at?->format('d/m/Y H:i') ?? __('Non publiée') }} · {{ $p->visibility === 'followers' ? __('Abonnés') : __('Public') }}@if($p->type === 'evenement' && $p->event_starts_at) · {{ __('Événement le :date', ['date' => $p->event_starts_at->format('d/m/Y H:i')]) }}@endif</div></div>
              </div>
            </td>
            <td class="px-4 py-3 text-ink/70">{{ $p->typeLabel() }}</td>
            <td class="px-4 py-3"><span class="rounded-full border border-{{ $sc }}/30 px-2 py-0.5 text-xs text-{{ $sc }}">{{ $sl }}</span></td>
            <td class="px-4 py-3 text-right text-ink/60 whitespace-nowrap">{{ __(':count réac. ·', ['count' => $p->reactions_count]) }} <a class="hover:underline" href="{{ route('social-presence.comments', $p->id) }}">{{ __(':count com.', ['count' => $p->comments_count]) }}</a> {{ __('· :count part.', ['count' => $p->shares_count]) }}</td>
            <td class="px-4 py-3 text-right whitespace-nowrap text-xs">
              @if ($p->status === 'published')<a class="text-pine hover:underline" href="{{ $central }}/publications/{{ $p->id }}" target="_blank" rel="noopener">{{ __('Voir') }}</a> · @endif
              <a class="text-pine hover:underline" href="{{ route('social-presence.edit', $p->id) }}">{{ __('Modifier') }}</a>
              @if ($p->status !== 'hidden')
                <form method="POST" action="{{ route('social-presence.status', $p->id) }}" class="inline">@csrf
                  @if ($p->status === 'published')<input type="hidden" name="status" value="archived"><button class="text-ink/60 hover:underline"> {{ __('· Archiver') }}</button>
                  @else<input type="hidden" name="status" value="published"><button class="text-pine hover:underline"> {{ __('· Publier') }}</button>@endif
                </form>
              @endif
              <form method="POST" action="{{ route('social-presence.destroy', $p->id) }}" class="inline" onsubmit="return confirm('Supprimer définitivement cette publication ?')">@csrf @method('DELETE')<button class="text-clay hover:underline"> {{ __('· Supprimer') }}</button></form>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="px-4 py-10 text-center text-ink/45">{{ __('Aucune publication.') }} <a class="text-pine underline" href="{{ route('social-presence.create') }}">{{ __('Publier votre première annonce') }}</a></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $posts->links() }}</div>
</x-layouts.tenant>
