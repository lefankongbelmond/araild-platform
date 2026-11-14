@php $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2.5 text-sm focus:border-pine focus:outline-none'; @endphp
<x-layouts.provider title="Connexion" :bare="true">
  <div class="grid gap-6 lg:grid-cols-2 lg:items-center">
    <section>
      <p class="text-[11.5px] font-semibold uppercase tracking-[.14em] text-brand-600">Espace prestataire de soins</p>
      <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-[#0B1F3A]">Un seul compte pour toutes vos mutuelles</h1>
      <p class="mt-3 text-[15px] text-ink/60">Hôpitaux, centres de santé, cliniques, pharmacies et laboratoires conventionnés : vérifiez les droits des adhérents, déclarez vos prises en charge et suivez vos factures auprès de chaque mutuelle, avec les rôles de votre personnel.</p>
      <ul class="mt-5 space-y-2.5 text-sm text-ink/70">
        <li class="flex items-start gap-2"><span class="text-leaf-600"><x-tenant.icon name="check" :size="18"/></span> Tableau de bord de l’établissement, chiffres réels de chaque mutuelle</li>
        <li class="flex items-start gap-2"><span class="text-leaf-600"><x-tenant.icon name="check" :size="18"/></span> Personnel et droits : médecin, accueil, facturation…</li>
        <li class="flex items-start gap-2"><span class="text-leaf-600"><x-tenant.icon name="check" :size="18"/></span> Données des adhérents conservées par chaque mutuelle, double authentification obligatoire</li>
      </ul>
    </section>
    <section class="rounded-3xl bg-white p-6 shadow-soft ring-1 ring-ink/[.05] sm:p-8">
      <h2 class="text-xl font-bold text-[#0B1F3A]">Connexion</h2>
      <form method="POST" action="{{ route('pro.login.store') }}" class="mt-5 space-y-4">@csrf
        <label class="block text-sm font-medium">Adresse e-mail<input name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="{{ $f }}"></label>
        @error('email')<p class="text-xs text-clay">{{ $message }}</p>@enderror
        <label class="block text-sm font-medium">Mot de passe<input name="password" type="password" required autocomplete="current-password" class="{{ $f }}"></label>
        <label class="flex items-center gap-2 text-sm text-ink/70"><input type="checkbox" name="remember"> Rester connecté sur cet appareil</label>
        <button class="w-full rounded-md bg-pine px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy">Se connecter</button>
      </form>
      <div class="mt-4 flex flex-wrap justify-between gap-2 text-sm">
        <a href="{{ route('pro.forgot') }}" class="text-brand-700 hover:underline">Mot de passe oublié ?</a>
        <a href="{{ route('pro.register') }}" class="font-semibold text-pine hover:underline">Inscrire mon établissement</a>
      </div>
      <p class="mt-5 border-t border-ink/[.06] pt-4 text-xs text-ink/55">Vous avez un ancien compte « Prestataire de soins » créé par une mutuelle ? Il fonctionne toujours sur l’adresse de cette mutuelle. Demandez-lui d’ouvrir l’espace prestataire pour le responsable de votre établissement.</p>
    </section>
  </div>
</x-layouts.provider>
