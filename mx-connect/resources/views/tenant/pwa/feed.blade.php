<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ __('mxconnect.feed.title') }}</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{ink:'#0f2e2b',pine:'#12433d',sage:'#5b8f83',clay:'#c86f4a',mist:'#eef3f1',paper:'#f7f5f0'}}}}</script>
</head>
<body class="bg-paper text-ink">
  <header class="sticky top-0 border-b border-ink/10 bg-white/90 backdrop-blur">
    <div class="mx-auto max-w-2xl px-4 h-14 flex items-center gap-2">
      <a href="{{ route('pwa.shell') }}" class="text-pine">←</a>
      <span class="font-semibold">{{ __('mxconnect.feed.title') }}</span>
      <span id="live" class="ml-auto text-xs text-ink/40">●&nbsp;{{ __('mxconnect.feed.connecting') }}</span>
    </div>
  </header>

  <main class="mx-auto max-w-2xl px-4 py-5">
    {{-- Composer --}}
    <div class="rounded-xl border border-ink/10 bg-white p-4">
      <textarea id="body" rows="3" maxlength="2000" placeholder="{{ __('mxconnect.feed.placeholder') }}"
                class="w-full resize-none rounded-md border-ink/20 text-sm"></textarea>
      <div class="mt-2 flex justify-end">
        <button id="publish" class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-ink transition">{{ __('mxconnect.feed.publish') }}</button>
      </div>
    </div>

    <div id="empty" class="mt-8 hidden text-center text-sm text-ink/40">{{ __('mxconnect.feed.empty') }}</div>
    <ul id="posts" class="mt-5 space-y-3"></ul>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
  <script>
    const API = '/api/member';
    const token = localStorage.getItem('ml_token');
    const tenantId = @json($tenantId);
    const reverb = @json($reverb);
    if (!token) { location.href = '{{ route('pwa.shell') }}'; }

    const esc = s => (s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const postsEl = document.getElementById('posts');
    const emptyEl = document.getElementById('empty');

    function card(p) {
      const li = document.createElement('li');
      li.className = 'rounded-xl border border-ink/10 bg-white p-4';
      li.dataset.id = p.id;
      li.innerHTML = `
        <div class="flex items-center gap-2">
          <div class="h-8 w-8 rounded-full bg-sage/25 grid place-items-center text-xs font-semibold text-pine">${esc((p.author||'?').slice(0,1))}</div>
          <div class="text-sm font-medium">${esc(p.author)}</div>
        </div>
        <p class="mt-2 whitespace-pre-line text-sm">${esc(p.body)}</p>
        <div class="mt-3 flex items-center gap-4 text-sm text-ink/50">
          <button class="like hover:text-clay ${p.liked?'text-clay':''}">♥ <span class="lc">${p.likes_count||0}</span></button>
          <span>💬 ${p.comments_count||0}</span>
        </div>`;
      li.querySelector('.like').addEventListener('click', () => like(p.id, li));
      return li;
    }

    async function like(id, li) {
      const r = await fetch(`${API}/feed/${id}/like`, {method:'POST', headers:{'Authorization':'Bearer '+token,'Accept':'application/json'}});
      if (r.ok) { const d = await r.json(); li.querySelector('.lc').textContent = d.likes_count; li.querySelector('.like').classList.toggle('text-clay', d.liked); }
    }

    async function load() {
      const r = await fetch(`${API}/feed`, {headers:{'Authorization':'Bearer '+token,'Accept':'application/json'}});
      if (!r.ok) return;
      const d = await r.json();
      postsEl.innerHTML = '';
      (d.posts||[]).forEach(p => postsEl.appendChild(card(p)));
      emptyEl.classList.toggle('hidden', (d.posts||[]).length > 0);
    }

    document.getElementById('publish').addEventListener('click', async () => {
      const body = document.getElementById('body').value.trim();
      if (!body) return;
      const r = await fetch(`${API}/feed`, {method:'POST', headers:{'Authorization':'Bearer '+token,'Content-Type':'application/json','Accept':'application/json'}, body: JSON.stringify({body})});
      if (r.ok) { document.getElementById('body').value = ''; }  // the broadcast will prepend it
    });

    // Real-time via Reverb.
    try {
      window.Pusher = Pusher;
      const echo = new Echo({
        broadcaster: 'reverb',
        key: reverb.key,
        wsHost: reverb.host, wsPort: reverb.port, wssPort: reverb.port,
        forceTLS: reverb.scheme === 'https',
        enabledTransports: ['ws','wss'],
        authEndpoint: `${API}/broadcasting/auth`,
        auth: { headers: { Authorization: 'Bearer ' + token } },
      });
      const liveEl = document.getElementById('live');
      echo.connector.pusher.connection.bind('connected', () => { liveEl.textContent = '● {{ __('mxconnect.feed.live') }}'; liveEl.className = 'ml-auto text-xs text-sage'; });
      echo.connector.pusher.connection.bind('unavailable', () => { liveEl.textContent = '● {{ __('mxconnect.feed.offline') }}'; liveEl.className = 'ml-auto text-xs text-clay'; });
      echo.private(`tenant.${tenantId}.feed`).listen('.feed.post.created', (e) => {
        if (postsEl.querySelector(`[data-id="${e.id}"]`)) return;
        emptyEl.classList.add('hidden');
        postsEl.prepend(card({id:e.id, author:e.author, body:e.body, likes_count:0, comments_count:0, liked:false}));
      });
    } catch (err) { console.warn('Realtime unavailable', err); }

    load();
  </script>
</body>
</html>
