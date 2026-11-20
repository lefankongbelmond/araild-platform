// Rend la fiche (fiche-v2.html) en PDF A4 : node render.js sortie.pdf [fichier.html], contrôle les débordements et produit des aperçus PNG.
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const dir = __dirname;
const out = process.argv[2];
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 794, height: 1123 }, deviceScaleFactor: 1 });
  await page.goto('file://' + path.join(dir, process.argv[3] || 'fiche-v2.html'));
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(400);
  // Contrôle : débordement de la page, éléments sortant du cadre, recouvrement du pied de page.
  const report = await page.evaluate(() => {
    const res = [];
    document.querySelectorAll('.page').forEach((p, i) => {
      const pr = p.getBoundingClientRect();
      const foot = p.querySelector('.foot');
      const fr = foot ? foot.getBoundingClientRect() : null;
      let maxBottom = 0;
      const offenders = [];
      p.querySelectorAll('*').forEach(el => {
        if (el.closest('.foot') || el.closest('.run') || el.closest('svg')) return;
        if (getComputedStyle(el).position === 'absolute') return;
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) return;
        maxBottom = Math.max(maxBottom, r.bottom);
        if (r.right > pr.right - 2 || r.left < pr.left + 2) offenders.push('H:' + el.tagName + '.' + el.className + ' ' + (el.textContent || '').slice(0, 30));
        if (el.scrollWidth > el.clientWidth + 1 && getComputedStyle(el).overflow !== 'visible') offenders.push('SW:' + el.tagName + '.' + el.className);
      });
      const limit = fr ? fr.top - 6 : pr.bottom;
      res.push({ page: i + 1, spare: Math.round(limit - maxBottom), offenders: offenders.slice(0, 4) });
    });
    return res;
  });
  for (const r of report) console.log(`p${String(r.page).padStart(2, '0')} marge restante ${r.spare}px ${r.spare < 0 ? '⚠ DÉBORDE' : ''} ${r.offenders.join(' | ')}`);
  // Aperçus PNG
  fs.mkdirSync(path.join(dir, 'png'), { recursive: true });
  const pages = await page.$$('.page');
  for (let i = 0; i < pages.length; i++) await pages[i].screenshot({ path: path.join(dir, 'png', `p${String(i + 1).padStart(2, '0')}.png`) });
  await page.emulateMedia({ media: 'print' });
  await page.pdf({ path: out, format: 'A4', printBackground: true, preferCSSPageSize: true });
  await browser.close();
  console.log('PDF :', out);
})();
