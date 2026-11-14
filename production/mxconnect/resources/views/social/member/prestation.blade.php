<x-layouts.social title="Prestation" active="mon-adhesion">
  @include('social.member._head')
  @php $p = $x['p']; $S = \App\Services\Tenant\MemberSelfService::PRESTATION_STATUS; $fmt = fn ($v) => $v === null ? '—' : $currency->format((int) $v); $who = $p->careClaim?->dependent; @endphp
  <a class="mx-small" href="{{ route('social.member.prestations', $mutual->slug) }}">← Mes prestations</a>
  <section class="mx-card mx-card__pad" style="margin-top:8px">
    <div class="mx-between" style="gap:8px;flex-wrap:wrap"><h1 style="font-size:19px;margin:0">{{ $p->actType?->label }}</h1><span class="mx-badge mx-badge--{{ $p->status === 'validated' ? 'green' : ($p->status === 'rejected' ? 'danger' : 'warn') }}">{{ $S[$p->status] ?? $p->status }}</span></div>
    <dl class="mx-kv" style="margin-top:12px">
      <dt>Référence</dt><dd>PC-{{ $p->id }}</dd>
      <dt>Date des soins</dt><dd>{{ $p->care_date?->format('d/m/Y') }}</dd>
      <dt>Établissement</dt><dd>{{ $x['provider'] ?? '—' }}</dd>
      <dt>Personne soignée</dt><dd>{{ $who ? $who->first_name . ' ' . $who->last_name : $m->first_name . ' ' . $m->last_name }}</dd>
      <dt>Montant total</dt><dd>{{ $fmt($p->total_minor) }}</dd>
      @if ($p->eligible_minor !== null)<dt>Montant retenu</dt><dd>{{ $fmt($p->eligible_minor) }}</dd>@endif
      <dt>Part de la mutuelle</dt><dd><b>{{ $fmt($p->mutual_part_minor) }}</b></dd>
      <dt>Votre part</dt><dd>{{ $fmt($p->beneficiary_part_minor) }}@if ($p->copay_minor !== null) <span class="mx-xs mx-muted">(dont ticket modérateur {{ $fmt($p->copay_minor) }}@if ($p->non_covered_minor) , non couvert {{ $fmt($p->non_covered_minor) }}@endif)</span>@endif</dd>
      @if ($x['preauth'])<dt>Accord préalable</dt><dd>{{ $x['preauth']->number ?? 'demande n° ' . $x['preauth']->id }}</dd>@endif
      @if ($p->emergency)<dt>Urgence</dt><dd>Oui</dd>@endif
    </dl>
    @if ($p->status === 'rejected' && $p->decision_note)<p class="mx-alert mx-alert--err" style="margin-top:10px">Motif du rejet : {{ $p->decision_note }}</p>@endif
    @if ($x['dispensation'])
      <h2 class="mx-card__title" style="font-size:15px;margin-top:14px">Médicaments délivrés (ordonnance {{ $x['dispensation']->prescription?->reference }})</h2>
      @foreach ($x['dispensation']->lines as $l)
        <p class="mx-small" style="margin:4px 0">{{ $l->medicine?->label }} × {{ $l->quantity_dispensed }} — {{ $fmt($l->amount_minor) }}@if (! $l->eligible) <span style="color:var(--mx-danger)">(non pris en charge)</span>@endif</p>
      @endforeach
    @endif
  </section>
  <p class="mx-xs mx-muted" style="margin-top:10px">Une question sur cette prestation ? <a href="{{ route('social.member.complaints', $mutual->slug) }}">Écrivez à votre mutuelle</a> en indiquant la référence PC-{{ $p->id }}.</p>
</x-layouts.social>
