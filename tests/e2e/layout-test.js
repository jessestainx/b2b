const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
  
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForTimeout(3000);

  const html = await page.evaluate(() => {
     const hero = document.querySelector('.awa-hero');
     const categories = document.querySelector('.top-home-content--category-carousel');
     const r = hero.getBoundingClientRect();
     const rC = categories.getBoundingClientRect();
     return `
        HERO: W=${Math.round(r.width)}, L=${Math.round(r.left)}
        CAT:  W=${Math.round(rC.width)}, L=${Math.round(rC.left)}
     `;
  });

  console.log(html);
  await browser.close();
})();
