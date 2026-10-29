@php $central = rtrim(config('app.url'), '/'); @endphp
<x-layouts.network title="Réglages de l'application">
  <h1 class="text-2xl font-semibold tracking-tight">Réglages de l’application Android</h1>
  <p class="mt-1 text-sm text-ink/60">L’application est une fenêtre plein écran sur ce site (Trusted Web Activity) : aucun identifiant de base de données ni secret n’y est embarqué. Ces réglages sont publics ou sans risque.</p>
  @if (session('success'))<div class="mt-4 rounded-md bg-pine/10 px-4 py-2 text-sm text-pine">{{ session('success') }}</div>@endif
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-2 text-sm text-clay">{{ $errors->first() }}</div>@endif
  <form method="POST" action="{{ route('network.android.settings.save') }}" class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    @csrf
    <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
      <h2 class="text-base font-semibold">Liaison du domaine</h2>
      <p class="mt-1 text-ink/60">Sans empreinte valide, l’application s’ouvre avec une barre d’adresse. L’empreinte SHA-256 se lit dans la console Play (Intégrité de l’application → Certificat de signature) ou avec <code>keytool -list -v</code> sur le certificat d’envoi. Application installée depuis Android Studio : panneau Gradle → <code>app › Tasks › android › signingReport</code>, ligne « SHA-256 » (collez-la telle quelle). Après l’enregistrement, désinstallez puis réinstallez l’application.</p>
      <label class="mt-3 block font-medium">Nom du paquet<input name="package" required value="{{ old('package', $package) }}" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2"></label>
      <label class="mt-3 block font-medium">Empreintes SHA-256 du certificat (une par ligne)
        <textarea name="sha256" rows="3" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2 font-mono text-xs" placeholder="AB:CD:…">{{ old('sha256', $sha256) }}</textarea></label>
      <p class="mt-2 text-xs text-ink/50">Fichier publié : <a class="text-pine hover:underline" href="{{ $central }}/.well-known/assetlinks.json" target="_blank" rel="noopener">/.well-known/assetlinks.json ↗</a></p>
    </section>
    <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
      <h2 class="text-base font-semibold">Versions et maintenance</h2>
      <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <label class="block font-medium">Version minimale acceptée<input name="min" required inputmode="numeric" value="{{ old('min', $min) }}" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2"></label>
        <label class="block font-medium">Dernière version publiée<input name="latest" required inputmode="numeric" value="{{ old('latest', $latest) }}" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2"></label>
      </div>
      <p class="mt-1 text-xs text-ink/50">Numéros de build (versionCode). En dessous du minimum, l’application affiche « Mise à jour requise ».</p>
      <label class="mt-3 block font-medium">Lien de téléchargement (Play Store ou page de test)<input name="store" type="url" value="{{ old('store', $store) }}" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2" placeholder="https://play.google.com/apps/testing/…"></label>
      <label class="mt-4 flex items-start gap-2"><input type="checkbox" name="maintenance" value="1" @checked(old('maintenance', $maintenance))> <span><b>Mode maintenance de l’application</b> (le site reste ouvert)</span></label>
      <label class="mt-2 block font-medium">Message affiché<input name="message" maxlength="300" value="{{ old('message', $message) }}" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2" placeholder="Maintenance en cours, merci de réessayer dans quelques minutes."></label>
    </section>
    <div><button class="rounded-md bg-pine px-5 py-2.5 text-sm font-medium text-white hover:bg-navy">Enregistrer</button></div>
  </form>
</x-layouts.network>
