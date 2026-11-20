@php
  $c = $data['convention'];
  $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '…';
  $acts = collect($data['acts'])->groupBy(fn ($a) => $a['category'] ?: __('Autres actes'));
@endphp
<x-layouts.provider :title="__('Convention · :name', ['name' => $mutual->name])">
  <a href="{{ route('pro.mutuals') }}" class="text-sm text-pine hover:underline">{{ __('← Mes mutuelles') }}</a>
  <x-tenant.hero icon="book" kicker="{{ __('Convention et tarifs') }}" tone="teal" class="mt-2">
    <x-slot:title>{{ $mutual->name }}</x-slot:title>
    <x-slot:subtitle>{{ __('Informations lues dans la base de la mutuelle. Devise : :name (:reference).', ['name' => $currency?->name, 'reference' => $currency?->code]) }}</x-slot:subtitle>
    <x-slot:actions>
      @if ($data['inForce'] && \App\Support\ProviderAccess::allowed('rights.verify'))
        <form method="POST" action="{{ route('pro.open', ['verify', $mutual->id]) }}">@csrf<button class="rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 px-4 py-2.5 text-sm font-semibold text-white">{{ __('Ouvrir le portail') }}</button></form>
      @endif
    </x-slot:actions>
  </x-tenant.hero>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <x-tenant.panel class="lg:col-span-2" title="{{ __('Convention') }}" icon="file">
      @if (! $c)
        <p class="px-5 pb-5 text-sm text-ink/55 sm:px-6">{{ __('Convention introuvable dans la mutuelle.') }}</p>
      @else
        <dl class="grid gap-3 p-5 text-sm sm:grid-cols-2 sm:px-6">
          <div><dt class="text-ink/50">{{ __('Situation') }}</dt><dd>@if($data['inForce'])<span class="rounded-full bg-leaf-50 px-2 py-0.5 text-leaf-700">{{ __('En vigueur') }}</span>@elseif(! $c['active'])<span class="rounded-full bg-clay/10 px-2 py-0.5 text-clay">{{ __('Suspendue par la mutuelle') }}</span>@else<span class="rounded-full bg-amber-50 px-2 py-0.5 text-amber-700">{{ __('Hors de ses dates') }}</span>@endif</dd></div>
          <div><dt class="text-ink/50">{{ __('Numéro') }}</dt><dd>{{ $c['reference'] ?: '—' }}</dd></div>
          <div><dt class="text-ink/50">{{ __('Signée le') }}</dt><dd>{{ $c['signed_on'] ? $d($c['signed_on']) : '—' }}</dd></div>
          <div><dt class="text-ink/50">{{ __('Période') }}</dt><dd>{{ $d($c['starts_on']) }} → {{ $d($c['ends_on']) }}</dd></div>
          <div><dt class="text-ink/50">{{ __('Délai de transmission des factures') }}</dt><dd>{{ $c['transmission_days'] ? __(':n jours', ['n' => $c['transmission_days']]) : __('Non fixé par la convention') }}</dd></div>
          <div><dt class="text-ink/50">{{ __('Délai de paiement') }}</dt><dd>{{ $c['payment_days'] ? __(':n jours', ['n' => $c['payment_days']]) : __('Non fixé par la convention') }}</dd></div>
          <div><dt class="text-ink/50">{{ __('Contact à la mutuelle') }}</dt><dd>{{ $c['contact_name'] ?: '—' }}@if($c['contact_phone']) · {{ $c['contact_phone'] }}@endif</dd></div>
          @if ($c['billing_terms'])<div class="sm:col-span-2"><dt class="text-ink/50">{{ __('Conditions de facturation') }}</dt><dd class="whitespace-pre-line">{{ $c['billing_terms'] }}</dd></div>@endif
        </dl>
      @endif
    </x-tenant.panel>

    <x-tenant.panel title="{{ __('Garanties qui acceptent l’établissement') }}" icon="shield">
      <ul class="space-y-2.5 p-5 text-sm sm:px-6">
        @forelse ($data['guarantees'] as $g)
          <li class="rounded-xl bg-paper px-3 py-2"><b>{{ $g['name'] }}</b>
            <span class="block text-xs text-ink/55">{{ __('Taux de couverture :') }} {{ $g['rate'] !== null ? rtrim(rtrim(number_format((float) $g['rate'], 2, ',', ''), '0'), ',') . ' %' : __('non défini — à confirmer par la mutuelle') }}@if($g['open']) · {{ __('réseau ouvert à tous les établissements') }} @endif</span></li>
        @empty
          <li class="text-ink/55">{{ __('Aucune garantie en cours n’inclut votre établissement dans son réseau de soins : les droits seront refusés « hors réseau ». Contactez la mutuelle.') }}</li>
        @endforelse
      </ul>
      <p class="px-5 pb-5 text-[11px] text-ink/45 sm:px-6">{{ __('Plafonds et exclusions : appliqués automatiquement lors de la déclaration (détail par adhérent à la vérification des droits).') }}</p>
    </x-tenant.panel>
  </div>

  <x-tenant.panel class="mt-6" title="{{ __('Actes et tarifs conventionnels') }}" icon="list">
    <div class="relative overflow-x-auto px-2 pb-4">
      <table class="w-full text-sm">
        <thead class="text-left text-ink/50"><tr><th class="px-3 py-2 font-medium">{{ __('Code') }}</th><th class="px-3 py-2 font-medium">{{ __('Acte') }}</th><th class="px-3 py-2 text-right font-medium">{{ __('Tarif conventionnel') }}</th></tr></thead>
        <tbody class="divide-y divide-ink/5">
          @forelse ($acts as $cat => $list)
            <tr><td colspan="3" class="bg-mist/60 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-ink/55">{{ $cat }}</td></tr>
            @foreach ($list as $a)
              <tr><td class="px-3 py-2 font-mono text-xs">{{ $a['code'] }}</td><td class="px-3 py-2">{{ $a['label'] }}</td>
                <td class="px-3 py-2 text-right tabular-nums">{{ $data['hasTariff'] && ($a['tariff_minor'] ?? null) !== null && $currency ? $currency->format((int) $a['tariff_minor']) : __('Non défini') }}</td></tr>
            @endforeach
          @empty
            <tr><td colspan="3" class="px-3 py-8 text-center text-ink/50">{{ __('La mutuelle n’a pas encore publié de catalogue d’actes.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <p class="px-5 pb-5 text-xs text-ink/50 sm:px-6">{{ __('« Non défini » : la mutuelle n’a pas fixé de tarif pour cet acte. Aucun tarif n’est appliqué par défaut. Les tarifs propres à votre établissement (avec historique) arrivent avec la phase 4.') }}</p>
  </x-tenant.panel>

  @if ($docs->isNotEmpty())
    <x-tenant.panel class="mt-6" title="{{ __('Documents liés à cette mutuelle') }}" icon="file">
      <ul class="divide-y divide-ink/5 px-5 pb-4 text-sm sm:px-6">
        @foreach ($docs as $doc)<li class="flex items-center justify-between gap-2 py-2"><span>{{ $doc->title }}@if($doc->expires_on) <span class="text-xs text-ink/50">{{ __('· expire le :date', ['date' => $doc->expires_on->format('d/m/Y')]) }}</span>@endif</span><a href="{{ route('pro.documents.download', $doc->id) }}" class="text-xs font-semibold text-pine hover:underline">{{ __('Télécharger') }}</a></li>@endforeach
      </ul>
    </x-tenant.panel>
  @endif
</x-layouts.provider>
