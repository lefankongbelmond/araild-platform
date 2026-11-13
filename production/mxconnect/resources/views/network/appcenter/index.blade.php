@php
  $L = \App\Support\AppCenter::LABELS;
  $osL = ['windows' => 'Windows', 'macos' => 'macOS', 'linux' => 'Linux', 'android' => 'Android', 'ios' => 'iPhone / iPad', 'chromeos' => 'ChromeOS', 'other' => 'Autre'];
  $brL = ['chrome' => 'Chrome', 'edge' => 'Edge', 'firefox' => 'Firefox', 'safari' => 'Safari', 'samsung' => 'Samsung Internet', 'opera' => 'Opera', 'other' => 'Autre'];
  $public = rtrim(config('app.url'), '/') . '/applications';
@endphp
<x-layouts.network title="App Center">
  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">App Center — applications et statistiques</h1>
      <p class="mt-1 text-sm text-ink/60">Page publique : <a class="text-pine hover:underline" href="{{ $public }}" target="_blank" rel="noopener">{{ $public }} ↗</a>. Statistiques anonymes : ni compte, ni adresse IP, ni donnée de santé.</p>
    </div>
    <form method="GET" class="flex items-center gap-2 text-sm">
      <label for="periode" class="text-ink/60">Période</label>
      <select id="periode" name="periode" class="rounded-md border border-ink/15 px-2 py-2" onchange="this.form.submit()">
        @foreach ($periods as $p)<option value="{{ $p }}" @selected($p === $days)>{{ $p }} derniers jours</option>@endforeach
      </select>
    </form>
  </div>
  @if (session('success'))<div class="mt-4 rounded-md bg-pine/10 px-4 py-2 text-sm text-pine">{{ session('success') }}</div>@endif

  <div class="mt-4 rounded-lg border border-ink/10 bg-white p-4 text-sm text-ink/70">
    <b class="text-ink">Lecture des chiffres.</b> Les <b>clics</b> sur « Installer » ne sont jamais comptés comme des installations.
    Une installation est <b>confirmée</b> seulement quand le navigateur la signale (événement d’installation) ou quand l’application est ouverte en mode application.
    Une installation est <b>active</b> si l’application a été ouverte dans les {{ \App\Support\AppCenter::ACTIVE_DAYS }} derniers jours.
    Les installations faites depuis le menu du navigateur ou l’écran d’accueil sur iPhone ne sont comptées qu’à leur première ouverture.
  </div>

  @foreach ($apps as $key => $app)
    @php $s = $summary[$key] ?? []; $e = $s['events'] ?? []; @endphp
    <section class="mt-6 rounded-lg border border-ink/10 bg-white p-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <img src="{{ route('appcenter.icon', $key) }}" alt="" class="h-10 w-10 rounded-lg border border-ink/10">
          <div>
            <h2 class="text-lg font-semibold">{{ $app->name }}</h2>
            <p class="text-xs text-ink/60">Version {{ $app->version }} @if ($app->released_on)· mise à jour le {{ $app->released_on->format('d/m/Y') }}@endif
              · @if ($app->enabled)<span class="text-pine">active</span>@else<span class="text-clay">désactivée</span>@endif
              @if ($app->hasApk()) · APK v{{ $app->apk_version }} publié @endif</p>
          </div>
        </div>
        @if ($canEdit)<a href="{{ route('network.appcenter.edit', $key) }}" class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-navy">Gérer l’application</a>@endif
      </div>

      <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="rounded-md bg-pine/10 p-3"><p class="text-xs text-ink/60">Installations confirmées (total)</p><p class="text-2xl font-semibold">{{ $s['installs_total'] ?? 0 }}</p></div>
        <div class="rounded-md bg-pine/10 p-3"><p class="text-xs text-ink/60">Confirmées sur la période</p><p class="text-2xl font-semibold">{{ $s['installs_period'] ?? 0 }}</p></div>
        <div class="rounded-md bg-pine/10 p-3"><p class="text-xs text-ink/60">Installations actives ({{ \App\Support\AppCenter::ACTIVE_DAYS }} j)</p><p class="text-2xl font-semibold">{{ $s['active'] ?? 0 }}</p></div>
        <div class="rounded-md bg-ink/5 p-3"><p class="text-xs text-ink/60">PC · Android (confirmées)</p><p class="text-2xl font-semibold">{{ $s['installs_pc'] ?? 0 }} · {{ $s['installs_android'] ?? 0 }}</p></div>
      </div>

      <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
          <h3 class="text-sm font-semibold">Événements sur {{ $days }} jours</h3>
          <table class="mt-2 w-full text-sm">
            <tbody>
              @foreach (['click_pc', 'click_android', 'qr', 'guide', 'prompt', 'accepted', 'dismissed', 'installed', 'launched', 'open', 'apk_download'] as $ev)
                <tr class="border-b border-ink/5"><td class="py-1.5 pr-3 text-ink/70">{{ $L[$ev] }}</td><td class="py-1.5 text-right font-medium">{{ $e[$ev] ?? 0 }}</td></tr>
              @endforeach
            </tbody>
          </table>
          <p class="mt-2 text-xs text-ink/50">« Ouvertures en mode application » : au plus une par appareil et par jour.</p>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <h3 class="text-sm font-semibold">Par système</h3>
            <table class="mt-2 w-full text-sm"><tbody>
              @forelse ($s['by_os'] ?? [] as $k => $n)<tr class="border-b border-ink/5"><td class="py-1.5">{{ $osL[$k] ?? $k }}</td><td class="py-1.5 text-right font-medium">{{ $n }}</td></tr>
              @empty<tr><td class="py-1.5 text-ink/50">Aucune installation confirmée.</td></tr>@endforelse
            </tbody></table>
          </div>
          <div>
            <h3 class="text-sm font-semibold">Par navigateur</h3>
            <table class="mt-2 w-full text-sm"><tbody>
              @forelse ($s['by_browser'] ?? [] as $k => $n)<tr class="border-b border-ink/5"><td class="py-1.5">{{ $brL[$k] ?? $k }}</td><td class="py-1.5 text-right font-medium">{{ $n }}</td></tr>
              @empty<tr><td class="py-1.5 text-ink/50">—</td></tr>@endforelse
            </tbody></table>
          </div>
          <div>
            <h3 class="text-sm font-semibold">Par version installée</h3>
            <table class="mt-2 w-full text-sm"><tbody>
              @forelse ($s['by_version'] ?? [] as $k => $n)<tr class="border-b border-ink/5"><td class="py-1.5 font-mono text-xs">{{ $k ?: '—' }}</td><td class="py-1.5 text-right font-medium">{{ $n }}</td></tr>
              @empty<tr><td class="py-1.5 text-ink/50">—</td></tr>@endforelse
            </tbody></table>
          </div>
          @if ($key === 'mutuelle')
            <div>
              <h3 class="text-sm font-semibold">Par mutuelle</h3>
              <table class="mt-2 w-full text-sm"><tbody>
                @forelse ($s['by_mutual'] ?? [] as $k => $n)<tr class="border-b border-ink/5"><td class="py-1.5">{{ $mutualNames[$k] ?? $k }}</td><td class="py-1.5 text-right font-medium">{{ $n }}</td></tr>
                @empty<tr><td class="py-1.5 text-ink/50">—</td></tr>@endforelse
              </tbody></table>
            </div>
          @endif
        </div>
      </div>
    </section>
  @endforeach
</x-layouts.network>
