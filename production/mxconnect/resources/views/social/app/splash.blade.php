{{-- Maquette 1 — écran de démarrage : logo au centre du cercle ; les membres du réseau s'y connectent
     (lignes tracées, membres qui apparaissent, signaux qui circulent). Le serveur a déjà vérifié la
     maintenance et la version ; on oriente ensuite (onboarding, connexion, conditions ou accueil). --}}
@php
  // Six membres sur le cercle (angle en degrés, couleur) : centre (160,160), rayon 118.
  $nodes = [[-90, '#2F6FDB'], [-30, '#2E9E4F'], [30, '#2F6FDB'], [90, '#0B2559'], [150, '#2E9E4F'], [210, '#2F6FDB']];
  $pts = array_map(fn ($n) => [round(160 + 118 * cos(deg2rad($n[0])), 1), round(160 + 118 * sin(deg2rad($n[0])), 1), $n[1]], $nodes);
@endphp
<x-layouts.app-plain title="Démarrage" class="mx-splash">
  <x-slot:head><meta http-equiv="refresh" content="4;url={{ $next }}">
    {{-- L'application démarre toujours ici : la suite de la session s'affiche en mode application. --}}
    <script>try{sessionStorage.setItem('mx_app','1')}catch(e){}</script></x-slot:head>

  <div class="mx-splash__hero">
  <div class="mx-net" role="img" aria-label="Le logo MX-CONNECT au centre d’un réseau de personnes qui se connectent">
    <svg class="mx-net__svg" viewBox="0 0 320 320" aria-hidden="true">
      <circle class="mx-net__orbit" cx="160" cy="160" r="146" />
      <polygon class="mx-net__ring" points="{{ collect($pts)->map(fn ($p) => $p[0] . ',' . $p[1])->implode(' ') }}" pathLength="1" />
      @foreach ($pts as $i => [$x, $y, $c])
        <line class="mx-net__line" x1="160" y1="160" x2="{{ $x }}" y2="{{ $y }}" pathLength="1" style="--d: {{ .15 + $i * .1 }}s" />
      @endforeach
      @foreach ($pts as $i => [$x, $y, $c])
        <circle class="mx-net__pulse" cx="160" cy="160" r="3.2" fill="{{ $c }}" style="--dx: {{ $x - 160 }}px; --dy: {{ $y - 160 }}px; --d: {{ 1.1 + $i * .22 }}s" />
      @endforeach
      @foreach ($pts as $i => [$x, $y, $c])
        <g class="mx-net__node" style="--d: {{ .45 + $i * .1 }}s" fill="{{ $c }}">
          <circle cx="{{ $x }}" cy="{{ $y }}" r="17" fill="#fff" stroke="{{ $c }}" stroke-opacity=".25" stroke-width="2" />
          <circle cx="{{ $x }}" cy="{{ $y - 4.5 }}" r="4.6" />
          <path d="M{{ $x - 8 }} {{ $y + 9.5 }}a8 8 0 0 1 16 0z" />
        </g>
      @endforeach
    </svg>
    <div class="mx-net__logo"><img src="/brand/mx-mark-128.webp" alt="" width="96" height="96"></div>
  </div>

  <div class="mx-splash__brand">
    <div class="mx-splash__word">M<b>X</b>-CONNECT</div>
    <div class="mx-splash__tag">Connecter · Collaborer · Réussir</div>
  </div>
  </div>

  <div>
    <p class="mx-splash__msg">Les initiatives qui changent la vie méritent d’être visibles.</p>
    <p class="mx-splash__status" role="status">Connexion au réseau<span class="mx-ldots" aria-hidden="true"><i>.</i><i>.</i><i>.</i></span></p>
    <noscript><a class="mx-abtn" href="{{ $next }}">Continuer</a></noscript>
  </div>
  <x-slot:scripts><script>(function(){var calm=matchMedia('(prefers-reduced-motion: reduce)').matches;
    setTimeout(function(){location.replace(@json($next));},calm?700:2000);})();</script></x-slot:scripts>
</x-layouts.app-plain>
