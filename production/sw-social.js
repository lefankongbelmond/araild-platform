/* MX Connect — service worker du réseau social.
   Ne met en cache QUE des fichiers publics et statiques (logo, styles, scripts, polices)
   et une page hors ligne. Aucune page personnelle, aucune réponse d'API n'est stockée. */
const VERSION = 'mx-social-2026.11.09';   // à changer à chaque livraison : purge les anciens caches
const STATIC = ['/hors-ligne.html', '/css/mx-social.css', '/js/mx-social.js', '/js/mx-social-plus.js', '/brand/mx-logo-full.webp', '/brand/mx-mark-128.webp',
                '/brand/favicon.png', '/brand/icon-192.png', '/fonts/inter-latin.woff2'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(STATIC)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
    .then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  // Pages : toujours le réseau ; page hors ligne seulement en cas d'échec de connexion.
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('/hors-ligne.html')));
    return;
  }
  // Fichiers statiques publics : cache d'abord. La clé garde le ?v= : une nouvelle version
  // du CSS/JS (asset_version) est donc téléchargée au lieu de resservir l'ancienne.
  if (/^\/(css|js|brand|fonts)\//.test(url.pathname)) {
    e.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      if (res.ok) { const copy = res.clone(); caches.open(VERSION).then((c) => c.put(req, copy)); }
      return res;
    })));
  }
});
