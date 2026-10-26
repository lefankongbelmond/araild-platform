@php $L = \App\Support\TenantNav::ROLE_LABELS; @endphp
<x-layouts.tenant title="Équipe et rôles">
  <x-tenant.hero icon="lock" kicker="Paramètres" tone="brand">
    <x-slot:title>Équipe et rôles</x-slot:title>
    <x-slot:subtitle>Les personnes qui travaillent dans l’espace de gestion de la mutuelle, leurs rôles et leurs accès. Les mots de passe provisoires s’affichent une seule fois.</x-slot:subtitle>
    <x-slot:actions>
      <a href="{{ route('team.create') }}" class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-navy">Ajouter une personne</a>
      <a href="{{ route('team.roles') }}" class="rounded-md border border-ink/10 bg-white px-4 py-2 text-sm font-medium text-ink/70">Rôles et droits</a>
    </x-slot:actions>
  </x-tenant.hero>

  @if (session('team_password'))
    @php [$pn, $pe, $pp] = session('team_password'); @endphp
    <div class="mt-4 rounded-xl border border-ink/10 bg-amber-50 p-4 text-sm" role="alert">
      <b>Mot de passe provisoire de {{ $pn }}</b> — à transmettre en main propre ou par téléphone, <b>il ne sera plus affiché</b> :
      <div class="mt-2 flex flex-wrap items-center gap-3"><span class="rounded bg-white px-3 py-1.5 font-mono text-lg tracking-wider select-all">{{ $pp }}</span>
        <span class="text-ink/60">Identifiant : {{ $pe }} · connexion : {{ url('/connexion') }}</span></div>
      <p class="mt-2 text-xs text-ink/60">La personne devra choisir son propre mot de passe à la première connexion, puis configurer la double authentification si son rôle l’exige.</p>
    </div>
  @endif
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 flex flex-wrap gap-2 text-sm">
    <a href="{{ route('team.index') }}" class="rounded-md px-3 py-1.5 {{ ! $role ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">Tous</a>
    @foreach ($L as $k => $v)<a href="{{ route('team.index', ['role' => $k]) }}" class="rounded-md px-3 py-1.5 {{ $role === $k ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ $v }}</a>@endforeach
  </div>

  <div class="mt-4 overflow-x-auto rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm">
      <thead class="bg-mist text-left text-ink/60"><tr><th class="px-4 py-2 font-medium">Personne</th><th class="px-4 py-2 font-medium">Rôles</th><th class="px-4 py-2 font-medium">Sécurité</th><th class="px-4 py-2 font-medium">Dernière connexion</th><th class="px-4 py-2 font-medium">Actions</th></tr></thead>
      <tbody class="divide-y divide-ink/5">
        @forelse ($users as $u)
          <tr class="{{ $u->active ? '' : 'text-ink/45' }}">
            <td class="px-4 py-3"><b>{{ $u->name }}</b>@if($u->is($me)) <span class="text-xs text-ink/45">(vous)</span>@endif<div class="text-xs text-ink/50">{{ $u->email }}@if($u->phone) · {{ $u->phone }}@endif</div>
              @unless ($u->active)<span class="rounded-full bg-clay/10 px-2 py-0.5 text-xs text-clay">Désactivé</span>@endunless</td>
            <td class="px-4 py-3">@foreach ($u->roles as $r)<span class="mr-1 inline-block rounded-full bg-mist px-2 py-0.5 text-xs text-pine">{{ $L[$r->name] ?? $r->name }}</span>@endforeach</td>
            <td class="px-4 py-3 text-xs">
              {{ $u->two_factor_confirmed_at ? 'Double authentification active' : (collect(config('mxconnect.mfa_required_roles'))->intersect($u->roles->pluck('name'))->isNotEmpty() ? 'Double authentification à configurer' : 'Mot de passe seul') }}
              @if ($u->must_change_password ?? false)<div class="text-ink/45">Mot de passe provisoire</div>@endif
            </td>
            <td class="px-4 py-3 text-ink/60">{{ $u->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap items-center gap-3 text-xs">
                <a class="text-pine hover:underline" href="{{ route('team.edit', $u->id) }}">Modifier</a>
                @unless ($u->is($me))
                  <form method="POST" action="{{ route('team.toggle', $u->id) }}" onsubmit="return confirm('{{ $u->active ? 'Désactiver ce compte ? La personne sera déconnectée.' : 'Réactiver ce compte ?' }}')">@csrf<button class="{{ $u->active ? 'text-clay' : 'text-pine' }} hover:underline">{{ $u->active ? 'Désactiver' : 'Réactiver' }}</button></form>
                  <form method="POST" action="{{ route('team.password', $u->id) }}" onsubmit="return confirm('Générer un nouveau mot de passe provisoire pour {{ $u->name }} ?')">@csrf<button class="text-ink/70 hover:underline">Nouveau mot de passe</button></form>
                @endunless
                @if ($u->two_factor_confirmed_at)
                  <form method="POST" action="{{ route('team.mfa', $u->id) }}" onsubmit="return confirm('Réinitialiser la double authentification de {{ $u->name }} (téléphone perdu ou changé) ?')">@csrf<button class="text-ink/70 hover:underline">Réinitialiser la double authentification</button></form>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="px-4 py-10 text-center text-ink/45">Personne avec ce rôle.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <p class="mt-3 text-xs text-ink/45">Contrôle « 4 yeux » : une même personne ne peut pas cumuler des rôles incompatibles (saisie et validation). Il reste toujours au moins un administrateur actif. Les modifications sont tracées dans le journal d’audit.</p>
</x-layouts.tenant>
