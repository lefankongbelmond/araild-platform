@php
  $L = \App\Support\TenantNav::ROLE_LABELS; $D = \App\Support\TenantNav::ROLE_DESCRIPTIONS;
  $short = ['mutual_admin' => 'Admin', 'enrollment_agent' => 'Agent', 'controller_validator' => 'Contrôleur', 'benefits_manager' => 'Prestations', 'treasurer_accountant' => 'Trésorier', 'community_manager' => 'Com.', 'auditor' => 'Auditeur', 'provider' => 'Prestataire'];
  $order = array_keys($short);
  $pairs = collect(config('mxconnect.incompatible_role_pairs', []))->map(fn ($p) => ($L[$p[0]] ?? $p[0]) . ' ≠ ' . ($L[$p[1]] ?? $p[1]))->implode(' ; ');
@endphp
<x-layouts.tenant title="Rôles et droits">
  <a href="{{ route('team.index') }}" class="text-sm text-pine hover:underline">← Équipe et rôles</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">Rôles et droits</h1>
  <p class="mt-1 max-w-3xl text-sm text-ink/60">Ce tableau est lu directement dans la matrice des droits appliquée par le logiciel à <b>chaque adresse</b> de l’espace de gestion : une rubrique fermée à un rôle est refusée même si l’on tape son adresse.</p>

  <div class="mt-6 overflow-x-auto rounded-lg border border-ink/10 bg-white">
    <table class="w-full text-sm" style="min-width:760px">
      <thead class="bg-mist text-left text-ink/60"><tr>
        <th class="px-4 py-2 font-medium">Rubrique et action</th>
        @foreach ($order as $r)<th class="px-2 py-2 text-center font-medium" title="{{ $L[$r] ?? $r }}">{{ $short[$r] }}</th>@endforeach
      </tr></thead>
      <tbody class="divide-y divide-ink/5">
        @foreach (\App\Support\TenantAccess::SUMMARY as $section => $actions)
          <tr><td colspan="{{ count($order) + 1 }}" class="bg-paper px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-ink/50">{{ $section }}</td></tr>
          @foreach ($actions as $route => $label)
            <tr>
              <td class="px-4 py-2">{{ $label }}</td>
              @foreach ($order as $r)
                <td class="px-2 py-2 text-center">@if (\App\Support\TenantAccess::allows($route, $r))<span class="font-bold text-pine" aria-label="oui">✓</span>@else<span class="text-ink/20" aria-label="non">—</span>@endif</td>
              @endforeach
            </tr>
          @endforeach
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="mt-6 grid gap-4 lg:grid-cols-2">
    @foreach ($order as $code)
      <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
        <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="font-semibold">{{ $L[$code] ?? $code }}</h2><span class="text-xs text-ink/50">{{ $counts[$code] ?? 0 }} personne(s) active(s)</span></div>
        <p class="mt-1 text-xs text-ink/55">{{ $D[$code] ?? '' }}@if (in_array($code, config('mxconnect.mfa_required_roles', []), true)) · double authentification obligatoire @endif</p>
        <ul class="mt-3 space-y-1">
          @foreach ($groups as $g => $items)
            @php $ok = collect($items)->filter(fn ($v, $r) => \App\Support\TenantNav::allows($r, $code) && Route::has($r)); @endphp
            @if ($ok->isNotEmpty())<li><span class="text-ink/50">{{ $g }} :</span> {{ $ok->map(fn ($v) => __($v[0]))->implode(', ') }}</li>@endif
          @endforeach
        </ul>
      </section>
    @endforeach
  </div>
  <div class="mt-4 space-y-1 text-xs text-ink/45">
    <p>Contrôle « 4 yeux » : {{ $pairs }}. {{ collect(config('mxconnect.exclusive_roles', []))->map(fn ($r) => $L[$r] ?? $r)->implode(' et ') }} : jamais cumulés avec un autre rôle.</p>
    <p>Personne, administrateur compris, ne valide, ne rejette ni ne paie ce qu’il a lui-même saisi.</p>
  </div>
</x-layouts.tenant>
