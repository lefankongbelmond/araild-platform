{{--
  Fiche « Identité et langue » de la mutuelle (M3a, 2026-11-30) — administrateur seul.
  Langue par défaut (personnel de la mutuelle, notifications aux adhérents qui n'ont pas choisi de
  langue) et coordonnées imprimées sur les reçus et attestations. Le nom, la ville et la devise
  restent gérés par l'administration du réseau.
--}}
<x-layouts.tenant :title="__('Identité et langue')">
  @php $f = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm'; @endphp
  <x-tenant.hero icon="globe" :kicker="__('Paramètres')" tone="brand">
    <x-slot:title>{{ __('Identité et langue') }}</x-slot:title>
    <x-slot:subtitle>{{ __('La langue par défaut de la mutuelle et les coordonnées qu’elle affiche sur ses documents.') }}</x-slot:subtitle>
    <x-slot:actions><a href="{{ route('settings.index') }}" class="rounded-md border border-ink/15 bg-white px-3 py-2 text-sm">{{ __('Tous les paramètres') }}</a></x-slot:actions>
  </x-tenant.hero>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <div class="mt-6 grid items-start gap-6 lg:grid-cols-2">
    <form method="POST" action="{{ route('settings.identity.update') }}" class="space-y-4 rounded-lg border border-ink/10 bg-white p-5">@csrf @method('PUT')
      <h2 class="font-semibold text-navy">{{ __('Langue') }}</h2>
      <label class="block text-sm font-medium">{{ __('Langue par défaut de la mutuelle') }}
        <select name="locale" class="{{ $f }}" required>
          @foreach ($locales as $code => $name)<option value="{{ $code }}" @selected(old('locale', $current ?? 'fr') === $code)>{{ $name }}</option>@endforeach
        </select>
      </label>
      <p class="text-xs text-ink/55">{{ __('Elle s’applique au personnel qui n’a pas choisi sa propre langue, et aux notifications envoyées aux adhérents qui n’en ont pas choisi. Chacun peut toujours choisir sa langue avec le sélecteur. Les langues proposées sont celles que la plateforme a activées.') }}</p>

      <h2 class="pt-2 font-semibold text-navy">{{ __('Coordonnées affichées') }}</h2>
      <label class="block text-sm font-medium">{{ __('Téléphone') }}
        <input name="phone" value="{{ old('phone', $contacts['phone'] ?? '') }}" maxlength="40" class="{{ $f }}" inputmode="tel">
      </label>
      <label class="block text-sm font-medium">{{ __('E-mail') }}
        <input type="email" name="email" value="{{ old('email', $contacts['email'] ?? '') }}" maxlength="180" class="{{ $f }}">
      </label>
      <label class="block text-sm font-medium">{{ __('Adresse') }}
        <input name="address" value="{{ old('address', $contacts['address'] ?? '') }}" maxlength="255" class="{{ $f }}">
      </label>
      <p class="text-xs text-ink/55">{{ __('Imprimées en tête des reçus de cotisation et des attestations. Laissez vide ce que vous ne voulez pas afficher.') }}</p>
      <button class="rounded-md bg-pine px-4 py-2 text-sm font-semibold text-white">{{ __('Enregistrer') }}</button>
    </form>

    <section class="space-y-3 rounded-lg border border-ink/10 bg-white p-5 text-sm">
      <h2 class="font-semibold text-navy">{{ __('Gérés par l’administration du réseau') }}</h2>
      <dl class="grid grid-cols-2 gap-2">
        <dt class="text-ink/60">{{ __('Nom') }}</dt><dd>{{ $mutual->name }}</dd>
        <dt class="text-ink/60">{{ __('Ville') }}</dt><dd>{{ $mutual->city ?: '—' }}</dd>
        <dt class="text-ink/60">{{ __('Région') }}</dt><dd>{{ $mutual->region ?: '—' }}</dd>
        <dt class="text-ink/60">{{ __('Devise') }}</dt><dd>{{ \App\Models\Central\Currency::find($mutual->currency_id)?->code ?? '—' }}</dd>
      </dl>
      <p class="text-ink/60">{{ __('Pour les modifier, écrivez à l’administration du réseau MX-CONNECT. La devise n’est jamais convertie.') }}</p>
    </section>
  </div>
</x-layouts.tenant>
