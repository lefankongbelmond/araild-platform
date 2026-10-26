/*
 * MX-CONNECT — lecteur du QR code de la carte de membre (portail des prestataires).
 * Deux façons de lire la carte : la caméra (si le navigateur le permet) ou une photo.
 * L'image est lue dans le navigateur ; seul le contenu du QR (lien signé de la carte)
 * est placé dans le formulaire de vérification, puis envoyé comme une saisie manuelle.
 * Décodage : BarcodeDetector natif quand il existe, sinon jsQR (servi en local).
 */
(function () {
  'use strict';
  var root = document.querySelector('[data-mx-scan]');
  if (!root) return;
  var input = document.getElementById(root.getAttribute('data-target'));
  var form = input && input.form;
  if (!form) return;
  var status = root.querySelector('[data-scan-status]');
  var camBtn = root.querySelector('[data-scan-camera]');
  var fileIn = root.querySelector('[data-scan-file]');
  var box = root.querySelector('[data-scan-box]');
  var video = root.querySelector('video');
  var stopBtn = root.querySelector('[data-scan-stop]');
  var canvas = document.createElement('canvas');
  var ctx = canvas.getContext('2d', { willReadFrequently: true });
  var stream = null, timer = null, detector = null, done = false;

  function say(text) { status.textContent = text; }
  function isCard(text) { return /[?&]t=/.test(text) || text.split('.').length === 3; }

  function found(text) {
    if (done) return;
    if (!isCard(text)) { say('Ce QR code n’est pas une carte de membre MX-CONNECT.'); return; }
    done = true;
    stop();
    input.value = text.trim();
    say('Carte lue : vérification des droits…');
    if (form.requestSubmit) form.requestSubmit(); else form.submit();
  }

  // Image (vidéo, photo) → texte du QR, ou null.
  function decode(source, w, h) {
    if (detector) {
      return detector.detect(source).then(function (codes) { return codes.length ? codes[0].rawValue : null; }).catch(function () { return jsqr(source, w, h); });
    }
    return Promise.resolve(jsqr(source, w, h));
  }
  function jsqr(source, w, h) {
    if (!window.jsQR || !w || !h) return null;
    var scale = Math.min(1, 1024 / Math.max(w, h));
    canvas.width = Math.round(w * scale); canvas.height = Math.round(h * scale);
    ctx.drawImage(source, 0, 0, canvas.width, canvas.height);
    var img = ctx.getImageData(0, 0, canvas.width, canvas.height);
    var r = window.jsQR(img.data, img.width, img.height, { inversionAttempts: 'attemptBoth' });
    return r ? r.data : null;
  }

  if ('BarcodeDetector' in window && window.BarcodeDetector.getSupportedFormats) {
    window.BarcodeDetector.getSupportedFormats().then(function (f) {
      if (f.indexOf('qr_code') >= 0) detector = new window.BarcodeDetector({ formats: ['qr_code'] });
    }).catch(function () {});
  }

  // --- Caméra (contexte sécurisé HTTPS uniquement) ---
  function stop() {
    if (timer) { clearTimeout(timer); timer = null; }
    if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
    box.style.display = 'none';
  }
  function tick() {
    if (!stream) return;
    decode(video, video.videoWidth, video.videoHeight).then(function (text) {
      if (text) found(text);
      if (stream) timer = setTimeout(tick, 250);
    });
  }
  if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.isSecureContext) {
    camBtn.style.display = '';
    camBtn.addEventListener('click', function () {
      done = false;
      say('Ouverture de la caméra…');
      navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false }).then(function (s) {
        stream = s; video.srcObject = s; box.style.display = '';
        video.play().catch(function () {});
        say('Placez le QR code de la carte dans le cadre.');
        tick();
      }).catch(function (e) {
        say(e && e.name === 'NotAllowedError'
          ? 'Accès à la caméra refusé : autorisez-le dans le navigateur, ou utilisez « Photo de la carte ».'
          : 'Caméra indisponible : utilisez « Photo de la carte » ou saisissez le code adhérent.');
      });
    });
    stopBtn.addEventListener('click', function () { stop(); say(''); });
  }

  // --- Photo de la carte (tous les téléphones) ---
  fileIn.addEventListener('change', function () {
    var file = fileIn.files && fileIn.files[0];
    if (!file) return;
    done = false;
    say('Lecture de la photo…');
    var url = URL.createObjectURL(file), img = new Image();
    img.onload = function () {
      decode(img, img.naturalWidth, img.naturalHeight).then(function (text) {
        URL.revokeObjectURL(url);
        if (text) found(text);
        else say('QR code non lu : reprenez la photo plus près et bien éclairée, ou saisissez le code adhérent.');
      });
    };
    img.onerror = function () { URL.revokeObjectURL(url); say('Image illisible : reprenez la photo.'); };
    img.src = url;
    fileIn.value = '';
  });
})();
