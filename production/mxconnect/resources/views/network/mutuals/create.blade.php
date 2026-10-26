<x-layouts.network :title="__('mxconnect.mutual.create')">
  <div class="mx-auto max-w-xl">
    <a href="{{ route('network.mutuals.index') }}" class="text-sm text-ink/50 hover:underline">&larr; {{ __('mxconnect.back') }}</a>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('mxconnect.mutual.create') }}</h1>
    <p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.mutual.create_sub') }}</p>

    @error('provisioning')
      <div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('network.mutuals.store') }}" class="mt-6 space-y-4">
      @csrf
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-sm font-medium">{{ __('mxconnect.mutual.name') }}</label>
          <input name="name" value="{{ old('name') }}" required
                 class="mt-1 w-full rounded-md border-ink/20 bg-white px-3 py-2 text-sm focus:border-pine focus:ring-pine">
          @error('name')<p class="mt-1 text-xs text-clay">{{ $message }}</p>@enderror
        </div>
        <div class="col-span-2">
          <label class="block text-sm font-medium">{{ __('mxconnect.mutual.slug') }}</label>
          <input name="slug" value="{{ old('slug') }}" required placeholder="pilote1"
                 class="mt-1 w-full rounded-md border-ink/20 bg-white px-3 py-2 text-sm focus:border-pine focus:ring-pine">
          <p class="mt-1 text-xs text-ink/40">{{ __('mxconnect.mutual.slug_help') }}</p>
          @error('slug')<p class="mt-1 text-xs text-clay">{{ $message }}</p>@enderror
        </div>
        <div>
          <label class="block text-sm font-medium">{{ __('mxconnect.mutual.country') }}</label>
          <select name="country_id" required class="mt-1 w-full rounded-md border-ink/20 bg-white px-3 py-2 text-sm focus:border-pine focus:ring-pine">
            @foreach ($countries as $c)<option value="{{ $c->id }}" @selected(old('country_id')==$c->id)>{{ $c->name }}</option>@endforeach
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium">{{ __('mxconnect.mutual.currency') }}</label>
          <select name="currency_id" required class="mt-1 w-full rounded-md border-ink/20 bg-white px-3 py-2 text-sm focus:border-pine focus:ring-pine">
            @foreach ($currencies as $c)<option value="{{ $c->id }}" @selected(old('currency_id')==$c->id)>{{ $c->code }} — {{ $c->name }}</option>@endforeach
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium">{{ __('mxconnect.mutual.locale') }}</label>
          <select name="locale_id" required class="mt-1 w-full rounded-md border-ink/20 bg-white px-3 py-2 text-sm focus:border-pine focus:ring-pine">
            @foreach ($locales as $l)<option value="{{ $l->id }}" @selected(old('locale_id')==$l->id)>{{ $l->name }}</option>@endforeach
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium">{{ __('mxconnect.mutual.region') }}</label>
          <input name="region" value="{{ old('region') }}"
                 class="mt-1 w-full rounded-md border-ink/20 bg-white px-3 py-2 text-sm focus:border-pine focus:ring-pine">
        </div>
      </div>
      @if (config('mxconnect.tenant_db_mode') === 'manual')
      <fieldset class="rounded-md border border-ink/15 p-4 space-y-3">
        <legend class="px-1 text-sm font-medium">Base de données de la mutuelle</legend>
        <p class="text-xs text-ink/60">Hébergement mutualisé : créez d'abord une base <strong>vide</strong> dans le panneau LWS
          (MySQL &rarr; Ajouter une base), puis saisissez-la ici. Laissez utilisateur et mot de passe vides
          si c'est le même utilisateur que la base centrale.</p>
        <div>
          <label class="block text-sm font-medium">Nom de la base</label>
          <input name="db_name" value="{{ old('db_name') }}" required placeholder="ex. lws12345_together"
                 class="mt-1 w-full rounded-md border-ink/20 bg-white px-3 py-2 text-sm font-mono focus:border-pine focus:ring-pine">
          @error('db_name')<p class="mt-1 text-xs text-clay">{{ $message }}</p>@enderror
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium">Utilisateur <span class="text-ink/40">(facultatif)</span></label>
            <input name="db_username" value="{{ old('db_username') }}" autocomplete="off"
                   class="mt-1 w-full rounded-md border-ink/20 bg-white px-3 py-2 text-sm font-mono focus:border-pine focus:ring-pine">
          </div>
          <div>
            <label class="block text-sm font-medium">Mot de passe <span class="text-ink/40">(facultatif)</span></label>
            <input name="db_password" type="password" autocomplete="new-password"
                   class="mt-1 w-full rounded-md border-ink/20 bg-white px-3 py-2 text-sm focus:border-pine focus:ring-pine">
          </div>
        </div>
      </fieldset>
      @endif
      <fieldset class="rounded-md border border-ink/15 p-4 space-y-3">
        <legend class="px-1 text-sm font-medium">Premier administrateur <span class="text-ink/40">(recommandé)</span></legend>
        <p class="text-xs text-ink/60">Son mot de passe est généré et affiché une seule fois après la création.
          La double authentification lui sera demandée à la première connexion.</p>
        <div>
          <label class="block text-sm font-medium">E-mail</label>
          <input name="admin_email" type="email" value="{{ old('admin_email') }}"
                 class="mt-1 w-full rounded-md border-ink/20 bg-white px-3 py-2 text-sm focus:border-pine focus:ring-pine">
          @error('admin_email')<p class="mt-1 text-xs text-clay">{{ $message }}</p>@enderror
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium">Prénom</label>
            <input name="admin_first_name" value="{{ old('admin_first_name') }}"
                   class="mt-1 w-full rounded-md border-ink/20 bg-white px-3 py-2 text-sm focus:border-pine focus:ring-pine">
          </div>
          <div>
            <label class="block text-sm font-medium">Nom</label>
            <input name="admin_last_name" value="{{ old('admin_last_name') }}"
                   class="mt-1 w-full rounded-md border-ink/20 bg-white px-3 py-2 text-sm focus:border-pine focus:ring-pine">
          </div>
        </div>
      </fieldset>
      <div class="rounded-md bg-mist px-4 py-3 text-xs text-ink/60">{{ __('mxconnect.mutual.provision_note') }}</div>
      <button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">
        {{ __('mxconnect.mutual.create_action') }}
      </button>
    </form>
  </div>
</x-layouts.network>
