const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
  
  // Use load instead of networkidle
  await page.goto('https://awamotos.com/', { waitUntil: 'load', timeout: 45000 });
  
  // Wait a bit for JS to settle
  await page.waitForTimeout(5000);

  const metrics = await page.evaluate(() => {
    const hero = document.querySelector('.awa-corporate-hero, .banner_item');
    const shelf = document.querySelector('.products-grid, .awa-shelf-carousel');
    const benefits = document.querySelector('.awa-benefits-bar');
    
    let hasOverflow = document.documentElement.scrollWidth > window.innerWidth;
    let overflowElement = null;
    if (hasOverflow) {
        const all = document.querySelectorAll('*');
        for (let i=0; i<all.length; i++) {
            if (all[i].scrollWidth > window.innerWidth) { 
                overflowElement = all[i].className; 
                break; 
            }
        }
    }

    const sections = document.querySelectorAll('.page-main > *, .content-top-home > *');
    const gaps = [];
    for(let j=0; j<Math.min(sections.length - 1, 8); j++) {
        if(sections[j] && sections[j+1]) {
            const rect1 = sections[j].getBoundingClientRect();
            const rect2 = sections[j+1].getBoundingClientRect();
            if(rect1.height > 0 && rect2.height > 0) {
                gaps.push(Math.round(rect2.top - rect1.bottom));
            }
        }
    }

    return {
        H1: {
            heroLeft: hero ? hero.getBoundingClientRect().left : null,
            shelfLeft: shelf ? shelf.getBoundingClientRect().left : null,
            benefitsLeft: benefits ? benefits.getBoundingClientRect().left : null
        },
        H2: {
            hasOverflow,
            overflowElement,
            scrollW: document.documentElement.scrollWidth,
            innerW: window.innerWidth
        },
        H3: {
            gaps
        }
    };
  });

  console.log(JSON.stringify(metrics, null, 2));
  await browser.close();
})();
