/* ARAILD — interactions (vanilla JS) */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {

    /* Sticky header */
    var header = document.getElementById('site-header');
    if (header) {
      var onScroll = function () {
        header.classList.toggle('scrolled', window.scrollY > 50);
      };
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll();
    }

    /* Menu mobile */
    var toggle = document.getElementById('menu-toggle');
    var mobile = document.getElementById('mobile-menu');
    var overlay = document.getElementById('mm-overlay');
    var closeBtn = document.getElementById('close-mm');
    function openMenu() {
      mobile && mobile.classList.add('open');
      overlay && overlay.classList.add('open');
      toggle && toggle.classList.add('active');
      toggle && toggle.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
    }
    function closeMenu() {
      mobile && mobile.classList.remove('open');
      overlay && overlay.classList.remove('open');
      toggle && toggle.classList.remove('active');
      toggle && toggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    }
    toggle && toggle.addEventListener('click', function () {
      mobile.classList.contains('open') ? closeMenu() : openMenu();
    });
    closeBtn && closeBtn.addEventListener('click', closeMenu);
    overlay && overlay.addEventListener('click', closeMenu);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeMenu();
    });

    /* Compteurs animés */
    function animateCount(el) {
      var raw = el.getAttribute('data-count') || el.textContent;
      var suffix = (raw.match(/[^0-9.,\s].*$/) || [''])[0];
      var target = parseFloat(raw.replace(/[^0-9.]/g, '')) || 0;
      if (!target) { return; }
      var start = null, dur = 1600;
      function step(ts) {
        if (!start) start = ts;
        var p = Math.min((ts - start) / dur, 1);
        var val = Math.floor(p * target);
        el.textContent = val.toLocaleString('fr-FR') + suffix;
        if (p < 1) requestAnimationFrame(step);
        else el.textContent = target.toLocaleString('fr-FR') + suffix;
      }
      requestAnimationFrame(step);
    }

    if ('IntersectionObserver' in window) {
      /* fade-up */
      var fadeObs = new IntersectionObserver(function (entries, obs) {
        entries.forEach(function (e) {
          if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); }
        });
      }, { threshold: 0.12 });
      document.querySelectorAll('.fade-up, .fade-in').forEach(function (el) { fadeObs.observe(el); });

      /* compteurs */
      var countObs = new IntersectionObserver(function (entries, obs) {
        entries.forEach(function (e) {
          if (e.isIntersecting) { animateCount(e.target); obs.unobserve(e.target); }
        });
      }, { threshold: 0.5 });
      document.querySelectorAll('[data-count]').forEach(function (el) { countObs.observe(el); });

      /* progress bars */
      var barObs = new IntersectionObserver(function (entries, obs) {
        entries.forEach(function (e) {
          if (e.isIntersecting) {
            e.target.style.width = (e.target.getAttribute('data-progress') || 0) + '%';
            obs.unobserve(e.target);
          }
        });
      }, { threshold: 0.4 });
      document.querySelectorAll('[data-progress]').forEach(function (el) { barObs.observe(el); });
    } else {
      document.querySelectorAll('.fade-up, .fade-in').forEach(function (el) { el.classList.add('visible'); });
      document.querySelectorAll('[data-progress]').forEach(function (el) { el.style.width = (el.getAttribute('data-progress') || 0) + '%'; });
    }

    /* Smooth scroll ancres */
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        var id = a.getAttribute('href');
        if (id.length > 1) {
          var t = document.querySelector(id);
          if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth' }); }
        }
      });
    });

    /* Accordéon FAQ */
    document.querySelectorAll('.faq-q').forEach(function (q) {
      q.addEventListener('click', function () {
        q.parentElement.classList.toggle('open');
      });
    });

    /* Filtre projets */
    var filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        filterBtns.forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var f = btn.getAttribute('data-filter');
        document.querySelectorAll('.project-card').forEach(function (card) {
          card.hidden = !(f === 'all' || card.getAttribute('data-axe') === f);
        });
      });
    });

  });
})();
