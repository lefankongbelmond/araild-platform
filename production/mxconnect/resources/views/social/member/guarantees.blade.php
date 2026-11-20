<x-layouts.social title="{{ __('Mes garanties') }}" active="mon-adhesion">
  @include('social.member._head')
  @php $fmt = fn ($v) => $v === null ? '—' : $currency->format((int) $v); $pct = fn ($x) => rtrim(rtrim(number_format((float) $x, 2, ',', ''), '0'), ',') . ' %'; @endphp
  @if (! $g['version'])
    @include('social.partials.empty', ['icon' => 'heart', 'title' => 'Aucune formule en cours', 'text' => 'Votre adhésion n’a pas de formule active aujourd’hui. Contactez votre mutuelle.'])
  @else
    <section class="mx-card mx-card__pad">
      <h2 class="mx-card__title" style="font-size:17px">{{ $g['formula'] }}</h2>
      <p class="mx-small mx-muted">{{ __('Ce que la mutuelle paie pour vos soins, selon votre formule en vigueur. Le reste (le « ticket modérateur ») est à votre charge.') }}</p>
      @if (! $g['catalog'])
        <dl class="mx-kv" style="margin-top:10px">
          <dt>{{ __('Part payée par la mutuelle') }}</dt><dd><b>{{ $pct($g['rate']) }}</b> {{ __('des soins couverts') }}</dd>
          <dt>{{ __('Votre part') }}</dt><dd>{{ $pct($g['copay']) }}</dd>
          <dt>{{ __('Délai d’attente') }}</dt><dd>{{ $g['observation_days'] }} jour(s) après votre adhésion @if ($g['observation_ends_on'])(jusqu’au {{ $g['observation_ends_on']->format('d/m/Y') }})@endif</dd>
        </dl>
      @endif
    </section>

    @if ($g['catalog'])
      <section class="mx-card" style="margin-top:14px">
        <div class="mx-card__pad"><h2 class="mx-card__title" style="font-size:16px">{{ __('Mes garanties, soin par soin') }}</h2></div>
        <div class="mx-scroll-x">
          <table class="mx-table" style="width:100%">
            <thead><tr><th>{{ __('Soins') }}</th><th>{{ __('Pour') }}</th><th>{{ __('Mutuelle') }}</th><th>{{ __('Vous') }}</th><th>{{ __('Délai d’attente') }}</th></tr></thead>
            <tbody>
              @foreach ($g['rules'] as $r)
                <tr>
                  <td><b>{{ $r['label'] }}</b><div class="mx-xs mx-muted">{{ $r['category'] }}</div>
                    @if ($r['authorization'])<div class="mx-xs" style="color:var(--mx-warning)">Accord préalable de la mutuelle{{ $r['authorization_above'] ? ' au-delà de ' . $fmt($r['authorization_above']) : '' }} (sauf urgence)</div>@endif
                    @if ($r['tariff_cap'])<div class="mx-xs mx-muted">{{ __('Dans la limite du tarif convenu avec l’établissement') }}</div>@endif
                    @if ($r['conditions'])<div class="mx-xs mx-muted">{{ $r['conditions'] }}</div>@endif</td>
                  <td class="mx-small">{{ $r['who'] }}</td>
                  <td><b>{{ $pct($r['rate']) }}</b></td>
                  <td>{{ $pct($r['copay']) }}</td>
                  <td class="mx-small">@if ($r['waiting_days'] > 0){{ $r['waiting_days'] }} j @if ($r['waiting_until'] && $r['waiting_until'] > now()->toDateString())<span style="color:var(--mx-warning)">(jusqu’au {{ \Illuminate\Support\Carbon::parse($r['waiting_until'])->format('d/m/Y') }})</span>@endif @else aucun @endif
                    @if ($r['emergency_exempt'] && $r['waiting_days'] > 0)<div class="mx-xs mx-muted">{{ __('pas en urgence') }}</div>@endif</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
    @endif

    <section class="mx-card mx-card__pad" style="margin-top:14px">
      <h2 class="mx-card__title" style="font-size:16px">{{ __('Plafonds') }}</h2>
      @forelse ($g['limits'] as $l)
        <p class="mx-small" style="margin:6px 0">• <b>{{ $l['label'] }}</b> : @if ($l['amount'] !== null){{ $fmt($l['amount']) }}@endif @if ($l['max_count'] !== null){{ $l['amount'] !== null ? 'et ' : '' }}{{ $l['max_count'] }} acte(s)@endif {{ $l['scope'] }}, {{ $l['holder'] }} <span class="mx-muted">— {{ $l['for'] }}</span></p>
      @empty
        <p class="mx-small mx-muted">{{ __('Aucun plafond.') }}</p>
      @endforelse
      <h2 class="mx-card__title" style="font-size:16px;margin-top:12px">{{ __('Soins non couverts') }}</h2>
      <p class="mx-small">{{ $g['exclusions'] ? implode(', ', $g['exclusions']) : 'Aucun soin exclu par votre formule.' }}</p>
      <h2 class="mx-card__title" style="font-size:16px;margin-top:12px">{{ __('Où me soigner ?') }}</h2>
      <p class="mx-small">{{ $g['network'] === null ? 'Dans tous les établissements conventionnés par votre mutuelle.' : 'Dans les ' . $g['network'] . ' établissement(s) du réseau de votre formule.' }} <a href="{{ route('social.member.providers', $mutual->slug) }}">{{ __('Voir la liste') }}</a></p>
      @if ($g['renewal_waiver'])<p class="mx-small">{{ __('En cas de renouvellement sans interruption, aucun nouveau délai d’attente.') }}</p>@endif
    </section>
  @endif
</x-layouts.social>
