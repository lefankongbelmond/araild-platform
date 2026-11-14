@extends('social.member._print', ['docTitle' => 'Reçu de paiement', 'back' => route('social.member.contributions', $mutual->slug)])
@section('doc')
  @php $p = $x['payment']; $modes = ['cash' => 'Espèces', 'mobile_money' => 'Mobile Money', 'group' => 'Groupe', 'bank' => 'Banque']; @endphp
  <p class="mx-xs mx-muted">{{ $mutual->name }}</p>
  <h1>Reçu de paiement @if ($x['cancelled'])<span style="color:#C0392B">— ANNULÉ</span>@endif</h1>
  <p class="mx-small">{{ $p->collection_ref ?? ('Paiement n° ' . $p->id) }} · {{ $p->paid_on?->format('d/m/Y') }} · {{ $modes[$p->mode] ?? $p->mode }}</p>
  <p>Adhérent : <b>{{ mb_strtoupper($m->last_name) }} {{ $m->first_name }}</b> · n° {{ $m->member_code }}</p>
  <table>
    <tr><th>Échéance</th><th>Montant</th></tr>
    @foreach ($x['lines'] as $l)<tr><td>{{ $l->schedule?->label() }}</td><td>{{ $currency->format((int) $l->amount_minor) }}</td></tr>@endforeach
    <tr><th>Total</th><th>{{ $currency->format($x['total']) }}</th></tr>
  </table>
  <p class="mx-xs mx-muted" style="margin-top:18px">Copie du reçu enregistré par la mutuelle, consultée le {{ now()->format('d/m/Y à H:i') }} sur le portail adhérent MX-CONNECT.</p>
@endsection
