<x-layouts.app-plain title="Maintenance" class="mx-as__center">
  <div class="mx-as__grow" style="display:flex;flex-direction:column;justify-content:center">
    <img src="/brand/mx-mark-128.webp" alt="" width="96" height="96" style="margin:0 auto 18px">
    <h1 class="mx-as__title">Service momentanément indisponible</h1>
    <p class="mx-as__lead">{{ $message }}</p>
  </div>
  <a class="mx-abtn" href="{{ route('social.app.start') }}">Réessayer</a>
</x-layouts.app-plain>
