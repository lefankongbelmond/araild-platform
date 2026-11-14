@php $r = $r ?? null; $f = 'mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm'; @endphp
<label class="block text-sm font-medium">Nom de la garantie *<input name="label" required maxlength="120" value="{{ $r?->label }}" class="{{ $f }}" placeholder="Ex. Consultations"></label>
<label class="block text-sm font-medium">Catégorie de soins
  <select name="service_category_id" class="{{ $f }}"><option value="">Toutes les prestations</option>
    @foreach ($categories as $c)<option value="{{ $c->id }}" @selected($r?->service_category_id === $c->id)>{{ $c->label }}</option>@endforeach</select></label>
<label class="block text-sm font-medium">Bénéficiaires concernés
  <select name="beneficiary_category" class="{{ $f }}">@foreach (\App\Models\Tenant\CoverageRule::BENEFICIARY_CATEGORIES as $k => $l)<option value="{{ $k }}" @selected(($r?->beneficiary_category ?? 'all') === $k)>{{ $l }}</option>@endforeach</select></label>
<div class="grid grid-cols-2 gap-2">
  <label class="block text-sm font-medium">Taux de prise en charge (%) *<input name="coverage_rate" required inputmode="decimal" value="{{ $r ? rtrim(rtrim(number_format($r->coverage_rate, 2, '.', ''), '0'), '.') : '' }}" class="{{ $f }}"></label>
  <label class="block text-sm font-medium">Ticket modérateur (%)<input name="copay_rate" inputmode="decimal" value="{{ $r && $r->copay_rate !== null ? rtrim(rtrim(number_format($r->copay_rate, 2, '.', ''), '0'), '.') : '' }}" class="{{ $f }}"></label>
</div>
<label class="block text-sm font-medium">Carence en cas d’accident (jours)<input name="accident_waiting_days" inputmode="numeric" value="{{ $r?->accident_waiting_days }}" class="{{ $f }}" placeholder="Vide = carence habituelle"></label>
<label class="block text-sm font-medium">Conditions particulières<textarea name="conditions" rows="2" maxlength="2000" class="{{ $f }}">{{ $r?->conditions }}</textarea></label>
<div class="space-y-1 sm:col-span-2">
  <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="emergency_exempt" value="1" class="mt-1" @checked($r ? $r->emergency_exempt : true)> Urgence : pas de délai de carence (soins de stabilisation)</label>
  <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="tariff_cap" value="1" class="mt-1" @checked($r?->tariff_cap)> Base de calcul limitée au tarif conventionnel de l’acte</label>
  <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="active" value="1" class="mt-1" @checked($r ? $r->active : true)> Garantie active</label>
</div>
