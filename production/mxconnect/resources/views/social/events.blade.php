<x-layouts.social title="Événements" active="evenements">
  <div class="mx-row mx-between mx-row--wrap">
    <h1 style="font-size:22px">Événements des mutuelles</h1>
    <nav class="mx-segment" style="width:auto" aria-label="Période">
      <a href="{{ route('social.events') }}" @class(['is-active' => ! $past])>À venir</a>
      <a href="{{ route('social.events', ['periode' => 'passes']) }}" @class(['is-active' => $past])>Passés</a>
    </nav>
  </div>
  @if ($posts->isEmpty())
    @include('social.partials.empty', ['icon' => 'calendar', 'title' => $past ? 'Aucun événement passé' : 'Aucun événement à venir', 'text' => 'Les journées de sensibilisation, dépistages et réunions publiés par les mutuelles apparaîtront ici.'])
  @else
    <div class="mx-stack">@include('social.partials.feed')</div>
  @endif
</x-layouts.social>
