@php $T = \App\Models\Tenant\ProviderConvention::TYPES; $S = ['captured' => 'À valider', 'validated' => 'Validées', 'rejected' => 'Rejetées', 'controlled' => 'Contrôlées']; @endphp
<x-layouts.tenant :title="$provider?->name ?? 'Prestataire'">
  <a href="{{ route('providers.index') }}" class="text-sm text-pine hover:underline">← Prestataires conventionnés</a>
  <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">{{ $provider?->name ?? 'Prestataire n° ' . $c->care_provider_id }}</h1>
      <p class="mt-1 text-sm text-ink/55">{{ $T[$provider?->type] ?? '' }}@if($provider?->locality) · {{ $provider->locality }}@endif @if($provider?->phone) · {{ $provider->phone }}@endif</p>
    </div>
    <div class="flex flex-wrap gap-2">
      @if ($c->inForce())<span class="rounded-full bg-mist px-3 py-1 text-sm text-pine">Convention en vigueur</span>
      @elseif (! $c->active)<span class="rounded-full bg-clay/10 px-3 py-1 text-sm text-clay">Convention suspendue</span>
      @else<span class="rounded-full bg-amber-50 px-3 py-1 text-sm text-amber-700">Hors des dates de la convention</span>@endif
    </div>
  </div>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm lg:col-span-2">
      <h2 class="font-semibold">Convention</h2>
      <dl class="mt-3 grid gap-3 sm:grid-cols-2">
        <div><dt class="text-ink/50">Numéro</dt><dd>{{ $c->reference ?: '—' }}</dd></div>
        <div><dt class="text-ink/50">Période</dt><dd>{{ $c->starts_on?->format('d/m/Y') ?? '…' }} → {{ $c->ends_on?->format('d/m/Y') ?? '…' }}</dd></div>
        <div><dt class="text-ink/50">Contact</dt><dd>{{ $c->contact_name ?: '—' }}@if($c->contact_phone) · {{ $c->contact_phone }}@endif</dd></div>
        <div><dt class="text-ink/50">Vérifications de droits (30 jours)</dt><dd>{{ $checks }}</dd></div>
        @if ($c->notes)<div class="sm:col-span-2"><dt class="text-ink/50">Notes</dt><dd class="whitespace-pre-line">{{ $c->notes }}</dd></div>@endif
      </dl>
      @if ($canEdit)
        <div class="mt-4 flex flex-wrap gap-3">
          <a href="{{ route('providers.edit', $c->id) }}" class="rounded-md border border-ink/15 px-4 py-2 text-sm font-medium hover:bg-mist">Modifier</a>
          <form method="POST" action="{{ route('providers.toggle', $c->id) }}" onsubmit="return confirm('{{ $c->active ? 'Suspendre la convention ? Les comptes du prestataire ne pourront plus vérifier de droits ni déclarer de soins.' : 'Réactiver la convention ?' }}')">@csrf
            <button class="rounded-md px-4 py-2 text-sm font-medium {{ $c->active ? 'text-clay hover:bg-clay/10' : 'text-pine hover:bg-mist' }}">{{ $c->active ? 'Suspendre la convention' : 'Réactiver la convention' }}</button></form>
        </div>
      @endif
    </section>

    <section class="space-y-4 text-sm">
      <div class="rounded-lg border border-ink/10 bg-white p-5">
        <h2 class="font-semibold">Prises en charge</h2>
        <ul class="mt-2 space-y-1">@foreach ($S as $k => $v)<li class="flex justify-between"><span class="text-ink/60">{{ $v }}</span><b>{{ $stats[$k] ?? 0 }}</b></li>@endforeach</ul>
        <h2 class="mt-4 font-semibold">Factures</h2>
        <ul class="mt-2 space-y-1">
          <li class="flex justify-between"><span class="text-ink/60">En attente de paiement</span><b>{{ ($invoices['captured'] ?? 0) + ($invoices['processed'] ?? 0) + ($invoices['prepared'] ?? 0) }}</b></li>
          <li class="flex justify-between"><span class="text-ink/60">Payées</span><b>{{ $invoices['paid'] ?? 0 }}</b></li>
        </ul>
      </div>
    </section>
  </div>

  <section class="mt-6 rounded-lg border border-ink/10 bg-white p-5 text-sm">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h2 class="font-semibold">Comptes du portail prestataire</h2>
      @acces('team.create')<a href="{{ route('team.create') }}" class="text-pine hover:underline">Créer un compte (rôle « Prestataire de soins »)</a>@endacces
    </div>
    <ul class="mt-3 divide-y divide-ink/5">
      @forelse ($accounts as $u)
        <li class="flex flex-wrap items-center justify-between gap-2 py-2 {{ $u->active ? '' : 'text-ink/45' }}"><span><b>{{ $u->name }}</b> <span class="text-xs text-ink/50">{{ $u->email }}</span></span>
          <span class="text-xs">{{ $u->active ? ($u->two_factor_confirmed_at ? 'Actif · double authentification active' : 'Actif · double authentification à configurer') : 'Désactivé' }}</span></li>
      @empty
        <li class="py-2 text-ink/50">Aucun compte. Créez-en un dans « Équipe et rôles » en choisissant le rôle « Prestataire de soins » et cet établissement.</li>
      @endforelse
    </ul>
  </section>
</x-layouts.tenant>
