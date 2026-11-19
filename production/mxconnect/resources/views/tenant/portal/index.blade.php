@php
  use App\Support\RightsStatus;
  $f = 'w-full rounded-md border border-ink/15 bg-white px-3 py-2.5 text-sm';
  $estimate = $estimate ?? null;
  $toneBox = ['ok' => 'border-pine/30 bg-mist', 'warn' => 'border-amber-200 bg-amber-50', 'ko' => 'border-clay/30 bg-clay/5'];
  $toneText = ['ok' => 'text-pine', 'warn' => 'text-amber-700', 'ko' => 'text-clay'];
  $icon = ['ok' => '✓', 'warn' => '!', 'ko' => '✕'];
  $fmt = fn ($v) => $currency->format((int) $v);
  $rel = ['spouse' => 'conjoint(e)', 'child' => 'enfant', 'ascendant' => 'ascendant', 'other' => 'autre'];
  $memberStatus = ['active' => 'Actif', 'suspended' => 'Suspendu', 'left' => 'Sorti', 'revoked' => 'Radié'];
  $rate = fn ($v) => $v === null ? 'non défini — à confirmer par la mutuelle' : rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',') . ' %';
@endphp
<x-layouts.tenant title="Vérifier les droits">
  @include('tenant.portal._head', ['title' => 'Vérifier les droits d’un bénéficiaire'])
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="space-y-4 print:hidden">
      {{-- 1. Par la carte : code imprimé ou QR --}}
      <form method="POST" action="{{ route('portal.verify') }}" class="space-y-3 rounded-lg border border-ink/10 bg-white p-5">
        @csrf
        <h2 class="font-semibold">Avec la carte de membre</h2>
        @if ($prefill)<p class="rounded-md bg-mist px-3 py-2 text-xs text-pine" role="status">Code transmis depuis votre espace prestataire : vérifiez-le puis cliquez sur « Vérifier les droits ».</p>@endif
        <label class="block text-sm font-medium" for="pv-code">Code adhérent ou lien de la carte</label>
        <input id="pv-code" name="code" required maxlength="500" autocomplete="off" value="{{ old('code', $prefill) }}" class="{{ $f }}" style="font-size:16px" placeholder="Ex. MUT-000123" @if($prefill) autofocus @endif>
        <p class="text-xs text-ink/55">Le code est imprimé sur la carte. Ou lisez le QR code de la carte :</p>
        <div data-mx-scan data-target="pv-code" class="space-y-2.5">
          <div class="flex flex-wrap gap-2">
            <button type="button" data-scan-camera style="display:none" class="rounded-md border border-pine px-3 py-2 text-sm font-medium text-pine hover:bg-mist">Scanner avec la caméra</button>
            <label class="cursor-pointer rounded-md border border-ink/15 px-3 py-2 text-sm font-medium text-ink/70 hover:bg-mist">Photo de la carte
              <input type="file" accept="image/*" capture="environment" data-scan-file class="sr-only"></label>
          </div>
          <div data-scan-box style="display:none" class="space-y-2.5">
            <video playsinline muted class="w-full rounded-md bg-ink" style="max-height:320px;object-fit:cover" aria-label="Aperçu de la caméra"></video>
            <button type="button" data-scan-stop class="text-sm text-ink/60 underline">Arrêter la caméra</button>
          </div>
          <p data-scan-status role="status" aria-live="polite" class="text-xs text-pine"></p>
        </div>
        <button class="w-full rounded-md bg-pine px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy" @disabled(! ($portal['act'] ?? false))>Vérifier les droits</button>
      </form>

      {{-- 2. Sans la carte : recherche encadrée (deux critères, journalisée) --}}
      <section class="rounded-lg border border-ink/10 bg-white p-5">
        <h2 class="font-semibold">Sans la carte : rechercher le bénéficiaire</h2>
        <p class="mt-1 text-xs text-ink/55">Deux critères sont toujours exigés. 5 résultats au plus, code masqué. Chaque recherche est enregistrée à votre nom et contrôlable par la mutuelle.</p>
        <details class="mt-3" @if(old('mode', $search['mode'] ?? 'name') === 'name' && ($search || old('mode'))) open @endif>
          <summary class="cursor-pointer text-sm font-medium text-pine">Par nom et date de naissance (adhérents et ayants droit)</summary>
          <form method="POST" action="{{ route('portal.search') }}" class="mt-3 grid gap-3 sm:grid-cols-2">@csrf
            <input type="hidden" name="mode" value="name">
            <label class="block text-sm font-medium">Nom de famille<input name="last_name" required maxlength="120" value="{{ old('last_name') }}" class="mt-1 {{ $f }}"></label>
            <label class="block text-sm font-medium">Prénom <span class="font-normal text-ink/50">(facultatif)</span><input name="first_name" maxlength="120" value="{{ old('first_name') }}" class="mt-1 {{ $f }}"></label>
            <label class="block text-sm font-medium">Date de naissance<input type="date" name="birth_date" required max="{{ now()->toDateString() }}" value="{{ old('birth_date') }}" class="mt-1 {{ $f }}"></label>
            <div class="flex items-end"><button class="w-full rounded-md border border-pine px-4 py-2.5 text-sm font-semibold text-pine hover:bg-mist" @disabled(! ($portal['act'] ?? false))>Rechercher</button></div>
          </form>
        </details>
        <details class="mt-3" @if(old('mode', $search['mode'] ?? '') === 'phone') open @endif>
          <summary class="cursor-pointer text-sm font-medium text-pine">Par téléphone et année de naissance (adhérents)</summary>
          <form method="POST" action="{{ route('portal.search') }}" class="mt-3 grid gap-3 sm:grid-cols-2">@csrf
            <input type="hidden" name="mode" value="phone">
            <label class="block text-sm font-medium">Téléphone<input name="phone" required inputmode="tel" maxlength="30" value="{{ old('phone') }}" class="mt-1 {{ $f }}"></label>
            <label class="block text-sm font-medium">Année de naissance<input name="birth_year" required inputmode="numeric" maxlength="4" value="{{ old('birth_year') }}" class="mt-1 {{ $f }}" placeholder="ex. 1985"></label>
            <div class="sm:col-span-2"><button class="w-full rounded-md border border-pine px-4 py-2.5 text-sm font-semibold text-pine hover:bg-mist" @disabled(! ($portal['act'] ?? false))>Rechercher</button></div>
          </form>
        </details>

        @if ($search)
          <div class="mt-4 border-t border-ink/10 pt-4" role="status">
            @if ($search['too_many'])
              <p class="text-sm text-amber-700">Trop de personnes correspondent : précisez la recherche (prénom, date de naissance complète) ou demandez la carte.</p>
            @elseif (empty($search['results']))
              <p class="text-sm text-clay"><b>BÉNÉFICIAIRE NON IDENTIFIÉ</b> — aucune personne ne correspond à ces critères. Vérifiez l’orthographe, ou orientez la personne vers la mutuelle.</p>
            @else
              <p class="text-sm font-medium">{{ count($search['results']) }} résultat(s) :</p>
              <ul class="mt-2 space-y-2.5">
                @foreach ($search['results'] as $r)
                  <li class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-ink/10 px-3 py-2 text-sm">
                    <span><b>{{ $r['name'] }}</b> <span class="text-xs text-ink/55">· né(e) en {{ $r['birth_year'] }} · {{ $r['quality'] }} · carte <span class="font-mono">{{ $r['code'] }}</span></span></span>
                    <form method="POST" action="{{ route('portal.pick') }}">@csrf<input type="hidden" name="pick" value="{{ $r['pick'] }}">
                      <button class="rounded-md bg-pine px-3 py-1.5 text-xs font-semibold text-white hover:bg-navy">Vérifier ses droits</button></form>
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        @endif
      </section>
      <p class="text-xs text-ink/45">Aucune donnée médicale n’est affichée. Chaque vérification est enregistrée au nom de votre compte. Sans connexion Internet, aucune vérification n’est possible : un acte urgent ne doit jamais être retardé pour autant.</p>
    </div>

    <div class="space-y-6">
      @if ($result)
        @php
          $st = $result['status']; $tone = RightsStatus::tone($st);
          $m = $result['member']; $dep = $result['dependent'] ?? null; $sit = $result['situation'];
          $person = $dep ?? $m;
        @endphp
        <section class="rounded-lg border p-5 {{ $toneBox[$tone] }}" role="status">
          <div class="flex items-start gap-3">
            <span class="text-3xl {{ $toneText[$tone] }}" aria-hidden="true">{{ $icon[$tone] }}</span>
            <div class="min-w-0 flex-1">
              <div class="text-lg font-bold {{ $toneText[$tone] }}">{{ RightsStatus::label($st) }}</div>
              <p class="mt-1 text-sm text-ink/70">{{ RightsStatus::HINTS[$st] }}</p>
              @if (! $result['ok'] && $result['reasons'])<ul class="mt-2 list-disc pl-5 text-sm text-ink/70">@foreach ($result['reasons'] as $r)<li>{{ __('mxconnect.rights.'.$r) }}</li>@endforeach</ul>@endif
              <p class="mt-2 text-xs text-ink/55">Vérifié le {{ $result['check']->created_at?->format('d/m/Y à H:i') }} (en direct, base de la mutuelle)@if (! empty($result['last'])) · vérification précédente par votre établissement : {{ $result['last']->created_at?->format('d/m/Y à H:i') }} ({{ $result['last']->ok ? 'droits ouverts' : 'non ouverts' }})@endif.</p>
            </div>
          </div>
        </section>

        @if ($m)
          <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
            <h2 class="font-semibold">Identité du bénéficiaire</h2>
            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
              <div><dt class="text-ink/50">Nom et prénom</dt><dd class="font-semibold">{{ mb_strtoupper($person->last_name) }} {{ $person->first_name }}</dd></div>
              <div><dt class="text-ink/50">Qualité</dt><dd>{{ $dep ? 'Ayant droit (' . ($rel[$dep->relationship] ?? $dep->relationship) . ') de ' . mb_strtoupper($m->last_name) . ' ' . $m->first_name : 'Adhérent principal' }}</dd></div>
              <div><dt class="text-ink/50">N° d’adhérent</dt><dd class="font-mono">{{ $m->member_code }}</dd></div>
              <div><dt class="text-ink/50">Date de naissance · sexe</dt><dd>{{ $person->birth_date?->format('d/m/Y') }} · {{ $person->sex === 'F' ? 'Féminin' : 'Masculin' }}</dd></div>
              <div><dt class="text-ink/50">Statut</dt><dd>{{ $dep ? ($dep->status === 'active' ? 'Ayant droit actif' : 'Ayant droit retiré') : ($memberStatus[$m->status->value ?? ''] ?? '—') }}</dd></div>
              <div><dt class="text-ink/50">Mutuelle d’affiliation</dt><dd>{{ tenant('name') }}</dd></div>
            </dl>
          </section>

          <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
            <h2 class="font-semibold">Situation mutualiste</h2>
            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
              <div><dt class="text-ink/50">Garantie</dt><dd>{{ $sit['guarantee'] ?? ($sit['pending_subscription'] ? 'Adhésion en attente de validation' : 'Aucune garantie en cours') }}@if($sit['version_valid_to']) <span class="text-xs text-ink/50">(valable jusqu’au {{ \Illuminate\Support\Carbon::parse($sit['version_valid_to'])->format('d/m/Y') }})</span>@endif</dd></div>
              <div><dt class="text-ink/50">Cotisations</dt><dd>@if ($sit['overdue_count'] > 0)<span class="text-clay">En retard : {{ $sit['overdue_count'] }} échéance(s) depuis le {{ \Illuminate\Support\Carbon::parse($sit['overdue_since'])->format('d/m/Y') }}</span>@if (! empty($sit['grace_until']))<span class="block text-xs text-ink/60">Délai de grâce : droits maintenus jusqu’au {{ \Illuminate\Support\Carbon::parse($sit['grace_until'])->format('d/m/Y') }}</span>@endif @else<span class="text-pine">À jour</span>@endif
                @if ($sit['paid_through'])<span class="block text-xs text-ink/50">Réglées jusqu’à l’échéance {{ $sit['paid_through']['label'] }} (due le {{ \Illuminate\Support\Carbon::parse($sit['paid_through']['due_date'])->format('d/m/Y') }})</span>@endif</dd></div>
              @if ($sit['observation_ends_on'])<div><dt class="text-ink/50">Fin de la période d’observation</dt><dd>{{ \Illuminate\Support\Carbon::parse($sit['observation_ends_on'])->format('d/m/Y') }}</dd></div>@endif
              @if ($sit['version'])
                @unless ($sit['catalog'] ?? null)
                <div><dt class="text-ink/50">Taux de couverture (part mutuelle)</dt><dd class="font-semibold">{{ $rate($sit['coverage_rate']) }}</dd></div>
                <div><dt class="text-ink/50">Ticket modérateur (part patient)</dt><dd>{{ $rate($sit['copay_rate']) }}</dd></div>
                @endunless
                <div><dt class="text-ink/50">Votre établissement</dt><dd>{{ $sit['in_network'] ? ($sit['network_open'] ? 'Accepté (réseau ouvert)' : 'Dans le réseau de la garantie') : 'Hors réseau de la garantie' }}</dd></div>
              @endif
            </dl>
            @if ($sit['catalog'] ?? null)
              <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink/50">Garanties pour ce bénéficiaire</h3>
              @if ($sit['catalog']['rules'])
                <ul class="mt-2 space-y-1">
                  @foreach ($sit['catalog']['rules'] as $g)
                    <li class="flex flex-wrap justify-between gap-2"><span>{{ $g['label'] }} <span class="text-xs text-ink/50">· {{ $g['category'] }}</span></span><span class="tabular-nums"><b>{{ $rate($g['rate']) }}</b>@if ($g['waiting_until']) · <span class="text-amber-700">carence jusqu’au {{ \Illuminate\Support\Carbon::parse($g['waiting_until'])->format('d/m/Y') }}</span>@endif</span></li>
                  @endforeach
                </ul>
              @else<p class="mt-1 text-ink/60">Aucune garantie ne s’applique à ce bénéficiaire.</p>@endif
            @endif
            @if ($sit['version'])
              <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink/50">Plafonds</h3>
              @if ($sit['ceilings'])
                <ul class="mt-2 space-y-1">
                  @foreach ($sit['ceilings'] as $c)
                    <li class="flex flex-wrap justify-between gap-2"><span>{{ $c['label'] }} — {{ $c['period'] }}</span><span class="tabular-nums">@if ($c['amount'] !== null){{ $fmt($c['amount']) }}@endif @if (($c['max_count'] ?? null) !== null) {{ $c['max_count'] }} acte(s)@endif @if($c['remaining'] !== null) · <b class="{{ $c['remaining'] <= 0 ? 'text-clay' : 'text-pine' }}">reste {{ $fmt($c['remaining']) }}</b>@endif @if (($c['remaining_count'] ?? null) !== null) · <b class="{{ $c['remaining_count'] <= 0 ? 'text-clay' : 'text-pine' }}">{{ $c['remaining_count'] }} restant(s)</b>@endif</span></li>
                  @endforeach
                </ul>
              @else<p class="mt-1 text-ink/60">Aucun plafond fixé par la garantie.</p>@endif
              <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink/50">Actes non couverts</h3>
              <p class="mt-1 text-ink/70">{{ $sit['exclusions'] ? implode(', ', $sit['exclusions']) : 'Aucune exclusion fixée par la garantie.' }}</p>
            @endif
          </section>
        @endif

        @if ($result['ok'])
          {{-- Estimation avant soins : rien n'est enregistré --}}
          <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
            <h2 class="font-semibold">Estimer la prise en charge</h2>
            <form method="POST" action="{{ route('portal.estimate', $result['check']->id) }}" class="mt-3 grid gap-3 sm:grid-cols-2 print:hidden">@csrf
              <label class="block text-sm font-medium">Acte prévu<select name="act_type_id" required class="mt-1 {{ $f }}"><option value="">—</option>@foreach ($acts as $a)<option value="{{ $a->id }}" @selected((int) old('act_type_id', $estimate['act']->id ?? 0) === $a->id)>{{ $a->label }}</option>@endforeach</select></label>
              <label class="block text-sm font-medium">Montant prévu ({{ $currency->code }})<input name="total" required inputmode="decimal" maxlength="20" value="{{ old('total') }}" class="mt-1 {{ $f }}" placeholder="Ex. 15 000"></label>
              @if ($sit['catalog'] ?? null)
                <div class="flex flex-wrap gap-x-4 gap-y-2 sm:col-span-2">
                  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="emergency" value="1" @checked(old('emergency', request('emergency')))> Urgence</label>
                  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="accident" value="1" @checked(old('accident', request('accident')))> Accident</label>
                </div>
              @endif
              <div class="sm:col-span-2"><button class="rounded-md border border-pine px-4 py-2 text-sm font-semibold text-pine hover:bg-mist">Calculer</button></div>
            </form>
            @if ($estimate)
              <div class="mt-4 rounded-md bg-mist p-4" id="estimation">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink/55">Estimation — {{ $estimate['act']?->label }}</p>
                @if (! empty($estimate['engine']))
                  <p class="mt-2 font-bold {{ $estimate['ok'] ? 'text-pine' : 'text-clay' }}">{{ $estimate['label'] }}</p>
                  @if ($estimate['guarantee_label'])<p class="text-xs text-ink/60">Garantie appliquée : {{ $estimate['guarantee_label'] }}</p>@endif
                  @if (! $estimate['ok'])
                    <ul class="mt-1 list-disc pl-5 text-xs text-ink/70">@foreach ($estimate['reasons'] as $r)<li>{{ __('mxconnect.rights.'.$r) }}</li>@endforeach</ul>
                    @if ($estimate['waiting_until'])<p class="mt-1 text-xs text-ink/70">Fin du délai de carence : {{ \Illuminate\Support\Carbon::parse($estimate['waiting_until'])->format('d/m/Y') }}.</p>@endif
                  @endif
                  @if ($estimate['exception'])<p class="mt-1 text-xs text-ink/60">Une dérogation accordée par la mutuelle est appliquée.</p>@endif
                @elseif ($estimate['excluded'])
                  <p class="mt-2 font-semibold text-clay">Acte non couvert par la garantie : la totalité est à la charge du patient.</p>
                @endif
                <dl class="mt-2 space-y-1">
                  <div class="flex justify-between gap-2"><dt>Montant des soins</dt><dd class="tabular-nums">{{ $fmt($estimate['total']) }}</dd></div>
                  <div class="flex justify-between gap-2"><dt>Part de la mutuelle @unless($estimate['excluded'])<span class="text-xs text-ink/50">({{ $rate($estimate['rate']) }}, plafonds appliqués)</span>@endunless</dt><dd class="font-semibold tabular-nums text-pine">{{ $fmt($estimate['mutual']) }}</dd></div>
                  <div class="flex justify-between gap-2"><dt>À payer par le patient</dt><dd class="font-semibold tabular-nums">{{ $fmt($estimate['patient']) }}</dd></div>
                  @unless ($estimate['excluded'])
                    <div class="flex justify-between gap-2 text-xs text-ink/60"><dt>dont part hors taux de couverture</dt><dd class="tabular-nums">{{ $fmt($estimate['copay']) }}</dd></div>
                  @endunless
                  <div class="flex justify-between gap-2 text-xs {{ $estimate['non_covered'] > 0 ? 'text-clay' : 'text-ink/60' }}"><dt>dont non couvert ({{ $estimate['excluded'] ? 'acte exclu' : 'au-delà du plafond' }})</dt><dd class="tabular-nums">{{ $fmt($estimate['non_covered']) }}</dd></div>
                  @if (! empty($estimate['engine']) && $estimate['limits'])
                    @foreach ($estimate['limits'] as $l)
                      <div class="flex justify-between gap-2 text-xs text-ink/60"><dt>{{ $l['label'] }} ({{ $l['scope'] }}, {{ $l['holder'] }})</dt><dd class="tabular-nums">@if ($l['remaining_before'] !== null){{ $fmt($l['remaining_before']) }} → {{ $fmt($l['remaining_after']) }}@endif @if ($l['remaining_count'] !== null) · {{ $l['remaining_count'] }} acte(s) restant(s)@endif</dd></div>
                    @endforeach
                  @elseif ($estimate['remaining_before'] !== null)
                    <div class="flex justify-between gap-2 text-xs text-ink/60"><dt>Plafond restant avant ces soins @if($estimate['remaining_after'] !== null) → après @endif</dt><dd class="tabular-nums">{{ $fmt($estimate['remaining_before']) }}@if($estimate['remaining_after'] !== null) → {{ $fmt($estimate['remaining_after']) }}@endif</dd></div>
                  @endif
                  <div class="flex justify-between gap-2 text-xs text-ink/60"><dt>Tarif conventionnel de l’acte</dt><dd class="tabular-nums">{{ $estimate['tariff'] ? $fmt($estimate['tariff']) : 'non défini par la mutuelle' }}</dd></div>
                </dl>
                @if ($estimate['above_tariff'])<p class="mt-2 text-xs text-amber-700">Le montant prévu dépasse le tarif conventionnel : la mutuelle examinera l’écart lors du contrôle.</p>@endif
                <p class="mt-3 text-xs text-ink/55">Estimation calculée le {{ now()->format('d/m/Y à H:i') }} selon la garantie en vigueur. Elle n’est ni un accord de prise en charge ni une facture : le montant définitif est fixé à la déclaration, puis validé par la mutuelle.</p>
                <button type="button" onclick="window.print()" class="mt-3 rounded-md border border-ink/15 px-3 py-1.5 text-xs font-medium hover:bg-mist print:hidden">Imprimer l’estimation</button>
              </div>
            @endif
          </section>

          <div class="print:hidden">
            @if ($portal['declare'] ?? true)
              <div class="flex flex-wrap gap-2">
                <a href="{{ route('portal.declare', $result['check']->id) }}" class="inline-block rounded-md bg-pine px-5 py-2.5 text-sm font-semibold text-white hover:bg-navy">Déclarer une prise en charge</a>
                <a href="{{ route('portal.pharmacy', $result['check']->id) }}" class="inline-block rounded-md border border-pine px-5 py-2.5 text-sm font-semibold text-pine hover:bg-mist">Délivrer une ordonnance</a>
                <a href="{{ route('portal.preauth.create', $result['check']->id) }}" class="inline-block rounded-md border border-ink/15 px-5 py-2.5 text-sm font-semibold text-ink/80 hover:bg-mist">Demander un accord préalable</a>
              </div>
              <p class="mt-2 text-xs text-ink/50">Déclaration possible pendant 12 heures à partir de cette vérification.</p>
            @else<p class="text-xs text-ink/50">Votre rôle dans l’établissement ne comprend pas la déclaration des soins.</p>@endif
          </div>
        @endif

        @if ($m && $result['history'] !== null)
          <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm print:hidden">
            <h2 class="font-semibold">Prises en charge de votre établissement pour ce bénéficiaire</h2>
            <p class="mt-1 text-xs text-ink/55">12 derniers mois, votre établissement uniquement. Les soins faits ailleurs ne sont jamais affichés.</p>
            <ul class="mt-2 divide-y divide-ink/5">
              @forelse ($result['history'] as $h)
                <li class="flex flex-wrap justify-between gap-2 py-2"><span>{{ $h->care_date?->format('d/m/Y') }} · {{ $h->actType?->label }}</span>
                  <span class="text-xs">{{ ['captured' => 'En attente', 'controlled' => 'En contrôle', 'validated' => 'Validée', 'rejected' => 'Rejetée'][$h->status] ?? $h->status }} · {{ $fmt($h->total_minor) }} (mutuelle {{ $fmt($h->mutual_part_minor) }})</span></li>
              @empty
                <li class="py-2 text-ink/50">Aucune prise en charge de votre établissement sur 12 mois.</li>
              @endforelse
            </ul>
          </section>
        @endif
      @endif

      <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm print:hidden">
        <h2 class="font-semibold">Mes dernières vérifications</h2>
        <ul class="mt-2 divide-y divide-ink/5">
          @forelse ($recent as $r)
            @php $who = $r->dependent ?? $r->member; @endphp
            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
              <span>{{ $who ? $who->last_name . ' ' . $who->first_name : 'Non identifié' }} <span class="text-xs text-ink/45">{{ $r->created_at?->format('d/m H:i') }}</span></span>
              <span class="text-xs {{ $r->ok ? 'text-pine' : 'text-clay' }}">{{ $r->ok ? 'Droits ouverts' : 'Non ouverts' }}</span>
            </li>
          @empty
            <li class="py-2 text-ink/50">Aucune vérification pour l’instant.</li>
          @endforelse
        </ul>
      </section>
    </div>
  </div>
  <x-slot:scripts>
    {{-- Servis PAR L'APPLICATION sur l'adresse de la mutuelle (même origine, politique de sécurité) :
         en production, le sous-domaine ne sert aucun fichier statique /js (correctif 2026-11-15). --}}
    <script src="/installer/fichiers/jsqr-1.4.0.min.js" defer></script>
    <script src="/installer/fichiers/mx-scan.js?v={{ config('mxconnect.asset_version') }}" defer></script>
  </x-slot:scripts>
</x-layouts.tenant>
