<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#12433d">
  <link rel="manifest" href="/espace-membre/manifest.json">
  <title>{{ tenant('name') }} — {{ __('mxconnect.pwa.title') }}</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{ink:'#0f2e2b',pine:'#12433d',sage:'#5b8f83',clay:'#c86f4a',mist:'#eef3f1',paper:'#f7f5f0'}}}}</script>
</head>
<body class="bg-paper text-ink">
  <div id="app" class="mx-auto max-w-md px-4 py-8"></div>

  <script>
    const API = '/api/member';
    const $ = (id) => document.getElementById(id);
    let token = localStorage.getItem('ml_token');

    async function api(path, opts = {}) {
      const res = await fetch(API + path, {
        ...opts,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                   ...(token ? { 'Authorization': 'Bearer ' + token } : {}), ...(opts.headers || {}) },
      });
      if (res.status === 401) { logout(); throw new Error('unauthorized'); }
      return res.json();
    }

    function loginView() {
      $('app').innerHTML = `
        <div class="text-center mb-6"><div class="inline-block h-10 w-10 rounded bg-pine"></div>
          <h1 class="mt-2 text-xl font-semibold">{{ tenant('name') }}</h1></div>
        <div class="rounded-xl border border-ink/10 bg-white p-5 space-y-3">
          <input id="phone" placeholder="{{ __('mxconnect.member.phone') }}" class="w-full rounded-md border-ink/20 px-3 py-2">
          <input id="pw" type="password" placeholder="{{ __('mxconnect.auth.password') }}" class="w-full rounded-md border-ink/20 px-3 py-2">
          <p id="err" class="text-sm text-clay"></p>
          <button onclick="doLogin()" class="w-full rounded-md bg-pine px-4 py-2 font-medium text-white">{{ __('mxconnect.auth.login') }}</button>
        </div>`;
    }

    async function doLogin() {
      $('err').textContent = '';
      try {
        const r = await api('/login', { method: 'POST', body: JSON.stringify({ phone: $('phone').value, password: $('pw').value }) });
        if (r.token) { token = r.token; localStorage.setItem('ml_token', token); home(); }
        else { $('err').textContent = (r.errors?.phone?.[0]) || r.message || 'Erreur'; }
      } catch (e) { $('err').textContent = 'Erreur'; }
    }

    function logout() { token = null; localStorage.removeItem('ml_token'); loginView(); }

    async function home() {
      const [me, sched] = await Promise.all([api('/me'), api('/schedules')]);
      const rows = (sched.schedules || []).map(s => `
        <tr class="border-t border-ink/5">
          <td class="py-2">${s.period}</td><td class="py-2">${s.amount}</td>
          <td class="py-2 text-${s.status==='paid'?'sage':(s.status==='overdue'?'clay':'ink/50')}">${s.status}</td>
        </tr>`).join('');
      $('app').innerHTML = `
        <div class="flex items-center justify-between mb-4">
          <div><div class="text-lg font-semibold">${me.first_name} ${me.last_name}</div>
            <div class="text-xs text-ink/50 font-mono">${me.code}</div></div>
          <button onclick="logout()" class="text-sm text-clay">{{ __('mxconnect.auth.logout') }}</button>
        </div>
        <div class="rounded-xl border border-ink/10 bg-white p-4">
          <h2 class="font-medium mb-2">{{ __('mxconnect.subscription.schedule') }}</h2>
          <table class="w-full text-sm"><tbody>${rows || '<tr><td class="py-3 text-ink/40">—</td></tr>'}</tbody></table>
        </div>`;
    }

    if ('serviceWorker' in navigator) navigator.serviceWorker.register('/pwa/sw.js').catch(()=>{});
    token ? home().catch(loginView) : loginView();
  </script>
</body>
</html>
