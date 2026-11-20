<x-layouts.tenant title="{{ __('Accords préalables') }}">
  @php
    $S = array_map('__', \App\Models\Tenant\Preauthorization::STATUSES); $f = 'mt-1 w-full rounded-md border-ink/20 px-3 py-2 text-sm';
    $canAsk = \App\Support\TenantAccess::can('preauth.store');
  @endphp
  <x-tenant.hero icon="shield" kicker="{{ __('Prestations · contrôle « 4 yeux »') }}" tone="teal">
    <x-slot:title>{{ __('Accords préalables') }}</x-slot:title>
    <x-slot:subtitle>{{ __('Demandes des prestataires (et de la mutuelle) pour les soins soumis à accord : contrôles automatiques (droits, garantie, carence, plafonds), puis examen administratif ou médical par une autre personne que le demandeur. Les urgences soignées sans accord arrivent ici pour régularisation.') }}</x-slot:subtitle>
  </x-tenant.hero>

  @if ($errors->any())<div class="mt-4 rounded-lg border border-clay/30 bg-white p-4 text-sm text-clay" role="alert"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="min-w-0 space-y-4 lg:col-span-2">
      <nav class="flex flex-wrap gap-2 text-sm" aria-label="{{ __('Filtrer') }}">
        <a href="{{ route('preauth.index') }}" @class(['rounded-full px-3 py-1', 'bg-pine text-white' => ! $status && ! request('urgence'), 'bg-white text-ink/70 ring-1 ring-ink/10' => $status || request('urgence')])>{{ __('Toutes') }}</a>
        @foreach (['requested', 'approved', 'used', 'rejected'] as $k)
          <a href="{{ route('preauth.index', ['statut' => $k]) }}" @class(['rounded-full px-3 py-1', 'bg-pine text-white' => $status === $k, 'bg-white text-ink/70 ring-1 ring-ink/10' => $status !== $k])>{{ $S[$k] }} ({{ $counts[$k] ?? 0 }})</a>
        @endforeach
        <a href="{{ route('preauth.index', ['urgence' => 1]) }}" @class(['rounded-full px-3 py-1', 'bg-pine text-white' => request('urgence'), 'bg-white text-ink/70 ring-1 ring-ink/10' => ! request('urgence')])>{{ __('Urgences') }}</a>
      </nav>
      <div class="overflow-x-auto rounded-lg border border-ink/10 bg-white">
        <table class="w-full text-sm">
          <thead class="bg-mist text-left text-ink/60"><tr>
            <th class="px-4 py-2 font-medium">{{ __('Demande') }}</th><th class="px-4 py-2 font-medium">{{ __('Bénéficiaire') }}</th><th class="px-4 py-2 font-medium">{{ __('Acte · établissement') }}</th>
            <th class="px-4 py-2 font-medium">{{ __('Montant') }}</th><th class="px-4 py-2 font-medium">{{ __('État') }}</th><th></th>
          </tr></thead>
          <tbody class="divide-y divide-ink/5">
            @forelse ($items as $p)
              @php $who = $p->dependent ?? $p->member; @endphp
              <tr class="align-top">
                <td class="px-4 py-3">{{ __('n° :id', ['id' => $p->id]) }}<div class="text-xs text-ink/50">{{ $p->planned_on?->format('d/m/Y') }} · {{ $p->review_type === 'medical' ? __('médical') : __('administratif') }}</div>@if ($p->emergency)<div class="text-xs font-semibold text-amber-700">{{ __('Urgence') }}</div>@endif</td>
                <td class="px-4 py-3">{{ $who?->last_name }} {{ $who?->first_name }}<div class="text-xs text-ink/50">{{ $p->member?->member_code }}</div></td>
                <td class="px-4 py-3">{{ $p->actType?->label }}<div class="text-xs text-ink/50">{{ $providers[$p->provider_id] ?? 'Établissement n° ' . $p->provider_id }}</div></td>
                <td class="px-4 py-3 tabular-nums">{{ $currency->format($p->estimated_minor) }}</td>
                <td class="px-4 py-3 text-xs"><b class="{{ in_array($p->status, ['approved', 'used'], true) ? 'text-pine' : ($p->status === 'rejected' ? 'text-clay' : 'text-ink/70') }}">{{ $S[$p->status] ?? $p->status }}</b>@if ($p->number)<div class="font-mono">{{ $p->number }}</div>@endif</td>
                <td class="px-4 py-3 text-right"><a href="{{ route('preauth.show', $p) }}" class="font-semibold text-pine hover:underline">{{ __('Ouvrir') }}</a></td>
              </tr>
            @empty
              <tr><td colspan="6" class="px-4 py-10 text-center text-ink/45">{{ __('Aucune demande d’accord préalable.') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      {{ $items->links() }}
    </div>

    @if ($canAsk)
      <form method="POST" action="{{ route('preauth.store') }}" class="self-start space-y-3 rounded-lg border border-ink/10 bg-white p-5">@csrf
        <h2 class="font-semibold">{{ __('Saisir une demande') }}</h2>
        <p class="text-xs text-ink/55">{{ __('Pour une demande reçue par un autre canal (courrier, téléphone). Une autre personne l’examinera.') }}</p>
        <label class="block text-sm font-medium">{{ __('N° d’adhérent *') }}<input name="member_code" required maxlength="40" value="{{ old('member_code') }}" class="{{ $f }}"></label>
        <label class="block text-sm font-medium">{{ __('Établissement conventionné *') }}<select name="provider_id" required class="{{ $f }}"><option value="">—</option>@foreach ($conventions as $pid)<option value="{{ $pid }}" @selected((int) old('provider_id') === $pid)>{{ $providers[$pid] ?? 'Établissement n° ' . $pid }}</option>@endforeach</select></label>
        <label class="block text-sm font-medium">{{ __('Acte *') }}<select name="act_type_id" required class="{{ $f }}"><option value="">—</option>@foreach ($acts as $a)<option value="{{ $a->id }}" @selected((int) old('act_type_id') === $a->id)>{{ $a->label }}</option>@endforeach</select></label>
        <div class="grid grid-cols-2 gap-2">
          <label class="block text-sm font-medium">{{ __('Date prévue *') }}<input type="date" name="planned_on" required min="{{ now()->toDateString() }}" value="{{ old('planned_on') }}" class="{{ $f }}"></label>
          <label class="block text-sm font-medium">{{ __('Montant (:currency) *', ['currency' => $currency->code]) }}<input name="estimated" required inputmode="decimal" value="{{ old('estimated') }}" class="{{ $f }}"></label>
        </div>
        @acces('capacite.donnees-sante')
          <label class="block text-sm font-medium">{{ __('Justification médicale') }}<textarea name="clinical_info" rows="3" maxlength="2000" class="{{ $f }}">{{ old('clinical_info') }}</textarea></label>
        @endacces
        <button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">{{ __('Enregistrer la demande') }}</button>
      </form>
    @endif
  </div>
</x-layouts.tenant>
