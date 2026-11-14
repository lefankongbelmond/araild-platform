<x-layouts.social title="Mes bénéficiaires" active="mon-adhesion">
  @include('social.member._head')
  @php
    $fmt = fn ($v) => $currency->format((int) $v);
    $rel = ['spouse' => 'Conjoint(e)', 'child' => 'Enfant', 'ascendant' => 'Parent', 'other' => 'Autre personne autorisée'];
    $K = \App\Models\Tenant\MemberChangeRequest::KINDS; $S = \App\Models\Tenant\MemberChangeRequest::STATUSES;
  @endphp
  <p class="mx-small mx-muted">Les personnes couvertes par votre adhésion. Un ajout ou un retrait se demande ici et n’est appliqué qu’après validation par la mutuelle : un nouveau bénéficiaire n’est jamais couvert automatiquement.</p>

  <section class="mx-card mx-card__pad">
    <div class="mx-between"><b>{{ mb_strtoupper($m->last_name) }} {{ $m->first_name }}</b><span class="mx-badge mx-badge--blue">Adhérent principal</span></div>
    <p class="mx-xs mx-muted">Né(e) le {{ $m->birth_date?->format('d/m/Y') }} · n° {{ $m->member_code }}</p>
  </section>
  @foreach ($x['dependents'] as $dep)
    @php $active = $dep->status === 'active' && ! $dep->trashed(); @endphp
    <section class="mx-card mx-card__pad" style="margin-top:10px">
      <div class="mx-between" style="gap:8px;flex-wrap:wrap"><b>{{ mb_strtoupper($dep->last_name) }} {{ $dep->first_name }}</b>
        <span class="mx-badge mx-badge--{{ $active ? 'green' : 'danger' }}">{{ $active ? 'Couvert(e)' : 'Retiré(e)' }}</span></div>
      <p class="mx-xs mx-muted">{{ $rel[$dep->relationship] ?? $dep->relationship }}@if ($dep->is_student) · élève @endif · né(e) le {{ $dep->birth_date?->format('d/m/Y') }}
        @if ($dep->joined_on) · intégré(e) le {{ $dep->joined_on->format('d/m/Y') }}@endif @if ($dep->left_on) · sorti(e) le {{ $dep->left_on->format('d/m/Y') }}@endif</p>
      @if ($active)
        <p class="mx-small">Pris en charge cette année : <b>{{ $fmt(($x['consumed'])($dep)) }}</b></p>
        @foreach (($x['limits'])($dep) as $l)
          @if ($l['amount'] !== null && str_contains($l['period'], 'bénéficiaire'))<p class="mx-xs mx-muted">{{ $l['label'] }} : reste {{ $fmt($l['remaining']) }} sur {{ $fmt($l['amount']) }}</p>@endif
        @endforeach
        <details style="margin-top:6px"><summary class="mx-small" style="cursor:pointer">Demander une modification ou un retrait</summary>
          <form method="POST" action="{{ route('social.member.dependents.request', $mutual->slug) }}" class="mx-grid-2" style="margin-top:8px">@csrf
            <input type="hidden" name="dependent_id" value="{{ $dep->id }}">
            <label class="mx-field"><span class="mx-label">Demande</span><select class="mx-input" name="kind"><option value="dependent_update">Corriger ses informations</option><option value="dependent_remove">Le (la) retirer de mon adhésion</option></select></label>
            <label class="mx-field"><span class="mx-label">Téléphone (facultatif)</span><input class="mx-input" name="phone" maxlength="20"></label>
            <label class="mx-field" style="grid-column:1/-1"><span class="mx-label">Précisez (obligatoire pour un retrait)</span><textarea class="mx-input" name="note" rows="2" maxlength="500"></textarea></label>
            <div><button class="mx-btn mx-btn--soft mx-btn--sm">Envoyer la demande</button></div>
          </form>
        </details>
      @endif
    </section>
  @endforeach

  <section class="mx-card mx-card__pad" style="margin-top:14px">
    <h2 class="mx-card__title" style="font-size:16px">Ajouter un bénéficiaire</h2>
    <form method="POST" action="{{ route('social.member.dependents.request', $mutual->slug) }}" class="mx-grid-2" style="margin-top:8px">@csrf
      <input type="hidden" name="kind" value="dependent_add">
      <label class="mx-field"><span class="mx-label">Nom</span><input class="mx-input" name="last_name" required maxlength="120" value="{{ old('last_name') }}"></label>
      <label class="mx-field"><span class="mx-label">Prénom</span><input class="mx-input" name="first_name" required maxlength="120" value="{{ old('first_name') }}"></label>
      <label class="mx-field"><span class="mx-label">Date de naissance</span><input class="mx-input" type="date" name="birth_date" required max="{{ now()->toDateString() }}" value="{{ old('birth_date') }}"></label>
      <label class="mx-field"><span class="mx-label">Sexe</span><select class="mx-input" name="sex"><option value="F">Féminin</option><option value="M">Masculin</option></select></label>
      <label class="mx-field"><span class="mx-label">Lien avec vous</span><select class="mx-input" name="relationship"><option value="spouse">Conjoint(e)</option><option value="child">Enfant</option><option value="adopted_child">Enfant adopté</option><option value="ascendant">Parent</option><option value="other">Autre personne autorisée par la mutuelle</option></select></label>
      <label class="mx-field"><span class="mx-label">Téléphone (facultatif)</span><input class="mx-input" name="phone" maxlength="20" value="{{ old('phone') }}"></label>
      <label class="mx-check" style="grid-column:1/-1"><input type="checkbox" name="is_student" value="1"> Élève ou étudiant(e)</label>
      <label class="mx-field" style="grid-column:1/-1"><span class="mx-label">Message pour la mutuelle (facultatif)</span><textarea class="mx-input" name="note" rows="2" maxlength="500"></textarea></label>
      <p class="mx-xs mx-muted" style="grid-column:1/-1">La mutuelle pourra vous demander un justificatif (acte de naissance, acte de mariage…) à présenter au guichet.</p>
      <div><button class="mx-btn mx-btn--primary">Envoyer la demande</button></div>
    </form>
  </section>

  @if ($x['requests']->isNotEmpty())
    <section class="mx-card mx-card__pad" style="margin-top:14px">
      <h2 class="mx-card__title" style="font-size:16px">Mes demandes</h2>
      @foreach ($x['requests'] as $r)
        <p class="mx-small" style="margin:6px 0">{{ $K[$r->kind] ?? $r->kind }} — {{ trim(($r->payload['first_name'] ?? '') . ' ' . ($r->payload['last_name'] ?? '')) }} <span class="mx-xs mx-muted">· {{ $r->created_at?->format('d/m/Y') }}</span>
          <span class="mx-badge mx-badge--{{ $r->status === 'approved' ? 'green' : ($r->status === 'rejected' ? 'danger' : 'warn') }}">{{ $S[$r->status] ?? $r->status }}</span>
          @if ($r->decision_note)<span class="mx-xs mx-muted" style="display:block">{{ $r->decision_note }}</span>@endif</p>
      @endforeach
    </section>
  @endif
</x-layouts.social>
