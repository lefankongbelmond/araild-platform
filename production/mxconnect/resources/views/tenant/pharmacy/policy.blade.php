<x-layouts.tenant title="{{ __('Politique pharmaceutique') }}">
  @php $f = 'mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm'; @endphp
  <a href="{{ route('pharmacy.index') }}" class="text-sm text-ink/50 hover:underline">{{ __('&larr; Délivrances en pharmacie') }}</a>
  <x-tenant.hero icon="pill" kicker="{{ __('Pharmacie') }}" tone="violet" class="mt-2">
    <x-slot:title>{{ __('Politique pharmaceutique') }}</x-slot:title>
    <x-slot:subtitle>{{ __('Réglages de la mutuelle et liste des médicaments pris en charge. Un réglage laissé vide n’est pas appliqué : aucune valeur n’est imposée par le logiciel. Les quantités prescrites ne sont jamais modifiées : au-delà du maximum, la part en trop reste à la charge du patient.') }}</x-slot:subtitle>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-lg border border-clay/30 bg-white p-4 text-sm text-clay" role="alert">{{ $errors->first() }}</div>@endif

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <form method="POST" action="{{ route('pharmacy.policy.update') }}" class="self-start space-y-3 rounded-lg border border-ink/10 bg-white p-5">@csrf
      <h2 class="font-semibold">{{ __('Réglages') }}</h2>
      @foreach (\App\Models\Tenant\MutualSetting::KEYS as $k => [$label, $type])
        <label class="block text-sm font-medium">{{ $label }}<input name="{{ $k }}" inputmode="numeric" value="{{ old($k, $settings[$k]) }}" class="{{ $f }}" placeholder="{{ __('Vide = non appliqué') }}"></label>
      @endforeach
      <p class="text-xs text-ink/50">{{ __('Les plafonds de pharmacie (par an, par ordonnance) se fixent dans chaque formule : Catalogue des formules → Plafonds (« par acte » = par ordonnance délivrée).') }}</p>
      <button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">{{ __('Enregistrer') }}</button>
    </form>

    <form method="POST" action="{{ route('pharmacy.medicines') }}" class="min-w-0 rounded-lg border border-ink/10 bg-white p-5 lg:col-span-2">@csrf
      <h2 class="font-semibold">{{ __('Médicaments pris en charge') }}</h2>
      <p class="mt-1 text-xs text-ink/55">{{ __('« Liste » : médicament de la liste positive de la mutuelle. « Exclu » : jamais pris en charge. Un médicament hors liste ou exclu reste à la charge du patient.') }}</p>
      <div class="mt-3 overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-left text-ink/55"><tr><th class="py-2 pr-3 font-medium">{{ __('Médicament') }}</th><th class="py-2 pr-2 font-medium">{{ __('Liste') }}</th><th class="py-2 pr-2 font-medium">{{ __('Exclu') }}</th><th class="py-2 pr-2 font-medium">{{ __('Générique') }}</th><th class="py-2 font-medium">{{ __('Qté max.') }}</th></tr></thead>
          <tbody class="divide-y divide-ink/5">
            @forelse ($medicines as $m)
              <tr>
                <td class="py-2 pr-3">{{ $m->label }}<div class="text-xs text-ink/45">{{ $m->categoryLabel() }}@if ($m->presentation) · {{ $m->presentation }}@endif</div></td>
                <td class="py-2 pr-2"><input type="checkbox" name="m[{{ $m->id }}][eligible]" value="1" @checked($m->eligible) aria-label="Liste positive : {{ $m->label }}"></td>
                <td class="py-2 pr-2"><input type="checkbox" name="m[{{ $m->id }}][excluded]" value="1" @checked($m->excluded) aria-label="Exclu : {{ $m->label }}"></td>
                <td class="py-2 pr-2"><input type="checkbox" name="m[{{ $m->id }}][generic]" value="1" @checked($m->generic) aria-label="Générique : {{ $m->label }}"></td>
                <td class="py-2"><input name="m[{{ $m->id }}][max_quantity]" inputmode="numeric" value="{{ $m->max_quantity }}" class="w-20 rounded-md border-ink/20 px-2 py-1 text-sm" aria-label="Quantité maximale : {{ $m->label }}"></td>
              </tr>
            @empty
              <tr><td colspan="5" class="py-6 text-center text-ink/45">{{ __('Aucun médicament : ajoutez-en dans Paramètres → Médicaments.') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($medicines->isNotEmpty())<button class="mt-3 rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">{{ __('Enregistrer la liste') }}</button>@endif
    </form>
  </div>
</x-layouts.tenant>
