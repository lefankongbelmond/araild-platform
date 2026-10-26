@php $R = \App\Models\Social\SocialRequest::class; @endphp
<x-layouts.social :title="'Demande ' . $r->reference" active="demandes">
  <a class="mx-small" href="{{ route('social.requests') }}"><x-mx.icon name="arrow-left" :size="14"/> Mes candidatures et engagements</a>
  <section class="mx-card mx-card__pad mx-stack">
    <div class="mx-row mx-between mx-row--wrap" style="gap:10px">
      <div class="mx-row" style="gap:12px;min-width:0"><x-mx.avatar :src="$r->mutual?->profile?->logoUrl()" :name="$r->mutual?->name ?? '?'" org />
        <div style="min-width:0"><h1 style="font-size:20px">{{ $r->kindLabel() }}</h1>
          <p class="mx-xs mx-muted">{{ $r->mutual?->name }} · {{ $r->reference }} · envoyée le {{ $r->created_at->format('d/m/Y à H:i') }}</p></div></div>
      <span class="mx-status mx-status--{{ $r->statusClass() }}">{{ $r->statusLabel() }}</span>
    </div>
    @if ($r->status === 'accepted')
      <div class="mx-alert mx-alert--ok">{{ $r->mutual?->name }} a accepté votre demande et va vous contacter avec les coordonnées que vous avez indiquées.</div>
    @endif
    <dl class="mx-kv">
      <dt>Sujet</dt><dd>
        @if ($r->opportunity && ! $r->opportunity->trashed())<a href="{{ route('social.opportunity', $r->opportunity) }}">{{ $r->opportunity->title }}</a>
        @elseif ($r->project && ! $r->project->trashed())<a href="{{ route('social.project', $r->project) }}">{{ $r->project->title }}</a>
        @elseif ($r->opportunity || $r->project){{ $r->subject() }} (retiré par l’organisation)
        @else<a href="{{ route('social.mutual', $r->mutual?->slug) }}">{{ $r->mutual?->name }}</a>@endif</dd>
      @if ($r->subtypeLabel())<dt>Type</dt><dd>{{ $r->subtypeLabel() }}</dd>@endif
      @if ($r->organisation_name)<dt>Au nom de</dt><dd>{{ $r->organisation_name }}</dd>@endif
      @if ($r->availability)<dt>Disponibilités</dt><dd>{{ $r->availability }}</dd>@endif
      <dt>Message</dt><dd style="white-space:pre-line;overflow-wrap:anywhere">{{ $r->message }}</dd>
      <dt>Coordonnées transmises</dt><dd>{{ $r->contact_name }}@if($r->contact_phone) · {{ $r->contact_phone }}@endif @if($r->contact_email) · {{ $r->contact_email }}@endif</dd>
      @if ($r->cv_path)<dt>CV</dt><dd><a href="{{ route('social.request.cv', $r->reference) }}">{{ $r->cv_name }}</a></dd>@endif
    </dl>
  </section>

  <section class="mx-card mx-card__pad mx-stack">
    <h2 class="mx-card__title">Suivi</h2>
    <ol class="mx-steps">
      @foreach ($r->events as $e)
        <li><span class="mx-dotstep"></span><div><b>{{ $R::STATUSES[$e->to_status] ?? $e->to_status }}</b> <span class="mx-xs mx-muted">· {{ $e->created_at?->format('d/m/Y H:i') }} · {{ $e->actor === 'organisation' ? 'par l’organisation' : 'par vous' }}</span>@if ($e->note)<p class="mx-small" style="margin-top:4px;white-space:pre-line">{{ $e->note }}</p>@endif</div></li>
      @endforeach
    </ol>
  </section>

  @if ($r->isOpen())
    <form method="POST" action="{{ route('social.request.withdraw', $r->reference) }}" data-confirm="Retirer définitivement cette demande ?">@csrf<button class="mx-btn mx-btn--ghost mx-btn--sm">Retirer ma demande</button></form>
  @endif
</x-layouts.social>
