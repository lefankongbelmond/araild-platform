@php
  $f = 'w-full rounded-xl border border-ink/10 bg-white px-3 py-2 text-sm outline-none focus:border-pine focus:ring-4 focus:ring-pine/10';
  $existing = $countries->keyBy('iso2');
  $canManage = auth('network')->user()?->can('manage-config');
  $missing = collect($catalog)->flatten(1)->reject(fn ($r) => $existing->has($r[0]))->count();
@endphp
<x-layouts.network title="Pays">
  <a href="{{ route('network.config.index') }}" class="text-sm font-medium text-pine hover:underline">← Configuration</a>
  <div class="mt-3 flex flex-wrap items-end justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold tracking-tight text-navy sm:text-3xl">Pays</h1>
      <p class="mt-1 max-w-2xl text-sm text-ink/60">Pays où des mutuelles peuvent être créées, avec leur devise et leur langue par défaut. Un pays inactif n’est plus proposé à la création, sans effet sur les mutuelles existantes.</p>
    </div>
    @if ($canManage && $missing)<a href="#catalogue" class="inline-flex h-11 items-center rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 px-5 text-sm font-bold text-white shadow-lg shadow-leaf-500/25">+ Ajouter des pays ({{ $missing }} disponibles)</a>@endif
  </div>
  @if ($errors->any())<div class="mt-4 rounded-xl border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 space-y-2">
    @foreach ($countries as $co)
      @php $ref = \App\Support\CountryCatalog::find($co->iso2); $diff = $ref && $co->defaultCurrency && $co->defaultCurrency->code !== $ref[2]; @endphp
      <details class="group rounded-2xl bg-white shadow-sm ring-1 ring-ink/[.06] {{ $co->active ? '' : 'opacity-70' }}">
        <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 p-4">
          <span class="flex min-w-0 items-center gap-3">
            <span class="grid h-9 w-11 shrink-0 place-items-center rounded-lg bg-mist font-mono text-xs font-bold text-navy">{{ $co->iso2 }}</span>
            <span class="min-w-0"><b class="text-navy">{{ $co->name }}</b>
              <span class="block text-xs text-ink/55">{{ $co->defaultCurrency?->code }} · {{ $co->defaultLocale?->name }}@if ($co->phone_prefix) · {{ $co->phone_prefix }}@endif · {{ $co->mutuals_count }} mutuelle(s)@if ($co->paymentProviders->isNotEmpty()) · {{ $co->paymentProviders->pluck('name')->join(', ') }}@endif</span>
              @if ($diff)<span class="block text-xs font-semibold text-amber-700">Devise de référence : {{ $ref[2] }} (enregistrée : {{ $co->defaultCurrency->code }}) — à vérifier.</span>@endif</span>
          </span>
          <span class="flex items-center gap-2">
            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $co->active ? 'bg-leaf-50 text-leaf-700 ring-leaf-100' : 'bg-mist text-ink/60 ring-ink/10' }}">{{ $co->active ? 'Actif' : 'Inactif' }}</span>
            @if ($canManage)<span class="text-sm font-semibold text-pine group-open:hidden">Modifier</span>@endif
          </span>
        </summary>
        @if ($canManage)
          <div class="border-t border-ink/[.06] p-4">
            <form method="POST" action="{{ route('network.config.countries.update', $co) }}" class="grid grid-cols-1 gap-3 sm:grid-cols-4">@csrf @method('PUT')
              <label class="text-sm font-medium">Code<input name="iso2" value="{{ $co->iso2 }}" maxlength="2" required class="{{ $f }} mt-1 uppercase"></label>
              <label class="text-sm font-medium sm:col-span-3">Nom<input name="name" value="{{ $co->name }}" maxlength="120" required class="{{ $f }} mt-1"></label>
              <label class="text-sm font-medium">Devise<select name="default_currency_id" class="{{ $f }} mt-1">@foreach ($currencies as $c)<option value="{{ $c->id }}" @selected($c->id === $co->default_currency_id)>{{ $c->code }}</option>@endforeach</select></label>
              <label class="text-sm font-medium">Langue<select name="default_locale_id" class="{{ $f }} mt-1">@foreach ($locales as $l)<option value="{{ $l->id }}" @selected($l->id === $co->default_locale_id)>{{ $l->name }}</option>@endforeach</select></label>
              <label class="text-sm font-medium">Indicatif<input name="phone_prefix" value="{{ $co->phone_prefix }}" maxlength="6" class="{{ $f }} mt-1"></label>
              <input type="hidden" name="active" value="{{ $co->active ? 1 : 0 }}">
              <div class="sm:col-span-4"><span class="text-sm font-medium">Paiement mobile disponible</span>
                <div class="mt-1 flex flex-wrap gap-4">@forelse ($providers as $p)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="providers[]" value="{{ $p->id }}" @checked($co->paymentProviders->contains($p))>{{ $p->name }}</label>@empty<span class="text-sm text-ink/50">Aucun prestataire de paiement actif.</span>@endforelse</div></div>
              <div class="flex flex-wrap gap-2 sm:col-span-4">
                <button class="h-10 rounded-xl bg-navy px-4 text-sm font-bold text-white">Enregistrer</button>
              </div>
            </form>
            <form method="POST" action="{{ route('network.config.countries.toggle', $co) }}" class="mt-2">@csrf
              <button class="h-10 rounded-xl px-4 text-sm font-semibold {{ $co->active ? 'text-clay hover:bg-clay/10' : 'text-leaf-700 hover:bg-leaf-50' }}">{{ $co->active ? 'Désactiver ce pays' : 'Activer ce pays' }}</button>
            </form>
          </div>
        @endif
      </details>
    @endforeach
  </div>

  @if ($canManage)
    <section id="catalogue" class="mt-8 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink/[.06] sm:p-6">
      <h2 class="text-lg font-bold text-navy">Ajouter des pays depuis la liste de référence</h2>
      <p class="mt-1 text-sm text-ink/60">Les 54 pays d’Afrique et des pays partenaires, avec leur devise officielle, leur indicatif et une langue d’interface (français ou anglais). Les devises manquantes sont ajoutées automatiquement.</p>
      <form method="POST" action="{{ route('network.config.countries.catalog') }}" class="mt-4">@csrf
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
          @foreach ($catalog as $region => $rows)
            <fieldset class="rounded-xl border border-ink/10 p-4">
              <legend class="px-1 text-sm font-bold text-navy">{{ $region }}</legend>
              <label class="mb-2 flex items-center gap-2 text-xs font-semibold text-pine"><input type="checkbox" data-all> Tout cocher</label>
              <div class="space-y-1.5">
                @foreach ($rows as [$iso, $name, $cur, $tel, $lang])
                  @if ($existing->has($iso))
                    <div class="flex items-center gap-2 text-sm text-ink/45"><span aria-hidden="true">✓</span>{{ $name }} <span class="text-xs">déjà ajouté</span></div>
                  @else
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="iso2[]" value="{{ $iso }}">{{ $name }} <span class="text-xs text-ink/45">{{ $cur }} · {{ $tel }}</span></label>
                  @endif
                @endforeach
              </div>
            </fieldset>
          @endforeach
        </div>
        <button class="mt-5 h-11 rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 px-6 text-sm font-bold text-white">Ajouter les pays cochés</button>
      </form>
      <details class="mt-6 rounded-xl border border-ink/10 p-4">
        <summary class="cursor-pointer text-sm font-semibold text-ink/70">Pays absent de la liste : l’ajouter à la main</summary>
        <form method="POST" action="{{ route('network.config.countries.store') }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-4">@csrf
          <label class="text-sm font-medium">Code ISO (2 lettres)<input name="iso2" maxlength="2" required class="{{ $f }} mt-1 uppercase" placeholder="CM"></label>
          <label class="text-sm font-medium sm:col-span-3">Nom<input name="name" maxlength="120" required class="{{ $f }} mt-1"></label>
          <label class="text-sm font-medium">Devise<select name="default_currency_id" class="{{ $f }} mt-1">@foreach ($currencies as $c)<option value="{{ $c->id }}">{{ $c->code }}</option>@endforeach</select></label>
          <label class="text-sm font-medium">Langue<select name="default_locale_id" class="{{ $f }} mt-1">@foreach ($locales as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select></label>
          <label class="text-sm font-medium">Indicatif<input name="phone_prefix" maxlength="6" class="{{ $f }} mt-1" placeholder="+237"></label>
          <div class="flex items-end"><button class="h-10 w-full rounded-xl bg-navy px-4 text-sm font-bold text-white">Ajouter</button></div>
        </form>
      </details>
    </section>
  @endif
  <x-slot:scripts>
  <script>
    document.querySelectorAll('[data-all]').forEach(function (all) {
      all.addEventListener('change', function () {
        all.closest('fieldset').querySelectorAll('input[name="iso2[]"]').forEach(function (c) { c.checked = all.checked; });
      });
    });
  </script>
  </x-slot:scripts>
</x-layouts.network>
