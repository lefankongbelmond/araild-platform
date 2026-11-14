<x-layouts.social title="Mon adhésion" active="mon-adhesion">
  @include('social.member._head')
  @php
    $tone = ['active' => 'green', 'waiting' => 'warn', 'not_activated' => 'warn', 'suspended' => 'danger', 'expired' => 'danger', 'limit_reached' => 'warn', 'not_covered' => 'danger'][$d['status']] ?? 'blue';
    $fmt = fn ($v) => $v === null ? '—' : $currency->format((int) $v);
  @endphp

  <section class="mx-card mx-card__pad">
    <div class="mx-between" style="gap:10px;flex-wrap:wrap">
      <div>
        <span class="mx-xs mx-muted">Statut de mon adhésion</span>
        <div><span class="mx-badge mx-badge--{{ $tone }}" style="height:28px;font-size:13px">{{ $d['status_label'] }}</span></div>
      </div>
      <a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.member.attestation', $mutual->slug) }}">Attestation d’adhésion</a>
    </div>
    @if ($d['reasons'])<ul class="mx-small mx-muted" style="margin:8px 0 0 18px">@foreach ($d['reasons'] as $r)<li>{{ __('mxconnect.rights.' . $r) }}</li>@endforeach</ul>@endif
    <dl class="mx-kv" style="margin-top:12px">
      <dt>Formule</dt><dd>{{ $d['formula'] ?? 'Aucune formule en cours' }}</dd>
      <dt>Adhérent depuis le</dt><dd>{{ $m->joined_at?->format('d/m/Y') }}</dd>
      <dt>Couvert depuis le</dt><dd>{{ $d['coverage_start']?->format('d/m/Y') ?? '—' }}</dd>
      <dt>Fin de couverture</dt><dd>{{ $d['coverage_end'] ? $d['coverage_end']->format('d/m/Y') : 'Sans date de fin (tant que les cotisations sont à jour)' }}</dd>
      <dt>Bénéficiaires</dt><dd>{{ $d['beneficiaries'] }} (vous compris)</dd>
    </dl>
  </section>

  <div class="mx-grid-2" style="margin-top:14px">
    <section class="mx-card mx-card__pad">
      <span class="mx-xs mx-muted">Mes cotisations</span>
      @if ($d['overdue_count'] > 0)
        <p style="margin:6px 0 0"><b style="color:var(--mx-danger)">En retard : {{ $d['overdue_count'] }} échéance(s), {{ $fmt($d['overdue_minor']) }}</b></p>
        <p class="mx-xs mx-muted">Vos droits sont suspendus tant que le retard n’est pas réglé auprès de votre mutuelle.</p>
      @else
        <p style="margin:6px 0 0"><b style="color:#0E7A47">À jour</b></p>
      @endif
      @if ($d['next_due'])<p class="mx-small" style="margin-top:6px">Prochaine échéance : <b>{{ $d['next_due']['date']->format('d/m/Y') }}</b> · {{ $fmt($d['next_due']['amount']) }}</p>@endif
      <a class="mx-small" href="{{ route('social.member.contributions', $mutual->slug) }}">Voir mes cotisations →</a>
    </section>
    <section class="mx-card mx-card__pad">
      <span class="mx-xs mx-muted">Mes soins cette année</span>
      <p style="margin:6px 0 0"><b>{{ $d['prestations_count'] }}</b> prestation(s) · <b>{{ $fmt($d['mutual_year_minor']) }}</b> pris en charge par la mutuelle</p>
      <a class="mx-small" href="{{ route('social.member.prestations', $mutual->slug) }}">Voir mes prestations →</a>
    </section>
  </div>

  <section class="mx-card mx-card__pad" style="margin-top:14px">
    <h2 class="mx-card__title" style="font-size:16px">Mes plafonds</h2>
    @forelse ($d['limits'] as $l)
      @php $pct = $l['amount'] ? min(100, (int) round($l['used'] * 100 / max(1, $l['amount']))) : ($l['max_count'] ? min(100, (int) round(($l['used_count'] ?? 0) * 100 / $l['max_count'])) : 0); @endphp
      <div style="margin-top:12px">
        <div class="mx-between mx-small" style="gap:8px;flex-wrap:wrap"><b>{{ $l['label'] }}</b><span class="mx-muted">{{ $l['period'] }}</span></div>
        <div class="mx-bar" style="height:10px;margin-top:6px" role="img" aria-label="{{ $pct }} % utilisé"><i style="width:{{ $pct }}%;{{ $pct >= 90 ? 'background:var(--mx-danger)' : '' }}"></i></div>
        <div class="mx-xs mx-muted" style="margin-top:4px">
          @if ($l['amount'] !== null)Utilisé {{ $fmt($l['used']) }} sur {{ $fmt($l['amount']) }} · <b>reste {{ $fmt($l['remaining']) }}</b>@endif
          @if ($l['max_count'] !== null) · {{ $l['used_count'] }} acte(s) sur {{ $l['max_count'] }}@endif
        </div>
      </div>
    @empty
      <p class="mx-small mx-muted">Votre formule ne fixe pas de plafond annuel.</p>
    @endforelse
  </section>

  @if ($d['pending_requests'] > 0)
    <p class="mx-alert mx-alert--info" style="margin-top:14px">{{ $d['pending_requests'] }} demande(s) de modification en attente de validation par la mutuelle.</p>
  @endif
  <p class="mx-xs mx-muted" style="margin-top:14px">Le paiement des cotisations en ligne n’est pas encore disponible : réglez-les auprès de votre mutuelle.</p>
</x-layouts.social>
