@php
  $titles = ['verify' => ['Vérifier les droits', 'shield', 'Choisissez la mutuelle de l’adhérent : son portail s’ouvre sur votre compte, double authentification déjà faite.'],
             'claims' => ['Prises en charge', 'clipboard', 'Vos prises en charge sont tenues par chaque mutuelle : choisissez celle à consulter.'],
             'invoices' => ['Mes factures', 'receipt', 'Vos factures et demandes de paiement sont tenues par chaque mutuelle : choisissez celle à consulter.']];
  [$t, $icon, $sub] = $titles[$target];
@endphp
<x-layouts.provider :title="$t">
  <x-tenant.hero :icon="$icon" kicker="Portail prestataire" tone="teal">
    <x-slot:title>{{ $t }}</x-slot:title>
    <x-slot:subtitle>{{ $sub }}</x-slot:subtitle>
  </x-tenant.hero>
  <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($mutuals as $mu)
      <form method="POST" action="{{ route('pro.open', [$target, $mu->id]) }}" class="flex flex-col justify-between gap-4 rounded-3xl bg-white p-5 shadow-soft ring-1 ring-ink/[.05]">@csrf
        <div><div class="text-lg font-bold text-[#0B1F3A]">{{ $mu->name }}</div><div class="text-sm text-ink/55">{{ $mu->city }}</div></div>
        <button class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 px-4 py-2.5 text-sm font-semibold text-white"><x-tenant.icon :name="$icon" :size="18"/> Ouvrir</button>
      </form>
    @empty
      <x-tenant.panel class="p-6 sm:col-span-2">
        <p class="text-sm text-ink/60">Aucune convention en place. @if(\App\Support\ProviderAccess::allowed('affiliation.request'))<a href="{{ route('pro.mutuals') }}" class="font-semibold text-pine hover:underline">Demander une affiliation</a>.@endif</p>
      </x-tenant.panel>
    @endforelse
  </div>
  <p class="mt-6 text-xs text-ink/50">Le portail s’ouvre sur l’adresse de la mutuelle : les données des adhérents restent dans sa base. Le lien d’entrée est à usage unique et expire après 60 secondes.</p>
</x-layouts.provider>
