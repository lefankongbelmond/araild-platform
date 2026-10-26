{
  "name": "{{ tenant('name') }} — MX-CONNECT",
  "short_name": "MX-CONNECT",
  "start_url": "/espace-membre",
  "display": "standalone",
  "background_color": "#f7f5f0",
  "theme_color": "#12433d",
  "icons": [
    { "src": "{{ rtrim(config('app.url'), '/') }}/pwa/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "{{ rtrim(config('app.url'), '/') }}/pwa/icon-512.png", "sizes": "512x512", "type": "image/png" }
  ]
}
