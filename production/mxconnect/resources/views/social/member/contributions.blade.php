<x-layouts.social title="Mes cotisations" active="mon-adhesion">
  @include('social.member._head')
  @php $S = \App\Services\Tenant\MemberSelfService::SCHEDULE_STATUS; $fmt = fn ($v) => $currency->format((int) $v); $modes = ['cash' => 'Espèces', 'mobile_money' => 'Mobile Money', 'group' => 'Groupe', 'bank' => 'Banque']; @endphp
  <div class="mx-grid-2">
    <section class="mx-card mx-card__pad"><span class="mx-xs mx-muted">Payé (échéances affichées)</span><p style="margin:4px 0 0;font-size:20px"><b>{{ $fmt($x['paid']) }}</b></p><span class="mx-xs mx-muted">sur {{ $fmt($x['expected']) }} attendus</span></section>
    <section class="mx-card mx-card__pad"><span class="mx-xs mx-muted">Impayés</span><p style="margin:4px 0 0;font-size:20px"><b style="{{ $x['overdue']->isNotEmpty() ? 'color:var(--mx-danger)' : '' }}">{{ $x['overdue']->count() }}</b></p><span class="mx-xs mx-muted">échéance(s) en retard</span></section>
  </div>
  <p class="mx-alert mx-alert--info" style="margin-top:12px">Le paiement en ligne (Mobile Money) n’est pas encore disponible : réglez vos cotisations auprès de votre mutuelle. Un paiement n’apparaît ici qu’une fois enregistré par la mutuelle.</p>

  <section class="mx-card" style="margin-top:12px">
    <div class="mx-card__pad"><h2 class="mx-card__title" style="font-size:16px">Échéances</h2></div>
    <div class="mx-scroll-x"><table class="mx-table" style="width:100%">
      <thead><tr><th>Période</th><th>Échéance</th><th>Montant</th><th>Payé</th><th>État</th></tr></thead>
      <tbody>
        @forelse ($x['schedules'] as $s)
          @php $late = $s->status === 'overdue' || ($s->status === 'to_pay' && $s->due_date?->isPast()); @endphp
          <tr><td>{{ $s->label() }}</td><td>{{ $s->due_date?->format('d/m/Y') }}</td><td>{{ $fmt($s->due_minor) }}</td><td>{{ $fmt($s->paid_minor ?? 0) }}</td>
            <td><span class="mx-badge mx-badge--{{ $s->status === 'paid' ? 'green' : ($late ? 'danger' : 'warn') }}">{{ $late && $s->status !== 'paid' ? 'En retard' : ($S[$s->status] ?? $s->status) }}</span></td></tr>
        @empty
          <tr><td colspan="5" class="mx-muted">Aucune échéance.</td></tr>
        @endforelse
      </tbody></table></div>
  </section>

  <section class="mx-card" style="margin-top:12px">
    <div class="mx-card__pad"><h2 class="mx-card__title" style="font-size:16px">Mes paiements</h2></div>
    <div class="mx-scroll-x"><table class="mx-table" style="width:100%">
      <thead><tr><th>Date</th><th>Montant</th><th>Mode</th><th>Reçu</th></tr></thead>
      <tbody>
        @forelse ($x['payments'] as $p)
          <tr><td>{{ $p->paid_on?->format('d/m/Y') }}</td><td>{{ $fmt($p->amount_minor) }}@if ($p->trashed()) <span class="mx-badge mx-badge--danger">annulé</span>@endif</td><td>{{ $modes[$p->mode] ?? $p->mode }}</td>
            <td><a href="{{ route('social.member.receipt', [$mutual->slug, $p->id]) }}">Voir le reçu</a></td></tr>
        @empty
          <tr><td colspan="4" class="mx-muted">Aucun paiement enregistré.</td></tr>
        @endforelse
      </tbody></table></div>
  </section>
</x-layouts.social>
