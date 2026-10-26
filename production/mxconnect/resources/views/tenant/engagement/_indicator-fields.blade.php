@php $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm'; @endphp
<label class="block">Intitulé *<input name="label" required maxlength="160" value="{{ $i->label }}" class="{{ $f }}" placeholder="Ex. : Personnes dépistées"></label>
<label class="block">Valeur *<input name="value" type="number" min="0" step="any" required value="{{ $i->exists ? $i->value + 0 : '' }}" class="{{ $f }}"></label>
<label class="block">Objectif<input name="target" type="number" min="0" step="any" value="{{ $i->target !== null ? $i->target + 0 : '' }}" class="{{ $f }}"></label>
<label class="block">Unité<input name="unit" maxlength="40" value="{{ $i->unit }}" class="{{ $f }}" placeholder="personnes, %…"></label>
<label class="block">Période<input name="period" maxlength="60" value="{{ $i->period }}" class="{{ $f }}" placeholder="Ex. : T3 2026"></label>
<label class="block">Source du chiffre<input name="source_note" maxlength="255" value="{{ $i->source_note }}" class="{{ $f }}"></label>
