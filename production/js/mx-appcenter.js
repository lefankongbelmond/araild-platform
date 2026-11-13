/* MX-CONNECT — App Center (2026-11-13).
 * Détection du système et du navigateur, installation d'une application web (PWA) via la
 * fenêtre NATIVE du navigateur quand elle existe, sinon guide adapté ; détection d'une
 * application déjà installée ; statistiques anonymes (aucun compte, aucune adresse IP).
 * Une application web ne s'installe QUE depuis la page dont elle dépend : le réseau social
 * depuis le domaine principal, l'espace d'une mutuelle depuis le sous-domaine de la mutuelle.
 * Rien ici ne télécharge un exécutable ; l'APK Android n'est proposé que s'il a été déposé
 * et vérifié (signature officielle) dans l'espace super-admin. */
(function () {
  'use strict';

  // ------------------------------------------------------------------ détection
  function detect() {
    var ua = navigator.userAgent || '';
    var mac = /Macintosh|Mac OS X/.test(ua);
    var os = 'other';
    if (/Android/i.test(ua)) os = 'android';
    else if (/iPhone|iPad|iPod/.test(ua) || (mac && navigator.maxTouchPoints > 1)) os = 'ios';
    else if (/Windows/.test(ua)) os = 'windows';
    else if (/CrOS/.test(ua)) os = 'chromeos';
    else if (mac) os = 'macos';
    else if (/Linux/.test(ua)) os = 'linux';
    var browser = 'other';
    if (/Edg(e|A|iOS)?\//.test(ua)) browser = 'edge';
    else if (/SamsungBrowser/.test(ua)) browser = 'samsung';
    else if (/OPR\/|Opera/.test(ua)) browser = 'opera';
    else if (/Firefox|FxiOS/.test(ua)) browser = 'firefox';
    else if (/CriOS/.test(ua)) browser = 'chrome';
    else if (/Chrome\//.test(ua)) browser = 'chrome';
    else if (/Safari/.test(ua)) browser = 'safari';
    var mobile = os === 'android' || os === 'ios';
    return { os: os, browser: browser, form: mobile ? 'mobile' : 'pc', mobile: mobile,
      standalone: window.matchMedia('(display-mode: standalone)').matches || window.matchMedia('(display-mode: window-controls-overlay)').matches || navigator.standalone === true,
      chromium: browser === 'chrome' || browser === 'edge' || browser === 'samsung' || browser === 'opera' };
  }
  var env = detect();
  var LABEL_OS = { windows: 'Windows', macos: 'macOS', linux: 'Linux', android: 'Android', ios: 'iPhone / iPad', chromeos: 'ChromeOS', other: 'système non reconnu' };
  var LABEL_BR = { chrome: 'Chrome', edge: 'Edge', firefox: 'Firefox', safari: 'Safari', samsung: 'Samsung Internet', opera: 'Opera', other: 'navigateur non reconnu' };

  // ---------------------------------------------------------- stockage prudent
  function lsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function lsSet(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function deviceId() {
    var id = lsGet('mxapp_device');
    if (!id || !/^[a-f0-9]{32}$/.test(id)) {
      var a = new Uint8Array(16); (window.crypto || window.msCrypto).getRandomValues(a);
      id = Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
      lsSet('mxapp_device', id);
    }
    return id;
  }

  // ------------------------------------------------------------- statistiques
  function track(endpoint, app, event) {
    if (!endpoint) return;
    var meta = document.querySelector('meta[name="csrf-token"]');
    var body = { app: app, event: event, os: env.os, browser: env.browser };
    if (event === 'installed' || event === 'launched') body.device = deviceId();
    try {
      fetch(endpoint, { method: 'POST', keepalive: true, credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': meta ? meta.content : '' },
        body: JSON.stringify(body) }).catch(function () {});
    } catch (e) {}
  }

  /** Ouverture en mode application : comptée une fois par jour (installation confirmée + utilisateur actif). */
  function launch(app, endpoint) {
    if (!env.standalone) return;
    lsSet('mxapp_installed_' + app, '1');
    var today = new Date().toISOString().slice(0, 10);
    if (lsGet('mxapp_launch_' + app) === today) return;
    lsSet('mxapp_launch_' + app, today);
    track(endpoint, app, 'launched');
  }

  // ----------------------------------------------- fenêtre native d'installation
  var deferred = null, listeners = [];
  window.addEventListener('beforeinstallprompt', function (e) {
    // Seules les pages d'installation (App Center, /installer) gardent la fenêtre pour leur bouton ;
    // ailleurs, le comportement habituel du navigateur (bandeau d'installation) est conservé.
    if (!document.documentElement.hasAttribute('data-app-install')) return;
    e.preventDefault(); deferred = e;
    listeners.forEach(function (f) { f(); });
  });
  function onPromptReady(f) { listeners.push(f); if (deferred) f(); }

  // ------------------------------------------------------------------- guides
  function step(t) { return '<li>' + t + '</li>'; }
  var ICON_INSTALL = '<span class="ac-kbd" aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M12 8v6M9 11l3 3 3-3M8 21h8"/></svg></span>';
  var ICON_SHARE = '<span class="ac-kbd" aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15V3M8 7l4-4 4 4"/><path d="M5 11v9h14v-9"/></svg></span>';

  /** Guide adapté au système et au navigateur. target = 'pc' | 'android'. Ne promet jamais une installation impossible. */
  function guide(name, target, note) {
    var o = env.os, b = env.browser, html = '', ok = true, illus = 'bar';
    if (target === 'android' && o !== 'android') {
      if (o === 'ios') {
        html = '<p>Vous utilisez un iPhone ou un iPad : l’installation Android ne s’applique pas. Vous pouvez ajouter <b>' + name + '</b> à votre écran d’accueil :</p><ol>'
          + step('Ouvrez cette page dans <b>Safari</b>.') + step('Touchez le bouton Partager ' + ICON_SHARE + '.')
          + step('Choisissez <b>« Sur l’écran d’accueil »</b>, puis <b>Ajouter</b>.') + '</ol>';
        illus = 'ios';
      } else {
        html = '<p>Pour installer <b>' + name + '</b> sur un téléphone Android, ouvrez cette page <b>sur le téléphone</b> : scannez le code ci-dessous avec l’appareil photo, puis touchez <b>« Installer sur Android »</b>.</p>';
        illus = 'qr';
      }
      return { html: html, ok: true, illus: illus };
    }
    if (target === 'pc' && env.mobile) {
      html = '<p>Vous êtes sur un ' + (o === 'ios' ? 'iPhone ou un iPad' : 'téléphone ou une tablette') + '. Pour l’ordinateur, ouvrez cette même page dans <b>Chrome</b> ou <b>Edge</b> sur votre PC (Windows, macOS ou Linux).</p>'
        + (o === 'android' ? '<p>Sur ce téléphone, utilisez plutôt le bouton <b>« Installer sur Android »</b>.</p>' : '');
      return { html: html, ok: false, illus: 'none' };
    }
    if (o === 'ios') {
      html = '<ol>' + step('Ouvrez cette page dans <b>Safari</b> (ou, depuis iOS 16.4, dans votre navigateur s’il propose « Sur l’écran d’accueil »).')
        + step('Touchez le bouton Partager ' + ICON_SHARE + '.') + step('Choisissez <b>« Sur l’écran d’accueil »</b>, puis <b>Ajouter</b>.') + '</ol>';
      illus = 'ios';
    } else if (o === 'android') {
      if (b === 'samsung') html = '<ol>' + step('Touchez le menu <b>≡</b> en bas de l’écran.') + step('Choisissez <b>« Ajouter la page à »</b> puis <b>« Écran d’accueil »</b>.') + '</ol>';
      else if (b === 'firefox') html = '<ol>' + step('Touchez le menu <b>⋮</b>.') + step('Choisissez <b>« Installer »</b> ou <b>« Ajouter à l’écran d’accueil »</b>.') + '</ol>';
      else html = '<ol>' + step('Ouvrez cette page dans <b>Chrome</b>.') + step('Touchez le menu <b>⋮</b> en haut à droite.') + step('Choisissez <b>« Installer l’application »</b> (ou <b>« Ajouter à l’écran d’accueil »</b>).') + '</ol>';
      illus = 'android';
    } else if (b === 'firefox') {
      ok = false;
      html = '<p>Firefox sur ordinateur n’installe pas les applications web comme le font Chrome et Edge. Pour installer <b>' + name + '</b>, ouvrez cette page dans <b>Google Chrome</b> ou <b>Microsoft Edge</b>.</p><p>En attendant, vous pouvez utiliser MX-CONNECT dans Firefox et l’ajouter à vos marque-pages.</p>';
      illus = 'none';
    } else if (b === 'safari') {
      html = '<ol>' + step('Utilisez Safari 17 ou plus récent (macOS Sonoma).') + step('Menu <b>Fichier</b> → <b>« Ajouter au Dock »</b>.') + step('Confirmez avec <b>Ajouter</b> : l’application apparaît dans le Dock et le Launchpad.') + '</ol>';
      illus = 'mac';
    } else if (b === 'edge') {
      html = '<ol>' + step('Cliquez sur l’icône d’installation ' + ICON_INSTALL + ' à droite de la barre d’adresse.') + step('Ou ouvrez le menu <b>…</b> → <b>Applications</b> → <b>« Installer ce site en tant qu’application »</b>.') + step('Confirmez avec <b>Installer</b>.') + '</ol>';
    } else if (b === 'chrome' || b === 'opera') {
      html = '<ol>' + step('Cliquez sur l’icône d’installation ' + ICON_INSTALL + ' à droite de la barre d’adresse.') + step('Ou ouvrez le menu <b>⋮</b> → <b>« Caster, enregistrer et partager »</b> → <b>« Installer… »</b>.') + step('Confirmez avec <b>Installer</b> : l’application s’ouvre dans sa propre fenêtre et rejoint le menu Démarrer ou le Dock.') + '</ol>';
    } else {
      ok = false;
      html = '<p>Votre navigateur n’a pas été reconnu. Pour installer <b>' + name + '</b>, ouvrez cette page dans <b>Google Chrome</b> ou <b>Microsoft Edge</b> sur ordinateur, ou dans <b>Chrome</b> sur Android.</p>';
      illus = 'none';
    }
    if (note) html += '<p class="ac-note">' + note + '</p>';
    return { html: html, ok: ok, illus: illus };
  }

  // ------------------------------------------------------------------ modale
  var modal, mTitle, mBody, lastFocus;
  function ensureModal() {
    if (modal) return;
    modal = document.createElement('div');
    modal.className = 'ac-modal'; modal.hidden = true;
    modal.innerHTML = '<div class="ac-modal__scrim" data-close></div><div class="ac-modal__box" role="dialog" aria-modal="true" aria-labelledby="ac-mt">'
      + '<button type="button" class="ac-modal__x" data-close aria-label="Fermer">×</button><h2 id="ac-mt"></h2><div class="ac-modal__body"></div></div>';
    document.body.appendChild(modal);
    mTitle = modal.querySelector('h2'); mBody = modal.querySelector('.ac-modal__body');
    modal.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) close(); });
  }
  function open(title, html) {
    ensureModal(); lastFocus = document.activeElement;
    mTitle.textContent = title; mBody.innerHTML = html; modal.hidden = false;
    document.documentElement.classList.add('ac-noscroll');
    var f = modal.querySelector('.ac-modal__body a, .ac-modal__body button, .ac-modal__body input, .ac-modal__x'); if (f) f.focus();
    return mBody;
  }
  function close() {
    if (!modal) return; modal.hidden = true; document.documentElement.classList.remove('ac-noscroll');
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  function progress(text) {
    return '<div class="ac-progress" role="status"><div class="ac-progress__bar"><span></span></div><p>' + text + '</p></div>';
  }
  function illustration(kind) {
    if (kind === 'bar') return '<div class="ac-illus" aria-hidden="true"><div class="ac-illus__bar"><span class="ac-illus__dots"><i></i><i></i><i></i></span><span class="ac-illus__url">m.araild.com</span><span class="ac-illus__hit">' + ICON_INSTALL + '</span><span class="ac-illus__menu">⋮</span></div><div class="ac-illus__hint">Icône d’installation dans la barre d’adresse</div></div>';
    if (kind === 'android') return '<div class="ac-illus ac-illus--phone" aria-hidden="true"><div class="ac-illus__phone"><div class="ac-illus__top"><span>m.araild.com</span><b>⋮</b></div><div class="ac-illus__sheet"><span>Nouvel onglet</span><span class="is-hit">Installer l’application</span><span>Paramètres</span></div></div></div>';
    if (kind === 'ios') return '<div class="ac-illus ac-illus--phone" aria-hidden="true"><div class="ac-illus__phone"><div class="ac-illus__sheet ac-illus__sheet--bottom"><span>Copier</span><span class="is-hit">Sur l’écran d’accueil</span><span>Ajouter aux favoris</span></div><div class="ac-illus__share">' + ICON_SHARE + '</div></div></div>';
    if (kind === 'mac') return '<div class="ac-illus" aria-hidden="true"><div class="ac-illus__bar"><span class="ac-illus__url">Fichier ▸ <b>Ajouter au Dock…</b></span></div></div>';
    return '';
  }

  // --------------------------------------------------- application installée ?
  function installedFlag(app) { return lsGet('mxapp_installed_' + app) === '1'; }
  function checkInstalled(app, cb) {
    if (env.standalone || installedFlag(app)) { cb(true); return; }
    if (navigator.getInstalledRelatedApps) {
      navigator.getInstalledRelatedApps().then(function (list) {
        var hit = (list || []).some(function (a) { return a.platform === 'webapp' || a.platform === 'play'; });
        if (hit) lsSet('mxapp_installed_' + app, '1');
        cb(hit);
      }).catch(function () { cb(false); });
    } else cb(false);
  }

  /**
   * Installe l'application liée au manifeste de CETTE page.
   * cfg : { app, name, endpoint, startUrl, target ('pc'|'android'), note, apk: {url, version, size, date}|null, store, qr (html) }
   */
  function install(cfg) {
    track(cfg.endpoint, cfg.app, cfg.target === 'android' ? 'click_android' : 'click_pc');
    // Mauvais appareil pour ce bouton : guide (sans fenêtre native).
    var wrongDevice = (cfg.target === 'android' && env.os !== 'android') || (cfg.target === 'pc' && env.mobile);
    if (!wrongDevice && (env.standalone || installedFlag(cfg.app))) { alreadyInstalled(cfg); return; }
    if (!wrongDevice && deferred) {
      var ev = deferred; deferred = null;
      track(cfg.endpoint, cfg.app, 'prompt');
      open('Installation de ' + cfg.name, progress('Confirmez l’installation dans la fenêtre de votre navigateur…'));
      ev.prompt();
      ev.userChoice.then(function (r) {
        if (r && r.outcome === 'accepted') {
          track(cfg.endpoint, cfg.app, 'accepted');
          open('Installation de ' + cfg.name, progress('Installation en cours…'));
          var done = false;
          var finish = function () { if (done) return; done = true; lsSet('mxapp_installed_' + cfg.app, '1'); success(cfg); };
          window.addEventListener('appinstalled', finish, { once: true });
          setTimeout(finish, 6000);
        } else {
          track(cfg.endpoint, cfg.app, 'dismissed');
          showGuide(cfg, 'Installation annulée. Vous pourrez installer l’application plus tard :');
        }
      });
      return;
    }
    showGuide(cfg);
  }

  function showGuide(cfg, intro) {
    track(cfg.endpoint, cfg.app, 'guide');
    var g = guide(cfg.name, cfg.target, cfg.note);
    var html = '<p class="ac-env">Appareil détecté : <b>' + LABEL_OS[env.os] + '</b> · <b>' + LABEL_BR[env.browser] + '</b></p>'
      + (intro ? '<p>' + intro + '</p>' : '') + g.html + illustration(g.illus);
    if (g.illus === 'qr' && cfg.qr) { html += '<div class="ac-qr">' + cfg.qr + '</div>'; track(cfg.endpoint, cfg.app, 'qr'); }
    if (cfg.target === 'android' && (cfg.apk || cfg.store)) html += apkBlock(cfg);
    if (cfg.target === 'android' && !cfg.apk && !cfg.store) html += '<p class="ac-muted">Une version APK officielle et signée pourra être proposée ici ultérieurement.</p>';
    if (g.ok && env.chromium && !env.mobile && cfg.target === 'pc' && !intro) html += '<p class="ac-muted">Si l’icône n’apparaît pas, rechargez la page : le navigateur peut avoir besoin de quelques secondes, ou l’application est peut-être déjà installée.</p>';
    open((cfg.target === 'android' ? 'Installer sur Android — ' : 'Installer sur PC — ') + cfg.name, html);
    bindApk(cfg);
  }

  function apkBlock(cfg) {
    var h = '<div class="ac-apk"><h3>Autres façons d’installer sur Android</h3>';
    if (cfg.store) h += '<a class="ac-btn ac-btn--ghost" href="' + cfg.store + '" target="_blank" rel="noopener">Ouvrir dans Google Play</a>';
    if (cfg.apk) h += '<a class="ac-btn ac-btn--ghost" data-apk href="' + cfg.apk.url + '">Télécharger l’APK officiel (v' + cfg.apk.version + ', ' + cfg.apk.size + ')</a>'
      + '<p class="ac-muted">Fichier signé avec la clé officielle d’ARAILD, vérifiée par le serveur (empreinte ' + cfg.apk.signer + '). Android demandera d’autoriser l’installation depuis ce navigateur.</p>';
    return h + '</div>';
  }
  // Le téléchargement de l'APK est compté par le serveur (y compris un lien partagé), pas ici.
  function bindApk() {}

  function alreadyInstalled(cfg) {
    var body = open(cfg.name, '<div class="ac-ok"><span class="ac-ok__mark" aria-hidden="true">✓</span><p><b>Application déjà installée</b> sur cet appareil.</p></div>'
      + '<p>Retrouvez-la dans le menu Démarrer, le Dock ou l’écran d’accueil. Vous pouvez aussi l’ouvrir ici :</p>'
      + '<p><a class="ac-btn ac-btn--green" data-open href="' + cfg.startUrl + '">Ouvrir ' + cfg.name + '</a></p>'
      + '<p class="ac-muted">Ce n’est pas le cas ? <button type="button" class="ac-link" data-reset>Afficher quand même les instructions</button></p>');
    body.querySelector('[data-open]').addEventListener('click', function () { track(cfg.endpoint, cfg.app, 'open'); });
    body.querySelector('[data-reset]').addEventListener('click', function () { lsSet('mxapp_installed_' + cfg.app, ''); showGuide(cfg); });
  }

  function success(cfg) {
    var body = open(cfg.name, '<div class="ac-ok"><span class="ac-ok__mark" aria-hidden="true">✓</span><p><b>Installation terminée.</b></p></div>'
      + '<p>' + cfg.name + ' est maintenant disponible ' + (env.mobile ? 'sur votre écran d’accueil.' : 'dans le menu Démarrer, le Dock ou la liste des applications.') + '</p>'
      + '<p><a class="ac-btn ac-btn--green" data-open href="' + cfg.startUrl + '">Ouvrir maintenant</a></p>');
    body.querySelector('[data-open]').addEventListener('click', function () { track(cfg.endpoint, cfg.app, 'open'); });
  }

  // L'événement appinstalled peut aussi venir de l'icône de la barre d'adresse.
  // C'est la SEULE source d'une « installation confirmée par le navigateur » (jamais un simple clic).
  window.addEventListener('appinstalled', function () {
    var root = document.documentElement, app = root.getAttribute('data-app') || '';
    if (app) { lsSet('mxapp_installed_' + app, '1'); track(root.getAttribute('data-app-endpoint'), app, 'installed'); }
  });

  // Enregistre le service worker de l'application liée à la page (contexte sécurisé uniquement).
  function registerWorker(url) {
    if ('serviceWorker' in navigator && window.isSecureContext) navigator.serviceWorker.register(url).catch(function () {});
  }

  window.MXApp = { env: env, detect: detect, labels: { os: LABEL_OS, browser: LABEL_BR }, track: track, launch: launch, install: install,
    guide: showGuide, open: open, close: close, checkInstalled: checkInstalled, onPromptReady: onPromptReady, registerWorker: registerWorker,
    hasPrompt: function () { return !!deferred; } };
})();
