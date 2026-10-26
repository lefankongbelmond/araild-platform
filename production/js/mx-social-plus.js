/* MX-CONNECT — fonctions « façon Facebook » (sans dépendance) :
   défilement continu, emojis, mentions (@), vidéos envoyées par morceaux, lecture automatique, réels. */
(function () {
  'use strict';
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var mentionUrl = (document.querySelector('meta[name="mx-mentions"]') || {}).content || '';

  function toast(msg, isErr) {
    var box = document.getElementById('mx-toasts'); if (!box) { if (isErr) alert(msg); return; }
    var t = document.createElement('div'); t.className = 'mx-toast' + (isErr ? ' mx-toast--err' : '');
    t.textContent = msg; box.appendChild(t);
    setTimeout(function () { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; }, 3200);
    setTimeout(function () { t.remove(); }, 3600);
  }

  /* ---------- 1. Défilement continu : « Charger plus » se déclenche tout seul ---------- */
  if ('IntersectionObserver' in window) {
    var moreObs = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        var a = en.target; moreObs.unobserve(a);
        if (!a.hidden && !a.classList.contains('is-loading')) { a.closest('[data-more-wrap]').classList.add('is-auto'); a.click(); }
      });
    }, { rootMargin: '900px 0px' });
    var watchMore = function (root) { (root || document).querySelectorAll('a[data-more]').forEach(function (a) { if (!a._watched) { a._watched = 1; moreObs.observe(a); } }); };
    watchMore();
    // Après un échec (réseau saturé, connexion coupée), le chargement continu réessaie tout seul.
    setInterval(function () {
      document.querySelectorAll('a[data-more]').forEach(function (a) { if (!a.hidden && !a.classList.contains('is-loading')) moreObs.observe(a); });
    }, 8000);
    new MutationObserver(function () { watchMore(); watchVideos(); watchReels(); }).observe(document.body, { childList: true, subtree: true });
  }

  /* ---------- 2. Emojis ---------- */
  var EMOJIS = {
    'Fréquents': '😀 😂 🥰 😍 😊 😉 🙏 👍 👏 🙌 💪 ❤️ 💙 💚 🎉 ✅ 🔥 ⭐ 👌 🤝',
    'Visages': '😃 😄 😁 😆 😅 🤣 🙂 🙃 😇 🤗 🤩 😘 😋 😎 🤔 🤨 😐 😶 🙄 😏 😮 😯 😲 😴 🤤 😪 😢 😭 😤 😡 🥺 😱 😳 🤒 🤕 😷 🥳 🤭 🫶',
    'Gestes et personnes': '👋 🤚 ✋ 🖐 👊 ✊ 🤛 🤜 🤞 ✌️ 🤟 👈 👉 👆 👇 ☝️ 🫵 👩‍⚕️ 👨‍⚕️ 🧑‍⚕️ 🤰 👶 👧 🧒 👦 👩 👨 🧓 👪 👨‍👩‍👧 👩‍👦',
    'Santé et solidarité': '🏥 🩺 💊 💉 🩹 🩸 🧪 🦠 🧼 😷 🚑 ⚕️ 🫀 🫁 🧠 🦷 👁️ 🍎 🥦 🥗 💧 🏃 🧘 🛌 🤲 🫂 💝 🎗️',
    'Événements': '📅 📌 📍 📢 📣 🎤 🎓 📚 📝 📊 📈 💼 🏆 🥇 🎁 🎈 🎊 🌍 🌱 ☀️ 🌧️ ⚽ 🎶',
    'Symboles': '✔️ ❌ ⚠️ ❗ ❓ ➡️ ⬅️ ⬆️ ⬇️ 🔔 🔒 🔑 💡 📞 ✉️ 🔗 🕒 💯 🆕 🆓 ℹ️'
  };
  var pop = null, popTarget = null;
  function closeEmoji() { if (pop) { pop.remove(); pop = null; popTarget = null; } }
  function insertAtCaret(ta, text) {
    ta.focus();
    var s = ta.selectionStart != null ? ta.selectionStart : ta.value.length, e2 = ta.selectionEnd != null ? ta.selectionEnd : s;
    ta.value = ta.value.slice(0, s) + text + ta.value.slice(e2);
    var p = s + text.length; try { ta.setSelectionRange(p, p); } catch (x) {}
    ta.dispatchEvent(new Event('input', { bubbles: true }));
  }
  function openEmoji(btn, ta) {
    if (pop && popTarget === ta) { closeEmoji(); return; }
    closeEmoji();
    pop = document.createElement('div'); pop.className = 'mx-emoji-pop'; pop.setAttribute('role', 'dialog'); pop.setAttribute('aria-label', 'Choisir un emoji');
    Object.keys(EMOJIS).forEach(function (cat) {
      var h = document.createElement('div'); h.className = 'mx-emoji-pop__cat'; h.textContent = cat; pop.appendChild(h);
      var g = document.createElement('div'); g.className = 'mx-emoji-pop__grid';
      EMOJIS[cat].split(' ').forEach(function (em) {
        var b = document.createElement('button'); b.type = 'button'; b.textContent = em; b.setAttribute('aria-label', em);
        b.addEventListener('click', function () { insertAtCaret(ta, em); });
        g.appendChild(b);
      });
      pop.appendChild(g);
    });
    document.body.appendChild(pop); popTarget = ta;
    var r = btn.getBoundingClientRect(), w = pop.offsetWidth, hgt = pop.offsetHeight;
    var left = Math.max(8, Math.min(r.left + window.scrollX, window.scrollX + document.documentElement.clientWidth - w - 8));
    var top = r.bottom + window.scrollY + 6;
    if (r.bottom + hgt + 12 > window.innerHeight && r.top > hgt + 12) top = r.top + window.scrollY - hgt - 6;
    pop.style.left = left + 'px'; pop.style.top = top + 'px';
    var first = pop.querySelector('button'); if (first) first.focus({ preventScroll: true });
  }
  function setupEmoji(root) {
    (root || document).querySelectorAll('textarea[data-emoji]').forEach(function (ta) {
      if (ta._emoji) return; ta._emoji = 1;
      var form = ta.closest('form');
      var btn = form && form.querySelector('[data-emoji-btn]');
      if (!btn || !form.contains(btn) || form.querySelectorAll('textarea[data-emoji]').length > 1) {
        btn = document.createElement('button'); btn.type = 'button'; btn.className = 'mx-emoji-inline';
        btn.setAttribute('aria-label', 'Ajouter un emoji'); btn.title = 'Emoji';
        btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 14.5a5 5 0 0 0 8 0"/><path d="M9 9.5h.01M15 9.5h.01"/></svg>';
        ta.insertAdjacentElement('afterend', btn);
      }
      btn.addEventListener('click', function (e) { e.preventDefault(); openEmoji(btn, ta); });
    });
  }
  document.addEventListener('click', function (e) { if (pop && !pop.contains(e.target) && !e.target.closest('[data-emoji-btn], .mx-emoji-inline')) closeEmoji(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeEmoji(); closeMention(); } });

  /* ---------- 3. Mentions (@) ---------- */
  var mpop = null, mState = null, mTimer = null;
  function closeMention() { if (mpop) { mpop.remove(); mpop = null; } mState = null; }
  function mentionQuery(ta) {
    var upto = ta.value.slice(0, ta.selectionStart);
    var m = upto.match(/(^|[\s(])@([^\s@#][^@#\n]{0,29}|)$/u);
    if (!m) return null;
    var q = m[2];
    if (/\s{2,}/.test(q) || (q.split(' ').length > 4)) return null;
    return { q: q, start: upto.length - q.length - 1 };
  }
  function renderMentions(ta, items, info) {
    closeMention();
    mpop = document.createElement('div'); mpop.className = 'mx-mention-pop'; mpop.setAttribute('role', 'listbox');
    if (!items.length) {
      mpop.innerHTML = '<div class="mx-mention-pop__empty">Aucun membre ou page trouvé</div>';
    }
    items.forEach(function (it, i) {
      var b = document.createElement('button'); b.type = 'button'; b.setAttribute('role', 'option'); if (i === 0) b.classList.add('is-active');
      var av;
      if (it.avatar) { av = document.createElement('img'); av.src = it.avatar; av.alt = ''; }
      else { av = document.createElement('span'); av.className = 'mx-mention-pop__ini'; av.textContent = it.initials || '@'; }
      var tx = document.createElement('span'); var nm = document.createElement('b'); nm.textContent = it.label;
      var sm = document.createElement('small'); sm.textContent = it.kind + (it.sub ? ' · ' + it.sub : '');
      tx.appendChild(nm); tx.appendChild(sm); b.appendChild(av); b.appendChild(tx);
      b.addEventListener('mousedown', function (e) { e.preventDefault(); pickMention(ta, it, info); });
      mpop.appendChild(b);
    });
    document.body.appendChild(mpop);
    var r = ta.getBoundingClientRect();
    mpop.style.left = Math.max(8, Math.min(r.left + window.scrollX, window.scrollX + document.documentElement.clientWidth - mpop.offsetWidth - 8)) + 'px';
    mpop.style.top = (r.bottom + window.scrollY + 4) + 'px';
    mState = { ta: ta, items: items, info: info, idx: 0 };
  }
  function pickMention(ta, it, info) {
    var before = ta.value.slice(0, info.start), after = ta.value.slice(ta.selectionStart);
    var ins = '@' + it.label + ' ';
    ta.value = before + ins + after.replace(/^\S*/, function (w) { return w; });
    var p = before.length + ins.length; ta.focus(); try { ta.setSelectionRange(p, p); } catch (x) {}
    var form = ta.closest('form');
    if (form && !form.querySelector('input[name="mentions[]"][value="' + it.id + '"]')) {
      var h = document.createElement('input'); h.type = 'hidden'; h.name = 'mentions[]'; h.value = it.id; h.dataset.label = it.label;
      form.appendChild(h);
    }
    closeMention();
    ta.dispatchEvent(new Event('input', { bubbles: true }));
  }
  function setupMentions(root) {
    if (!mentionUrl) return;
    (root || document).querySelectorAll('textarea[data-mentions]').forEach(function (ta) {
      if (ta._mentions) return; ta._mentions = 1;
      ta.addEventListener('input', function () {
        var info = mentionQuery(ta);
        clearTimeout(mTimer);
        if (!info) { if (mState && mState.ta === ta) closeMention(); return; }
        if (info.q.length < 1) { renderMentionsHint(ta); return; }
        mTimer = setTimeout(function () {
          fetch(mentionUrl + '?q=' + encodeURIComponent(info.q), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : { results: [] }; })
            .then(function (d) { var now = mentionQuery(ta); if (now && now.q === info.q) renderMentions(ta, d.results || [], info); })
            .catch(function () {});
        }, 180);
      });
      ta.addEventListener('keydown', function (e) {
        if (!mState || mState.ta !== ta || !mState.items.length) return;
        var btns = mpop.querySelectorAll('button');
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
          e.preventDefault(); btns[mState.idx].classList.remove('is-active');
          mState.idx = (mState.idx + (e.key === 'ArrowDown' ? 1 : -1) + btns.length) % btns.length;
          btns[mState.idx].classList.add('is-active');
        } else if (e.key === 'Enter' || e.key === 'Tab') {
          e.preventDefault(); pickMention(ta, mState.items[mState.idx], mState.info);
        }
      });
      ta.addEventListener('blur', function () { setTimeout(function () { if (mState && mState.ta === ta) closeMention(); }, 150); });
      // Avant l'envoi : ne garder que les mentions encore présentes dans le texte.
      var form = ta.closest('form');
      if (form && !form._mentionClean) {
        form._mentionClean = 1;
        form.addEventListener('submit', function () {
          form.querySelectorAll('input[name="mentions[]"]').forEach(function (h) { if (ta.value.indexOf('@' + h.dataset.label) === -1) h.remove(); });
        }, true);
        form.addEventListener('reset', function () { setTimeout(function () { form.querySelectorAll('input[name="mentions[]"]').forEach(function (h) { h.remove(); }); }, 0); });
      }
    });
  }
  function renderMentionsHint(ta) {
    closeMention();
    mpop = document.createElement('div'); mpop.className = 'mx-mention-pop';
    mpop.innerHTML = '<div class="mx-mention-pop__empty">Tapez le nom d’un membre ou d’une page…</div>';
    document.body.appendChild(mpop);
    var r = ta.getBoundingClientRect();
    mpop.style.left = Math.max(8, r.left + window.scrollX) + 'px'; mpop.style.top = (r.bottom + window.scrollY + 4) + 'px';
    mState = { ta: ta, items: [], info: null, idx: 0 };
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-mention-btn]'); if (!b) return;
    var ta = b.closest('form').querySelector('textarea[data-mentions]'); if (!ta) return;
    var s = ta.selectionStart || ta.value.length, pre = ta.value.slice(0, s);
    insertAtCaret(ta, (pre && !/\s$/.test(pre) ? ' ' : '') + '@');
  });

  /* ---------- 4. Vidéos : contrôle, aperçu, envoi par morceaux ---------- */
  var VIDEO_MAX = 100 * 1024 * 1024, REEL_MAX = 90;
  var EXT_MIME = { mp4: 'video/mp4', m4v: 'video/mp4', mov: 'video/quicktime', webm: 'video/webm' };
  document.addEventListener('change', function (e) {
    var input = e.target; if (!input.matches('[data-video]')) return;
    var form = input.closest('form'), file = input.files && input.files[0]; input.value = '';
    if (!file) return;
    var ext = (file.name.split('.').pop() || '').toLowerCase();
    var mime = /^video\/(mp4|quicktime|webm)$/.test(file.type) ? file.type : (EXT_MIME[ext] || '');
    if (!mime) { toast('Format vidéo non accepté : utilisez une vidéo MP4, MOV ou WebM.', true); return; }
    if (file.size > VIDEO_MAX) { toast('Vidéo trop lourde : 100 Mo maximum (' + Math.round(file.size / 1048576) + ' Mo).', true); return; }
    var imgs = form.querySelector('[data-images]');
    if (imgs && imgs.files && imgs.files.length) { toast('Publiez soit des photos, soit une vidéo. Retirez d’abord les photos.', true); return; }
    startVideo(form, file, mime);
  });

  function startVideo(form, file, mime) {
    var box = form.querySelector('[data-video-box]'); if (!box) return;
    resetVideo(form);
    var token = {}; form._videoToken = token;
    box.hidden = false;
    var box2 = form.querySelector('[data-composer-box]'); if (box2) box2.classList.add('is-open');
    form.classList.add('is-composing');
    box.querySelector('[data-video-name]').textContent = file.name;
    var status = box.querySelector('[data-video-status]'), bar = box.querySelector('[data-video-progress]'), reel = box.querySelector('[data-reel]');
    bar.classList.remove('is-done'); bar.firstElementChild.style.width = '0%';
    var vid = box.querySelector('[data-video-preview]'), url = URL.createObjectURL(file);
    vid.src = url;
    vid.onloadedmetadata = function () {
      var d = vid.duration || 0, w = vid.videoWidth, h = vid.videoHeight;
      form.querySelector('[data-video-duration]').value = d ? d.toFixed(1) : '';
      form.querySelector('[data-video-width]').value = w || ''; form.querySelector('[data-video-height]').value = h || '';
      var tooLong = d > REEL_MAX + 0.5;
      reel.disabled = tooLong; reel.checked = !tooLong && h > w;
      reel.closest('label').title = tooLong ? 'Un réel dure au maximum ' + REEL_MAX + ' secondes.' : '';
      // Image d'aperçu : une image prise vers le début de la vidéo.
      try { vid.currentTime = Math.min(1, d / 3 || 0.1); } catch (x) {}
    };
    vid.onseeked = function () {
      if (form._poster || !vid.videoWidth) return;
      try {
        var scale = Math.min(1, 1280 / Math.max(vid.videoWidth, vid.videoHeight));
        var c = document.createElement('canvas'); c.width = Math.round(vid.videoWidth * scale); c.height = Math.round(vid.videoHeight * scale);
        c.getContext('2d').drawImage(vid, 0, 0, c.width, c.height);
        c.toBlob(function (b) { if (b && form._videoToken === token) form._poster = b; }, 'image/jpeg', 0.82);
      } catch (x) {}
    };
    vid.onerror = function () { status.textContent = 'Aperçu indisponible sur cet appareil : la vidéo sera tout de même envoyée.'; };

    // Envoi par morceaux (reprend où le serveur s'est arrêté en cas de coupure).
    form.dataset.videoBusy = '1';
    status.textContent = 'Envoi de la vidéo… 0 %';
    var fd = new FormData(); fd.append('size', file.size); fd.append('mime', mime); fd.append('name', file.name);
    post(form.dataset.videoStart, fd).then(function (d) {
      var id = d.id, chunk = d.chunk || 1536 * 1024, offset = 0, tries = 0;
      function next() {
        if (form._videoToken !== token) return;
        if (offset >= file.size) {
          form.querySelector('[data-video-id]').value = id; delete form.dataset.videoBusy;
          bar.classList.add('is-done'); bar.firstElementChild.style.width = '100%';
          status.textContent = 'Vidéo prête (' + Math.round(file.size / 1048576 * 10) / 10 + ' Mo). Ajoutez un texte et publiez.';
          return;
        }
        var part = new FormData(); part.append('offset', offset); part.append('chunk', file.slice(offset, offset + chunk), 'morceau');
        post(form.dataset.videoStart + '/' + id, part).then(function (r) {
          tries = 0; offset = r.received;
          var pc = Math.min(100, Math.round(offset / file.size * 100));
          bar.firstElementChild.style.width = pc + '%'; bar.setAttribute('aria-valuenow', pc);
          status.textContent = 'Envoi de la vidéo… ' + pc + ' %';
          next();
        }).catch(function (err) {
          if (form._videoToken !== token) return;
          if (++tries <= 4 && !err.final) { status.textContent = 'Connexion instable, nouvel essai…'; setTimeout(next, 1500 * tries); return; }
          delete form.dataset.videoBusy; status.textContent = err.message || 'Envoi interrompu.';
          toast(err.message || 'Envoi de la vidéo interrompu. Réessayez.', true);
        });
      }
      next();
    }).catch(function (err) {
      delete form.dataset.videoBusy; status.textContent = err.message || 'Envoi impossible.';
      toast(err.message || 'Envoi de la vidéo impossible.', true);
    });
  }
  function resetVideo(form) {
    form._videoToken = null; form._poster = null; delete form.dataset.videoBusy;
    var box = form.querySelector('[data-video-box]'); if (!box) return;
    var v = box.querySelector('[data-video-preview]'); if (v.src) { try { URL.revokeObjectURL(v.src); } catch (x) {} v.removeAttribute('src'); v.load(); }
    ['[data-video-id]', '[data-video-duration]', '[data-video-width]', '[data-video-height]'].forEach(function (s) { form.querySelector(s).value = ''; });
    var reel = box.querySelector('[data-reel]'); reel.checked = false; reel.disabled = false;
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-video-remove]'); if (!b) return;
    var form = b.closest('form'); resetVideo(form); form.querySelector('[data-video-box]').hidden = true;
  });
  function post(url, body) {
    return fetch(url, { method: 'POST', body: body, credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (d) {
          if (r.status === 401) { location.href = '/compte/connexion'; }
          if (!r.ok) {
            var err = new Error(d.message && r.status !== 422 ? d.message : (d.errors && Object.values(d.errors)[0][0]) || d.message || 'Erreur ' + r.status);
            err.final = r.status === 422 || r.status === 403 || r.status === 413; throw err;
          }
          return d;
        });
      });
  }

  /* ---------- 5. Lecture automatique (son coupé) des vidéos du fil, comme Facebook ---------- */
  var saveData = (navigator.connection && navigator.connection.saveData) || (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches)
    || document.body.hasAttribute('data-light');   // mode allégé du réseau : pas de lecture automatique
  var vidObs = ('IntersectionObserver' in window) && !saveData ? new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      var v = en.target;
      if (en.isIntersecting && en.intersectionRatio >= 0.6) { var p = v.play(); if (p && p.catch) p.catch(function () {}); }
      else if (!v.paused) v.pause();
    });
  }, { threshold: [0, 0.6] }) : null;
  function watchVideos() { if (!vidObs) return; document.querySelectorAll('video[data-autoplay]').forEach(function (v) { if (!v._w) { v._w = 1; vidObs.observe(v); } }); }

  /* ---------- 6. Réels ---------- */
  var reelsBox = document.querySelector('[data-reels]'), reelSound = false;
  if (reelsBox) {
    var setTop = function () { document.documentElement.style.setProperty('--reels-top', Math.max(0, reelsBox.getBoundingClientRect().top + window.scrollY) + 'px'); };
    setTop(); window.addEventListener('resize', setTop);
    var hash = location.search.match(/[?&]r=(\d+)/);
    if (hash) { var t = document.getElementById('reel-' + hash[1]); if (t) t.scrollIntoView(); }
    document.addEventListener('keydown', function (e) {
      if (e.target.closest('input, textarea')) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); reelsBox.scrollBy({ top: (e.key === 'ArrowDown' ? 1 : -1) * reelsBox.clientHeight, behavior: 'smooth' }); }
    });
  }
  var reelObs = ('IntersectionObserver' in window) ? new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      var v = en.target;
      if (en.isIntersecting && en.intersectionRatio >= 0.7) { v.muted = !reelSound; var p = v.play(); if (p && p.catch) p.catch(function () { v.muted = true; v.play().catch(function () {}); }); }
      else { v.pause(); }
    });
  }, { root: reelsBox || null, threshold: [0, 0.7] }) : null;
  function watchReels() { if (!reelObs || !reelsBox) return; reelsBox.querySelectorAll('[data-reel-video]').forEach(function (v) { if (!v._w) { v._w = 1; reelObs.observe(v); } }); }
  document.addEventListener('click', function (e) {
    var v = e.target.closest('[data-reel-video]');
    if (v) { if (v.paused) v.play().catch(function () {}); else v.pause(); return; }
    var s = e.target.closest('[data-reel-sound]');
    if (s) {
      reelSound = !reelSound;
      document.querySelectorAll('[data-reel-video]').forEach(function (x) { x.muted = !reelSound; });
      document.querySelectorAll('[data-reel-sound] span').forEach(function (x) { x.textContent = reelSound ? 'Son activé' : 'Son coupé'; });
      document.querySelectorAll('[data-reel-sound]').forEach(function (x) { x.setAttribute('aria-label', reelSound ? 'Couper le son' : 'Activer le son'); });
    }
  });

  setupEmoji(); setupMentions(); watchVideos(); watchReels();
  // Commentaires ajoutés sans rechargement (formulaires de réponse) : brancher emojis et mentions.
  new MutationObserver(function () { setupEmoji(); setupMentions(); }).observe(document.body, { childList: true, subtree: true });
})();
