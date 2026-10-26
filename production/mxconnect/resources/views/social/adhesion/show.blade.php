@php $R = \App\Models\Social\AdhesionRequest::class; @endphp
<x-layouts.social :title="'Dossier ' . $r->reference" active="adhesions">
  <a class="mx-small" href="{{ route('social.adhesions') }}"><x-mx.icon name="arrow-left" :size="14"/> Mes demandes</a>
  <section class="mx-card mx-card__pad mx-stack">
    <div class="mx-row mx-between mx-row--wrap">
      <div class="mx-row" style="gap:12px"><x-mx.avatar :src="$r->mutual?->profile?->logoUrl()" :name="$r->mutual?->name ?? '?'" org />
        <div><h1 style="font-size:20px">{{ $r->mutual?->name }}</h1><p class="mx-xs mx-muted">Dossier {{ $r->reference }} · envoyé le {{ $r->created_at->format('d/m/Y à H:i') }}</p></div></div>
      <span class="mx-status mx-status--{{ $r->status }}">{{ $r->statusLabel() }}</span>
    </div>
    @if ($r->status === 'accepted')
      <div class="mx-alert mx-alert--ok">Votre demande a été acceptée. {{ $r->mutual?->name }} vous indiquera les modalités de paiement de la cotisation et la date d’ouverture de vos droits.@if($r->member_code) Votre numéro d’adhérent : <b>{{ $r->member_code }}</b>.@endif</div>
    @elseif ($r->status === 'info_requested')
      <div class="mx-alert mx-alert--warn">La mutuelle demande un complément. Lisez sa demande ci-dessous puis répondez.</div>
    @endif
  </section>

  @if ($r->status === 'info_requested')
    <form class="mx-card mx-card__pad mx-stack" method="POST" action="{{ route('social.adhesion.complete', $r) }}" enctype="multipart/form-data">
      @csrf
      <h2 class="mx-card__title">Envoyer le complément</h2>
      <div class="mx-field"><label class="mx-label" for="c-note">Votre réponse</label><textarea class="mx-textarea" id="c-note" name="note" required maxlength="2000">{{ old('note') }}</textarea>@error('note')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-row mx-row--wrap">
        <select class="mx-select" name="kinds[0]" style="width:auto" aria-label="Type de pièce">@foreach ($R::DOC_KINDS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
        <input type="file" name="documents[0]" accept="application/pdf,image/jpeg,image/png,image/webp" aria-label="Pièce complémentaire">
      </div>
      @error('documents')<p class="mx-error">{{ $message }}</p>@enderror
      <div><button class="mx-btn mx-btn--primary">Envoyer</button></div>
    </form>
  @endif

  <section class="mx-card mx-card__pad mx-stack">
    <h2 class="mx-card__title">Suivi</h2>
    <ol class="mx-steps">
      @foreach ($r->events as $e)
        <li><span class="mx-dotstep"></span><div><b>{{ $R::STATUSES[$e->to_status] ?? $e->to_status }}</b> <span class="mx-xs mx-muted">· {{ $e->created_at?->format('d/m/Y H:i') }} · {{ $e->actor === 'mutual' ? 'par la mutuelle' : 'par vous' }}</span>@if ($e->note)<p class="mx-small" style="margin-top:4px;white-space:pre-line">{{ $e->note }}</p>@endif</div></li>
      @endforeach
    </ol>
  </section>

  <section class="mx-card mx-card__pad mx-stack">
    <h2 class="mx-card__title">Informations transmises</h2>
    <dl class="mx-kv">
      <dt>Demandeur</dt><dd>{{ $r->name }} · {{ $r->sex === 'F' ? 'Féminin' : 'Masculin' }} · né(e) le {{ $r->birth_date->format('d/m/Y') }}</dd>
      <dt>Contact</dt><dd>{{ $r->phone }}@if($r->email) · {{ $r->email }}@endif</dd>
      <dt>Ville</dt><dd>{{ $r->city }}@if($r->address) — {{ $r->address }}@endif</dd>
      <dt>Foyer</dt><dd>{{ $r->household_size }} personne(s)@if($r->formula) · formule : {{ $r->formula }}@endif</dd>
      @if (! empty($r->beneficiaries))<dt>Bénéficiaires</dt><dd>@foreach ($r->beneficiaries as $b){{ $b['name'] }} ({{ $R::RELATIONS[$b['relation'] ?? 'autre'] ?? 'Autre' }}@if(! empty($b['birth_date'])), {{ \Illuminate\Support\Carbon::parse($b['birth_date'])->format('d/m/Y') }}@endif)@if(! $loop->last)<br>@endif @endforeach</dd>@endif
      <dt>Pièces</dt><dd>@forelse ($r->documents as $d)<a href="{{ route('social.adhesion.document', [$r, $d->id]) }}">{{ $d->kindLabel() }} — {{ $d->original_name }}</a>@if(! $loop->last)<br>@endif @empty Aucune @endforelse</dd>
    </dl>
  </section>

  <section class="mx-card mx-card__pad mx-stack">
    <h2 class="mx-card__title">Écrire à la mutuelle à propos de ce dossier</h2>
    @if ($r->conversation)<p class="mx-small"><a href="{{ route('social.messages.show', $r->conversation) }}">Voir la conversation</a></p>@endif
    <form method="POST" action="{{ route('social.adhesion.message', $r) }}" class="mx-stack">
      @csrf
      <label class="mx-sr" for="m-body">Votre message</label>
      <textarea class="mx-textarea" id="m-body" name="body" required maxlength="3000" placeholder="Votre question…"></textarea>
      <div><button class="mx-btn mx-btn--soft"><x-mx.icon name="send" :size="16"/> Envoyer</button></div>
    </form>
  </section>

  @if ($r->isOpen())
    <form method="POST" action="{{ route('social.adhesion.withdraw', $r) }}" data-confirm="Retirer définitivement cette demande ?">@csrf<button class="mx-btn mx-btn--ghost mx-btn--sm">Retirer ma demande</button></form>
  @endif
</x-layouts.social>
