{{--
  Paramètres de la mutuelle (multilingue M3a, 2026-11-30) : toutes les rubriques en cartes, avec une
  recherche. Chaque carte mène à l'écran existant (aucune adresse ne change) et n'apparaît que si la
  personne a le droit de l'ouvrir (App\Support\TenantSettings + TenantAccess).
--}}
<x-layouts.tenant :title="__('Paramètres')">
  <x-tenant.hero icon="lock" :kicker="__('Paramètres')" tone="brand">
    <x-slot:title>{{ __('Paramètres de la mutuelle') }}</x-slot:title>
    <x-slot:subtitle>{{ __('Toutes les rubriques de paramétrage au même endroit. Vous voyez seulement celles que votre rôle permet d’ouvrir.') }}</x-slot:subtitle>
  </x-tenant.hero>

  <div class="mt-5">
    <label for="settings-search" class="sr-only">{{ __('Rechercher un paramètre') }}</label>
    <input id="settings-search" type="search" data-settings-search placeholder="{{ __('Rechercher un paramètre…') }}" autocomplete="off"
           class="w-full max-w-md rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm">
  </div>

  @forelse ($sections as $section => $cards)
    <section class="mt-6" data-settings-section>
      <h2 class="text-[11.5px] font-semibold uppercase tracking-[.14em] text-ink/50">{{ __($section) }}</h2>
      <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cards as $route => [$title, $desc, $icon])
          <a href="{{ route($route) }}" data-settings-card data-search="{{ \Illuminate\Support\Str::lower(__($title) . ' ' . __($desc) . ' ' . __($section)) }}"
             class="group flex items-start gap-3 rounded-2xl bg-white p-4 shadow-soft ring-1 ring-ink/[.05] transition hover:bg-paper">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-tenant.icon :name="$icon" :size="20" /></span>
            <span class="min-w-0">
              <span class="block font-semibold text-[#0B1F3A]">{{ __($title) }}</span>
              <span class="mt-0.5 block text-[13px] leading-snug text-ink/60">{{ __($desc) }}</span>
            </span>
          </a>
        @endforeach
      </div>
    </section>
  @empty
    <p class="mt-6 text-sm text-ink/60">{{ __('Aucune rubrique de paramétrage n’est ouverte à votre rôle.') }}</p>
  @endforelse
  <p class="mt-6 hidden text-sm text-ink/60" data-settings-none>{{ __('Aucun paramètre ne correspond à votre recherche.') }}</p>

  <script>
    (function () {
      var input = document.querySelector('[data-settings-search]'); if (!input) return;
      input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase(), any = false;
        document.querySelectorAll('[data-settings-section]').forEach(function (sec) {
          var shown = 0;
          sec.querySelectorAll('[data-settings-card]').forEach(function (c) {
            var ok = !q || c.dataset.search.indexOf(q) !== -1; c.hidden = !ok; if (ok) shown++;
          });
          sec.hidden = shown === 0; if (shown) any = true;
        });
        document.querySelector('[data-settings-none]').classList.toggle('hidden', any);
      });
    })();
  </script>
</x-layouts.tenant>
