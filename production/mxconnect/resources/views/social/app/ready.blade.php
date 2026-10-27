{{-- Maquette 6 — première connexion réussie. --}}
<x-layouts.app-plain title="Votre compte est prêt">
  <div class="mx-confetti" aria-hidden="true">
    @foreach ([[12,6,'#2E9E4F',-30],[30,14,'#2F6FDB',20],[70,4,'#E0B23A',-10],[84,16,'#2F6FDB',35],[50,26,'#E07B24',60],[20,34,'#E0B23A',15],[78,36,'#2E9E4F',-40]] as [$x,$y,$c,$r])
      <i style="left:{{ $x }}%;top:{{ $y * 4 }}px;background:{{ $c }};transform:rotate({{ $r }}deg)"></i>
    @endforeach
  </div>
  <div class="mx-ready__check"><x-mx.icon name="check" :size="50"/></div>
  <h1 class="mx-as__title mx-as__center">Votre compte est prêt !</h1>
  <p class="mx-as__lead mx-as__center">Bienvenue dans la communauté MX-CONNECT{{ $account ? ', ' . $account->first_name : '' }}.</p>
  <div class="mx-steps">
    <a class="mx-step" href="{{ route('social.settings') }}"><span class="mx-step__ic" style="background:var(--mx-sky-soft);color:var(--mx-sky)"><x-mx.icon name="user" :size="20"/></span><span style="flex:1"><b>Compléter mon profil</b><span>Ajoutez une photo et vos informations</span></span><x-mx.icon name="chevron-right" :size="18"/></a>
    <a class="mx-step" href="{{ route('social.discover') }}"><span class="mx-step__ic" style="background:var(--mx-green-soft);color:var(--mx-blue)"><x-mx.icon name="building" :size="20"/></span><span style="flex:1"><b>Découvrir les organisations</b><span>Trouvez des initiatives près de chez vous</span></span><x-mx.icon name="chevron-right" :size="18"/></a>
    <a class="mx-step" href="{{ route('social.home') }}"><span class="mx-step__ic" style="background:var(--mx-danger-soft);color:var(--mx-danger)"><x-mx.icon name="list" :size="20"/></span><span style="flex:1"><b>Explorer le fil d’actualité</b><span>Suivez les actualités et opportunités</span></span><x-mx.icon name="chevron-right" :size="18"/></a>
  </div>
  <div class="mx-as__grow"></div>
  <a class="mx-abtn" href="{{ route('social.home') }}">Commencer maintenant</a>
</x-layouts.app-plain>
