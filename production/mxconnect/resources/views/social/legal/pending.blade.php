{{-- Maquette 5 — documents à valider. Chaque case est décochée ; « Continuer » s'active quand toutes
     les cases dues sont cochées (le serveur le vérifie aussi). Les documents déjà acceptés sont signalés. --}}
@php $icons = ['terms' => ['document', ''], 'privacy' => ['shield', 'mx-ldoc__ic--green'], 'community' => ['users', 'mx-ldoc__ic--navy']]; @endphp
<x-layouts.app-plain title="Conditions d’utilisation et confidentialité">
  <div class="mx-as__top"><a class="mx-as__back" href="{{ $app ? route('social.app.start') : route('social.home') }}" aria-label="Retour"><x-mx.icon name="arrow-left" :size="22"/></a></div>
  <h1 class="mx-as__title">Conditions d’utilisation et confidentialité</h1>
  <p class="mx-as__lead">{{ count($pending) ? 'Avant de continuer, veuillez lire et accepter nos documents pour utiliser MX-CONNECT.' : 'Vos acceptations sont à jour.' }}</p>
  @if (session('info'))<div class="mx-aalert mx-aalert--info" role="status">{{ session('info') }}</div>@endif
  @if ($errors->any())<div class="mx-aalert" role="alert">{{ $errors->first() }}</div>@endif
  <div class="mx-ldocs">
    @foreach ($documents as $d)
      <a class="mx-ldoc" href="{{ $d->url() }}" target="_blank" rel="noopener">
        <span class="mx-ldoc__ic {{ $icons[$d->type][1] ?? '' }}"><x-mx.icon :name="$icons[$d->type][0] ?? 'document'" :size="22"/></span>
        <span class="mx-ldoc__t"><b>{{ $d->label() }}</b><span>Version {{ $d->version }} • {{ $d->published_at?->translatedFormat('j M Y') }}</span></span>
        <x-mx.icon name="chevron-right" :size="20" />
      </a>
    @endforeach
  </div>
  <form method="POST" action="{{ route('social.legal.accept') }}" class="mx-as__grow" style="display:flex;flex-direction:column" id="lg-form">
    @csrf
    @if ($app)<input type="hidden" name="app" value="1">@endif
    @if ($next)<input type="hidden" name="suite" value="{{ $next }}">@endif
    @foreach ($documents as $d)
      @if (in_array($d->id, $pending, true))
        <label class="mx-acheck"><input type="checkbox" name="doc_{{ $d->id }}" value="1" data-lg>
          <span>{{ \Illuminate\Support\Str::before($d->checkboxText(), $d->label()) }}<a href="{{ $d->url() }}" target="_blank" rel="noopener">{{ $d->label() }}</a></span></label>
      @elseif (isset($accepted[$d->id]))
        <p class="mx-ldoc__ok"><x-mx.icon name="check" :size="16"/> {{ $d->label() }} : {{ $d->action === 'acknowledge' ? 'pris connaissance' : 'accepté' }} le {{ \Illuminate\Support\Carbon::parse($accepted[$d->id]->created_at)->format('d/m/Y') }}</p>
      @endif
    @endforeach
    <div class="mx-as__grow"></div>
    <button class="mx-abtn" id="lg-go" @disabled(count($pending) > 0)>Continuer</button>
  </form>
  <x-slot:scripts><script>
    (function () { var boxes = document.querySelectorAll('[data-lg]'), go = document.getElementById('lg-go');
      function upd() { var all = true; boxes.forEach(function (b) { if (!b.checked) all = false; }); go.disabled = !all; }
      boxes.forEach(function (b) { b.addEventListener('change', upd); }); upd(); })();
  </script></x-slot:scripts>
</x-layouts.app-plain>
