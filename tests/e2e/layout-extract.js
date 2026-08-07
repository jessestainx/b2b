const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
  
  await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForTimeout(3000);

  const html = await page.evaluate(() => {
     let out = [];
     const hero = document.querySelector('.awa-hero');
     const categories = document.querySelector('.top-home-content--category-carousel');
     const featured = document.querySelector('.awa-carousel-section--featured');
     const promos = document.querySelector('.awa-product-promo-banners');
     const grid = document.querySelector('.awa-grid-section');
     const niches = document.querySelector('.awa-home-niche-shelves');

     [
       {name: 'Hero', el: hero},
       {name: 'Categories', el: categories},
       {name: 'Featured', el: featured},
       {name: 'Promos', el: promos},
       {name: 'Grid', el: grid},
       {name: 'Niches', el: niches}
     ].forEach(item => {
         if (item.el) {
             const r = item.el.getBoundingClientRect();
             out.push(`${item.name}: W=${Math.round(r.width)}, Left=${Math.round(r.left)}, Right=${Math.round(r.right)} (windowW=${window.innerWidth})`);
         }
     });

     return out.join('\n');
  });

  console.log(html);
  await browser.close();
})();
