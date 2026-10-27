{{-- Maquette 1 — écran de démarrage : logo, service vérifié côté serveur, puis orientation. --}}
<x-layouts.app-plain title="Démarrage" class="mx-splash">
  <x-slot:head><meta http-equiv="refresh" content="3;url={{ $next }}">
    {{-- L'application démarre toujours ici : la suite de la session s'affiche en mode application. --}}
    <script>try{sessionStorage.setItem('mx_app','1')}catch(e){}</script></x-slot:head>
  <div>
    <div class="mx-splash__logo"><img src="/brand/mx-mark-128.webp" alt="" width="132" height="132"></div>
    <div class="mx-splash__word">M<b>X</b>-CONNECT</div>
    <div class="mx-splash__tag">Connecter · Collaborer · Réussir</div>
  </div>
  <svg class="mx-splash__art" viewBox="0 0 320 190" role="img" aria-label="Des personnes reliées entre elles">
    <g fill="none" stroke="#C9D6EA" stroke-width="1.5" stroke-dasharray="4 4">
      <path d="M160 95 60 40M160 95 260 40M160 95 50 140M160 95 270 140M160 95 160 20M60 40 160 20 260 40M50 140 160 170 270 140"/>
      <ellipse cx="160" cy="95" rx="140" ry="80"/>
    </g>
    @foreach ([[160,95,22,'#0B2559'],[60,40,14,'#2F6FDB'],[260,40,14,'#2F6FDB'],[50,140,14,'#2E9E4F'],[270,140,14,'#2E9E4F'],[160,20,12,'#2F6FDB'],[160,170,12,'#0B2559']] as [$x,$y,$r,$c])
      <g fill="{{ $c }}"><circle cx="{{ $x }}" cy="{{ $y - $r * .45 }}" r="{{ $r * .42 }}"/><path d="M{{ $x - $r * .75 }} {{ $y + $r * .75 }}a{{ $r * .75 }} {{ $r * .75 }} 0 0 1 {{ $r * 1.5 }} 0z"/></g>
    @endforeach
  </svg>
  <div>
    <p class="mx-splash__msg">Les initiatives qui changent la vie méritent d’être visibles.</p>
    <div class="mx-spin" aria-hidden="true"></div>
    <p class="mx-small mx-muted" role="status">Chargement…</p>
    <noscript><a class="mx-abtn" href="{{ $next }}">Continuer</a></noscript>
  </div>
  <x-slot:scripts><script>setTimeout(function () { location.replace(@json($next)); }, 700);</script></x-slot:scripts>
</x-layouts.app-plain>
