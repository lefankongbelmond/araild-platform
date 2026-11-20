{{-- Version déjà utilisée : la modification ouvre une nouvelle version datée (jamais d'effet rétroactif). --}}
@if ($v->locked)
  <div class="rounded-md bg-mist p-3 text-xs text-ink/70 sm:col-span-2">
    <p>{{ __('La version V:version_no a déjà servi à des prestations : elle reste inchangée. Cette modification ouvre la version V:version_no2.', ['version_no' => $v->version_no, 'version_no2' => $v->version_no + 1]) }}</p>
    <div class="mt-2 grid gap-2 sm:grid-cols-2">
      <label class="block font-medium">{{ __('Applicable à partir du *') }}<input type="date" name="effective_from" required min="{{ now()->toDateString() }}" value="{{ old('effective_from', now()->addDay()->toDateString()) }}" class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm"></label>
      <label class="block font-medium">{{ __('Note de version') }}<input name="change_note" maxlength="255" value="{{ old('change_note') }}" class="mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm" placeholder="{{ __('Ex. Pharmacie portée à 60 %') }}"></label>
    </div>
  </div>
@endif
