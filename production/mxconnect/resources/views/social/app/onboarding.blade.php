{{-- Maquette 2 — onboarding (3 écrans), « Passer » à tout moment, montré une seule fois. --}}
@php
  $slides = [
    1 => ['Découvrez', 'Des organisations, des projets et des initiatives près de chez vous et partout dans le monde.', [['building', '', 'top:8%;left:-2%'], ['users', 'mx-onb__bubble--sky', 'top:30%;left:-9%'], ['heart', 'mx-onb__bubble--red', 'top:40%;right:-6%'], ['chart', 'mx-onb__bubble--sky', 'bottom:6%;right:12%']]],
    2 => ['Engagez-vous', 'Proposez votre aide aux projets qui vous tiennent à cœur : bénévolat, compétences, matériel ou participation à un événement.', [['hand', '', 'top:10%;right:-4%'], ['calendar', 'mx-onb__bubble--sky', 'bottom:12%;left:-6%'], ['message', 'mx-onb__bubble--sun', 'top:42%;left:-9%']]],
    3 => ['Soutenez et suivez les résultats', 'Devenez partenaire d’un projet et suivez son avancement réel : indicateurs, rapports et résultats vérifiés.', [['handshake', 'mx-onb__bubble--sun', 'top:8%;left:0'], ['target', '', 'top:44%;right:-8%'], ['chart', 'mx-onb__bubble--sky', 'bottom:8%;left:6%']]],
  ];
  [$title, $text, $bubbles] = $slides[$step];
@endphp
<x-layouts.app-plain :title="$title">
  <div class="mx-as__top" style="justify-content:flex-end"><a class="mx-as__skip" href="{{ route('social.app.onboarding.done') }}">Passer</a></div>
  <div class="mx-onb__slide is-active">
    <div class="mx-onb__art">
      <img src="/brand/hero-equipe.webp" alt="" width="300" height="300" style="object-position:{{ ['', '35% 30%', '60% 40%', '80% 35%'][$step] }}">
      @foreach ($bubbles as [$icon, $cls, $pos])
        <span class="mx-onb__bubble {{ $cls }}" style="{{ $pos }}"><x-mx.icon :name="$icon" :size="24"/></span>
      @endforeach
    </div>
    <h1 class="mx-onb__h">{{ $title }}</h1>
    <p class="mx-onb__p">{{ $text }}</p>
  </div>
  <div class="mx-dots" aria-label="Étape {{ $step }} sur 3">@for ($i = 1; $i <= 3; $i++)<span @class(['is-active' => $i === $step])></span>@endfor</div>
  @if ($step < 3)
    <a class="mx-abtn" href="{{ route('social.app.onboarding', ['etape' => $step + 1]) }}">Suivant</a>
  @else
    <a class="mx-abtn" href="{{ route('social.app.onboarding.done') }}">Commencer</a>
  @endif
</x-layouts.app-plain>
