/* MX Connect — interactions du réseau social (sans dépendance).
   Toutes les actions passent par des routes serveur protégées (CSRF + permissions). */
(function () {
  'use strict';
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  function toast(msg, isErr) {
    var box = document.getElementById('mx-toasts'); if (!box) return;
    var t = document.createElement('div');
    t.className = 'mx-toast' + (isErr ? ' mx-toast--err' : '');
    t.textContent = msg; box.appendChild(t);
    setTimeout(function () { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; }, 2600);
    setTimeout(function () { t.remove(); }, 3000);
  }

  function send(url, body, method) {
    return fetch(url, {
      method: method || 'POST',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: body || null, credentials: 'same-origin'
    }).then(function (r) {
      if (r.status === 401) { location.href = '/compte/connexion'; throw new Error('auth'); }
      return r.json().catch(function () { return {}; }).then(function (d) {
        if (!r.ok) { throw new Error(d.message || (d.errors && Object.values(d.errors)[0][0]) || 'Une erreur est survenue.'); }
        return d;
      });
    });
  }

  function busy(el, on) { if (el) { el.classList.toggle('is-loading', on); el.disabled = !!on; } }

  document.addEventListener('click', function (e) {
    var el;

    // Réactions
    if ((el = e.target.closest('[data-react]'))) {
      e.preventDefault();
      var post = el.closest('[data-post]'), fd = new FormData();
      fd.append('type', el.dataset.type || 'jaime');
      send(el.dataset.react, fd).then(function (d) {
        var main = post.querySelector('.mx-reactbar > .mx-act') || post.querySelector('.mx-reel__btn[data-react]');
        if (main) main.classList.toggle('is-on', !!d.reaction);
        var lab = post.querySelector('[data-react-label]'); if (lab) lab.textContent = d.label;
        var cnt = post.querySelector('[data-react-count]');
        if (cnt) cnt.textContent = post.classList.contains('mx-reel') ? (d.count || '') : (d.count ? d.count + ' réaction' + (d.count > 1 ? 's' : '') : '');
        if (document.activeElement) document.activeElement.blur();
      }).catch(function (err) { if (err.message !== 'auth') toast(err.message, true); });
      return;
    }

    // Enregistrer
    if ((el = e.target.closest('[data-save]'))) {
      e.preventDefault();
      send(el.dataset.save).then(function (d) {
        var post = el.closest('[data-post]');
        post.querySelectorAll('[data-save]').forEach(function (b) {
          b.classList.toggle('is-on', d.saved);
          var s = b.querySelector('span'); if (s && b.closest('.mx-menu__list')) s.textContent = d.saved ? 'Retirer des enregistrements' : 'Enregistrer';
        });
        toast(d.saved ? 'Publication enregistrée' : 'Retirée des enregistrements');
      }).catch(function (err) { if (err.message !== 'auth') toast(err.message, true); });
      return;
    }

    // Suivre / ne plus suivre une mutuelle
    if ((el = e.target.closest('[data-follow]'))) {
      e.preventDefault(); busy(el, true);
      send(el.dataset.follow).then(function (d) {
        document.querySelectorAll('[data-follow="' + el.dataset.follow + '"]').forEach(function (b) {
          b.classList.toggle('is-on', d.following);
          b.textContent = d.following ? 'Suivi' : 'Suivre';
        });
        document.querySelectorAll('[data-followers="' + el.dataset.follow + '"]').forEach(function (n) { n.textContent = d.followers; });
        toast(d.following ? 'Vous suivez maintenant ' + d.name : 'Vous ne suivez plus ' + d.name);
      }).catch(function (err) { if (err.message !== 'auth') toast(err.message, true); })
        .finally(function () { busy(el, false); });
      return;
    }

    // Partager
    if ((el = e.target.closest('[data-share]'))) {
      e.preventDefault();
      var note = window.prompt('Ajouter un message à votre partage (facultatif) :', '');
      if (note === null) return;
      var f = new FormData(); f.append('body', note);
      send(el.dataset.share, f).then(function () { toast('Publication partagée sur votre profil'); })
        .catch(function (err) { if (err.message !== 'auth') toast(err.message, true); });
      return;
    }

    // Copier le lien
    if ((el = e.target.closest('[data-copy]'))) {
      e.preventDefault();
      (navigator.clipboard ? navigator.clipboard.writeText(el.dataset.copy) : Promise.reject())
        .then(function () { toast('Lien copié'); }, function () { window.prompt('Copiez ce lien :', el.dataset.copy); });
      el.closest('details') && el.closest('details').removeAttribute('open');
      return;
    }

    // Signaler (ouvre la fenêtre)
    if ((el = e.target.closest('[data-report]'))) {
      e.preventDefault();
      var dlg = document.getElementById('mx-report'); if (!dlg) return;
      dlg.querySelector('form').action = el.dataset.report;
      el.closest('details') && el.closest('details').removeAttribute('open');
      dlg.showModal ? dlg.showModal() : dlg.setAttribute('open', '');
      return;
    }
    if ((el = e.target.closest('[data-close]'))) { var d2 = el.closest('dialog'); d2 && d2.close(); return; }

    // Répondre à un commentaire
    if ((el = e.target.closest('[data-reply]'))) {
      var form = document.querySelector('[data-reply-form="' + el.dataset.reply + '"]');
      if (form) { form.hidden = false; form.querySelector('textarea').focus(); }
      return;
    }

    // Charger plus (pagination par curseur)
    if ((el = e.target.closest('[data-more]'))) {
      e.preventDefault(); busy(el, true);
      var ghosts = document.createElement('div'); ghosts.className = 'mx-stack'; ghosts.setAttribute('aria-hidden', 'true');
      ghosts.innerHTML = new Array(3).join('<div class="mx-card mx-ghost"><div class="mx-ghost__row"><span class="mx-skeleton" style="width:44px;height:44px;border-radius:50%"></span><span class="mx-grow mx-stack" style="gap:8px"><span class="mx-skeleton" style="width:45%"></span><span class="mx-skeleton" style="width:25%"></span></span></div><span class="mx-skeleton"></span><span class="mx-skeleton" style="width:80%"></span><span class="mx-skeleton" style="height:140px;border-radius:12px"></span></div>');
      var mw = el.closest('[data-more-wrap]'); mw.parentNode.insertBefore(ghosts, mw); el.hidden = true;
      var url = new URL(el.href, location.href); url.searchParams.set('fragment', '1');
      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error(); return r.text(); })
        .then(function (html) {
          ghosts.remove();
          var wrap = el.closest('[data-more-wrap]'), tmp = document.createElement('div');
          tmp.innerHTML = html; while (tmp.firstChild) wrap.parentNode.insertBefore(tmp.firstChild, wrap); wrap.remove();
        })
        .catch(function () { ghosts.remove(); el.hidden = false; busy(el, false); toast('Chargement impossible. Vérifiez votre connexion.', true); });
    }
  });

  // Fermer les menus déroulants au clic extérieur
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.mx-menu[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });

  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.dataset.confirm && !window.confirm(f.dataset.confirm)) { e.preventDefault(); return; }

    // Commentaires sans rechargement
    if (f.hasAttribute('data-comment-form')) {
      e.preventDefault();
      var btn = f.querySelector('button'); busy(btn, true);
      send(f.action, new FormData(f)).then(function (d) {
        var tmp = document.createElement('div'); tmp.innerHTML = d.html;
        var node = tmp.firstElementChild;
        if (f.dataset.replyForm) { f.parentNode.insertBefore(node, f); f.hidden = true; }
        else {
          var list = f.closest('section').querySelector('[data-comment-list]');
          var none = list.querySelector('[data-no-comment]'); none && none.remove();
          list.appendChild(node);
        }
        f.reset();
        var post = f.closest('[data-post]'), c = post && post.querySelector('[data-comment-count]');
        if (c) c.textContent = d.count + ' commentaire' + (d.count > 1 ? 's' : '');
      }).catch(function (err) { if (err.message !== 'auth') toast(err.message, true); })
        .finally(function () { busy(btn, false); });
      return;
    }

    // Signalement
    if (f.hasAttribute('data-report-form')) {
      e.preventDefault();
      send(f.action, new FormData(f)).then(function () {
        f.closest('dialog').close(); f.reset(); toast('Merci, votre signalement a été transmis.');
      }).catch(function (err) { if (err.message !== 'auth') toast(err.message, true); });
      return;
    }

    // Publication : envoi avec barre de progression ; en cas d'erreur, le texte est conservé.
    if (f.hasAttribute('data-composer')) {
      var pbtn = f.querySelector('button.mx-btn--primary');
      if (!window.FormData || !window.XMLHttpRequest) { busy(pbtn, true); return; }
      e.preventDefault();
      if (f.dataset.videoBusy) { toast('Patientez : la vidéo est encore en cours d’envoi.', true); return; }
      busy(pbtn, true); f.dataset.sending = '1';
      var bar = f.querySelector('[data-progress]'), errs = f.querySelector('[data-composer-errors]');
      if (errs) errs.innerHTML = '';
      var xhr = new XMLHttpRequest();
      xhr.open('POST', f.action);
      xhr.setRequestHeader('X-CSRF-TOKEN', csrf); xhr.setRequestHeader('Accept', 'application/json'); xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      if (bar && xhr.upload) {
        xhr.upload.onprogress = function (ev) {
          if (!ev.lengthComputable) return;
          var pc = Math.round(ev.loaded / ev.total * 100); bar.hidden = false;
          bar.firstElementChild.style.width = pc + '%'; bar.setAttribute('aria-valuenow', pc);
        };
      }
      var fail = function (msgs) {
        busy(pbtn, false); if (bar) bar.hidden = true; delete f.dataset.sending; draft.save(f);
        msgs.forEach(function (m) { var p = document.createElement('p'); p.className = 'mx-error'; p.textContent = m; errs && errs.appendChild(p); });
        toast(msgs[0] || 'La publication n’a pas pu être envoyée.', true);
      };
      xhr.onload = function () {
        var d = {}; try { d = JSON.parse(xhr.responseText); } catch (x) {}
        if (xhr.status === 401) { location.href = '/compte/connexion'; return; }
        if (xhr.status >= 200 && xhr.status < 300 && d.url) {
          draft.clear(f);
          var u = new URL(d.url, location.href);
          if (u.pathname === location.pathname && u.search === location.search) { location.hash = u.hash; location.reload(); }
          else { location.href = d.url; }
          return;
        }
        var list = [];
        if (d.errors) Object.keys(d.errors).forEach(function (k) { list = list.concat(d.errors[k]); });
        else if (xhr.status === 413) list.push('Fichiers trop lourds pour être envoyés ensemble. Réduisez le nombre ou la taille des photos.');
        else if (xhr.status === 419) list.push('Votre session a expiré. Rechargez la page : votre texte est conservé en brouillon.');
        else if (xhr.status === 429) list.push('Trop de publications en peu de temps. Réessayez dans une minute.');
        else list.push(d.message || 'La publication n’a pas pu être envoyée. Votre texte est conservé.');
        fail(list);
      };
      xhr.onerror = function () { fail(['Connexion interrompue. Votre texte est conservé : réessayez.']); };
      var payload = new FormData(f);
      if (f._poster) payload.append('poster', f._poster, 'apercu.jpg');   // image d'aperçu de la vidéo (préparée par le navigateur)
      xhr.send(payload);
    }
  });

  // Brouillon de publication conservé sur l'appareil (jamais envoyé au serveur).
  var draft = {
    key: function (f) { return 'mx-draft:' + (f.getAttribute('action') || ''); },
    save: function (f) {
      if (f.dataset.sending) return;
      try {
        var t = f.querySelector('[data-composer-text]'); if (!t) return;
        if (t.value.trim()) localStorage.setItem(this.key(f), t.value); else localStorage.removeItem(this.key(f));
        var n = f.querySelector('[data-draftnote]'); if (n) n.hidden = !t.value.trim();
      } catch (x) {}
    },
    clear: function (f) { try { localStorage.removeItem(this.key(f)); } catch (x) {} },
    restore: function (f) {
      try {
        var t = f.querySelector('[data-composer-text]'), v = localStorage.getItem(this.key(f));
        if (t && v && !t.value) { t.value = v; openComposer(f); var n = f.querySelector('[data-draftnote]'); if (n) n.hidden = false; }
      } catch (x) {}
    }
  };
  function openComposer(f) {
    f.classList.add('is-composing');
    var box = f.querySelector('[data-composer-box]'); if (box) box.classList.add('is-open');
    var t = f.querySelector('[data-composer-text]');
    if (t) { t.style.height = 'auto'; t.style.height = Math.min(Math.max(t.scrollHeight + 2, 96), 360) + 'px'; }
  }
  document.querySelectorAll('form[data-composer]').forEach(function (f) {
    f.classList.add('js-composer');
    if (f.querySelector('.mx-composer.is-open')) openComposer(f);
    draft.restore(f);
    var t = f.querySelector('[data-composer-text]'); if (!t) return;
    t.addEventListener('focus', function () { openComposer(f); });
    var timer;
    t.addEventListener('input', function () { openComposer(f); clearTimeout(timer); timer = setTimeout(function () { draft.save(f); }, 400); });
  });

  // Aperçu et contrôle des images avant envoi
  document.addEventListener('change', function (e) {
    var input = e.target;
    if (!input.matches('[data-images]')) return;
    renderPreview(input);
  });

  // Aperçu des photos choisies, avec retrait d'une photo avant l'envoi.
  function renderPreview(input) {
    var form = input.closest('form'), box = form.querySelector('[data-preview]'); if (!box) return;
    box.innerHTML = '';
    var files = Array.prototype.slice.call(input.files || []);
    var vid = form.querySelector('[data-video-id]');
    if (files.length && vid && (vid.value || form.dataset.videoBusy)) { toast('Publiez soit des photos, soit une vidéo. Retirez d’abord la vidéo.', true); input.value = ''; return; }
    if (files.length > 6) { toast('6 images maximum par publication.', true); input.value = ''; return; }
    var bad = files.filter(function (file) { return !/^image\/(jpeg|png|webp|gif)$/.test(file.type) || file.size > 6 * 1024 * 1024; });
    if (bad.length) { toast(bad[0].size > 6 * 1024 * 1024 ? 'Image trop lourde (6 Mo max) : ' + bad[0].name : 'Format non accepté : ' + bad[0].name, true); }
    files.forEach(function (file, i) {
      var cell = document.createElement('span'); cell.className = 'mx-preview__item';
      var img = document.createElement('img'); img.alt = 'Photo ' + (i + 1) + ' sélectionnée'; img.src = URL.createObjectURL(file); cell.appendChild(img);
      if (window.DataTransfer) {
        var rm = document.createElement('button'); rm.type = 'button'; rm.className = 'mx-preview__rm'; rm.setAttribute('aria-label', 'Retirer la photo ' + (i + 1)); rm.textContent = '×';
        rm.addEventListener('click', function () {
          var dt = new DataTransfer();
          Array.prototype.forEach.call(input.files, function (f2, j) { if (j !== i) dt.items.add(f2); });
          input.files = dt.files; renderPreview(input);
        });
        cell.appendChild(rm);
      }
      box.appendChild(cell);
    });
    if (files.length) openComposer(form);
  }

  // Ouvrir le formulaire si l'adresse se termine par #composer
  if (location.hash === '#composer') {
    var ta = document.getElementById('composer-body'); if (ta) setTimeout(function () { ta.focus(); }, 150);
  }
  // Bouton « + » de la barre du bas quand on est déjà sur le fil : ouvrir la zone de publication.
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href$="#composer"]'); if (!a) return;
    var ta = document.getElementById('composer-body'); if (!ta) return;
    var u = new URL(a.href, location.href); if (u.pathname !== location.pathname) return;
    e.preventDefault(); document.getElementById('composer').scrollIntoView({ behavior: 'smooth', block: 'start' }); setTimeout(function () { ta.focus(); }, 250);
  });
  // Fermer le menu complet
  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-close-drawer]'); if (c) { e.preventDefault(); var d = c.closest('details'); if (d) d.removeAttribute('open'); }
  });
  // Onglet actif visible dans la barre défilante.
  document.querySelectorAll('[data-feedtabs] .is-active').forEach(function (a) { try { a.scrollIntoView({ block: 'nearest', inline: 'center' }); } catch (x) {} });

  // Application installable (PWA) : service worker limité aux fichiers publics.
  if ('serviceWorker' in navigator && location.protocol === 'https:') {
    navigator.serviceWorker.register('/sw-social.js').catch(function () {});
  }
})();
