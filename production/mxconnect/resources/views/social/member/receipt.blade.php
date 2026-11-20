@extends('social.member._print', ['docTitle' => 'Reçu de paiement', 'back' => route('social.member.contributions', $mutual->slug)])
@section('doc')
  @php $p = $x['payment']; $modes = ['cash' => __('Espèces'), 'mobile_money' => 'Mobile Money', 'group' => __('Groupe'), 'bank' => __('Banque')]; @endphp
  <p class="mx-xs mx-muted">{{ $mutual->name }}</p>
  <h1>{{ __('Reçu de paiement') }} @if ($x['cancelled'])<span style="color:#C0392B">{{ __('— ANNULÉ') }}</span>@endif</h1>
  <p class="mx-small">{{ $p->collection_ref ?? ('Paiement n° ' . $p->id) }} · {{ $p->paid_on?->format('d/m/Y') }} · {{ $modes[$p->mode] ?? $p->mode }}</p>
  <p>{{ __('Adhérent :') }} <b>{{ mb_strtoupper($m->last_name) }} {{ $m->first_name }}</b> {{ __('· n° :reference', ['reference' => $m->member_code]) }}</p>
  <table>
    <tr><th>{{ __('Échéance') }}</th><th>{{ __('Montant') }}</th></tr>
    @foreach ($x['lines'] as $l)<tr><td>{{ $l->schedule?->label() }}</td><td>{{ $currency->format((int) $l->amount_minor) }}</td></tr>@endforeach
    <tr><th>{{ __('Total') }}</th><th>{{ $currency->format($x['total']) }}</th></tr>
  </table>
  <p class="mx-xs mx-muted" style="margin-top:18px">{{ __('Copie du reçu enregistré par la mutuelle, consultée le :date sur le portail adhérent MX-CONNECT.', ['date' => now()->format('d/m/Y à H:i')]) }}</p>
@endsection
