@php $home = route('home'); @endphp
<footer class="h-footer">
  <div class="h-wrap">
    <div class="h-footer__grid">
      <div>
        <a class="h-logo" href="{{ $home }}"><img src="/brand/mx-mark-128.webp" alt="" width="58" height="58"><span><span class="h-logo__name">M<b>X</b>-CONNECT</span><span class="h-logo__tag">CONNECTER • COLLABORER • RÉUSSIR</span></span></a>
        <p style="margin-top:18px;font-size:14px;line-height:1.6;max-width:340px">La plateforme digitale des mutuelles de santé : gestion, collaboration en réseau et services aux adhérents.</p>
      </div>
      <div><h4>Plateforme</h4><a href="{{ $home }}#fonctionnalites">Fonctionnalités</a><a href="{{ $home }}#solutions">Solutions</a><a href="{{ $home }}#tarifs">Tarifs</a><a href="{{ route('comparator') }}">Comparateur d’offres</a></div>
      <div><h4>Réseau social</h4><a href="{{ route('social.home') }}">Actualités</a><a href="{{ route('social.mutuals') }}">Mutuelles</a><a href="{{ route('social.groups') }}">Groupes</a><a href="{{ route('social.register') }}">Créer un compte</a></div>
      <div><h4>Accès</h4><a href="{{ route('home.login') }}">Se connecter</a><a href="{{ $home }}#contact">Demander une démo</a><a href="{{ route('social.about') }}">À propos du réseau</a><a href="{{ route('social.privacy') }}">Confidentialité</a></div>
    </div>
    <div class="h-footer__bottom"><span>© {{ date('Y') }} MX-CONNECT — Connecter aujourd’hui pour protéger demain.</span><span><a href="{{ route('health') }}" style="display:inline">État du service</a></span></div>
  </div>
</footer>
<script>
  (function () {
    var h = document.querySelector('.h-header'), b = document.querySelector('.h-burger');
    if (b) b.addEventListener('click', function () { var o = h.classList.toggle('is-open'); b.setAttribute('aria-expanded', o ? 'true' : 'false'); });
    document.querySelectorAll('.h-nav a').forEach(function (a) { a.addEventListener('click', function () { h.classList.remove('is-open'); }); });
    window.addEventListener('scroll', function () { h.classList.toggle('is-scrolled', window.scrollY > 8); }, { passive: true });
    document.addEventListener('click', function (e) { document.querySelectorAll('.h-drop[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); }); });
  })();
</script>
