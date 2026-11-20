<x-layouts.tenant title="{{ __('Catalogue des formules') }}">
  @php $canEdit = \App\Support\TenantAccess::can('formulas.rules.store'); @endphp
  <x-tenant.hero icon="library" kicker="{{ __('Paramètres · Catalogue des formules') }}" tone="brand">
    <x-slot:title>{{ __('Catalogue des formules') }}</x-slot:title>
    <x-slot:subtitle>{{ __('Chaque formule réunit des garanties détaillées (taux par catégorie de soins et de bénéficiaire), des plafonds et des délais de carence. Tout est paramétré par la mutuelle : aucune valeur n’est imposée par le logiciel.') }}</x-slot:subtitle>
    <x-slot:actions>
      <a href="{{ route('formulas.categories') }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-ink/10 bg-white px-4 text-sm font-semibold text-[#0B1F3A] hover:bg-paper"><x-tenant.icon name="list" :size="16"/> {{ __('Catégories de soins') }}</a>
      @if ($canEdit)<a href="{{ route('guarantees.create') }}" class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('+ Nouvelle formule') }}</a>@endif
    </x-slot:actions>
  </x-tenant.hero>

  @if ($isTest)
    <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
      <b>{{ __('Environnement de test.') }}</b> Cette mutuelle est la MUTUELLE TEST : les données servent aux essais et peuvent être effacées.
      @if ($canEdit && ! $hasExamples)
        <form method="POST" action="{{ route('formulas.examples') }}" class="mt-2">@csrf
          <button class="rounded-md border border-amber-200 bg-white px-3 py-1.5 text-xs font-semibold hover:bg-mist">{{ __('Charger les formules d’exemple (ESSENTIEL, FAMILLE, SCOLAIRE, CONFORT, SENIOR, PERSONNALISÉE)') }}</button>
        </form>
      @endif
    </div>
  @endif

  @if ($unmapped > 0)
    <p class="mt-5 rounded-lg border border-ink/10 bg-white p-4 text-sm text-ink/70">{{ __(':unmapped acte(s) ne sont rattachés à aucune catégorie de soins : pour une formule à garanties détaillées, ils ne sont couverts que par une garantie « toutes prestations ».', ['unmapped' => $unmapped]) }} <a href="{{ route('formulas.categories') }}" class="font-semibold text-pine underline">{{ __('Rattacher les actes') }}</a></p>
  @endif

  <div class="mt-6 overflow-x-auto rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">{{ __('Formule') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('Statut') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('Cotisation') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('Version en cours') }}</th>
        <th class="px-4 py-2 font-medium">{{ __('Garanties · plafonds · carences') }}</th>
        <th class="px-4 py-2"></th>
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($guarantees as $g)
          @php $v = $g->currentVersion; @endphp
          <tr>
            <td class="px-4 py-3"><span class="font-medium">{{ $g->name }}</span>@if ($g->is_example) <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{{ __('Exemple') }}</span>@endif
              <div class="text-xs text-ink/45">{{ $g->code }}@if ($g->category) · {{ $g->category }}@endif</div></td>
            <td class="px-4 py-3">{{ __(\App\Support\CatalogLabels::STATUSES[$g->catalog_status ?? 'active'] ?? $g->catalog_status) }}</td>
            <td class="px-4 py-3">@if ($v){{ $currency->format($v->base_contribution_minor) }} <span class="text-xs text-ink/45">/ {{ \App\Support\CatalogLabels::periodicity($v) }}</span>@else — @endif</td>
            <td class="px-4 py-3">@if ($v)V{{ $v->version_no }} <span class="text-xs text-ink/45">{{ __('depuis le :date', ['date' => $v->valid_from?->format('d/m/Y')]) }}@if ($v->locked) · {{ __('utilisée') }} @endif</span>@else — @endif</td>
            <td class="px-4 py-3">@if ($v && $v->rules_count)<b>{{ $v->rules_count }}</b> · {{ $v->limits_count }} · {{ $v->waiting_periods_count }}@else<span class="text-xs text-ink/50">Taux unique ({{ $v ? rtrim(rtrim(number_format((float) $v->coverage_rate, 2, ',', ''), '0'), ',') : '—' }} %)</span>@endif</td>
            <td class="px-4 py-3 text-right"><a href="{{ route('formulas.show', $g) }}" class="font-semibold text-pine hover:underline">{{ __('Ouvrir') }}</a></td>
          </tr>
        @empty
          <tr><td colspan="6" class="px-4 py-10 text-center text-ink/45">{{ __('Aucune formule. Créez la première avec « Nouvelle formule ».') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <p class="mt-3 text-xs text-ink/50">{{ __('Une formule sans garantie détaillée garde le fonctionnement actuel (taux unique, plafonds et période d’observation de la page « Garanties »). Dès qu’une garantie détaillée est ajoutée à une version, le moteur de prise en charge s’applique à cette version.') }}</p>
</x-layouts.tenant>
