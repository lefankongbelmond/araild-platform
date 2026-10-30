// Fond avec cadre de téléphone (fenêtre transparente) + cartes d'ouverture et de fin, en PNG 1080×1920.
const { chromium } = require('playwright'); const fs = require('fs');
const BR = '/home/user/araild-platform/production/brand/'; const OUT = __dirname + '/';
const logo = 'data:image/png;base64,' + fs.readFileSync(BR + 'mx-mark.png').toString('base64');
const S = { x: 240, y: 250, w: 600, h: 1298, r: 34 };
const bg = `background:radial-gradient(circle at 85% 8%, rgba(46,158,79,.35), transparent 40%), radial-gradient(circle at 10% 92%, rgba(47,111,219,.35), transparent 45%), linear-gradient(160deg,#071a40,#0B2559 50%,#123A7A);`;
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1080, height: 1920 } });
  // Cadre : fond complet, fenêtre arrondie découpée, bord de téléphone
  await p.setContent(`<html><body style="margin:0;background:transparent"><canvas id=c width=1080 height=1920></canvas>
    <div id=h style="position:absolute;inset:0;${bg}"></div></body></html>`);
  const b64 = await p.evaluate(async ([S, logo]) => {
    const c = document.getElementById('c'), x = c.getContext('2d');
    const g = x.createLinearGradient(0, 0, 1080, 1920); g.addColorStop(0, '#071a40'); g.addColorStop(.5, '#0B2559'); g.addColorStop(1, '#123A7A'); x.fillStyle = g; x.fillRect(0, 0, 1080, 1920);
    const glow = (cx, cy, r, col) => { const rg = x.createRadialGradient(cx, cy, 0, cx, cy, r); rg.addColorStop(0, col); rg.addColorStop(1, 'rgba(0,0,0,0)'); x.fillStyle = rg; x.fillRect(0, 0, 1080, 1920); };
    glow(920, 150, 520, 'rgba(46,158,79,.30)'); glow(100, 1780, 600, 'rgba(47,111,219,.30)');
    const rr = (X, Y, W, H, R) => { x.beginPath(); x.moveTo(X + R, Y); x.arcTo(X + W, Y, X + W, Y + H, R); x.arcTo(X + W, Y + H, X, Y + H, R); x.arcTo(X, Y + H, X, Y, R); x.arcTo(X, Y, X + W, Y, R); x.closePath(); };
    // ombre + coque du téléphone
    x.save(); x.shadowColor = 'rgba(0,0,0,.45)'; x.shadowBlur = 60; x.shadowOffsetY = 20; rr(S.x - 18, S.y - 18, S.w + 36, S.h + 36, S.r + 18); x.fillStyle = '#0a0f1e'; x.fill(); x.restore();
    rr(S.x - 18, S.y - 18, S.w + 36, S.h + 36, S.r + 18); x.strokeStyle = 'rgba(255,255,255,.18)'; x.lineWidth = 3; x.stroke();
    // fenêtre transparente
    x.globalCompositeOperation = 'destination-out'; x.fillStyle = '#000'; rr(S.x, S.y, S.w, S.h, S.r); x.fill(); x.globalCompositeOperation = 'source-over';
    // logo + nom en haut
    const img = new Image(); img.src = logo; await img.decode(); x.drawImage(img, 330, 58, 86, 86);
    x.font = '900 60px Inter'; x.fillStyle = '#fff'; x.textBaseline = 'middle'; x.fillText('M', 430, 103); let w = x.measureText('M').width;
    x.fillStyle = '#4FCB76'; x.fillText('X', 430 + w, 103); w += x.measureText('X').width; x.fillStyle = '#fff'; x.fillText('-CONNECT', 430 + w, 103);
    return c.toDataURL('image/png').split(',')[1];
  }, [S, logo]);
  fs.writeFileSync(OUT + 'frame.png', Buffer.from(b64, 'base64'));
  // Cartes
  const card = async (file, inner) => { await p.setContent(`<html><head><style>body{margin:0;width:1080px;height:1920px;${bg};font-family:Inter;color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding-bottom:260px;box-sizing:border-box}
    .logo{width:300px;height:300px;border-radius:50%;background:#fff;display:grid;place-items:center;box-shadow:0 0 0 22px rgba(255,255,255,.08),0 0 0 48px rgba(255,255,255,.05),0 30px 80px rgba(0,0,0,.4)}
    .logo img{width:240px}.w{font-size:96px;font-weight:900;margin:90px 0 10px;letter-spacing:1px}.w b{color:#4FCB76}.t{font-size:44px;font-weight:600;color:#9FE3B4}
    .s{font-size:48px;font-weight:700;margin-top:110px;line-height:1.3}.u{display:inline-block;margin-top:40px;font-size:42px;font-weight:700;background:#2E9E4F;padding:22px 44px;border-radius:999px}
    .f{position:absolute;bottom:80px;font-size:30px;color:rgba(255,255,255,.7)}</style></head><body>${inner}</body></html>`); await p.screenshot({ path: OUT + file }); };
  await card('intro.png', `<div class=logo><img src="${logo}"></div><div class=w>M<b>X</b>-CONNECT</div><div class=t>Connecter · Collaborer · Réussir</div>
    <div class=s>Découvrez l’application<br>en quelques minutes</div>`);
  await card('outro.png', `<div class=logo><img src="${logo}"></div><div class=w>M<b>X</b>-CONNECT</div><div class=t>Connecter · Collaborer · Réussir</div>
    <div class=s>Rejoignez le réseau<br>et invitez vos amis !</div><div class=u>m.araild.com</div>`);
  await b.close(); console.log('ok');
})();
