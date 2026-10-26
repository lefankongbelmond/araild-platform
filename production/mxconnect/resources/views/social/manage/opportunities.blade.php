@php $O = \App\Models\Social\Opportunity::class; @endphp
<x-layouts.social :title="'Opportunités · ' . $page->name" active="pages">
  @include('social.manage._head')
  <div class="mx-row mx-between mx-row--wrap" style="gap:10px;padding:0 4px">
    <p class="mx-small mx-muted" style="flex:1;min-width:220px">Emplois, stages, bénévolat, consultances, appels d’offres ou à partenaires : vos offres apparaissent dans « Emplois et opportunités ».</p>
    <a class="mx-btn mx-btn--primary mx-btn--sm" href="{{ route('social.manage.opportunity.create', $page->slug) }}"><x-mx.icon name="plus" :size="16"/> Nouvelle offre</a>
  </div>
  @forelse ($items as $o)
    @include('social.opportunities._card', ['o' => $o->setRelation('mutual', $page)])
    <div class="mx-row mx-row--wrap" style="gap:6px;margin:-6px 4px 6px;align-items:center">
      <a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.manage.opportunity.edit', [$page->slug, $o->id]) }}"><x-mx.icon name="edit" :size="14"/> Modifier</a>
      <form method="POST" action="{{ route('social.manage.opportunity.availability', [$page->slug, $o->id]) }}" class="mx-row" style="gap:6px">@csrf
        <label class="mx-sr" for="av-{{ $o->id }}">Statut de l’offre</label>
        <select class="mx-select" id="av-{{ $o->id }}" name="availability" style="width:auto;height:34px">@foreach ($O::AVAILABILITIES as $k => $v)<option value="{{ $k }}" @selected($o->availability === $k)>{{ $v }}</option>@endforeach</select>
        <button class="mx-btn mx-btn--ghost mx-btn--sm">Appliquer</button></form>
      <span class="mx-xs mx-muted">{{ $o->requests_count }} candidature(s) / demande(s)</span>
    </div>
  @empty
    @include('social.partials.empty', ['icon' => 'briefcase', 'title' => 'Aucune offre', 'text' => 'Publiez une offre d’emploi, un stage, une mission de bénévolat ou un appel à partenaires.', 'action' => ['Nouvelle offre', route('social.manage.opportunity.create', $page->slug)]])
  @endforelse
  {{ $items->links('social.partials.pagination') }}
</x-layouts.social>
