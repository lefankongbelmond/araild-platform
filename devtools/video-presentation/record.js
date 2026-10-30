// Enregistrement de la démonstration (vraie application, copie fidèle du site). Note le début de chaque scène.
const { chromium } = require('playwright'); const fs = require('fs');
const B = 'http://m.araild.test:8080'; const DIR = __dirname; const d = JSON.parse(fs.readFileSync(DIR + '/../nav/data.json'));
const EMAIL = process.argv[2] || 'aminata.fouda@exemple.cm';
(async () => {
  const b = await chromium.launch({ args: ['--host-resolver-rules=MAP m.araild.test 127.0.0.1, MAP *.m.araild.test 127.0.0.1'] });
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'fr-FR' });
  // Indicateur de toucher (cercle) visible dans la vidéo
  await ctx.addInitScript(() => { addEventListener('pointerdown', (e) => { const c = document.createElement('div');
    c.style.cssText = `position:fixed;left:${e.clientX - 22}px;top:${e.clientY - 22}px;width:44px;height:44px;border-radius:50%;background:rgba(46,158,79,.35);border:2px solid rgba(46,158,79,.8);z-index:99999;pointer-events:none;transition:transform .45s,opacity .45s`;
    document.documentElement.appendChild(c); requestAnimationFrame(() => { c.style.transform = 'scale(1.6)'; c.style.opacity = '0'; }); setTimeout(() => c.remove(), 600); }, true); });
  const p = await ctx.newPage(); const t0 = Date.now(); const marks = [];
  // Capture haute définition (pixels réels de l'écran, ×2) : images JPEG horodatées
  fs.rmSync(DIR + '/shots', { recursive: true, force: true }); fs.mkdirSync(DIR + '/shots'); const frames = [];
  const cdp = await ctx.newCDPSession(p);
  cdp.on('Page.screencastFrame', async (f) => { const n = frames.length; fs.writeFileSync(`${DIR}/shots/${String(n).padStart(6, '0')}.jpg`, Buffer.from(f.data, 'base64'));
    frames.push((f.metadata.timestamp * 1000 - t0) / 1000); cdp.send('Page.screencastFrameAck', { sessionId: f.sessionId }).catch(() => {}); });
  await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 88, maxWidth: 780, maxHeight: 1688, everyNthFrame: 1 });
  const mark = (k) => { marks.push([k, (Date.now() - t0) / 1000]); console.log(k, ((Date.now() - t0) / 1000).toFixed(1)); };
  const W = (ms) => p.waitForTimeout(ms);
  const tap = async (sel, ms = 900) => { await p.locator(sel).first().scrollIntoViewIfNeeded(); await W(250); await p.locator(sel).first().click(); await W(ms); };
  const scroll = async (dy, steps = 6, pause = 180) => { for (let i = 0; i < steps; i++) { await p.mouse.wheel(0, dy / steps); await W(pause); } };
  const typ = async (sel, text) => { await p.locator(sel).first().click(); await p.locator(sel).first().pressSequentially(text, { delay: 55 }); await W(300); };

  await p.goto('about:blank'); await W(400);
  mark('ouvrir'); await p.goto(B + '/app?source=android&v=1', { waitUntil: 'domcontentloaded' }); await p.waitForURL(/bienvenue/, { timeout: 8000 }); await W(800);
  mark('bienvenue'); await W(2200); await tap('text=Suivant', 2300); await tap('text=Suivant', 2300); await tap('text=Commencer', 1200);
  mark('inscription'); await W(1200); await tap('text=Créer un compte', 1200);
  await typ('[name=last_name]', 'Fouda'); await typ('[name=first_name]', 'Aminata'); await typ('[name=email]', EMAIL);
  await typ('[name=password]', 'Mxconnect2026'); await tap('[data-eye]', 900); await tap('input[name^=doc_]', 900);
  await Promise.all([p.waitForNavigation(), tap('form .mx-abtn', 0)]); await W(600);
  mark('conditions'); await W(2600); for (const c of await p.$$('input[name^=doc_]')) { await c.click(); await W(700); }
  await W(800); await Promise.all([p.waitForNavigation(), tap('form .mx-abtn', 0)]);
  mark('pret'); await W(3800); await Promise.all([p.waitForNavigation(), tap('text=Commencer maintenant', 0)]); await p.waitForLoadState('networkidle');
  mark('accueil'); await W(2500); await scroll(1600, 10, 260); await W(1500); await scroll(-500, 4, 200); await W(1800); await scroll(-2000, 4, 120); await W(1200);
  mark('publier'); await tap('.mx-tabbar__plus', 1200); await typ('#composer-body', 'Bonjour à toute la communauté MX-CONNECT ! Heureuse de vous rejoindre.'); await W(800);
  await tap('[data-publish]', 2500);
  mark('decouvrir'); await tap('.mx-tabbar >> text=Découvrir', 1500); await p.waitForLoadState('networkidle'); await W(1200);
  await tap('.mx-dfilters > summary', 1500); await tap('.mx-dfilters > summary', 800); await tap('.mx-underline-tabs >> text=Projets', 1500); await scroll(500, 5); await W(1500);
  mark('organisation'); await p.goto(B + '/mutuelles/' + d.page, { waitUntil: 'networkidle' }); await W(1800); await tap('[data-follow]', 1800); await scroll(450, 5); await W(1500);
  mark('projet'); await p.goto(B + '/projets/' + d.project, { waitUntil: 'networkidle' }); await W(2200); await scroll(500, 5); await W(800);
  await tap('.mx-ptabs >> text=Résultats', 2600); await scroll(300, 3); await W(1200);
  mark('engagement'); await p.goto(B + '/projets/' + d.project, { waitUntil: 'networkidle' }); await W(900); await tap('.mx-pbar >> text=Je m’engage', 1200); await p.waitForLoadState('networkidle');
  await scroll(250, 3); await tap('.mx-rcard >> nth=0', 1000); await typ('#rq-av', 'Le samedi matin'); await scroll(250, 3); await W(1800);
  mark('soutien'); await p.goto(B + '/projets/' + d.project, { waitUntil: 'networkidle' }); await W(700); await tap('.mx-pbar >> text=Soutenir', 1200); await p.waitForLoadState('networkidle');
  await scroll(250, 3); await tap('.mx-rcard >> nth=1', 1200); await scroll(350, 4); await W(2200);
  mark('messages'); await tap('.mx-tabbar >> text=Messages', 2200); await p.waitForLoadState('networkidle'); await W(1500);
  await p.goto(B + '/notifications', { waitUntil: 'networkidle' }); await W(2600);
  mark('profil'); await tap('.mx-tabbar >> text=Profil', 1500); await p.waitForLoadState('networkidle'); await W(1500); await scroll(500, 5, 220); await W(1500); await scroll(-500, 3); await W(800);
  mark('inviter'); await tap('main >> text=Inviter un ami', 1500); await p.waitForLoadState('networkidle'); await W(3500); await scroll(300, 3); await W(2000);
  mark('aide'); await p.goto(B + '/aide', { waitUntil: 'networkidle' }); await W(1200); await tap('main summary >> nth=0', 2500); await W(800);
  mark('fin'); await W(500);
  const end = (Date.now() - t0) / 1000; await cdp.send('Page.stopScreencast'); await W(300); await ctx.close(); await b.close();
  // Vidéo à cadence variable depuis les images (chaque image dure jusqu'à la suivante)
  let list = ''; for (let i = 0; i < frames.length; i++) { const dur = (i + 1 < frames.length ? frames[i + 1] : end) - frames[i];
    list += `file 'shots/${String(i).padStart(6, '0')}.jpg'\nduration ${Math.max(dur, 0.001).toFixed(3)}\n`; }
  list += `file 'shots/${String(frames.length - 1).padStart(6, '0')}.jpg'\n`; fs.writeFileSync(DIR + '/frames.txt', list);
  require('child_process').execFileSync('ffmpeg', ['-v', 'error', '-y', '-f', 'concat', '-safe', '0', '-i', DIR + '/frames.txt',
    '-vf', 'fps=30,scale=780:1688:force_original_aspect_ratio=decrease,pad=780:1688:(ow-iw)/2:0:white,format=yuv420p', '-c:v', 'libx264', '-crf', '16', '-preset', 'medium', DIR + '/raw.mp4']);
  fs.writeFileSync(DIR + '/marks.json', JSON.stringify({ video: DIR + '/raw.mp4', marks, end, first: frames[0], frames: frames.length }, null, 1));
})().catch(e => { console.error('ERREUR', e.message); process.exit(1); });
