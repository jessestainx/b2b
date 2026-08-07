const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1574, height: 906 } });
  await page.goto(
    'https://awamotos.com/bauleto-awa-modelo-proos-34-litros-dourado-340-dr.html?t=' + Date.now(),
    { waitUntil: 'networkidle', timeout: 90000 }
  );
  await page.waitForTimeout(3000);

  const pre = await page.evaluate(() => ({
    jsUrl: Array.from(document.scripts).find((x) => x.src && x.src.includes('awa-pdp-gallery-sync'))?.src,
    hasJq: !!window.jQuery
  }));
  console.log('pre', pre);

  const ok = await page.evaluate(() => {
    const gp = document.querySelector('[data-gallery-role=gallery-placeholder]');
    const item = document.querySelector('.fotorama-item');
    const el = gp || item;
    const f = el && window.jQuery && window.jQuery(el).data('fotorama');
    if (!f) {
      return 'no-fotorama';
    }
    if (typeof f.requestFullScreen === 'function') {
      f.requestFullScreen();
      return 'requestFullScreen';
    }
    if (typeof f.fullScreen === 'function') {
      f.fullScreen();
      return 'fullScreen';
    }
    return 'keys:' + Object.keys(f).slice(0, 20).join(',');
  });
  console.log('fs trigger', ok);

  await page.waitForTimeout(2500);

  const m = await page.evaluate(() => {
    const fs = document.querySelector('.fotorama-item.fotorama--fullscreen');
    const frame =
      fs?.querySelector('.fotorama__stage__frame.fotorama__active') ||
      fs?.querySelector('.fotorama__stage__frame');
    const img = frame?.querySelector('img.fotorama__img--full, img.fotorama__img');
    const fr = frame?.getBoundingClientRect();
    const ir = img?.getBoundingClientRect();
    return {
      bodyFs: document.body.classList.contains('fotorama__fullscreen'),
      hasFsItem: !!fs,
      jsUrl: Array.from(document.scripts).find((x) => x.src && x.src.includes('awa-pdp-gallery-sync'))?.src,
      frameW: fr ? Math.round(fr.width) : null,
      imgW: ir ? Math.round(ir.width) : null,
      widthDelta: fr && ir ? Math.abs(Math.round(fr.width - ir.width)) : null,
      frameInlineW: frame?.style.width || null,
      stageInlineW: fs?.querySelector('.fotorama__stage')?.style.width || null,
      stageW: fs?.querySelector('.fotorama__stage')?.offsetWidth,
      wrapW: fs?.querySelector('.fotorama__wrap')?.offsetWidth
    };
  });
  console.log(JSON.stringify(m, null, 2));
  await browser.close();
})();
