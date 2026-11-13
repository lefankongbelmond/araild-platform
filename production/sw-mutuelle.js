/* MX-CONNECT MUTUELLE — service worker de l'espace de gestion d'une mutuelle (2026-11-13).
   Servi à la racine du sous-domaine de chaque mutuelle (portée « / »).
   Ne met en cache QUE des fichiers publics et statiques (styles, polices, logo, icônes) et la
   page hors ligne. AUCUNE page de gestion, aucune réponse contenant des données d'adhérents,
   de cotisations ou de prestations n'est stockée sur l'appareil. */
const VERSION = 'mx-mutuelle-2026.11.13';   // à changer à chaque livraison : purge les anciens caches
const STATIC = ['/hors-ligne-mutuelle.html', '/css/mx-apps.css', '/brand/mx-mark-128.webp', '/brand/favicon.png',
                '/brand/icon-192.png', '/fonts/inter-latin.woff2'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(STATIC)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k.startsWith('mx-mutuelle-') && k !== VERSION).map((k) => caches.delete(k))))
    .then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  // Pages : toujours le réseau (données à jour, droits vérifiés à chaque requête) ;
  // page hors ligne seulement si la connexion échoue.
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('/hors-ligne-mutuelle.html')));
    return;
  }
  // Fichiers statiques publics : cache d'abord (la clé garde le ?v= de la version).
  if (/^\/(css|brand|fonts)\//.test(url.pathname)) {
    e.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      if (res.ok) { const copy = res.clone(); caches.open(VERSION).then((c) => c.put(req, copy)); }
      return res;
    })));
  }
});
