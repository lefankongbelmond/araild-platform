<x-layouts.tenant :title="$g->name">
  @php
    $canEdit = \App\Support\TenantAccess::can('formulas.rules.store');
    $f = 'mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm';
    $pct = fn ($x) => $x === null ? '—' : rtrim(rtrim(number_format((float) $x, 2, ',', ''), '0'), ',') . ' %';
    $bc = \App\Models\Tenant\CoverageRule::BENEFICIARY_CATEGORIES;
    $sim = $simulation;
  @endphp
  <a href="{{ route('formulas.index') }}" class="text-sm text-ink/50 hover:underline">{{ __('&larr; Catalogue des formules') }}</a>
  <x-tenant.hero icon="library" kicker="Formule · {{ $g->code }}" tone="brand" class="mt-2">
    <x-slot:title>{{ $g->name }}</x-slot:title>
    <x-slot:subtitle>
      {{ __(\App\Support\CatalogLabels::STATUSES[$g->catalog_status ?? 'active'] ?? $g->catalog_status) }}
      · version en cours V{{ $v->version_no }} depuis le {{ $v->valid_from?->format('d/m/Y') }}@if ($v->locked) (déjà utilisée : toute modification ouvre une nouvelle version)@endif
      · cotisation {{ $currency->format($v->base_contribution_minor) }} / {{ \App\Support\CatalogLabels::periodicity($v) }}
    </x-slot:subtitle>
    <x-slot:actions>
      @if ($g->is_example)<span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">{{ __('Exemple') }}</span>@endif
      <a href="{{ route('guarantees.show', $g) }}" class="inline-flex h-10 items-center rounded-xl border border-ink/10 bg-white px-4 text-sm font-semibold text-[#0B1F3A] hover:bg-paper">{{ __('Cotisation, réseau, versions') }}</a>
    </x-slot:actions>
  </x-tenant.hero>

  @if ($errors->any())
    <div class="mt-4 rounded-lg border border-clay/30 bg-white p-4 text-sm text-clay" role="alert">
      <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="min-w-0 space-y-6 lg:col-span-2">

      {{-- GARANTIES --}}
      <section class="rounded-lg border border-ink/10 bg-white p-5">
        <h2 class="font-semibold">{{ __('Garanties de la version V:version_no', ['version_no' => $v->version_no]) }}</h2>
        <p class="mt-1 text-xs text-ink/55">{{ __('Pour un acte, le moteur retient la garantie de sa catégorie de soins (sinon « toutes les prestations ») et la catégorie de bénéficiaire la plus précise (nouveau conjoint, élève, enfant, senior, adulte, puis tous).') }}</p>
        @if ($v->rules->isEmpty())
          <p class="mt-3 rounded-md bg-mist p-3 text-sm text-ink/70">{{ __('Aucune garantie détaillée : cette formule applique le taux unique de :coverage_rate, ses plafonds et sa période d’observation (page « Garanties »). Ajouter une garantie fait passer cette version au calcul détaillé.', ['coverage_rate' => $pct($v->coverage_rate)]) }}</p>
        @else
          <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="text-left text-ink/55"><tr><th class="py-2 pr-3 font-medium">{{ __('Garantie') }}</th><th class="py-2 pr-3 font-medium">{{ __('Bénéficiaires') }}</th><th class="py-2 pr-3 font-medium">{{ __('Taux') }}</th><th class="py-2 pr-3 font-medium">{{ __('Options') }}</th><th></th></tr></thead>
              <tbody class="divide-y divide-ink/5">
                @foreach ($v->rules as $r)
                  <tr class="align-top">
                    <td class="py-2 pr-3"><b>{{ $r->label }}</b><div class="text-xs text-ink/50">{{ $r->category?->label ?? __('Toutes les prestations') }}</div>@unless ($r->active)<span class="text-xs text-clay">{{ __('inactive') }}</span>@endunless</td>
                    <td class="py-2 pr-3">{{ $bc[$r->beneficiary_category] ?? $r->beneficiary_category }}</td>
                    <td class="py-2 pr-3"><b>{{ $pct($r->coverage_rate) }}</b>@if ($r->copay_rate !== null)<div class="text-xs text-ink/50">{{ __('TM :copay_rate', ['copay_rate' => $pct($r->copay_rate)]) }}</div>@endif</td>
                    <td class="py-2 pr-3 text-xs text-ink/60">{{ $r->emergency_exempt ? __('Urgence sans carence') : __('Carence même en urgence') }}@if ($r->accident_waiting_days !== null) · accident : {{ $r->accident_waiting_days }} j @endif @if ($r->tariff_cap) · tarif conventionnel @endif @if ($r->requires_authorization) · <b class="text-amber-700">{{ __('accord préalable') }}</b> ({{ $r->authorization_review === 'medical' ? __('médical') : __('administratif') }}@if ($r->authorization_above_minor !== null), au-delà de {{ $currency->format($r->authorization_above_minor) }}@endif) @endif</td>
                    <td class="py-2 text-right">
                      @if ($canEdit)
                        <details class="text-left"><summary class="cursor-pointer text-xs font-semibold text-pine">{{ __('Modifier') }}</summary>
                          <form method="POST" action="{{ route('formulas.rules.update', [$g, $r]) }}" class="mt-2 grid w-64 gap-2">@csrf
                            @include('tenant.formulas._rule-fields', ['r' => $r])
                            @include('tenant.formulas._effective')
                            <button class="rounded-md bg-pine px-3 py-2 text-xs font-semibold text-white hover:bg-navy">{{ __('Enregistrer') }}</button>
                          </form>
                          <form method="POST" action="{{ route('formulas.rules.destroy', [$g, $r]) }}" class="mt-2 grid w-64 gap-2" onsubmit="return confirm('Retirer cette garantie ? Ses délais de carence sont retirés aussi.')">@csrf
                            @include('tenant.formulas._effective')
                            <button class="rounded-md border border-clay/40 px-3 py-2 text-xs font-semibold text-clay hover:bg-mist">{{ __('Retirer la garantie') }}</button>
                          </form>
                        </details>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
        @if ($canEdit)
          <details class="mt-4 rounded-md border border-ink/10 p-3" @if ($errors->has('rule')) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-pine">{{ __('+ Ajouter une garantie') }}</summary>
            <form method="POST" action="{{ route('formulas.rules.store', $g) }}" class="mt-3 grid gap-3 sm:grid-cols-2">@csrf
              @include('tenant.formulas._rule-fields', ['r' => null])
              @include('tenant.formulas._effective')
              <div class="sm:col-span-2"><button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">{{ __('Ajouter') }}</button></div>
            </form>
          </details>
        @endif
      </section>

      {{-- PLAFONDS --}}
      <section class="rounded-lg border border-ink/10 bg-white p-5">
        <h2 class="font-semibold">{{ __('Plafonds') }}</h2>
        <p class="mt-1 text-xs text-ink/55">{{ __('Part de la mutuelle au plus, et/ou nombre d’actes au plus, par acte, jour, mois ou année civile, pour chaque bénéficiaire ou pour toute la famille. Les prestations saisies, contrôlées et validées consomment le plafond ; une prestation rejetée le libère.') }}</p>
        @if ($v->limits->isEmpty())
          <p class="mt-3 text-sm text-ink/60">{{ __('Aucun plafond détaillé.') }}</p>
        @else
          <ul class="mt-3 divide-y divide-ink/5 text-sm">
            @foreach ($v->limits as $l)
              <li class="flex flex-wrap items-start justify-between gap-2 py-2">
                <div class="min-w-0"><b>{{ $l->label ?: __('Plafond') }}</b>@unless ($l->active) <span class="text-xs text-clay">{{ __('inactif') }}</span>@endunless
                  <div class="text-xs text-ink/60">
                    @if ($l->amount_minor !== null){{ $currency->format($l->amount_minor) }}@endif @if ($l->amount_minor !== null && $l->max_count) et @endif @if ($l->max_count){{ $l->max_count }} acte(s)@endif
                    {{ __(\App\Models\Tenant\CoverageLimit::SCOPES[$l->scope] ?? $l->scope) }}, {{ __(\App\Models\Tenant\CoverageLimit::HOLDERS[$l->holder] ?? $l->holder) }}
                    · {{ $l->rule_keys ? (count($l->rule_keys) > 1 ? 'partagé : ' : '') . collect($l->rule_keys)->map(fn ($k) => $ruleNames[$k] ?? '?')->implode(', ') : __('toutes les garanties') }}
                  </div>
                </div>
                @if ($canEdit)
                  <details class="text-left"><summary class="cursor-pointer text-xs font-semibold text-pine">{{ __('Modifier') }}</summary>
                    <form method="POST" action="{{ route('formulas.limits.update', [$g, $l]) }}" class="mt-2 grid w-64 gap-2">@csrf
                      @include('tenant.formulas._limit-fields', ['l' => $l])
                      @include('tenant.formulas._effective')
                      <button class="rounded-md bg-pine px-3 py-2 text-xs font-semibold text-white hover:bg-navy">{{ __('Enregistrer') }}</button>
                    </form>
                    <form method="POST" action="{{ route('formulas.limits.destroy', [$g, $l]) }}" class="mt-2 grid w-64 gap-2" onsubmit="return confirm('Retirer ce plafond ?')">@csrf
                      @include('tenant.formulas._effective')
                      <button class="rounded-md border border-clay/40 px-3 py-2 text-xs font-semibold text-clay hover:bg-mist">{{ __('Retirer le plafond') }}</button>
                    </form>
                  </details>
                @endif
              </li>
            @endforeach
          </ul>
        @endif
        @if ($canEdit)
          <details class="mt-4 rounded-md border border-ink/10 p-3" @if ($errors->has('limit')) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-pine">{{ __('+ Ajouter un plafond') }}</summary>
            <form method="POST" action="{{ route('formulas.limits.store', $g) }}" class="mt-3 grid gap-3 sm:grid-cols-2">@csrf
              @include('tenant.formulas._limit-fields', ['l' => null])
              @include('tenant.formulas._effective')
              <div class="sm:col-span-2"><button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">{{ __('Ajouter') }}</button></div>
            </form>
          </details>
        @endif
      </section>

      {{-- CARENCES --}}
      <section class="rounded-lg border border-ink/10 bg-white p-5">
        <h2 class="font-semibold">{{ __('Délais de carence') }}</h2>
        <p class="mt-1 text-xs text-ink/55">{{ __('Le moteur retient le délai le plus précis (garantie + catégorie de bénéficiaire), sinon le délai « toutes garanties », sinon la période d’observation de la version (:observation_days jour(s)). Point de départ : la date d’effet de la souscription, ou l’arrivée de l’ayant droit ; avec le renouvellement continu, la date de première adhésion.', ['observation_days' => $v->observation_days]) }}</p>
        @if ($v->waitingPeriods->isEmpty())
          <p class="mt-3 text-sm text-ink/60">{{ __('Aucun délai détaillé.') }}</p>
        @else
          <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="text-left text-ink/55"><tr><th class="py-2 pr-3 font-medium">{{ __('Garantie') }}</th><th class="py-2 pr-3 font-medium">{{ __('Bénéficiaires') }}</th><th class="py-2 pr-3 font-medium">{{ __('Délai') }}</th><th></th></tr></thead>
              <tbody class="divide-y divide-ink/5">
                @foreach ($v->waitingPeriods->sortBy(fn ($w) => ($ruleNames[$w->rule_key] ?? '') . $w->beneficiary_category) as $w)
                  <tr>
                    <td class="py-2 pr-3">{{ $w->rule_key ? ($ruleNames[$w->rule_key] ?? '?') : __('Toutes les garanties') }}</td>
                    <td class="py-2 pr-3">{{ $bc[$w->beneficiary_category] ?? $w->beneficiary_category }}</td>
                    <td class="py-2 pr-3"><b>{{ __(':days j', ['days' => $w->days]) }}</b></td>
                    <td class="py-2 text-right">@if ($canEdit)
                      <form method="POST" action="{{ route('formulas.waiting.destroy', [$g, $w]) }}" onsubmit="return confirm('Retirer ce délai ?')">@csrf
                        @if ($v->locked)<input type="hidden" name="effective_from" value="{{ now()->addDay()->toDateString() }}">@endif
                        <button class="text-xs font-semibold text-clay hover:underline">{{ __('Retirer') }}</button></form>@endif</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          @if ($canEdit && $v->locked)<p class="mt-1 text-xs text-ink/50">{{ __('Retirer un délai d’une version utilisée crée une nouvelle version applicable demain.') }}</p>@endif
        @endif
        @if ($canEdit)
          <details class="mt-4 rounded-md border border-ink/10 p-3">
            <summary class="cursor-pointer text-sm font-semibold text-pine">{{ __('+ Ajouter ou modifier un délai') }}</summary>
            <form method="POST" action="{{ route('formulas.waiting.store', $g) }}" class="mt-3 grid gap-3 sm:grid-cols-2">@csrf
              <label class="block text-sm font-medium">{{ __('Garantie') }}<select name="rule_key" class="{{ $f }}"><option value="">{{ __('Toutes les garanties') }}</option>@foreach ($v->rules as $r)<option value="{{ $r->rule_key }}">{{ $r->label }} ({{ $bc[$r->beneficiary_category] }})</option>@endforeach</select></label>
              <label class="block text-sm font-medium">{{ __('Bénéficiaires') }}<select name="beneficiary_category" class="{{ $f }}">@foreach ($bc as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></label>
              <label class="block text-sm font-medium">{{ __('Délai (jours) *') }}<input name="days" required inputmode="numeric" class="{{ $f }}"></label>
              @include('tenant.formulas._effective')
              <div class="sm:col-span-2"><button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">{{ __('Enregistrer') }}</button></div>
            </form>
          </details>
        @endif
      </section>

      {{-- SIMULATEUR --}}
      <section id="simulateur" class="rounded-lg border border-ink/10 bg-white p-5">
        <h2 class="font-semibold">{{ __('Simulateur') }}</h2>
        <p class="mt-1 text-xs text-ink/55">{{ __('Calcule une prise en charge à partir des paramètres, sans adhérent et sans rien enregistrer. Utile pour vérifier une formule avant de la proposer.') }}</p>
        <form method="POST" action="{{ route('formulas.simulate', $g) }}" class="mt-3 grid gap-3 sm:grid-cols-2">@csrf
          <label class="block text-sm font-medium">{{ __('Version') }}<select name="version_id" class="{{ $f }}">@foreach ($versions as $ver)<option value="{{ $ver->id }}" @selected((int) old('version_id', $v->id) === $ver->id)>V{{ $ver->version_no }} ({{ $ver->valid_from?->format('d/m/Y') }}{{ $ver->valid_to ? ' → ' . $ver->valid_to->format('d/m/Y') : '' }})</option>@endforeach</select></label>
          <label class="block text-sm font-medium">{{ __('Acte *') }}<select name="act_type_id" required class="{{ $f }}"><option value="">—</option>@foreach ($acts as $a)<option value="{{ $a->id }}" @selected((int) old('act_type_id') === $a->id)>{{ $a->label }}</option>@endforeach</select></label>
          <label class="block text-sm font-medium">{{ __('Montant facturé (:currency) *', ['currency' => $currency->code]) }}<input name="amount" required inputmode="decimal" value="{{ old('amount') }}" class="{{ $f }}"></label>
          <label class="block text-sm font-medium">{{ __('Âge du bénéficiaire *') }}<input name="age" required inputmode="numeric" value="{{ old('age') }}" class="{{ $f }}"></label>
          <label class="block text-sm font-medium">{{ __('Couvert depuis (jours) *') }}<input name="days" required inputmode="numeric" value="{{ old('days', 365) }}" class="{{ $f }}"></label>
          <div class="grid grid-cols-2 gap-2">
            <label class="block text-sm font-medium">{{ __('Déjà consommé (:currency)', ['currency' => $currency->code]) }}<input name="used" inputmode="decimal" value="{{ old('used') }}" class="{{ $f }}"></label>
            <label class="block text-sm font-medium">{{ __('Actes déjà faits') }}<input name="used_count" inputmode="numeric" value="{{ old('used_count') }}" class="{{ $f }}"></label>
          </div>
          <div class="flex flex-wrap gap-x-4 gap-y-2 sm:col-span-2">
            @foreach (['student' => 'Élève', 'new_spouse' => 'Nouveau conjoint', 'emergency' => 'Urgence', 'accident' => 'Accident'] as $k => $t)
              <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="{{ $k }}" value="1" @checked(old($k))> {{ $t }}</label>
            @endforeach
          </div>
          <div class="sm:col-span-2"><button class="rounded-md border border-pine px-4 py-2 text-sm font-semibold text-pine hover:bg-mist">{{ __('Simuler') }}</button></div>
        </form>
        @if ($sim)
          <div class="mt-4 rounded-md bg-mist p-4 text-sm" role="status">
            <p class="font-bold {{ $sim['ok'] ? 'text-pine' : 'text-clay' }}">{{ $sim['label'] }}</p>
            @if ($sim['rule'])<p class="text-xs text-ink/60">{{ __('Garantie appliquée : :rule (:rate)', ['rule' => $sim['rule'], 'rate' => $pct($sim['rate'])]) }}</p>@endif
            @if (! $sim['ok'])<ul class="mt-1 list-disc pl-5 text-xs text-ink/70">@foreach ($sim['reasons'] as $why)<li>{{ __('mxconnect.rights.' . $why) }}</li>@endforeach</ul>@endif
            @if ($sim['waiting_until'])<p class="text-xs text-ink/70">{{ __('Fin du délai de carence (:waiting_days j) : :date', ['waiting_days' => $sim['waiting_days'], 'date' => \Illuminate\Support\Carbon::parse($sim['waiting_until'])->format('d/m/Y')]) }}</p>@endif
            <dl class="mt-2 space-y-1">
              <div class="flex justify-between gap-2"><dt>{{ __('Montant facturé') }}</dt><dd class="tabular-nums">{{ $currency->format($sim['billed']) }}</dd></div>
              <div class="flex justify-between gap-2"><dt>{{ __('Montant éligible') }}</dt><dd class="tabular-nums">{{ $currency->format($sim['eligible']) }}</dd></div>
              <div class="flex justify-between gap-2"><dt>{{ __('Part de la mutuelle') }}</dt><dd class="font-semibold tabular-nums text-pine">{{ $currency->format($sim['mutual']) }}</dd></div>
              <div class="flex justify-between gap-2"><dt>{{ __('Ticket modérateur') }}</dt><dd class="tabular-nums">{{ $currency->format($sim['copay']) }}</dd></div>
              <div class="flex justify-between gap-2"><dt>{{ __('Non couvert (hors tarif, au-delà du plafond, refus)') }}</dt><dd class="tabular-nums">{{ $currency->format($sim['non_covered']) }}</dd></div>
              <div class="flex justify-between gap-2"><dt>{{ __('À payer par le patient') }}</dt><dd class="font-semibold tabular-nums">{{ $currency->format($sim['patient']) }}</dd></div>
              @foreach ($sim['limits'] as $lv)
                <div class="flex justify-between gap-2 text-xs text-ink/60"><dt>{{ $lv['label'] }} ({{ $lv['scope'] }})</dt><dd class="tabular-nums">@if ($lv['remaining_before'] !== null){{ $currency->format($lv['remaining_before']) }} → {{ $currency->format($lv['remaining_after']) }}@endif @if ($lv['remaining_count'] !== null) · {{ $lv['remaining_count'] }} acte(s) restant(s)@endif</dd></div>
              @endforeach
            </dl>
          </div>
        @endif
      </section>
    </div>

    <div class="space-y-6 self-start">
      {{-- FICHE --}}
      <section class="rounded-lg border border-ink/10 bg-white p-5">
        <h2 class="font-semibold">{{ __('Fiche de la formule') }}</h2>
        @if ($canEdit)
          <form method="POST" action="{{ route('formulas.update', $g) }}" class="mt-3 space-y-3">@csrf
            <label class="block text-sm font-medium">{{ __('Nom *') }}<input name="name" required maxlength="120" value="{{ old('name', $g->name) }}" class="{{ $f }}"></label>
            <label class="block text-sm font-medium">{{ __('Statut') }}<select name="catalog_status" class="{{ $f }}">@foreach (\App\Support\CatalogLabels::STATUSES as $k => $t)<option value="{{ $k }}" @selected(old('catalog_status', $g->catalog_status ?? 'active') === $k)>{{ __($t) }}</option>@endforeach</select></label>
            <p class="text-xs text-ink/50">{{ __('Seules les formules « Active » (et « En test » dans la mutuelle de test) sont proposées aux nouvelles souscriptions. Les adhésions en cours ne changent pas.') }}</p>
            <label class="block text-sm font-medium">{{ __('Catégorie') }}<input name="category" maxlength="60" value="{{ old('category', $g->category) }}" class="{{ $f }}" placeholder="{{ __('Ex. Familiale, Scolaire') }}"></label>
            <label class="block text-sm font-medium">{{ __('Population cible') }}<input name="target_population" maxlength="255" value="{{ old('target_population', $g->target_population) }}" class="{{ $f }}"></label>
            <div class="grid grid-cols-2 gap-2">
              <label class="block text-sm font-medium">{{ __('Âge minimal') }}<input name="min_age" inputmode="numeric" value="{{ old('min_age', $g->min_age) }}" class="{{ $f }}"></label>
              <label class="block text-sm font-medium">{{ __('Âge maximal') }}<input name="max_age" inputmode="numeric" value="{{ old('max_age', $g->max_age) }}" class="{{ $f }}"></label>
            </div>
            <label class="block text-sm font-medium">{{ __('Bénéficiaires au plus (adhérent compris)') }}<input name="max_beneficiaries" inputmode="numeric" value="{{ old('max_beneficiaries', $g->max_beneficiaries) }}" class="{{ $f }}"></label>
            <div class="grid grid-cols-2 gap-2">
              <label class="block text-sm font-medium">{{ __('Vendue du') }}<input type="date" name="sale_from" value="{{ old('sale_from', $g->sale_from) }}" class="{{ $f }}"></label>
              <label class="block text-sm font-medium">{{ __('au') }}<input type="date" name="sale_to" value="{{ old('sale_to', $g->sale_to) }}" class="{{ $f }}"></label>
            </div>
            <label class="block text-sm font-medium">{{ __('Description') }}<textarea name="description" rows="3" maxlength="2000" class="{{ $f }}">{{ old('description', $g->description) }}</textarea></label>
            <button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">{{ __('Enregistrer la fiche') }}</button>
          </form>
        @else
          <dl class="mt-3 space-y-1 text-sm">
            <div><dt class="text-ink/50">{{ __('Population cible') }}</dt><dd>{{ $g->target_population ?: '—' }}</dd></div>
            <div><dt class="text-ink/50">{{ __('Âges') }}</dt><dd>{{ __(':min_age à :max_age ans', ['min_age' => $g->min_age ?? '—', 'max_age' => $g->max_age ?? '—']) }}</dd></div>
            <div><dt class="text-ink/50">{{ __('Bénéficiaires au plus') }}</dt><dd>{{ $g->max_beneficiaries ?? __('sans limite') }}</dd></div>
            <div><dt class="text-ink/50">{{ __('Description') }}</dt><dd>{{ $g->description ?: '—' }}</dd></div>
          </dl>
        @endif
      </section>

      {{-- PARAMÈTRES DE LA VERSION --}}
      <section class="rounded-lg border border-ink/10 bg-white p-5">
        <h2 class="font-semibold">{{ __('Paramètres de la version V:version_no', ['version_no' => $v->version_no]) }}</h2>
        @if ($canEdit)
          <form method="POST" action="{{ route('formulas.params', $g) }}" class="mt-3 grid gap-3">@csrf
            <label class="block text-sm font-medium">{{ __('Âge « senior » (à partir de)') }}<input name="senior_age" inputmode="numeric" value="{{ old('senior_age', $v->senior_age) }}" class="{{ $f }}" placeholder="{{ __('Non défini : pas de catégorie senior') }}"></label>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="renewal_waiver" value="1" class="mt-1" @checked($v->renewal_waiver)> {{ __('Renouvellement continu : pas de nouvelle carence pour un adhérent déjà couvert (carence comptée depuis sa première adhésion)') }}</label>
            @include('tenant.formulas._effective')
            <button class="rounded-md border border-pine px-4 py-2 text-sm font-semibold text-pine hover:bg-mist">{{ __('Enregistrer') }}</button>
          </form>
        @else
          <p class="mt-2 text-sm">Âge senior : {{ $v->senior_age ? $v->senior_age . ' ans' : __('non défini') }} · renouvellement continu : {{ $v->renewal_waiver ? __('oui') : __('non') }}</p>
        @endif
      </section>

      {{-- VERSIONS --}}
      <section class="rounded-lg border border-ink/10 bg-white p-5">
        <h2 class="font-semibold">{{ __('Versions') }}</h2>
        <ul class="mt-2 space-y-2.5 text-sm">
          @foreach ($versions as $ver)
            <li><b>{{ __('V:version_no', ['version_no' => $ver->version_no]) }}</b> <span class="text-xs text-ink/55">{{ $ver->valid_from?->format('d/m/Y') }} → {{ $ver->valid_to?->format('d/m/Y') ?? '…' }} · {{ $ver->rules_count }} garantie(s)@if ($ver->locked) · utilisée @endif</span>
              @if ($ver->change_note)<div class="text-xs text-ink/60">{{ $ver->change_note }}</div>@endif</li>
          @endforeach
        </ul>
        <p class="mt-2 text-xs text-ink/50">{{ __('Une prestation est toujours calculée avec la version en vigueur à la date des soins.') }}</p>
      </section>
    </div>
  </div>
</x-layouts.tenant>
