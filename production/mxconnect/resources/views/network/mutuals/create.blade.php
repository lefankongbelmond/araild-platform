@php
  $f = 'mt-1 w-full rounded-xl border border-ink/10 bg-white px-3 py-2.5 text-sm outline-none focus:border-pine focus:ring-4 focus:ring-pine/10';
  $shared = in_array(config('mxconnect.tenant_db_mode'), ['manual', 'prefix'], true);
  $storage = old('storage', 'auto');
  $base = config('mxconnect.tenant_base_domain');
@endphp
<x-layouts.network title="Nouvelle mutuelle">
  <div class="mx-auto max-w-3xl">
    <a href="{{ route('network.mutuals.index') }}" class="text-sm font-medium text-pine hover:underline">← Mutuelles</a>
    <h1 class="mt-3 text-2xl font-bold tracking-tight text-navy sm:text-3xl">Nouvelle mutuelle</h1>
    <p class="mt-1 text-sm text-ink/60">Tout est créé automatiquement : espace de gestion sur son sous-domaine, tables de données, 8 rôles, plan comptable SYSCOHADA et premier administrateur.</p>

    @error('provisioning')<div class="mt-4 rounded-xl border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $message }}</div>@enderror
    @if ($errors->any() && ! $errors->has('provisioning'))<div class="mt-4 rounded-xl border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ route('network.mutuals.store') }}" class="mt-6 space-y-5" id="mx-create">
      @csrf
      <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink/[.06] sm:p-6">
        <h2 class="text-lg font-bold text-navy">1. Identité</h2>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <label class="block text-sm font-medium sm:col-span-2">Nom de la mutuelle *<input name="name" id="m-name" value="{{ old('name') }}" required maxlength="180" class="{{ $f }}" placeholder="Ex. Mutuelle de santé d’Akono"></label>
          <label class="block text-sm font-medium sm:col-span-2">Sous-domaine *
            <span class="mt-1 flex items-stretch overflow-hidden rounded-xl border border-ink/10 bg-white focus-within:border-pine">
              <input name="slug" id="m-slug" value="{{ old('slug') }}" required maxlength="60" pattern="[A-Za-z0-9_-]+" class="min-w-0 flex-1 px-3 py-2.5 text-sm outline-none" placeholder="akono">
              <span class="hidden items-center bg-paper px-3 text-sm text-ink/50 sm:flex">.{{ $base }}</span>
            </span>
            <span class="mt-1 block text-xs font-normal text-ink/50">Adresse de l’espace : <b id="m-url">https://{{ old('slug') ?: '…' }}.{{ $base }}</b> — lettres, chiffres et tirets.</span></label>
          <label class="block text-sm font-medium">Région<input name="region" value="{{ old('region') }}" maxlength="120" class="{{ $f }}"></label>
          <label class="block text-sm font-medium">Ville<input name="city" value="{{ old('city') }}" maxlength="120" class="{{ $f }}"></label>
        </div>
      </section>

      <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink/[.06] sm:p-6">
        <h2 class="text-lg font-bold text-navy">2. Pays, devise et langue</h2>
        <p class="mt-1 text-sm text-ink/55">La devise et la langue se remplissent selon le pays ; vous pouvez les changer. La devise ne se convertit jamais. Pays manquant : <a class="text-pine underline" href="{{ route('network.config.countries') }}">Configuration → Pays</a>.</p>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
          <label class="block text-sm font-medium">Pays *
            <select name="country_id" id="m-country" required class="{{ $f }}">
              @foreach ($countries as $c)<option value="{{ $c->id }}" data-currency="{{ $c->default_currency_id }}" data-locale="{{ $c->default_locale_id }}" @selected(old('country_id') == $c->id)>{{ $c->name }}</option>@endforeach
            </select></label>
          <label class="block text-sm font-medium">Devise *
            <select name="currency_id" id="m-currency" required class="{{ $f }}">@foreach ($currencies as $c)<option value="{{ $c->id }}" @selected(old('currency_id') == $c->id)>{{ $c->code }} — {{ $c->name }}</option>@endforeach</select></label>
          <label class="block text-sm font-medium">Langue *
            <select name="locale_id" id="m-locale" required class="{{ $f }}">@foreach ($locales as $l)<option value="{{ $l->id }}" @selected(old('locale_id') == $l->id)>{{ $l->name }}</option>@endforeach</select></label>
        </div>
      </section>

      <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink/[.06] sm:p-6">
        <h2 class="text-lg font-bold text-navy">3. Premier administrateur <span class="text-sm font-normal text-ink/50">(recommandé)</span></h2>
        <p class="mt-1 text-sm text-ink/55">Mot de passe provisoire affiché une seule fois après la création ; il sera changé à la 1re connexion, avec la double authentification.</p>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
          <label class="block text-sm font-medium sm:col-span-3">E-mail<input name="admin_email" type="email" value="{{ old('admin_email') }}" maxlength="180" class="{{ $f }}"></label>
          <label class="block text-sm font-medium">Prénom<input name="admin_first_name" value="{{ old('admin_first_name') }}" maxlength="120" class="{{ $f }}"></label>
          <label class="block text-sm font-medium">Nom<input name="admin_last_name" value="{{ old('admin_last_name') }}" maxlength="120" class="{{ $f }}"></label>
        </div>
      </section>

      @if ($shared)
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink/[.06] sm:p-6">
          <h2 class="text-lg font-bold text-navy">4. Stockage des données</h2>
          <label class="mt-4 flex items-start gap-3 rounded-xl border border-ink/10 p-4 has-[:checked]:border-leaf-500 has-[:checked]:bg-leaf-50">
            <input type="radio" name="storage" value="auto" class="mt-1" @checked($storage !== 'existing')>
            <span><b class="block text-navy">Automatique (recommandé)</b><span class="text-sm text-ink/60">Les tables de la mutuelle sont créées immédiatement, sous un préfixe qui lui est propre. Rien à faire dans le panneau LWS.</span></span>
          </label>
          <details class="mt-3 rounded-xl border border-ink/10 p-4" @if ($storage === 'existing') open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-ink/70">Option avancée : utiliser une base déjà créée dans le panneau d’hébergement</summary>
            <label class="mt-3 flex items-start gap-3"><input type="radio" name="storage" value="existing" class="mt-1" @checked($storage === 'existing')><span class="text-sm text-ink/60">Base dédiée, <b>vide</b>, créée à la main (MySQL → Ajouter une base). Laissez utilisateur et mot de passe vides si ce sont ceux de la base centrale.</span></label>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
              <label class="block text-sm font-medium">Nom de la base<input name="db_name" value="{{ old('db_name') }}" maxlength="64" class="{{ $f }} font-mono" placeholder="ex. lws12345_akono"></label>
              <label class="block text-sm font-medium">Utilisateur<input name="db_username" value="{{ old('db_username') }}" autocomplete="off" maxlength="80" class="{{ $f }} font-mono"></label>
              <label class="block text-sm font-medium">Mot de passe<input name="db_password" type="password" autocomplete="new-password" maxlength="190" class="{{ $f }}"></label>
            </div>
          </details>
        </section>
      @endif

      <div class="flex flex-wrap items-center gap-3">
        <button class="h-12 rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 px-6 text-sm font-bold text-white shadow-lg shadow-leaf-500/25">Créer la mutuelle</button>
        <a href="{{ route('network.mutuals.index') }}" class="px-3 text-sm text-ink/60 hover:underline">Annuler</a>
        <span class="text-xs text-ink/50">Elle sera « en attente d’agrément » jusqu’à votre validation.</span>
      </div>
    </form>
  </div>
  <x-slot:scripts>
  <script>
    (function () {
      var name = document.getElementById('m-name'), slug = document.getElementById('m-slug'), url = document.getElementById('m-url');
      var base = @json($base), touched = slug.value !== '';
      function show() { url.textContent = 'https://' + (slug.value || '…') + '.' + base; }
      slug.addEventListener('input', function () { touched = true; show(); });
      name.addEventListener('input', function () {
        if (touched) return;
        slug.value = name.value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
          .replace(/\b(mutuelle|de|du|des|la|le|les|d|l|sante)\b/g, ' ').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 30);
        show();
      });
      var country = document.getElementById('m-country');
      function fill() {
        var o = country.options[country.selectedIndex]; if (!o) return;
        document.getElementById('m-currency').value = o.getAttribute('data-currency');
        document.getElementById('m-locale').value = o.getAttribute('data-locale');
      }
      country.addEventListener('change', fill);
      @if (! old('currency_id')) fill(); @endif
    })();
  </script>
  </x-slot:scripts>
</x-layouts.network>
