@php $L = \App\Support\TenantNav::ROLE_LABELS; $D = \App\Support\TenantNav::ROLE_DESCRIPTIONS; $P = ['subscription.capture' => 'Saisir une souscription', 'subscription.validate' => 'Valider une souscription', 'prestation.capture' => 'Saisir une prestation', 'prestation.validate' => 'Valider une prestation']; @endphp
<x-layouts.tenant title="Rôles et droits">
  <a href="{{ route('team.index') }}" class="text-sm text-pine hover:underline">← Équipe et rôles</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">Rôles et droits</h1>
  <p class="mt-1 max-w-3xl text-sm text-ink/60">Ce tableau est calculé à partir des règles réellement appliquées par l’espace de gestion : rubriques visibles dans le menu, et autorisations de saisie et de validation. Certains écrans restreignent en plus des actions précises (par exemple, la décision finale sur une adhésion en ligne).</p>

  <div class="mt-6 grid gap-4 lg:grid-cols-2">
    @foreach ($L as $code => $label)
      <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
        <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="font-semibold">{{ $label }}</h2><span class="text-xs text-ink/50">{{ $counts[$code] ?? 0 }} personne(s) active(s)</span></div>
        <p class="mt-1 text-xs text-ink/55">{{ $D[$code] ?? '' }}@if (in_array($code, config('mxconnect.mfa_required_roles', []), true)) · double authentification obligatoire @endif</p>
        @if (! empty($permissions[$code]))
          <p class="mt-2 text-xs"><b>Autorisations :</b> {{ collect($permissions[$code])->map(fn ($p) => $P[$p] ?? $p)->implode(', ') }}</p>
        @endif
        <ul class="mt-3 space-y-1">
          @foreach ($groups as $g => $items)
            @php $ok = collect($items)->filter(fn ($v, $r) => \App\Support\TenantNav::allows($r, $code) && Route::has($r)); @endphp
            @if ($ok->isNotEmpty())<li><span class="text-ink/50">{{ $g }} :</span> {{ $ok->map(fn ($v) => __($v[0]))->implode(', ') }}</li>@endif
          @endforeach
        </ul>
      </section>
    @endforeach
  </div>
  <p class="mt-4 text-xs text-ink/45">Contrôle « 4 yeux » : {{ collect(config('mxconnect.incompatible_role_pairs', []))->map(fn ($p) => ($L[$p[0]] ?? $p[0]) . ' ≠ ' . ($L[$p[1]] ?? $p[1]))->implode(' ; ') }}.</p>
</x-layouts.tenant>
