@php
  $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2.5 text-sm focus:border-pine focus:outline-none';
  $T = \App\Models\Central\CareProvider::TYPES;
  $hours = (array) ($p->opening_hours ?? []);
@endphp
<x-layouts.provider title="Profil de l’établissement">
  <x-tenant.hero icon="stethoscope" kicker="Gestion de l’établissement" tone="teal">
    <x-slot:title>Profil de l’établissement</x-slot:title>
    <x-slot:subtitle>Ces informations sont visibles par les mutuelles avec lesquelles l’établissement travaille.@if($p->isPending()) Établissement en attente de reconnaissance par une mutuelle.@endif</x-slot:subtitle>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="POST" action="{{ route('pro.profile.update') }}" class="mt-6 space-y-6">@csrf
    <fieldset @disabled(! $canEdit) class="space-y-6">
    <x-tenant.panel title="Identité" icon="stethoscope">
      <div class="grid gap-4 p-5 sm:grid-cols-2 sm:px-6">
        <label class="block text-sm font-medium sm:col-span-2">Nom de l’établissement<input name="name" required maxlength="180" value="{{ old('name', $p->name) }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">Type de structure<select name="type" class="{{ $f }}">@foreach ($T as $k => $v)<option value="{{ $k }}" @selected(old('type', $p->type) === $k)>{{ $v }}</option>@endforeach</select></label>
        <label class="block text-sm font-medium">Responsable / directeur<input name="manager_name" maxlength="120" value="{{ old('manager_name', $p->manager_name) }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">N° d’agrément<input name="approval_number" maxlength="60" value="{{ old('approval_number', $p->approval_number) }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">Agrément valable jusqu’au<input type="date" name="approval_expires_on" value="{{ old('approval_expires_on', $p->approval_expires_on?->toDateString()) }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium sm:col-span-2">Spécialités <span class="font-normal text-ink/50">(séparées par des virgules)</span><input name="specialties" maxlength="500" value="{{ old('specialties', implode(', ', (array) ($p->specialties ?? []))) }}" placeholder="Médecine générale, pédiatrie, maternité, laboratoire…" class="{{ $f }}"></label>
      </div>
    </x-tenant.panel>

    <x-tenant.panel title="Adresse et contacts" icon="pin">
      <div class="grid gap-4 p-5 sm:grid-cols-2 sm:px-6">
        <label class="block text-sm font-medium">Localité<input name="locality" maxlength="120" value="{{ old('locality', $p->locality) }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">Adresse<input name="address" maxlength="255" value="{{ old('address', $p->address) }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">Latitude <span class="font-normal text-ink/50">(ex. 3.8480)</span><input name="latitude" inputmode="decimal" value="{{ old('latitude', $p->latitude) }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">Longitude <span class="font-normal text-ink/50">(ex. 11.5021)</span><input name="longitude" inputmode="decimal" value="{{ old('longitude', $p->longitude) }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">Téléphone principal<input name="phone" maxlength="30" value="{{ old('phone', $p->phone) }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">Autre téléphone<input name="phone2" maxlength="30" value="{{ old('phone2', $p->phone2) }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium sm:col-span-2">E-mail de l’établissement<input type="email" name="email" maxlength="180" value="{{ old('email', $p->email) }}" class="{{ $f }}"></label>
        @if ($p->latitude !== null && $p->longitude !== null)
          <p class="text-xs text-ink/55 sm:col-span-2">Position enregistrée : <a class="text-brand-700 hover:underline" href="https://www.openstreetmap.org/?mlat={{ $p->latitude }}&mlon={{ $p->longitude }}#map=17/{{ $p->latitude }}/{{ $p->longitude }}" target="_blank" rel="noopener noreferrer">voir sur la carte (OpenStreetMap)</a></p>
        @endif
      </div>
    </x-tenant.panel>

    <x-tenant.panel title="Horaires d’ouverture" icon="history">
      <div class="grid gap-3 p-5 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
        @foreach ($days as $k => $v)
          <label class="block text-sm font-medium">{{ $v }}<input name="hours[{{ $k }}]" maxlength="40" value="{{ old('hours.' . $k, $hours[$k] ?? '') }}" placeholder="ex. 7 h 30 – 18 h, ou Fermé" class="{{ $f }}"></label>
        @endforeach
      </div>
    </x-tenant.panel>
    </fieldset>
    @if ($canEdit)<button class="rounded-md bg-pine px-5 py-2.5 text-sm font-semibold text-white hover:bg-navy">Enregistrer la fiche</button>
    @else<p class="text-sm text-ink/55">Votre rôle permet de consulter la fiche ; seuls l’administrateur et le gestionnaire administratif la modifient.</p>@endif
  </form>

  @if ($canBank)
    <form method="POST" action="{{ route('pro.bank.update') }}" class="mt-8">@csrf
      <x-tenant.panel title="Coordonnées bancaires" icon="wallet">
        <div class="grid gap-4 p-5 sm:grid-cols-2 sm:px-6">
          <label class="block text-sm font-medium">Banque<input name="bank_name" maxlength="120" value="{{ old('bank_name', $bank['bank_name'] ?? '') }}" class="{{ $f }}"></label>
          <label class="block text-sm font-medium">Titulaire du compte<input name="account_holder" maxlength="120" value="{{ old('account_holder', $bank['account_holder'] ?? '') }}" class="{{ $f }}"></label>
          <label class="block text-sm font-medium">N° de compte / RIB<input name="account_number" maxlength="60" value="{{ old('account_number', $bank['account_number'] ?? '') }}" class="{{ $f }}"></label>
          <label class="block text-sm font-medium">Code SWIFT / BIC<input name="swift" maxlength="20" value="{{ old('swift', $bank['swift'] ?? '') }}" class="{{ $f }}"></label>
          <label class="block text-sm font-medium">Numéro Mobile Money<input name="mobile_money" maxlength="30" value="{{ old('mobile_money', $bank['mobile_money'] ?? '') }}" class="{{ $f }}"></label>
          <p class="text-xs text-ink/55 sm:col-span-2">Stockées chiffrées, visibles seulement par les personnes ayant le droit « coordonnées bancaires ». Les mutuelles paient hors de la plateforme : MX-CONNECT ne détient aucun fonds.</p>
          <div class="sm:col-span-2"><button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">Enregistrer les coordonnées</button></div>
        </div>
      </x-tenant.panel>
    </form>
  @endif
</x-layouts.provider>
