@php
  $tones = ['pending' => 'bg-amber-50 text-amber-800 ring-amber-200', 'approved' => 'bg-leaf-50 text-leaf-700 ring-leaf-100', 'suspended' => 'bg-clay/10 text-clay ring-clay/20'];
  $labels = ['pending' => 'En attente d’agrément', 'approved' => 'Agréée', 'suspended' => 'Suspendue'];
  $actions = ['approved' => 'Agréée', 'suspended' => 'Suspendue', 'reactivated' => 'Réactivée'];
  $domain = $mutual->domains->first()?->domain;
  $f = 'mt-1 w-full rounded-xl border border-ink/10 bg-white px-3 py-2.5 text-sm outline-none focus:border-pine focus:ring-4 focus:ring-pine/10';
@endphp
<x-layouts.network :title="$mutual->name">
  <a href="{{ route('network.mutuals.index') }}" class="text-sm font-medium text-pine hover:underline">← Mutuelles</a>
  <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
      <h1 class="text-2xl font-bold tracking-tight text-navy sm:text-3xl">{{ $mutual->name }}</h1>
      <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-ink/60">
        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $tones[$mutual->status] ?? '' }}">{{ $labels[$mutual->status] ?? $mutual->status }}</span>
        <span>{{ $mutual->country?->name }}@if ($mutual->city) · {{ $mutual->city }}@endif</span>
      </div>
    </div>
    <div class="flex flex-wrap gap-2">
      @if ($domain && $mutual->status !== 'suspended')<a href="https://{{ $domain }}/connexion" target="_blank" rel="noopener" class="inline-flex h-10 items-center rounded-xl border border-ink/10 bg-white px-4 text-sm font-semibold text-navy hover:bg-mist">Ouvrir son espace ↗</a>@endif
      @can('approve', $mutual)
        <form method="POST" action="{{ route('network.mutuals.approve', $mutual) }}">@csrf<button class="h-10 rounded-xl bg-leaf-600 px-4 text-sm font-bold text-white">Agréer la mutuelle</button></form>
      @endcan
      @can('reactivate', $mutual)
        <form method="POST" action="{{ route('network.mutuals.reactivate', $mutual) }}" onsubmit="return confirm('Réactiver cette mutuelle ? Son espace sera de nouveau ouvert.')">@csrf<button class="h-10 rounded-xl bg-leaf-600 px-4 text-sm font-bold text-white">Réactiver la mutuelle</button></form>
      @endcan
    </div>
  </div>

  @if ($mutual->status === 'suspended')
    <div class="mt-5 rounded-2xl border border-clay/30 bg-clay/5 p-4 text-sm text-clay">
      <b>Espace fermé</b> depuis le {{ $mutual->suspended_at ? \Illuminate\Support\Carbon::parse($mutual->suspended_at)->format('d/m/Y à H:i') : '—' }} : back-office, portail des prestataires et espace des membres affichent « Espace suspendu ». Les données sont conservées.
      @if ($mutual->suspension_reason)<div class="mt-1">Motif : {{ $mutual->suspension_reason }}</div>@endif
    </div>
  @endif

  @if (session('admin_password'))
    @php [$ae, $ap, $al, $reset] = session('admin_password'); @endphp
    <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm" role="alert">
      <b>{{ $reset ? 'Nouveau mot de passe provisoire' : 'Mot de passe provisoire' }} de {{ $ae }}</b> — à transmettre en main propre ou par téléphone, <b>il ne sera plus affiché</b> :
      <div class="mt-2 flex flex-wrap items-center gap-3"><span class="select-all rounded-lg bg-white px-3 py-1.5 font-mono text-lg tracking-wider">{{ $ap }}</span><span class="break-all text-ink/60">Connexion : {{ $al }}</span></div>
      <p class="mt-2 text-xs text-ink/60">À la 1re connexion : choix d’un mot de passe personnel, puis configuration de la double authentification.</p>
    </div>
  @endif
  @if ($errors->any())<div class="mt-5 rounded-xl border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="min-w-0 space-y-6 lg:col-span-2">
      <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink/[.06] sm:p-6">
        <h2 class="text-lg font-bold text-navy">Identité et espace</h2>
        <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
          <div><dt class="text-ink/50">Sous-domaine</dt><dd class="break-all font-mono">{{ $domain ?? '—' }}</dd></div>
          <div><dt class="text-ink/50">Identifiant</dt><dd class="font-mono">{{ $mutual->id }}</dd></div>
          <div><dt class="text-ink/50">Pays</dt><dd>{{ $mutual->country?->name ?? '—' }}</dd></div>
          <div><dt class="text-ink/50">Devise</dt><dd>{{ $mutual->currency ? $mutual->currency->code . ' — ' . $mutual->currency->name : '—' }}</dd></div>
          <div><dt class="text-ink/50">Langue</dt><dd>{{ $locale?->name ?? '—' }}</dd></div>
          <div><dt class="text-ink/50">Région</dt><dd>{{ $mutual->region ?: '—' }}</dd></div>
          <div><dt class="text-ink/50">Créée le</dt><dd>{{ $mutual->created_at?->format('d/m/Y') }}</dd></div>
          <div><dt class="text-ink/50">Agréée le</dt><dd>{{ $mutual->approved_at?->format('d/m/Y') ?? '—' }}</dd></div>
          <div class="sm:col-span-2"><dt class="text-ink/50">Stockage des données</dt>
            <dd>{{ \App\Support\TenantStorage::label($mutual) }}
              <span class="block text-xs text-ink/50">{{ \App\Support\TenantStorage::isPrefixed($mutual)
                ? 'Tables créées automatiquement dans la base centrale, réservées à cette mutuelle (aucune base à créer dans le panneau d’hébergement).'
                : 'Base de données propre à la mutuelle.' }}</span></dd></div>
        </dl>
      </section>

      <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink/[.06] sm:p-6">
        <h2 class="text-lg font-bold text-navy">Administrateurs de la mutuelle</h2>
        <ul class="mt-3 divide-y divide-ink/5 text-sm">
          @forelse ($admins as $a)
            <li class="flex flex-wrap items-center justify-between gap-2 py-2.5 {{ $a->active ? '' : 'text-ink/45' }}">
              <span><b>{{ $a->name }}</b> <span class="break-all text-ink/55">{{ $a->email }}</span></span>
              <span class="text-xs text-ink/55">{{ $a->active ? ($a->mfa ? 'Double authentification active' : 'Double authentification à configurer') : 'Désactivé' }} · dernière connexion : {{ $a->last_login ?? 'jamais' }}</span>
            </li>
          @empty
            <li class="py-2.5 text-ink/55">Aucun administrateur : créez-en un ci-dessous.</li>
          @endforelse
        </ul>
        @can('manageAccess', $mutual)
          <form method="POST" action="{{ route('network.mutuals.admin', $mutual) }}" class="mt-4 grid grid-cols-1 gap-3 rounded-xl bg-paper p-4 sm:grid-cols-3">@csrf
            <p class="text-sm text-ink/70 sm:col-span-3"><b>Créer un administrateur ou rétablir un accès</b> (mot de passe oublié, téléphone perdu) : un mot de passe provisoire s’affiche une fois ; la double authentification est à reconfigurer. Un compte existant devient « Administrateur » seul.</p>
            <label class="text-sm font-medium sm:col-span-3">E-mail *<input type="email" name="admin_email" required maxlength="180" class="{{ $f }}"></label>
            <label class="text-sm font-medium">Prénom<input name="admin_first_name" maxlength="120" class="{{ $f }}"></label>
            <label class="text-sm font-medium">Nom<input name="admin_last_name" maxlength="120" class="{{ $f }}"></label>
            <div class="flex items-end"><button class="h-11 w-full rounded-xl bg-navy px-4 text-sm font-bold text-white" onclick="return confirm('Générer un mot de passe provisoire pour cette adresse ?')">Valider</button></div>
          </form>
        @endcan
      </section>
    </div>

    <div class="min-w-0 space-y-6">
      @can('suspend', $mutual)
        <form id="suspendre" method="POST" action="{{ route('network.mutuals.suspend', $mutual) }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-clay/20 sm:p-6" onsubmit="return confirm('Suspendre {{ addslashes($mutual->name) }} ? Son espace sera fermé immédiatement.')">@csrf
          <h2 class="text-lg font-bold text-clay">Suspendre la mutuelle</h2>
          <p class="mt-1 text-sm text-ink/60">Ferme immédiatement son back-office, le portail de ses prestataires et l’espace de ses membres. Les données sont conservées ; la réactivation rouvre tout.</p>
          <label class="mt-3 block text-sm font-medium">Motif *<textarea name="reason" required minlength="5" maxlength="500" rows="3" class="{{ $f }}" placeholder="Ex. cotisation SaaS impayée depuis 3 mois">{{ old('reason') }}</textarea></label>
          <button class="mt-3 h-11 w-full rounded-xl bg-clay px-4 text-sm font-bold text-white">Suspendre</button>
        </form>
      @endcan

      <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink/[.06] sm:p-6">
        <h2 class="text-lg font-bold text-navy">Historique</h2>
        <ol class="mt-3 space-y-3 text-sm">
          @forelse ($history as $h)
            <li class="border-l-2 border-ink/10 pl-3"><b>{{ $actions[$h['action']] ?? $h['action'] }}</b>
              <span class="block text-xs text-ink/55">{{ \Illuminate\Support\Carbon::parse($h['at'])->format('d/m/Y à H:i') }} · {{ $byId[$h['by']] ?? '—' }}</span>
              @if (! empty($h['note']))<span class="block text-ink/70">{{ $h['note'] }}</span>@endif</li>
          @empty
            <li class="text-ink/55">Aucun changement d’état enregistré depuis la mise à jour du 02/11/2026.</li>
          @endforelse
        </ol>
      </section>
    </div>
  </div>
</x-layouts.network>
