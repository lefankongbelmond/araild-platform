@php
  $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2.5 text-sm';
  $old = old('lines', array_fill(0, 5, []));
  $defaultAct = old('act_type_id', $pharmacyActs[0] ?? null);
@endphp
<x-layouts.tenant title="{{ __('Délivrer une ordonnance') }}">
  <a href="{{ route('portal.index') }}" class="text-sm text-pine hover:underline">{{ __('← Vérifier les droits') }}</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('Délivrer une ordonnance') }}</h1>
  <p class="mt-1 text-sm text-ink/60">{{ $member->last_name }} {{ $member->first_name }} · <span class="font-mono">{{ $member->member_code }}</span> {{ __('· droits vérifiés le :date — :name', ['date' => $check->created_at->format('d/m/Y à H:i'), 'name' => $provider->name]) }}</p>

  @if (session('rights_block'))
    <div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><b>{{ __('Prise en charge refusée :') }}</b>
      <ul class="mt-1 list-disc pl-5">@foreach (session('rights_block') as $r)<li>{{ __('mxconnect.rights.'.$r) }}</li>@endforeach</ul></div>
  @endif
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay" role="alert"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="POST" action="{{ route('portal.pharmacy.store', $check->id) }}" class="mt-6 max-w-3xl space-y-4 rounded-lg border border-ink/10 bg-white p-5">
    @csrf
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm font-medium">{{ __('Patient *') }}
        <select name="beneficiary" required class="{{ $f }}">
          <option value="member">{{ __(':last_name :name (adhérent)', ['last_name' => $member->last_name, 'name' => $member->first_name]) }}</option>
          @foreach ($dependents as $d)<option value="{{ $d->id }}" @selected(old('beneficiary', $checkedDependent ?? null) == $d->id)>{{ __(':last_name :name (personne à charge)', ['last_name' => $d->last_name, 'name' => $d->first_name]) }}</option>@endforeach
        </select></label>
      <label class="block text-sm font-medium">{{ __('Acte facturé *') }}
        <select name="act_type_id" required class="{{ $f }}"><option value="">—</option>@foreach ($acts as $a)<option value="{{ $a->id }}" @selected((int) $defaultAct === $a->id)>{{ $a->label }}</option>@endforeach</select></label>
      <label class="block text-sm font-medium">{{ __('N° de l’ordonnance *') }}<input name="reference" required maxlength="60" value="{{ old('reference') }}" class="{{ $f }}"></label>
      <label class="block text-sm font-medium">{{ __('Prescripteur (médecin, établissement) *') }}<input name="prescriber" required maxlength="160" value="{{ old('prescriber') }}" class="{{ $f }}"></label>
      <label class="block text-sm font-medium">{{ __('Date de l’ordonnance *') }}<input type="date" name="prescribed_on" required max="{{ now()->toDateString() }}" value="{{ old('prescribed_on') }}" class="{{ $f }}"></label>
      <label class="block text-sm font-medium">{{ __('Renouvellements autorisés') }}<input name="renewals_allowed" inputmode="numeric" value="{{ old('renewals_allowed', 0) }}" class="{{ $f }}"></label>
    </div>

    <fieldset>
      <legend class="text-sm font-medium">{{ __('Médicaments délivrés') }}</legend>
      <p class="text-xs text-ink/55">{{ __('Une ligne par médicament. Les lignes vides sont ignorées. Les médicaments exclus ou hors liste de la mutuelle restent à la charge du patient.') }}</p>
      <div class="mt-2 space-y-3">
        @foreach ($old as $i => $l)
          <div class="grid grid-cols-2 gap-2 rounded-md border border-ink/10 p-3 sm:grid-cols-3">
            <label class="col-span-2 block text-xs font-medium">{{ __('Médicament') }}
              <select name="lines[{{ $i }}][medicine_id]" class="{{ $f }}"><option value="">—</option>
                @foreach ($medicines as $m)<option value="{{ $m->id }}" @selected((int) ($l['medicine_id'] ?? 0) === $m->id)>{{ $m->label }}{{ $m->presentation ? ' — ' . $m->presentation : '' }}{{ $m->excluded ? ' (exclu)' : (! $m->eligible ? ' (hors liste)' : '') }}</option>@endforeach
              </select></label>
            <label class="block text-xs font-medium">{{ __('Qté prescrite') }}<input name="lines[{{ $i }}][prescribed]" inputmode="numeric" value="{{ $l['prescribed'] ?? '' }}" class="{{ $f }}"></label>
            <label class="block text-xs font-medium">{{ __('Qté délivrée') }}<input name="lines[{{ $i }}][dispensed]" inputmode="numeric" value="{{ $l['dispensed'] ?? '' }}" class="{{ $f }}"></label>
            <label class="block text-xs font-medium">{{ __('Prix unitaire (:currency)', ['currency' => $currency->code]) }}<input name="lines[{{ $i }}][unit_price]" inputmode="decimal" value="{{ $l['unit_price'] ?? '' }}" class="{{ $f }}"></label>
          </div>
        @endforeach
      </div>
    </fieldset>

    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="emergency" value="1" @checked(old('emergency')) class="mt-1"> {{ __('Urgence (délivrance sans attendre un accord préalable)') }}</label>
    <p class="rounded-md bg-mist px-3 py-2 text-xs text-ink/60">{{ __('Une même ordonnance ne peut pas être facturée deux fois : MX-CONNECT refuse une délivrance qui dépasse les quantités prescrites (renouvellements compris), même dans une autre pharmacie.') }}@if ($validity) {{ __('La mutuelle accepte les ordonnances de moins de :count jours.', ['count' => $validity]) }}@endif {{ __('N’indiquez aucun diagnostic.') }}</p>
    <button class="rounded-md bg-pine px-5 py-2.5 text-sm font-semibold text-white hover:bg-navy">{{ __('Enregistrer la délivrance') }}</button>
  </form>
</x-layouts.tenant>
