{{-- Champs d'une convention (création, modification, acceptation d'une demande d'affiliation). --}}
@php $hasTerms = \Illuminate\Support\Facades\Schema::hasColumn('provider_conventions', 'signed_on'); @endphp
<fieldset class="grid gap-4 sm:grid-cols-2">
  <legend class="mb-2 font-semibold text-sm">La convention</legend>
  <label class="block text-sm font-medium">N° de convention<input name="reference" maxlength="60" value="{{ old('reference', $convention->reference) }}" class="{{ $f }}"></label>
  @if ($hasTerms)<label class="block text-sm font-medium">Date de signature<input type="date" name="signed_on" value="{{ old('signed_on', $convention->signed_on?->toDateString()) }}" class="{{ $f }}"></label>@else<div></div>@endif
  <label class="block text-sm font-medium">Début<input type="date" name="starts_on" value="{{ old('starts_on', $convention->starts_on?->toDateString()) }}" class="{{ $f }}"></label>
  <label class="block text-sm font-medium">Fin<input type="date" name="ends_on" value="{{ old('ends_on', $convention->ends_on?->toDateString()) }}" class="{{ $f }}"></label>
  @if ($hasTerms)
    <label class="block text-sm font-medium">Délai de transmission des factures <span class="font-normal text-ink/50">(jours après les soins)</span><input type="number" min="1" max="365" name="transmission_days" value="{{ old('transmission_days', $convention->transmission_days) }}" class="{{ $f }}"></label>
    <label class="block text-sm font-medium">Délai de paiement <span class="font-normal text-ink/50">(jours après réception)</span><input type="number" min="1" max="365" name="payment_days" value="{{ old('payment_days', $convention->payment_days) }}" class="{{ $f }}"></label>
    <p class="text-xs text-ink/50 sm:col-span-2">Laissez vide si la convention ne fixe pas de délai : aucun délai n’est appliqué par défaut. Le délai de paiement sert à signaler au prestataire les factures en retard.</p>
  @endif
  <label class="block text-sm font-medium">Contact<input name="contact_name" maxlength="120" value="{{ old('contact_name', $convention->contact_name) }}" class="{{ $f }}"></label>
  <label class="block text-sm font-medium">Téléphone du contact<input name="contact_phone" maxlength="30" value="{{ old('contact_phone', $convention->contact_phone) }}" class="{{ $f }}"></label>
  @if ($hasTerms)<label class="block text-sm font-medium sm:col-span-2">Conditions de facturation <span class="font-normal text-ink/50">(visibles par l’établissement)</span><textarea name="billing_terms" rows="3" maxlength="2000" class="{{ $f }}">{{ old('billing_terms', $convention->billing_terms) }}</textarea></label>@endif
  <label class="block text-sm font-medium sm:col-span-2">Notes internes <span class="font-normal text-ink/50">(non visibles par l’établissement)</span><textarea name="notes" rows="3" maxlength="2000" class="{{ $f }}">{{ old('notes', $convention->notes) }}</textarea></label>
</fieldset>
