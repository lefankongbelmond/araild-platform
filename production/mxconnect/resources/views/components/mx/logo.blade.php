@props(['href' => null, 'size' => 36])
<a href="{{ $href ?? url('/') }}" {{ $attributes->merge(['class' => 'mx-brand']) }} aria-label="MX-CONNECT — accueil">
  <img src="/brand/mx-mark-128.webp" width="{{ $size }}" height="{{ $size }}" alt="">
  <span><span class="mx-wordmark">M<b>X</b>-CONNECT</span><span class="mx-tagline">CONNECTER · COLLABORER · RÉUSSIR</span></span>
</a>
