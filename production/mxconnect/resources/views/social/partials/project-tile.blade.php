{{-- Carte de projet « À la une » (design Lot 2). $p = projet avec mutual.profile. --}}
<a class="mx-card mx-pcard" href="{{ route('social.project', $p) }}">
  <span class="mx-pcard__img" @if($p->cover_path) style="background-image:url('{{ $p->coverUrl() }}')" @endif>
    <span class="mx-badge">{{ $p->phaseLabel() }}</span>
    @unless ($p->cover_path)<x-mx.icon name="folder" :size="36"/>@endunless
  </span>
  <span class="mx-pcard__body">
    <span class="mx-pcard__title">{{ $p->title }}@if($p->is_demo || $p->mutual?->profile?->is_demo) <span class="mx-demo-ribbon">Démo</span>@endif</span>
    <span class="mx-small mx-muted">{{ \Illuminate\Support\Str::limit($p->summary, 110) }}</span>
    <span class="mx-row" style="gap:8px;margin-top:auto;padding-top:6px"><x-mx.avatar :src="$p->mutual?->profile?->logoUrl()" :name="$p->mutual?->name ?? '?'" org size="sm" /><span class="mx-xs mx-muted mx-truncate">{{ $p->mutual?->name }}</span></span>
    @if ($p->progress !== null)
      <span class="mx-bar" role="img" aria-label="Avancement déclaré : {{ $p->progress }} %"><i style="width:{{ $p->progress }}%"></i></span>
      <span class="mx-xs mx-muted" style="text-align:right">{{ $p->progress }} % (déclaré)</span>
    @endif
  </span>
</a>
