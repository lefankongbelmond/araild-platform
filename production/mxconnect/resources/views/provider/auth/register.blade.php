@php
  $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2.5 text-sm focus:border-pine focus:outline-none';
  $countryNames = $countries->pluck('name', 'id');
@endphp
<x-layouts.provider title="{{ __('Inscrire un établissement') }}" :bare="true">
  <p class="text-[11.5px] font-semibold uppercase tracking-[.14em] text-brand-600">{{ __('Espace prestataire de soins') }}</p>
  <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#0B1F3A] sm:text-[30px]">{{ __('Inscrire mon établissement') }}</h1>
  <p class="mt-2 max-w-3xl text-sm text-ink/60">{{ __('L’inscription crée votre compte d’administrateur de l’établissement. Votre établissement n’est reconnu qu’après acceptation d’une demande d’affiliation par une mutuelle : c’est elle qui vérifie votre agrément et signe la convention.') }}</p>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="POST" action="{{ route('pro.register.store') }}" class="mt-6 space-y-6">@csrf
    <section class="rounded-3xl bg-white p-5 shadow-soft ring-1 ring-ink/[.05] sm:p-6">
      <h2 class="text-lg font-bold text-[#0B1F3A]">{{ __('1. L’établissement') }}</h2>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <label class="block text-sm font-medium sm:col-span-2">{{ __('Nom de l’établissement') }}<input name="establishment" required maxlength="180" value="{{ old('establishment') }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">{{ __('Type de structure') }}<select name="type" class="{{ $f }}">@foreach ($types as $k => $v)<option value="{{ $k }}" @selected(old('type', 'health_center') === $k)>{{ __($v) }}</option>@endforeach</select></label>
        <label class="block text-sm font-medium">{{ __('N° d’agrément') }} <span class="font-normal text-ink/50">{{ __('(si attribué)') }}</span><input name="approval_number" maxlength="60" value="{{ old('approval_number') }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">{{ __('Pays') }}<select name="country_id" id="mx-country" required class="{{ $f }}">@foreach ($countries as $c)<option value="{{ $c->id }}" @selected((int) old('country_id', $countries->firstWhere('iso2', 'CM')?->id) === $c->id)>{{ $c->name }}</option>@endforeach</select></label>
        <label class="block text-sm font-medium">{{ __('Localité (ville, quartier)') }}<input name="locality" required maxlength="120" value="{{ old('locality') }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">{{ __('Téléphone de l’établissement') }}<input name="establishment_phone" maxlength="30" value="{{ old('establishment_phone') }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">{{ __('E-mail de l’établissement') }}<input type="email" name="establishment_email" maxlength="180" value="{{ old('establishment_email') }}" class="{{ $f }}"></label>
      </div>
    </section>

    <section class="rounded-3xl bg-white p-5 shadow-soft ring-1 ring-ink/[.05] sm:p-6">
      <h2 class="text-lg font-bold text-[#0B1F3A]">{{ __('2. Vous, responsable du compte') }}</h2>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <label class="block text-sm font-medium">{{ __('Prénom') }}<input name="first_name" required maxlength="120" value="{{ old('first_name') }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">{{ __('Nom') }}<input name="last_name" required maxlength="120" value="{{ old('last_name') }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">{{ __('E-mail (identifiant de connexion)') }}<input type="email" name="email" required maxlength="180" value="{{ old('email') }}" autocomplete="username" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">{{ __('Téléphone') }}<input name="phone" maxlength="30" value="{{ old('phone') }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">{{ __('Mot de passe') }} <span class="font-normal text-ink/50">{{ __('(10 caractères au moins, lettres et chiffres)') }}</span><input type="password" name="password" required autocomplete="new-password" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">{{ __('Confirmer le mot de passe') }}<input type="password" name="password_confirmation" required autocomplete="new-password" class="{{ $f }}"></label>
      </div>
    </section>

    <section class="rounded-3xl bg-white p-5 shadow-soft ring-1 ring-ink/[.05] sm:p-6">
      <h2 class="text-lg font-bold text-[#0B1F3A]">{{ __('3. Demandes d’affiliation') }} <span class="text-sm font-normal text-ink/50">{{ __('(facultatif, possible plus tard)') }}</span></h2>
      <p class="mt-1 text-sm text-ink/60">{{ __('Cochez les mutuelles avec lesquelles vous souhaitez travailler. Seules les mutuelles du pays de l’établissement reçoivent la demande.') }}</p>
      <div class="mt-4 grid gap-2 sm:grid-cols-2" id="mx-mutuals">
        @forelse ($mutuals as $mu)
          <label class="flex items-start gap-3 rounded-xl border border-ink/10 px-3 py-2 text-sm" data-country="{{ $mu->country_id }}">
            <input type="checkbox" name="mutuals[]" value="{{ $mu->id }}" class="mt-1" @checked(in_array($mu->id, (array) old('mutuals', []), true))>
            <span><b>{{ $mu->name }}</b><span class="block text-xs text-ink/50">{{ $mu->city }}@if($mu->city) · @endif{{ $countryNames[$mu->country_id] ?? '' }}</span></span>
          </label>
        @empty
          <p class="text-sm text-ink/50">{{ __('Aucune mutuelle disponible pour l’instant.') }}</p>
        @endforelse
      </div>
      <label class="mt-4 block text-sm font-medium">{{ __('Message aux mutuelles') }} <span class="font-normal text-ink/50">{{ __('(spécialités, services, horaires…)') }}</span><textarea name="message" rows="3" maxlength="1000" class="{{ $f }}">{{ old('message') }}</textarea></label>
    </section>

    <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="attest" value="1" class="mt-1" @checked(old('attest')) required>
      <span>{{ __('J’atteste représenter cet établissement et être habilité(e) à l’inscrire. Je sais que les mutuelles vérifient l’agrément avant d’accepter une affiliation, et que les données des adhérents restent la propriété de chaque mutuelle.') }}</span></label>
    <div class="flex flex-wrap items-center gap-3">
      <button class="rounded-md bg-pine px-5 py-2.5 text-sm font-semibold text-white hover:bg-navy">{{ __('Créer mon compte et inscrire l’établissement') }}</button>
      <a href="{{ route('pro.login') }}" class="text-sm text-ink/60 hover:underline">{{ __('J’ai déjà un compte') }}</a>
    </div>
  </form>
  <x-slot:scripts>
    <script>
      (function () {
        var sel = document.getElementById('mx-country');
        function filter() {
          document.querySelectorAll('#mx-mutuals [data-country]').forEach(function (el) {
            var same = el.getAttribute('data-country') === sel.value;
            el.style.display = same ? '' : 'none';
            if (! same) { el.querySelector('input').checked = false; }
          });
        }
        if (sel) { sel.addEventListener('change', filter); filter(); }
      })();
    </script>
  </x-slot:scripts>
</x-layouts.provider>
