@php
  use App\Support\ProviderAccess;
  $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2.5 text-sm focus:border-pine focus:outline-none';
  $tp = session('temp_password');
@endphp
<x-layouts.provider title="Personnel et utilisateurs">
  <x-tenant.hero icon="users" kicker="Gestion de l’établissement" tone="teal">
    <x-slot:title>Personnel et utilisateurs</x-slot:title>
    <x-slot:subtitle>Chaque personne a son propre compte (double authentification obligatoire) et un rôle dans l’établissement. Vous pouvez ajouter ou retirer des droits personne par personne.</x-slot:subtitle>
  </x-tenant.hero>
  @if ($tp)
    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm" role="status">
      <b>Accès à transmettre</b> (affiché une seule fois) :
      <div class="mt-2 grid gap-1 sm:grid-cols-3"><span>Adresse : <b class="select-all">{{ route('pro.login') }}</b></span><span>Identifiant : <b class="select-all">{{ $tp['email'] }}</b></span><span>Mot de passe provisoire : <b class="select-all font-mono">{{ $tp['password'] }}</b></span></div>
      <p class="mt-1 text-xs text-ink/60">À la première connexion, la personne choisit son mot de passe puis active la double authentification.</p>
    </div>
  @endif
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <x-tenant.panel class="mt-6" title="Ajouter une personne" icon="user-plus">
    <form method="POST" action="{{ route('pro.staff.store') }}" class="grid gap-4 p-5 sm:grid-cols-2 sm:px-6 lg:grid-cols-3">@csrf
      <label class="block text-sm font-medium">Prénom<input name="first_name" required maxlength="120" value="{{ old('first_name') }}" class="{{ $f }}"></label>
      <label class="block text-sm font-medium">Nom<input name="last_name" required maxlength="120" value="{{ old('last_name') }}" class="{{ $f }}"></label>
      <label class="block text-sm font-medium">E-mail (identifiant)<input type="email" name="email" required maxlength="180" value="{{ old('email') }}" class="{{ $f }}"></label>
      <label class="block text-sm font-medium">Téléphone<input name="phone" maxlength="30" value="{{ old('phone') }}" class="{{ $f }}"></label>
      <label class="block text-sm font-medium">Rôle<select name="role" class="{{ $f }}">@foreach (ProviderAccess::ROLES as $k => $v)<option value="{{ $k }}" @selected(old('role', 'doctor') === $k)>{{ $v }}</option>@endforeach</select></label>
      <label class="block text-sm font-medium">Fonction / service <span class="font-normal text-ink/50">(facultatif)</span><input name="job_title" maxlength="120" value="{{ old('job_title') }}" placeholder="ex. Pédiatrie, Accueil" class="{{ $f }}"></label>
      <div class="sm:col-span-2"><button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">Ajouter</button>
        <p class="mt-2 text-xs text-ink/50">Un mot de passe provisoire est généré et affiché une seule fois. Si la personne a déjà un compte prestataire, elle est simplement rattachée.</p></div>
    </form>
  </x-tenant.panel>

  <x-tenant.panel class="mt-6" :title="'Personnel (' . $members->count() . ')'" icon="users">
    <ul class="divide-y divide-ink/5 px-5 pb-4 sm:px-6">
      @foreach ($members as $mb)
        @php $acc = $mb->account; $eff = $mb->effectivePermissions(); $isMe = $mb->id === $me->id; @endphp
        <li class="py-4 text-sm {{ $mb->active ? '' : 'text-ink/45' }}">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div><b>{{ $acc?->name }}</b>@if($isMe) <span class="text-xs text-ink/50">(vous)</span>@endif
              <div class="text-xs text-ink/55">{{ $acc?->email }} · {{ $mb->roleLabel() }}@if($mb->job_title) · {{ $mb->job_title }}@endif</div></div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
              @if (! $mb->active)<span class="rounded-full bg-mist px-2 py-0.5">Désactivé</span>
              @elseif ($acc?->must_change_password)<span class="rounded-full bg-amber-50 px-2 py-0.5 text-amber-700">Mot de passe provisoire</span>
              @elseif (! $acc?->mfa_enabled)<span class="rounded-full bg-amber-50 px-2 py-0.5 text-amber-700">Double authentification à activer</span>
              @else<span class="rounded-full bg-leaf-50 px-2 py-0.5 text-leaf-700">Actif</span>@endif
              <span class="text-ink/45">Dernière connexion : {{ $acc?->last_login_at?->format('d/m/Y H:i') ?? 'jamais' }}</span>
            </div>
          </div>
          <details class="mt-2">
            <summary class="cursor-pointer text-sm font-medium text-pine">Rôle et droits</summary>
            <form method="POST" action="{{ route('pro.staff.update', $mb->id) }}" class="mt-3 space-y-4">@csrf
              <input type="hidden" name="perms_sent" value="1">
              <div class="grid gap-4 sm:grid-cols-3">
                <label class="block text-sm font-medium">Rôle<select name="role" class="{{ $f }}">@foreach (ProviderAccess::ROLES as $k => $v)<option value="{{ $k }}" @selected($mb->role === $k)>{{ $v }}</option>@endforeach</select></label>
                <label class="block text-sm font-medium">Fonction / service<input name="job_title" maxlength="120" value="{{ $mb->job_title }}" class="{{ $f }}"></label>
                <label class="mt-6 flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked($mb->active)> Accès actif</label>
              </div>
              <div class="grid gap-4 md:grid-cols-3">
                @foreach (ProviderAccess::PERMISSIONS as $group => $perms)
                  <fieldset class="rounded-xl bg-paper p-3"><legend class="px-1 text-xs font-semibold uppercase tracking-wide text-ink/50">{{ $group }}</legend>
                    @foreach ($perms as $p => $lbl)
                      @php $bound = in_array($p, ProviderAccess::ROLE_BOUND, true); @endphp
                      <label class="mt-1 flex items-start gap-2 text-sm {{ $bound ? 'text-ink/45' : '' }}"><input type="checkbox" name="perms[]" value="{{ $p }}" class="mt-1" @checked(in_array($p, $eff, true)) @disabled($bound)>
                        <span>{{ $lbl }}@if($bound) <span class="text-[11px]">(rôle Administrateur)</span>@endif</span></label>
                    @endforeach
                  </fieldset>
                @endforeach
              </div>
              <p class="text-xs text-ink/50">En changeant de rôle, les droits par défaut du nouveau rôle s’appliquent, avec les cases que vous avez cochées ou décochées en plus.</p>
              <div class="flex flex-wrap items-center gap-3">
                <button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white hover:bg-navy">Enregistrer</button>
              </div>
            </form>
            @unless ($isMe)
              <form method="POST" action="{{ route('pro.staff.reset', $mb->id) }}" class="mt-3" onsubmit="return confirm('Réinitialiser le mot de passe et la double authentification de cette personne ?')">@csrf
                <button class="text-sm text-clay hover:underline">Réinitialiser le mot de passe et la double authentification</button></form>
            @endunless
          </details>
        </li>
      @endforeach
    </ul>
  </x-tenant.panel>

  <x-tenant.panel class="mt-6" title="Droits par défaut de chaque rôle" icon="shield">
    <div class="relative overflow-x-auto px-2 pb-4">
      <table class="w-full text-xs">
        <thead class="text-left text-ink/50"><tr><th class="px-2 py-2 font-medium">Droit</th>@foreach (ProviderAccess::ROLES as $k => $v)<th class="px-2 py-2 text-center font-medium">{{ $v }}</th>@endforeach</tr></thead>
        <tbody class="divide-y divide-ink/5">
          @foreach (ProviderAccess::PERMISSIONS as $group => $perms)
            @foreach ($perms as $p => $lbl)
              <tr><td class="px-2 py-1.5">{{ $lbl }}</td>@foreach (array_keys(ProviderAccess::ROLES) as $r)<td class="px-2 py-1.5 text-center">{!! in_array($p, ProviderAccess::defaults($r), true) ? '<span class="text-leaf-600" aria-label="oui">✓</span>' : '<span class="text-ink/30" aria-label="non">—</span>' !!}</td>@endforeach</tr>
            @endforeach
          @endforeach
        </tbody>
      </table>
    </div>
  </x-tenant.panel>
</x-layouts.provider>
