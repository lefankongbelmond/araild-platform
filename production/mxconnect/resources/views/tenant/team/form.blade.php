@php
  $field = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm focus:border-pine focus:outline-none';
  $editing = $member->exists;
  $L = \App\Support\TenantNav::ROLE_LABELS; $D = \App\Support\TenantNav::ROLE_DESCRIPTIONS;
  $sel = old('roles', $current);
  $pairs = collect(config('mxconnect.incompatible_role_pairs', []))->map(fn ($p) => ($L[$p[0]] ?? $p[0]) . ' / ' . ($L[$p[1]] ?? $p[1]))->implode(' ; ');
@endphp
<x-layouts.tenant :title="$editing ? 'Modifier ' . $member->name : 'Ajouter une personne'">
  <a href="{{ route('team.index') }}" class="text-sm text-pine hover:underline">← Équipe et rôles</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ $editing ? $member->name : 'Ajouter une personne à l’équipe' }}</h1>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="POST" action="{{ $editing ? route('team.update', $member->id) : route('team.store') }}" class="mt-6 max-w-3xl space-y-4 rounded-lg border border-ink/10 bg-white p-5">
    @csrf
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm font-medium">Prénom *<input name="first_name" required maxlength="120" value="{{ old('first_name', $member->first_name) }}" class="{{ $field }}"></label>
      <label class="block text-sm font-medium">Nom *<input name="last_name" required maxlength="120" value="{{ old('last_name', $member->last_name) }}" class="{{ $field }}"></label>
      @if ($editing)
        <div class="text-sm"><div class="font-medium">E-mail (identifiant)</div><div class="mt-2 text-ink/70">{{ $member->email }}</div></div>
      @else
        <label class="block text-sm font-medium">E-mail (identifiant de connexion) *<input type="email" name="email" required maxlength="180" value="{{ old('email') }}" class="{{ $field }}"></label>
      @endif
      <label class="block text-sm font-medium">Téléphone<input name="phone" maxlength="20" value="{{ old('phone', $member->phone) }}" class="{{ $field }}"></label>
      @if ($antennas->isNotEmpty())
        <label class="block text-sm font-medium">Antenne
          <select name="antenna_id" class="{{ $field }}"><option value="">Toutes / siège</option>@foreach ($antennas as $a)<option value="{{ $a->id }}" @selected((int) old('antenna_id', $member->antenna_id) === $a->id)>{{ $a->name }}</option>@endforeach</select></label>
      @endif
    </div>
    <fieldset class="space-y-2 text-sm">
      <legend class="font-medium">Rôles *</legend>
      @foreach ($L as $code => $label)
        <label class="flex items-start gap-3 rounded-md border border-ink/10 px-3 py-2">
          <input type="checkbox" name="roles[]" value="{{ $code }}" class="mt-1" @checked(in_array($code, (array) $sel, true))>
          <span><b>{{ $label }}</b>@if (in_array($code, config('mxconnect.mfa_required_roles', []), true)) <span class="text-xs text-ink/45">· double authentification obligatoire</span>@endif
            <span class="block text-xs text-ink/55">{{ $D[$code] ?? '' }}</span></span>
        </label>
      @endforeach
      @if ($pairs)<p class="text-xs text-ink/45">Incompatibles (contrôle « 4 yeux ») : {{ $pairs }}.</p>@endif
    </fieldset>
    @unless ($editing)
      <p class="rounded-md bg-mist px-3 py-2 text-xs text-ink/60">Un mot de passe provisoire sera affiché une seule fois après l’enregistrement. Aucun mot de passe n’est envoyé par e-mail.</p>
    @endunless
    <div class="flex gap-3">
      <button class="rounded-md bg-pine px-5 py-2 text-sm font-medium text-white hover:bg-navy">{{ $editing ? 'Enregistrer' : 'Ajouter à l’équipe' }}</button>
      <a href="{{ route('team.index') }}" class="px-3 py-2 text-sm text-ink/60 hover:underline">Annuler</a>
    </div>
  </form>
</x-layouts.tenant>
