<x-layouts.social :title="'Projets · ' . $page->name" active="pages">
  @include('social.manage._head')
  <div class="mx-row mx-between mx-row--wrap" style="gap:10px;padding:0 4px">
    <p class="mx-small mx-muted" style="flex:1;min-width:220px">Présentez vos projets, publiez leurs résultats et dites si vous cherchez des bénévoles ou des partenaires.</p>
    <a class="mx-btn mx-btn--primary mx-btn--sm" href="{{ route('social.manage.project.create', $page->slug) }}"><x-mx.icon name="plus" :size="16"/> Nouveau projet</a>
  </div>
  @forelse ($items as $p)
    @include('social.projects._card', ['p' => $p->setRelation('mutual', $page)])
    <div class="mx-row mx-row--wrap" style="gap:6px;margin:-6px 4px 6px">
      <a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.manage.project.edit', [$page->slug, $p->id]) }}"><x-mx.icon name="edit" :size="14"/> Modifier, résultats, actualités</a>
      <a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.manage.opportunity.create', [$page->slug, 'projet' => $p->id]) }}"><x-mx.icon name="briefcase" :size="14"/> Publier une offre liée</a>
    </div>
  @empty
    @include('social.partials.empty', ['icon' => 'target', 'title' => 'Aucun projet', 'text' => 'Publiez votre premier projet : objectifs, zone d’intervention, résultats et besoins.', 'action' => ['Nouveau projet', route('social.manage.project.create', $page->slug)]])
  @endforelse
  {{ $items->links('social.partials.pagination') }}
</x-layouts.social>
