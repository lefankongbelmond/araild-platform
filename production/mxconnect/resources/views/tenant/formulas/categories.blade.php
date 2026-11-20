<x-layouts.tenant title="{{ __('Catégories de soins') }}">
  @php $canEdit = \App\Support\TenantAccess::can('formulas.categories.map'); $f = 'mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm'; @endphp
  <a href="{{ route('formulas.index') }}" class="text-sm text-ink/50 hover:underline">{{ __('&larr; Catalogue des formules') }}</a>
  <x-tenant.hero icon="list" kicker="{{ __('Catalogue des formules') }}" tone="brand" class="mt-2">
    <x-slot:title>{{ __('Catégories de soins') }}</x-slot:title>
    <x-slot:subtitle>{{ __('Bibliothèque des catégories utilisées par les garanties, et rattachement de chaque acte à une catégorie. Une catégorie ne porte aucun taux : les taux sont fixés dans chaque formule.') }}</x-slot:subtitle>
  </x-tenant.hero>

  @if ($errors->any())<div class="mt-4 rounded-lg border border-clay/30 bg-white p-4 text-sm text-clay" role="alert">{{ $errors->first() }}</div>@endif

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section class="min-w-0 rounded-lg border border-ink/10 bg-white p-5 lg:col-span-2">
      <h2 class="font-semibold">{{ __('Rattacher les actes') }}</h2>
      @if ($suggest)
        <p class="mt-1 rounded-md bg-mist p-3 text-xs text-ink/70">{{ __(':count rattachement(s) sont', ['count' => count($suggest)]) }} <b>{{ __('proposés') }}</b> {{ __('d’après le code ou la catégorie de l’acte (pré-sélectionnés ci-dessous). Rien n’est enregistré tant que vous ne validez pas.') }}</p>
      @endif
      <form method="POST" action="{{ route('formulas.categories.map') }}" class="mt-3">@csrf
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-left text-ink/55"><tr><th class="py-2 pr-3 font-medium">{{ __('Acte') }}</th><th class="py-2 font-medium">{{ __('Catégorie de soins') }}</th></tr></thead>
            <tbody class="divide-y divide-ink/5">
              @forelse ($acts as $a)
                @php $sel = $a->service_category_id ?? ($suggest[$a->id] ?? null); @endphp
                <tr>
                  <td class="py-2 pr-3">{{ $a->label }}<div class="text-xs text-ink/45">{{ $a->code }}@if ($a->category) · {{ $a->category }}@endif @if (! $a->service_category_id && isset($suggest[$a->id]))<span class="text-amber-700"> {{ __('· proposé') }}</span>@endif</div></td>
                  <td class="py-2"><select name="map[{{ $a->id }}]" class="w-full rounded-md border-ink/20 px-3 py-2 text-sm" @disabled(! $canEdit)><option value="">{{ __('— Non rattaché —') }}</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}" @selected($sel === $c->id)>{{ $c->label }}</option>@endforeach</select></td>
                </tr>
              @empty
                <tr><td colspan="2" class="py-6 text-center text-ink/45">{{ __('Aucun acte. Ajoutez des actes dans Paramètres → Actes.') }}</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($canEdit && $acts->isNotEmpty())<button class="mt-3 rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">{{ __('Enregistrer le rattachement') }}</button>@endif
      </form>
    </section>

    <div class="space-y-6 self-start">
      <section class="rounded-lg border border-ink/10 bg-white p-5">
        <h2 class="font-semibold">{{ __('Bibliothèque') }}</h2>
        <ul class="mt-2 space-y-1 text-sm">
          @foreach ($categories as $c)
            <li class="flex justify-between gap-2"><span>{{ $c->label }} <span class="text-xs text-ink/45">· {{ __(\App\Models\Tenant\ServiceCategory::FAMILIES[$c->family] ?? $c->family) }}</span></span><span class="text-xs text-ink/55">{{ __(':count acte(s)', ['count' => $c->acts_count]) }}</span></li>
          @endforeach
        </ul>
      </section>
      @if ($canEdit)
        <form method="POST" action="{{ route('formulas.categories.store') }}" class="rounded-lg border border-ink/10 bg-white p-5 space-y-3">@csrf
          <h2 class="font-semibold">{{ __('Ajouter une catégorie') }}</h2>
          <label class="block text-sm font-medium">{{ __('Libellé *') }}<input name="label" required maxlength="120" class="{{ $f }}"></label>
          <label class="block text-sm font-medium">{{ __('Famille') }}<select name="family" class="{{ $f }}">@foreach (\App\Models\Tenant\ServiceCategory::FAMILIES as $k => $t)<option value="{{ $k }}">{{ __($t) }}</option>@endforeach</select></label>
          <button class="rounded-md border border-pine px-4 py-2 text-sm font-semibold text-pine hover:bg-mist">{{ __('Ajouter') }}</button>
        </form>
      @endif
    </div>
  </div>
</x-layouts.tenant>
