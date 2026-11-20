@php $l = $l ?? null; $f = 'mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm'; $major = fn ($m) => $m === null ? '' : rtrim(rtrim(number_format($m / (10 ** $currency->minor_unit), $currency->minor_unit, '.', ''), '0'), '.'); @endphp
<label class="block text-sm font-medium">{{ __('Libellé') }}<input name="label" maxlength="120" value="{{ $l?->label }}" class="{{ $f }}" placeholder="{{ __('Ex. Pharmacie familiale') }}"></label>
<div class="grid grid-cols-2 gap-2">
  <label class="block text-sm font-medium">{{ __('Période') }}<select name="scope" class="{{ $f }}">@foreach (\App\Models\Tenant\CoverageLimit::SCOPES as $k => $t)<option value="{{ $k }}" @selected(($l?->scope ?? 'year') === $k)>{{ __($t) }}</option>@endforeach</select></label>
  <label class="block text-sm font-medium">{{ __('Pour') }}<select name="holder" class="{{ $f }}">@foreach (\App\Models\Tenant\CoverageLimit::HOLDERS as $k => $t)<option value="{{ $k }}" @selected(($l?->holder ?? 'beneficiary') === $k)>{{ __($t) }}</option>@endforeach</select></label>
  <label class="block text-sm font-medium">{{ __('Montant maximal (:currency)', ['currency' => $currency->code]) }}<input name="amount" inputmode="decimal" value="{{ $major($l?->amount_minor) }}" class="{{ $f }}"></label>
  <label class="block text-sm font-medium">{{ __('Nombre maximal d’actes') }}<input name="max_count" inputmode="numeric" value="{{ $l?->max_count }}" class="{{ $f }}"></label>
</div>
<fieldset class="sm:col-span-2">
  <legend class="text-sm font-medium">{{ __('Garanties concernées') }}</legend>
  <p class="text-xs text-ink/55">{{ __('Aucune cochée : toutes les garanties de la formule. Plusieurs : plafond PARTAGÉ.') }}</p>
  <div class="mt-1 grid gap-1 sm:grid-cols-2">
    @foreach ($v->rules as $rr)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="rule_keys[]" value="{{ $rr->rule_key }}" @checked(in_array($rr->rule_key, (array) $l?->rule_keys, true))> {{ $rr->label }}</label>@endforeach
  </div>
</fieldset>
<label class="flex items-start gap-2 text-sm sm:col-span-2"><input type="checkbox" name="active" value="1" class="mt-1" @checked($l ? $l->active : true)> {{ __('Plafond actif') }}</label>
