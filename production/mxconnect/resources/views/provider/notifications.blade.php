@php
  $icons = ['claim_validated' => ['check', 'text-pine'], 'claim_rejected' => ['alert', 'text-clay'], 'invoice_paid' => ['wallet', 'text-pine'],
            'invoice_rejected' => ['alert', 'text-clay'], 'invoice_checked' => ['check', 'text-pine'], 'preauth_approved' => ['shield', 'text-pine'], 'preauth_rejected' => ['alert', 'text-clay'],
            'affiliation_approved' => ['globe', 'text-pine'], 'affiliation_rejected' => ['alert', 'text-clay']];
  $labels = ['claims' => 'Voir mes prises en charge', 'invoices' => 'Voir mes factures', 'preauth' => 'Voir mes accords préalables'];
@endphp
<x-layouts.provider title="Avis des mutuelles">
  <x-tenant.hero icon="bell" kicker="Portail prestataire" tone="brand">
    <x-slot:title>Avis des mutuelles</x-slot:title>
    <x-slot:subtitle>Les décisions des mutuelles sur vos prises en charge, factures, accords préalables et demandes d’affiliation. Vous ne voyez que ce que votre rôle dans l’établissement permet.</x-slot:subtitle>
  </x-tenant.hero>

  <div class="mt-6 overflow-hidden rounded-lg border border-ink/10 bg-white">
    <ul class="divide-y divide-ink/5">
      @forelse ($items as $n)
        @php [$ic, $tone] = $icons[$n->kind] ?? ['bell', 'text-ink/60']; @endphp
        <li class="flex gap-3 px-4 py-4 {{ $n->id > $seen ? 'bg-leaf-50' : '' }}">
          <span class="mt-0.5 shrink-0 {{ $tone }}"><x-tenant.icon :name="$ic" :size="20"/></span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-navy">{{ $n->title }}@if ($n->id > $seen) <span class="ml-1 rounded-full bg-pine px-2 py-0.5 text-[11px] font-medium text-white">Nouveau</span>@endif</p>
            @if ($n->body)<p class="mt-0.5 text-sm text-ink/70">{{ $n->body }}</p>@endif
            <p class="mt-1 text-xs text-ink/50">{{ $n->created_at?->format('d/m/Y à H:i') }}</p>
            @if (isset($labels[$n->target]) && $n->mutual_id && in_array((string) $n->mutual_id, $open, true) && \App\Support\ProviderAccess::allowed($n->permission))
              <form method="POST" action="{{ route('pro.open', [$n->target, $n->mutual_id]) }}" class="mt-2">@csrf
                <button class="text-left text-sm font-semibold text-pine hover:underline">{{ $labels[$n->target] }} chez {{ $mutuals[$n->mutual_id] ?? 'la mutuelle' }} →</button></form>
            @elseif ($n->target === 'mutuals' && \App\Support\ProviderAccess::allowed('conventions.view'))
              <a href="{{ route('pro.mutuals') }}" class="mt-2 inline-block text-sm font-semibold text-pine hover:underline">Voir mes mutuelles →</a>
            @endif
          </div>
        </li>
      @empty
        <li class="px-4 py-10 text-center text-sm text-ink/45">Aucun avis pour l’instant. Les décisions des mutuelles apparaîtront ici.</li>
      @endforelse
    </ul>
  </div>
  <div class="mt-4">{{ $items->links() }}</div>
</x-layouts.provider>
