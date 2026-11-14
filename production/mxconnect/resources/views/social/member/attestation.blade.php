@extends('social.member._print', ['docTitle' => 'Attestation d’adhésion', 'back' => route('social.member.profile', $mutual->slug)])
@section('doc')
  @php $rel = ['spouse' => 'Conjoint(e)', 'child' => 'Enfant', 'ascendant' => 'Parent', 'other' => 'Autre']; @endphp
  <p class="mx-xs mx-muted">{{ $mutual->name }}{{ $mutual->city ? ' · ' . $mutual->city : '' }}</p>
  <h1>Attestation d’adhésion</h1>
  <p style="margin-top:14px">La mutuelle <b>{{ $mutual->name }}</b> atteste que <b>{{ mb_strtoupper($m->last_name) }} {{ $m->first_name }}</b>, né(e) le {{ $m->birth_date?->format('d/m/Y') }}, est adhérent(e) sous le numéro <b>{{ $m->member_code }}</b> depuis le {{ $m->joined_at?->format('d/m/Y') }}.</p>
  <table>
    <tr><th>Formule</th><td>{{ $d['formula'] ?? '—' }}</td></tr>
    <tr><th>Couverture depuis le</th><td>{{ $d['coverage_start']?->format('d/m/Y') ?? '—' }}</td></tr>
    <tr><th>Situation au {{ now()->format('d/m/Y') }}</th><td><b>{{ $d['status_label'] }}</b></td></tr>
  </table>
  @php $deps = $m->dependents->where('status', 'active'); @endphp
  @if ($deps->isNotEmpty())
    <p style="margin-top:14px"><b>Bénéficiaires rattachés</b></p>
    <table>@foreach ($deps as $dep)<tr><td>{{ mb_strtoupper($dep->last_name) }} {{ $dep->first_name }}</td><td>{{ $rel[$dep->relationship] ?? $dep->relationship }}</td><td>né(e) le {{ $dep->birth_date?->format('d/m/Y') }}</td></tr>@endforeach</table>
  @endif
  <p class="mx-xs mx-muted" style="margin-top:18px">Document établi le {{ now()->format('d/m/Y à H:i') }} depuis le portail adhérent MX-CONNECT, à partir des données de la mutuelle. Il reflète la situation à cette date ; les droits se vérifient en temps réel auprès de la mutuelle (carte de membre, QR code).</p>
@endsection
