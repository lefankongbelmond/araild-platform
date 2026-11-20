@php
  $titles = ['verify' => [__('Vérifier les droits'), 'shield', __('Choisissez la mutuelle de l’adhérent : son portail s’ouvre sur votre compte, double authentification déjà faite.')],
             'claims' => [__('Prises en charge'), 'clipboard', __('Vos prises en charge sont tenues par chaque mutuelle : choisissez celle à consulter.')],
             'invoices' => [__('Mes factures'), 'receipt', __('Vos factures et demandes de paiement sont tenues par chaque mutuelle : choisissez celle à consulter.')]];
  [$t, $icon, $sub] = $titles[$target];
@endphp
<x-layouts.provider :title="$t">
  <x-tenant.hero :icon="$icon" kicker="{{ __('Portail prestataire') }}" tone="teal">
    <x-slot:title>{{ $t }}</x-slot:title>
    <x-slot:subtitle>{{ $sub }}</x-slot:subtitle>
  </x-tenant.hero>
  @if ($target === 'verify' && $mutuals->isNotEmpty())
    <x-tenant.panel class="mt-6" title="{{ __('Identifier le bénéficiaire') }}" icon="search">
      <form method="POST" action="{{ route('pro.lookup') }}" class="space-y-3 p-5 sm:px-6">@csrf
        <label class="block text-sm font-medium" for="pl-code">{{ __('Code de la carte ou lien du QR code') }}</label>
        <input id="pl-code" name="code" required maxlength="500" autocomplete="off" value="{{ old('code', $lookup['code'] ?? '') }}" class="w-full rounded-md border border-ink/15 bg-white px-3 py-2.5" style="font-size:16px" placeholder="{{ __('Ex. MUT-000123') }}">
        <div data-mx-scan data-target="pl-code" class="space-y-2.5">
          <div class="flex flex-wrap gap-2">
            <button type="button" data-scan-camera style="display:none" class="rounded-md border border-pine px-3 py-2 text-sm font-medium text-pine hover:bg-mist">{{ __('Scanner avec la caméra') }}</button>
            <label class="cursor-pointer rounded-md border border-ink/15 px-3 py-2 text-sm font-medium text-ink/70 hover:bg-mist">{{ __('Photo de la carte') }}
              <input type="file" accept="image/*" capture="environment" data-scan-file class="sr-only"></label>
          </div>
          <div data-scan-box style="display:none" class="space-y-2.5">
            <video playsinline muted class="w-full rounded-md bg-ink" style="max-height:320px;object-fit:cover" aria-label="{{ __('Aperçu de la caméra') }}"></video>
            <button type="button" data-scan-stop class="text-sm text-ink/60 underline">{{ __('Arrêter la caméra') }}</button>
          </div>
          <p data-scan-status role="status" aria-live="polite" class="text-xs text-pine"></p>
        </div>
        <button class="rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 px-4 py-2.5 text-sm font-semibold text-white">{{ __('Rechercher dans mes mutuelles') }}</button>
        <p class="text-xs text-ink/55">{{ __('La carte est recherchée dans toutes vos mutuelles conventionnées ; le portail de la bonne mutuelle s’ouvre avec le code déjà saisi. La vérification des droits est faite et enregistrée par la mutuelle. Sans la carte, ouvrez le portail de la mutuelle ci-dessous : la recherche par nom ou téléphone s’y trouve.') }}</p>
      </form>
      @if ($lookup)
        <div class="border-t border-ink/[.06] p-5 text-sm sm:px-6" role="status">
          @if ($lookup['mutuals']->isEmpty())
            <p class="font-bold text-clay">{{ __('BÉNÉFICIAIRE NON IDENTIFIÉ') }}</p>
            <p class="mt-1 text-ink/60">{{ __('Aucune de vos mutuelles conventionnées ne connaît cette carte (recherche du :date). Vérifiez le code, ou orientez la personne vers sa mutuelle.', ['date' => $lookup['at']->format('d/m/Y à H:i')]) }}</p>
          @else
            <p class="font-semibold">{{ __('Cette carte existe dans plusieurs de vos mutuelles : choisissez celle de la personne.') }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
              @foreach ($lookup['mutuals'] as $mu)
                <form method="POST" action="{{ route('pro.open', ['verify', $mu->id]) }}">@csrf<input type="hidden" name="code" value="{{ $lookup['code'] }}">
                  <button class="rounded-md border border-pine px-3 py-2 text-sm font-semibold text-pine hover:bg-mist">{{ $mu->name }}</button></form>
              @endforeach
            </div>
          @endif
        </div>
      @endif
    </x-tenant.panel>
    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-ink/50">{{ __('Ou ouvrir directement le portail d’une mutuelle') }}</h2>
  @endif
  <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($mutuals as $mu)
      <form method="POST" action="{{ route('pro.open', [$target, $mu->id]) }}" class="flex flex-col justify-between gap-4 rounded-3xl bg-white p-5 shadow-soft ring-1 ring-ink/[.05]">@csrf
        <div><div class="text-lg font-bold text-[#0B1F3A]">{{ $mu->name }}</div><div class="text-sm text-ink/55">{{ $mu->city }}</div></div>
        <button class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-leaf-500 to-leaf-600 px-4 py-2.5 text-sm font-semibold text-white"><x-tenant.icon :name="$icon" :size="18"/> {{ __('Ouvrir') }}</button>
      </form>
    @empty
      <x-tenant.panel class="p-6 sm:col-span-2">
        <p class="text-sm text-ink/60">{{ __('Aucune convention en place.') }} @if(\App\Support\ProviderAccess::allowed('affiliation.request'))<a href="{{ route('pro.mutuals') }}" class="font-semibold text-pine hover:underline">{{ __('Demander une affiliation') }}</a>.@endif</p>
      </x-tenant.panel>
    @endforelse
  </div>
  <p class="mt-6 text-xs text-ink/50">{{ __('Le portail s’ouvre sur l’adresse de la mutuelle : les données des adhérents restent dans sa base. Le lien d’entrée est à usage unique et expire après 60 secondes.') }}</p>
  @if ($target === 'verify')
    <x-slot:scripts>
      <script src="{{ rtrim(config('app.url'), '/') }}/js/vendor/jsqr-1.4.0.min.js" defer></script>
      <script src="{{ rtrim(config('app.url'), '/') }}/js/mx-scan.js?v={{ config('mxconnect.asset_version') }}" defer></script>
    </x-slot:scripts>
  @endif
</x-layouts.provider>
