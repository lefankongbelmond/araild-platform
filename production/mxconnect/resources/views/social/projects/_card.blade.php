{{-- Carte de projet (listes). $p = projet, chargé avec mutual.profile. --}}
<a class="mx-card mx-listcard" href="{{ route('social.project', $p) }}">
  @if ($p->cover_path)<img class="mx-listcard__thumb" src="{{ $p->coverUrl() }}" alt="" loading="lazy">@endif
  <span class="mx-listcard__body">
    <span class="mx-tags">
      <span class="mx-badge mx-badge--blue">{{ $p->phaseLabel() }}</span>
      @if ($p->seeks_volunteers)<span class="mx-badge mx-badge--green">Cherche des bénévoles</span>@endif
      @if ($p->seeks_partners)<span class="mx-badge mx-badge--green">Cherche des partenaires</span>@endif
      @if ($p->is_demo || $p->mutual?->profile?->is_demo)<span class="mx-demo-ribbon">Démo</span>@endif
      @if ($p->status !== 'published')<span class="mx-badge mx-badge--warn">{{ \App\Models\Social\Project::STATUSES[$p->status] ?? $p->status }}</span>@endif
    </span>
    <span class="mx-listcard__title">{{ $p->title }}</span>
    <span class="mx-small">{{ \Illuminate\Support\Str::limit($p->summary, 160) }}</span>
    <span class="mx-meta">
      <span><x-mx.icon name="building" :size="14"/> {{ $p->mutual?->name }}@if($p->mutual?->isVerified()) <x-mx.icon name="verified" :size="13"/>@endif</span>
      @if ($p->zone || $p->region)<span><x-mx.icon name="pin" :size="14"/> {{ $p->zone ?: $p->region }}</span>@endif
      @isset($p->indicators_count)<span><x-mx.icon name="chart" :size="14"/> {{ $p->indicators_count }} résultat(s) publié(s)</span>@endisset
    </span>
  </span>
</a>
