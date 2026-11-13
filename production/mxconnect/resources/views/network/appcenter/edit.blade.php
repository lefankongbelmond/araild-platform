<x-layouts.network :title="'App Center · ' . $app->name">
  <p class="text-sm"><a class="text-pine hover:underline" href="{{ route('network.appcenter.index') }}">← App Center</a></p>
  <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ $app->name }}</h1>
  <p class="mt-1 text-sm text-ink/60">
    @if ($app->key === 'mutuelle')
      Installée depuis le sous-domaine de chaque mutuelle (page <code>/installer</code>) : chaque mutuelle a sa propre application, qui s’ouvre sur sa page de connexion.
    @else
      Installée depuis le domaine principal ; c’est aussi le contenu de l’application Android (TWA).
    @endif
  </p>
  @if (session('success'))<div class="mt-4 rounded-md bg-pine/10 px-4 py-2 text-sm text-pine">{{ session('success') }}</div>@endif
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-2 text-sm text-clay">{{ $errors->first() }}</div>@endif

  <form method="POST" action="{{ route('network.appcenter.update', $app->key) }}" class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    @csrf
    <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
      <h2 class="text-base font-semibold">Présentation</h2>
      <label class="mt-3 flex items-start gap-2"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $app->enabled))> <span><b>Application active</b> — désactivée, sa carte affiche « Bientôt disponible » et l’installation est fermée.</span></label>
      <label class="mt-3 block font-medium">Nom<input name="name" required maxlength="80" value="{{ old('name', $app->name) }}" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2"></label>
      <label class="mt-3 block font-medium">Accroche<input name="tagline" maxlength="160" value="{{ old('tagline', $app->tagline) }}" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2"></label>
      <label class="mt-3 block font-medium">Description<textarea name="description" required maxlength="2000" rows="4" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2">{{ old('description', $app->description) }}</textarea></label>
      <label class="mt-3 block font-medium">Fonctionnalités mises en avant (une par ligne, 16 au plus)
        <textarea name="features" rows="9" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2">{{ old('features', implode("\n", (array) $app->features)) }}</textarea></label>
    </section>
    <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
      <h2 class="text-base font-semibold">Version et installation</h2>
      @php [$pv, $pd] = \App\Support\AppCenter::platformVersion(); @endphp
      <label class="mt-3 flex items-start gap-2"><input type="checkbox" name="version_auto" value="1" @checked(old('version_auto', $app->version_auto))> <span><b>Suivre la version de la plateforme</b> ({{ $pv ?? '—' }}{{ $pd ? ', ' . \Illuminate\Support\Carbon::parse($pd)->format('d/m/Y') : '' }}) : la version et la date se mettent à jour à chaque livraison. Décochez pour saisir une version vous-même.</span></label>
      <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <label class="block font-medium">Version affichée<input name="version" maxlength="20" value="{{ old('version', $app->version) }}" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2 font-mono"></label>
        <label class="block font-medium">Date de mise à jour<input type="date" name="released_on" value="{{ old('released_on', optional($app->released_on)->format('Y-m-d')) }}" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2"></label>
      </div>
      <p class="mt-1 text-xs text-ink/50">Les statistiques « par version » utilisent la version affichée au moment de l’installation.</p>
      <label class="mt-3 block font-medium">Consigne ajoutée aux guides d’installation
        <textarea name="guide_note" maxlength="600" rows="3" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2" placeholder="Ex. : en cas de difficulté, contactez le support au …">{{ old('guide_note', $app->guide_note) }}</textarea></label>
      @if ($app->key === 'social')
        <label class="mt-3 block font-medium">Lien Google Play (facultatif)<input name="play_store_url" type="url" value="{{ old('play_store_url', $app->play_store_url) }}" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2" placeholder="https://play.google.com/store/apps/details?id=com.araild.mxconnect"></label>
        <p class="mt-1 text-xs text-ink/50">À défaut, le lien saisi dans « Application Android → Réglages » est utilisé.</p>
      @endif
      <div class="mt-5"><button class="rounded-md bg-pine px-5 py-2.5 text-sm font-medium text-white hover:bg-navy">Enregistrer</button></div>
    </section>
  </form>

  <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
      <h2 class="text-base font-semibold">Icône</h2>
      <div class="mt-3 flex items-center gap-3">
        <img src="{{ route('appcenter.icon', $app->key) }}?v={{ optional($app->updated_at)->timestamp }}" alt="Icône actuelle" class="h-16 w-16 rounded-xl border border-ink/10">
        <p class="text-ink/60">{{ $app->icon_path ? 'Icône personnalisée.' : 'Icône MX-CONNECT par défaut.' }} Utilisée sur la carte de l’App Center{{ $app->key === 'mutuelle' ? ' et dans l’application installée' : '' }}.</p>
      </div>
      <form method="POST" action="{{ route('network.appcenter.icon', $app->key) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-3">
        @csrf
        <label class="block font-medium">PNG carré, 192 à 1024 px, 1 Mo au plus<input type="file" name="icone" accept="image/png" required class="mt-1 block"></label>
        <button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-navy">Envoyer</button>
      </form>
      @if ($app->icon_path)
        <form method="POST" action="{{ route('network.appcenter.icon', $app->key) }}" class="mt-2">@csrf<input type="hidden" name="retirer" value="1"><button class="text-sm text-clay hover:underline">Revenir à l’icône par défaut</button></form>
      @endif
    </section>

    <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
      <h2 class="text-base font-semibold">APK Android officiel (facultatif)</h2>
      <p class="mt-1 text-ink/60">Le fichier est refusé s’il n’est pas signé avec une clé déclarée dans <a class="text-pine hover:underline" href="{{ route('network.android.settings') }}">Application Android → Réglages</a>. Un APK de débogage n’est jamais publié.</p>
      @if ($fingerprints)
        <p class="mt-2 text-xs text-ink/50">Empreintes acceptées :</p>
        <ul class="mt-1 space-y-1">@foreach ($fingerprints as $f)<li class="break-all font-mono text-xs">{{ $f }}</li>@endforeach</ul>
      @else
        <p class="mt-2 rounded-md border border-clay/30 bg-clay/5 px-3 py-2 text-clay">Aucune empreinte déclarée : le dépôt d’un APK est impossible.</p>
      @endif
      @if ($app->hasApk())
        <div class="mt-3 rounded-md bg-pine/10 p-3">
          <p><b>Publié :</b> version {{ $app->apk_version }} · {{ number_format(($app->apk_size ?? 0) / 1048576, 1, ',', ' ') }} Mo · déposé le {{ optional($app->apk_uploaded_at)->format('d/m/Y H:i') }}</p>
          <p class="mt-1 break-all text-xs">Signataire : <span class="font-mono">{{ $app->apk_signer }}</span></p>
          <p class="mt-1 break-all text-xs">SHA-256 du fichier : <span class="font-mono">{{ $app->apk_sha256 }}</span></p>
          <p class="mt-1 text-xs"><a class="text-pine hover:underline" href="{{ route('appcenter.apk', $app->key) }}">Lien public de téléchargement</a></p>
        </div>
        <form method="POST" action="{{ route('network.appcenter.apk.remove', $app->key) }}" class="mt-2">@csrf<button class="text-sm text-clay hover:underline">Retirer l’APK</button></form>
      @endif
      <form method="POST" action="{{ route('network.appcenter.apk', $app->key) }}" enctype="multipart/form-data" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
        @csrf
        <label class="block font-medium">Fichier .apk (50 Mo au plus)<input type="file" name="apk" accept=".apk,application/vnd.android.package-archive" required class="mt-1 block"></label>
        <label class="block font-medium">Version<input name="apk_version" required maxlength="30" value="{{ old('apk_version') }}" placeholder="0.2.0-beta (2)" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2"></label>
        <div><button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-navy" @disabled(! $fingerprints)>Vérifier et publier</button></div>
      </form>
    </section>
  </div>
</x-layouts.network>
