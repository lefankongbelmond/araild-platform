@php $R = \App\Models\Social\SocialRequest::class; @endphp
<x-layouts.social :title="'Demande ' . $r->reference" active="pages">
  @include('social.manage._head')
  <a class="mx-small" href="{{ route('social.manage.requests', $page->slug) }}"><x-mx.icon name="arrow-left" :size="14"/> Demandes reçues</a>
  <section class="mx-card mx-card__pad mx-stack">
    <div class="mx-row mx-between mx-row--wrap" style="gap:10px">
      <div style="min-width:0"><h1 style="font-size:20px">{{ $r->kindLabel() }} — {{ $r->contact_name }}</h1>
        <p class="mx-xs mx-muted">{{ $r->reference }} · reçue le {{ $r->created_at->format('d/m/Y à H:i') }}</p></div>
      <span class="mx-status mx-status--{{ $r->statusClass() }}">{{ $r->statusLabel() }}</span>
    </div>
    <dl class="mx-kv">
      <dt>Sujet</dt><dd>{{ $r->subject() }}</dd>
      @if ($r->subtypeLabel())<dt>Type</dt><dd>{{ $r->subtypeLabel() }}</dd>@endif
      @if ($r->organisation_name)<dt>Au nom de</dt><dd>{{ $r->organisation_name }}</dd>@endif
      @if ($r->availability)<dt>Disponibilités</dt><dd>{{ $r->availability }}</dd>@endif
      <dt>Message</dt><dd style="white-space:pre-line;overflow-wrap:anywhere">{{ $r->message }}</dd>
      <dt>Téléphone</dt><dd>@if($r->contact_phone)<a href="tel:{{ $r->contact_phone }}">{{ $r->contact_phone }}</a>@else — @endif</dd>
      <dt>E-mail</dt><dd>@if($r->contact_email)<a href="mailto:{{ $r->contact_email }}">{{ $r->contact_email }}</a>@else — @endif</dd>
      @if ($r->cv_path)<dt>CV</dt><dd><a href="{{ route('social.manage.request.cv', [$page->slug, $r->reference]) }}"><x-mx.icon name="file" :size="14"/> {{ $r->cv_name }}</a></dd>@endif
      @if ($r->account)<dt>Profil</dt><dd><a href="{{ route('social.user', $r->account) }}">{{ $r->account->name }}</a></dd>@endif
    </dl>
  </section>

  @if ($transitions)
    <form class="mx-card mx-card__pad mx-stack" method="POST" action="{{ route('social.manage.request.decide', [$page->slug, $r->reference]) }}">
      @csrf
      <h2 class="mx-card__title">Répondre</h2>
      <div class="mx-field"><label class="mx-label" for="d-note">Message au demandeur (facultatif)</label>
        <textarea class="mx-textarea" id="d-note" name="note" maxlength="2000" placeholder="Ex. : merci, nous vous appelons cette semaine pour un entretien.">{{ old('note') }}</textarea></div>
      <div class="mx-row mx-row--wrap">
        @foreach ($transitions as $t)
          <button class="mx-btn {{ $t === 'accepted' ? 'mx-btn--primary' : ($t === 'rejected' ? 'mx-btn--ghost' : 'mx-btn--soft') }}" name="status" value="{{ $t }}">{{ ['in_review' => 'Marquer « en cours d’étude »', 'accepted' => 'Accepter', 'rejected' => 'Ne pas retenir', 'closed' => 'Clôturer'][$t] }}</button>
        @endforeach
      </div>
      <p class="mx-xs mx-muted">Le demandeur est notifié de chaque réponse. Accepter ne déclenche aucun paiement ni engagement automatique : la suite se convient directement avec lui.</p>
    </form>
  @endif

  <section class="mx-card mx-card__pad mx-stack">
    <h2 class="mx-card__title">Historique</h2>
    <ol class="mx-steps">
      @foreach ($r->events as $e)
        <li><span class="mx-dotstep"></span><div><b>{{ $R::STATUSES[$e->to_status] ?? $e->to_status }}</b> <span class="mx-xs mx-muted">· {{ $e->created_at?->format('d/m/Y H:i') }} · {{ $e->actor_label }}</span>@if ($e->note)<p class="mx-small" style="margin-top:4px;white-space:pre-line">{{ $e->note }}</p>@endif</div></li>
      @endforeach
    </ol>
  </section>
</x-layouts.social>
