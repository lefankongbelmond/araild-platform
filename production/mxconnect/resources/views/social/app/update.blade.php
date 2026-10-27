<x-layouts.app-plain title="Mise à jour requise" class="mx-as__center">
  <div class="mx-as__grow" style="display:flex;flex-direction:column;justify-content:center">
    <img src="/brand/mx-mark-128.webp" alt="" width="96" height="96" style="margin:0 auto 18px">
    <h1 class="mx-as__title">Une mise à jour est nécessaire</h1>
    <p class="mx-as__lead">Cette version de l’application n’est plus prise en charge. Installez la dernière version pour continuer.</p>
  </div>
  @if ($store)
    <a class="mx-abtn" href="{{ $store }}">Mettre à jour l’application</a>
  @else
    <p class="mx-as__lead">Le lien de téléchargement sera communiqué par l’équipe MX-CONNECT.</p>
  @endif
  <a class="mx-abtn mx-abtn--ghost" style="margin-top:10px" href="{{ route('social.home') }}">Continuer dans le navigateur</a>
</x-layouts.app-plain>
