<x-layouts.tenant title="Groupes">
  <a href="{{ route('social-presence.index') }}" class="text-sm text-pine hover:underline">← Réseau social</a>
  <x-tenant.hero icon="users" kicker="Réseau social" tone="violet">
    <x-slot:title>Groupes animés par la mutuelle</x-slot:title>
    <x-slot:subtitle>Espaces d’échange thématiques. Vous en êtes responsable : approuvez les membres des groupes privés et modérez les publications.</x-slot:subtitle>
    <x-slot:actions>

    <a href="{{ route('social-groups.create') }}" class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-navy">+ Nouveau groupe</a>
    </x-slot:actions>
  </x-tenant.hero>
  <div class="mt-6 overflow-hidden rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr><th class="px-4 py-2 font-medium">Groupe</th><th class="px-4 py-2 font-medium">Type</th><th class="px-4 py-2 font-medium">Membres</th><th class="px-4 py-2 font-medium">Publications</th><th></th></tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($groups as $g)
          <tr class="{{ $g->status === 'archived' ? 'opacity-60' : '' }}">
            <td class="px-4 py-3"><b>{{ $g->name }}</b>@if($g->status === 'archived') <span class="text-xs">(archivé)</span>@endif<div class="text-xs text-ink/50"><a class="hover:underline" href="{{ $central }}/groupes/{{ $g->slug }}" target="_blank" rel="noopener">Voir sur le réseau ↗</a></div></td>
            <td class="px-4 py-3">{{ $g->visibility === 'private' ? 'Privé' : 'Public' }}</td>
            <td class="px-4 py-3">{{ $g->members_count }} @if($g->pending_count)<span class="ml-1 rounded-full bg-clay px-2 text-xs text-white">{{ $g->pending_count }} en attente</span>@endif</td>
            <td class="px-4 py-3">{{ $g->posts_count }}</td>
            <td class="px-4 py-3 text-right text-xs whitespace-nowrap">
              <a class="text-pine hover:underline" href="{{ route('social-groups.members', $g->id) }}">Membres</a> ·
              <a class="text-pine hover:underline" href="{{ route('social-groups.posts', $g->id) }}">Publications</a> ·
              <a class="text-pine hover:underline" href="{{ route('social-groups.edit', $g->id) }}">Modifier</a> ·
              <form method="POST" action="{{ route('social-groups.archive', $g->id) }}" class="inline">@csrf<button class="text-ink/60 hover:underline">{{ $g->status === 'active' ? 'Archiver' : 'Réactiver' }}</button></form>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="px-4 py-10 text-center text-ink/45">Aucun groupe. <a class="text-pine underline" href="{{ route('social-groups.create') }}">Créer le premier</a></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</x-layouts.tenant>
