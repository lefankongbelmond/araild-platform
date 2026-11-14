@php $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2.5 text-sm'; @endphp
<x-layouts.provider title="Paramètres">
  <x-tenant.hero icon="lock" kicker="Gestion de l’établissement" tone="teal">
    <x-slot:title>Paramètres du compte</x-slot:title>
    <x-slot:subtitle>{{ $account->email }} · dernière connexion {{ $account->last_login_at?->format('d/m/Y H:i') ?? '—' }} · double authentification {{ $account->mfa_enabled ? 'active' : 'à activer' }}</x-slot:subtitle>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  <div class="mt-6 grid gap-6 lg:grid-cols-2">
    <form method="POST" action="{{ route('pro.settings.update') }}">@csrf
      <x-tenant.panel title="Mes informations" icon="users">
        <div class="grid gap-4 p-5 sm:grid-cols-2 sm:px-6">
          <label class="block text-sm font-medium">Prénom<input name="first_name" required maxlength="120" value="{{ old('first_name', $account->first_name) }}" class="{{ $f }}"></label>
          <label class="block text-sm font-medium">Nom<input name="last_name" required maxlength="120" value="{{ old('last_name', $account->last_name) }}" class="{{ $f }}"></label>
          <label class="block text-sm font-medium sm:col-span-2">Téléphone<input name="phone" maxlength="30" value="{{ old('phone', $account->phone) }}" class="{{ $f }}"></label>
          <p class="text-xs text-ink/50 sm:col-span-2">L’adresse e-mail sert d’identifiant : pour la changer, demandez à l’administrateur de votre établissement.</p>
          <div><button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">Enregistrer</button></div>
        </div>
      </x-tenant.panel>
    </form>
    <form method="POST" action="{{ route('pro.settings.password') }}">@csrf
      <x-tenant.panel title="Mot de passe" icon="lock">
        <div class="grid gap-4 p-5 sm:px-6">
          <label class="block text-sm font-medium">Mot de passe actuel<input type="password" name="current_password" required autocomplete="current-password" class="{{ $f }}"></label>
          <label class="block text-sm font-medium">Nouveau mot de passe <span class="font-normal text-ink/50">(10 caractères au moins, lettres et chiffres)</span><input type="password" name="password" required autocomplete="new-password" class="{{ $f }}"></label>
          <label class="block text-sm font-medium">Confirmer<input type="password" name="password_confirmation" required autocomplete="new-password" class="{{ $f }}"></label>
          <div><button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">Changer le mot de passe</button></div>
        </div>
      </x-tenant.panel>
    </form>
  </div>
  <x-tenant.panel class="mt-6" title="Mes établissements" icon="stethoscope">
    <ul class="divide-y divide-ink/5 px-5 pb-4 text-sm sm:px-6">
      @foreach ($providerMemberships as $ms)
        <li class="py-2"><b>{{ $ms->provider?->name }}</b> <span class="text-ink/55">· {{ $ms->roleLabel() }}@if($ms->job_title) · {{ $ms->job_title }}@endif</span></li>
      @endforeach
    </ul>
  </x-tenant.panel>
</x-layouts.provider>
