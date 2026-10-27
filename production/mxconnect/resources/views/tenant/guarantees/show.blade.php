<x-layouts.tenant :title="$guarantee->name">
  <a href="{{ route('guarantees.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.back') }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ $guarantee->name }} <span class="text-sm text-ink/40">{{ $guarantee->code }}</span></h1>
  <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.guarantee.history_sub') }}</p>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    {{-- Version history: past versions stay valid for claims dated within their window --}}
    <div class="min-w-0 lg:col-span-2 space-y-3">
      @foreach ($guarantee->versions as $v)
        <div class="rounded-lg border {{ is_null($v->valid_to) ? 'border-pine/40 bg-pine/5' : 'border-ink/10 bg-white' }} p-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="font-medium">V{{ $v->version_no }}
              @if (is_null($v->valid_to))<span class="ml-2 rounded-full bg-pine px-2 py-0.5 text-xs text-white">{{ __('mxconnect.guarantee.current') }}</span>@endif
              @if ($v->locked)<span class="ml-2 rounded-full bg-ink/60 px-2 py-0.5 text-xs text-white">{{ __('mxconnect.guarantee.locked') }}</span>@endif
            </div>
            <div class="text-xs text-ink/50">{{ $v->valid_from?->format('d/m/Y') }} → {{ $v->valid_to?->format('d/m/Y') ?? '…' }}</div>
          </div>
          <div class="mt-2 grid gap-2 text-sm text-ink/70 sm:grid-cols-3">
            <div>{{ __('mxconnect.guarantee.contribution') }}: <b>{{ $currency->format($v->base_contribution_minor) }}</b> / {{ $v->periodicity }}</div>
            <div>{{ __('mxconnect.guarantee.coverage') }}: <b>{{ $v->coverage_rate }}%</b></div>
            <div>{{ __('mxconnect.guarantee.copay') }}: <b>{{ $v->copay_rate }}%</b></div>
            <div>{{ __('mxconnect.guarantee.observation') }}: <b>{{ $v->observation_days }} {{ __('mxconnect.guarantee.days') }}</b></div>
          </div>
          <div class="mt-2 text-sm text-ink/70">Réseau de soins :
            @if (is_array($v->provider_network))<b>{{ collect($v->provider_network)->map(fn ($id) => $providers[$id] ?? 'Prestataire n° ' . $id)->implode(', ') }}</b>
            @else<b>tous les prestataires</b>@endif
          </div>
          @php $exNames = $acts->whereIn('id', (array) $v->exclusions)->pluck('label'); @endphp
          <div class="mt-1 text-sm text-ink/70">Actes non couverts : <b>{{ $exNames->isNotEmpty() ? $exNames->implode(', ') : 'aucun' }}</b></div>
          <div class="mt-1 text-sm text-ink/70">Plafonds :
            @forelse ($v->ceilings as $c)<b>{{ $c->actType?->label ?? 'tous les actes' }} : {{ $currency->format($c->ceiling_minor) }} {{ \App\Models\Tenant\Ceiling::PERIODS[$c->period] ?? $c->period }}</b>@if (! $loop->last) ; @endif
            @empty<b>aucun</b>@endforelse
          </div>
        </div>
      @endforeach
    </div>

    <div class="space-y-6 self-start">
    @php $current = $guarantee->versions->firstWhere('valid_to', null); @endphp
    @if ($current && \App\Support\TenantAccess::can('guarantees.network'))
    {{-- Réseau de soins : prestataires acceptés pour cette garantie --}}
    <form method="POST" action="{{ route('guarantees.network', $guarantee) }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3">
      @csrf
      <h2 class="font-medium">Réseau de soins</h2>
      <p class="text-xs text-ink/55">Prestataires où les adhérents de cette garantie sont pris en charge. Hors réseau, la vérification des droits répond « Prestataire non conventionné ».</p>
      @if ($errors->hasAny(['mode', 'providers', 'providers.*', 'effective_from']))<p class="text-xs text-clay">{{ $errors->first('providers') ?: $errors->first('providers.*') ?: $errors->first('effective_from') ?: $errors->first('mode') }}</p>@endif
      @php $restricted = old('mode', is_array($current->provider_network) ? 'list' : 'all') === 'list'; $sel = old('providers', $current->provider_network ?? []); @endphp
      <label class="flex items-start gap-2 text-sm"><input type="radio" name="mode" value="all" class="mt-1" @checked(! $restricted)> <span><b>Tous les prestataires</b><span class="block text-xs text-ink/55">Aucune restriction de réseau.</span></span></label>
      <label class="flex items-start gap-2 text-sm"><input type="radio" name="mode" value="list" class="mt-1" @checked($restricted)> <span><b>Seulement ces prestataires conventionnés</b></span></label>
      <div class="space-y-1 pl-6">
        @forelse ($conventions as $pid => $c)
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="providers[]" value="{{ $pid }}" @checked(in_array($pid, array_map('intval', (array) $sel), true))> {{ $providers[$pid] ?? 'Prestataire n° ' . $pid }}@unless ($c->active)<span class="text-xs text-clay">(convention suspendue)</span>@endunless</label>
        @empty
          <p class="text-xs text-ink/55">Aucun prestataire conventionné : ajoutez-en dans <a class="text-pine underline" href="{{ route('providers.index') }}">Prestataires conventionnés</a>.</p>
        @endforelse
      </div>
      @if ($current->locked)
        <label class="block text-sm">Applicable à partir du
          <input name="effective_from" type="date" required min="{{ now()->toDateString() }}" value="{{ old('effective_from', now()->addDay()->toDateString()) }}" class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm"></label>
        <p class="text-xs text-ink/50">La version V{{ $current->version_no }} a déjà servi à des recours : elle reste inchangée. Le nouveau réseau ouvre une version V{{ $current->version_no + 1 }}, avec les mêmes conditions financières.</p>
      @endif
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">Enregistrer le réseau</button>
    </form>
    @endif

    @can('manage-parameters')
    {{-- Roll a new version: closes the current one, opens the successor --}}
    <form method="POST" action="{{ route('guarantees.version', $guarantee) }}" class="rounded-lg border border-ink/10 bg-white p-4 space-y-3">
      @csrf
      <h2 class="font-medium">{{ __('mxconnect.guarantee.new_version') }}</h2>
      <p class="text-xs text-ink/50">{{ __('mxconnect.guarantee.new_version_note') }} Le réseau de soins, les exclusions et les plafonds sont repris.</p>
      <input name="base_contribution" type="number" step="any" min="0" placeholder="{{ __('mxconnect.guarantee.contribution') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <select name="periodicity" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        <option value="monthly">{{ __('mxconnect.periodicity.monthly') }}</option>
        <option value="quarterly">{{ __('mxconnect.periodicity.quarterly') }}</option>
        <option value="annual">{{ __('mxconnect.periodicity.annual') }}</option>
      </select>
      <div class="flex gap-2">
        <input name="coverage_rate" type="number" step="any" min="0" max="100" placeholder="{{ __('mxconnect.guarantee.coverage') }} %" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
        <input name="copay_rate" type="number" step="any" min="0" max="100" placeholder="{{ __('mxconnect.guarantee.copay') }} %" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      </div>
      <input name="membership_fee" type="number" step="any" min="0" value="0" placeholder="{{ __('mxconnect.guarantee.membership_fee') }}" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <input name="observation_days" type="number" min="0" value="0" placeholder="{{ __('mxconnect.guarantee.observation') }}" required class="w-full rounded-md border-ink/20 px-3 py-2 text-sm">
      <label class="block text-sm">{{ __('mxconnect.guarantee.effective_from') }}
        <input name="effective_from" type="date" required class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm"></label>
      @error('effective_from')<p class="text-xs text-clay">{{ $message }}</p>@enderror
      <button class="w-full rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.guarantee.roll') }}</button>
    </form>
    @endcan
    </div>
  </div>

  @if ($current && \App\Support\TenantAccess::can('guarantees.terms'))
    @php
      $raw = fn ($minor) => rtrim(rtrim(number_format($minor / (10 ** $currency->minor_unit), $currency->minor_unit, '.', ''), '0'), '.');
      $rows = old('ceilings', $current->ceilings->map(fn ($c) => ['act_type_id' => $c->act_type_id, 'amount' => $raw($c->ceiling_minor), 'period' => $c->period])->all());
      $rows = array_values($rows);
      for ($i = count($rows); $i < count($rows) + 3 && count($rows) < 60; $i++) { $rows[] = ['act_type_id' => null, 'amount' => null, 'period' => 'annual']; }
      $excluded = array_map('intval', (array) old('exclusions', $current->exclusions ?? []));
      $fx = 'w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm';
    @endphp
    <form method="POST" action="{{ route('guarantees.terms', $guarantee) }}" class="mt-6 space-y-5 rounded-lg border border-ink/10 bg-white p-4 sm:p-6">
      @csrf
      <div>
        <h2 class="text-lg font-semibold">Plafonds et exclusions</h2>
        <p class="mt-1 text-sm text-ink/60">Version en cours V{{ $current->version_no }}. Un acte exclu bloque la prise en charge (« Acte non couvert »). Un plafond limite la part de la mutuelle ; au-delà, la différence reste à la charge de l’adhérent et une alerte « Plafond atteint » est créée.</p>
      </div>
      @if ($errors->hasAny(['ceilings', 'exclusions', 'exclusions.*', 'effective_from']) || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'ceilings.')))
        <div class="rounded-md border border-clay/30 bg-clay/5 px-3 py-2 text-sm text-clay">{{ collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'ceilings') || str_starts_with($k, 'exclusions') || $k === 'effective_from')->flatten()->first() }}</div>
      @endif

      <fieldset>
        <legend class="text-sm font-semibold">Plafonds <span class="font-normal text-ink/50">({{ $currency->code }} ; laisser le montant vide pour supprimer une ligne)</span></legend>
        <div class="mt-2 space-y-2">
          @foreach ($rows as $i => $r)
            <div class="grid gap-2 rounded-md bg-paper px-3 py-2 sm:grid-cols-3">
              <label class="text-xs text-ink/60">Acte<select name="ceilings[{{ $i }}][act_type_id]" class="{{ $fx }} mt-1">
                <option value="">Tous les actes (plafond global)</option>
                @foreach ($acts as $a)<option value="{{ $a->id }}" @selected((int) ($r['act_type_id'] ?? 0) === $a->id)>{{ $a->label }}</option>@endforeach
              </select></label>
              <label class="text-xs text-ink/60">Montant maximal<input name="ceilings[{{ $i }}][amount]" value="{{ $r['amount'] ?? '' }}" inputmode="decimal" class="{{ $fx }} mt-1" placeholder="Ex. 150000"></label>
              <label class="text-xs text-ink/60">Période<select name="ceilings[{{ $i }}][period]" class="{{ $fx }} mt-1">
                @foreach (\App\Models\Tenant\Ceiling::PERIODS as $k => $l)<option value="{{ $k }}" @selected(($r['period'] ?? 'annual') === $k)>{{ $l }}</option>@endforeach
              </select></label>
            </div>
          @endforeach
        </div>
        <p class="mt-1 text-xs text-ink/50">Un plafond « par an » se cumule sur les prestations validées de l’adhérent et de ses ayants droit dans l’année civile. Un plafond propre à un acte remplace le plafond global pour cet acte.</p>
      </fieldset>

      <fieldset>
        <legend class="text-sm font-semibold">Actes non couverts</legend>
        @if ($acts->isEmpty())
          <p class="mt-1 text-sm text-ink/55">Aucun acte : ajoutez-en dans Paramètres → Actes.</p>
        @else
          <div class="mt-2 grid gap-1 overflow-y-auto rounded-md border border-ink/10 p-3 sm:grid-cols-2 lg:grid-cols-3" style="max-height:18rem">
            @foreach ($acts as $a)
              <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="exclusions[]" value="{{ $a->id }}" class="mt-1" @checked(in_array($a->id, $excluded, true))><span>{{ $a->label }}@if ($a->category) <span class="text-xs text-ink/45">{{ $a->category }}</span>@endif</span></label>
            @endforeach
          </div>
        @endif
      </fieldset>

      @if ($current->locked)
        <div class="grid gap-2 sm:grid-cols-3">
          <label class="text-sm">Applicable à partir du
            <input name="effective_from" type="date" required min="{{ now()->toDateString() }}" value="{{ old('effective_from', now()->addDay()->toDateString()) }}" class="{{ $fx }} mt-1"></label>
          <p class="text-xs text-ink/50 sm:col-span-2">La version V{{ $current->version_no }} a déjà servi à des recours : elle reste inchangée. Ces conditions ouvrent une version V{{ $current->version_no + 1 }} (cotisation, taux et réseau repris).</p>
        </div>
      @endif
      <button class="rounded-md bg-pine px-5 py-2 text-sm font-medium text-white hover:bg-ink transition">Enregistrer les plafonds et exclusions</button>
    </form>
  @endif
</x-layouts.tenant>
