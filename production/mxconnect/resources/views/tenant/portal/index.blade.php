<x-layouts.tenant title="Vérifier les droits">
  @include('tenant.portal._head', ['title' => 'Vérifier les droits d’un adhérent'])
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="space-y-4">
      <form method="POST" action="{{ route('portal.verify') }}" class="space-y-3 rounded-lg border border-ink/10 bg-white p-5">
        @csrf
        <label class="block text-sm font-semibold" for="pv-code">Code adhérent ou lien de la carte</label>
        <input id="pv-code" name="code" required maxlength="500" autocomplete="off" value="{{ old('code') }}" class="w-full rounded-md border border-ink/15 px-3 py-2.5" style="font-size:16px" placeholder="Ex. MUT-000123">
        <p class="text-xs text-ink/55">Le code est imprimé sur la carte de membre. Ou lisez le QR code de la carte :</p>
        <div data-mx-scan data-target="pv-code" class="space-y-2">
          <div class="flex flex-wrap gap-2">
            <button type="button" data-scan-camera style="display:none" class="rounded-md border border-pine px-3 py-2 text-sm font-medium text-pine hover:bg-mist">Scanner avec la caméra</button>
            <label class="cursor-pointer rounded-md border border-ink/15 px-3 py-2 text-sm font-medium text-ink/70 hover:bg-mist">Photo de la carte
              <input type="file" accept="image/*" capture="environment" data-scan-file class="sr-only"></label>
          </div>
          <div data-scan-box style="display:none" class="space-y-2">
            <video playsinline muted class="w-full rounded-md bg-ink" style="max-height:320px;object-fit:cover" aria-label="Aperçu de la caméra"></video>
            <button type="button" data-scan-stop class="text-sm text-ink/60 underline">Arrêter la caméra</button>
          </div>
          <p data-scan-status role="status" aria-live="polite" class="text-xs text-pine"></p>
        </div>
        <button class="w-full rounded-md bg-pine px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy" @disabled(! $convention->inForce())>Vérifier les droits</button>
      </form>
      <p class="text-xs text-ink/45">Seul le verdict s’affiche : aucune donnée médicale. Chaque vérification est enregistrée au nom de votre compte.</p>
    </div>

    <div>
      @if ($result)
        @if ($result['ok'])
          <div class="rounded-lg border border-pine/30 bg-mist p-5 text-center" role="status">
            <div class="text-3xl text-pine" aria-hidden="true">✓</div>
            <div class="mt-1 text-lg font-semibold text-pine">Droits ouverts</div>
            <div class="mt-1 text-sm">{{ $result['member']->last_name }} {{ $result['member']->first_name }} · <span class="font-mono">{{ $result['member']->member_code }}</span></div>
            <a href="{{ route('portal.declare', $result['check']->id) }}" class="mt-4 inline-block rounded-md bg-pine px-5 py-2.5 text-sm font-semibold text-white hover:bg-navy">Déclarer une prise en charge</a>
            <p class="mt-2 text-xs text-ink/50">Déclaration possible pendant 12 heures à partir de cette vérification.</p>
          </div>
        @else
          <div class="rounded-lg border border-clay/30 bg-clay/5 p-5 text-center" role="status">
            <div class="text-3xl text-clay" aria-hidden="true">✕</div>
            <div class="mt-1 text-lg font-semibold text-clay">Droits non ouverts</div>
            @if ($result['member'])<div class="mt-1 text-sm">{{ $result['member']->last_name }} {{ $result['member']->first_name }}</div>@endif
            <ul class="mt-2 text-sm text-ink/70">@foreach ($result['reasons'] as $r)<li>{{ __('mxconnect.rights.'.$r) }}</li>@endforeach</ul>
            <p class="mt-2 text-xs text-ink/50">Orientez l’adhérent vers la mutuelle. Aucune prise en charge ne peut être déclarée.</p>
          </div>
        @endif
      @endif

      <section class="@if($result) mt-6 @endif rounded-lg border border-ink/10 bg-white p-5 text-sm">
        <h2 class="font-semibold">Mes dernières vérifications</h2>
        <ul class="mt-2 divide-y divide-ink/5">
          @forelse ($recent as $r)
            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
              <span>{{ $r->member ? $r->member->last_name . ' ' . $r->member->first_name : 'Code inconnu' }} <span class="text-xs text-ink/45">{{ $r->created_at?->format('d/m H:i') }}</span></span>
              <span class="text-xs {{ $r->ok ? 'text-pine' : 'text-clay' }}">{{ $r->ok ? 'Droits ouverts' : 'Non ouverts' }}</span>
            </li>
          @empty
            <li class="py-2 text-ink/50">Aucune vérification pour l’instant.</li>
          @endforelse
        </ul>
      </section>
    </div>
  </div>
  <x-slot:scripts>
    {{-- Servis par la mutuelle elle-même (même origine : autorisé par la politique de sécurité). --}}
    <script src="/js/vendor/jsqr-1.4.0.min.js" defer></script>
    <script src="/js/mx-scan.js?v={{ config('mxconnect.asset_version') }}" defer></script>
  </x-slot:scripts>
</x-layouts.tenant>
