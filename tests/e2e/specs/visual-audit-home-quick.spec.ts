import { test, expect } from '@playwright/test';

test('Home - visual audit', async ({ page }) => {
  await page.setViewportSize({ width: 1366, height: 768 });
  await page.goto('https://awamotos.com/', { waitUntil: 'networkidle', timeout: 90000 });
  await page.screenshot({ path: '/tmp/home-desktop.png', fullPage: false });

  // Checks críticos
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  const criticals = await page.evaluate(() => ({
    header:   !!document.querySelector('.page-header'),
    logo:     !!document.querySelector('.logo img'),
    nav:      !!document.querySelector('.navigation, .nav-sections'),
    search:   !!document.querySelector('#search'),
    minicart: !!document.querySelector('.minicart-wrapper'),
    footer:   !!document.querySelector('.page-footer'),
  }));
  const brokenImgs = await page.evaluate(() =>
    [...document.querySelectorAll('img')]
      .filter(i => i.complete && i.naturalWidth === 0 && i.getBoundingClientRect().width > 5)
      .map(i => i.src.split('/').pop()).slice(0, 5)
  );

  console.log('OVERFLOW:', overflow);
  console.log('CRÍTICOS:', JSON.stringify(criticals));
  console.log('IMGS QUEBRADAS:', JSON.stringify(brokenImgs));

  // Mobile
  await page.setViewportSize({ width: 390, height: 844 });
  await page.screenshot({ path: '/tmp/home-mobile.png', fullPage: false });
  const overflowM = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  const touchTargets = await page.evaluate(() =>
    [...document.querySelectorAll('a,button,[role="button"]')]
      .filter(el => { const r = el.getBoundingClientRect(); return r.width > 1 && r.height > 1 && (r.width < 44 || r.height < 44); })
      .map(el => ({ tag: el.tagName, cls: el.className?.toString().slice(0,60), w: Math.round(el.getBoundingClientRect().width), h: Math.round(el.getBoundingClientRect().height) }))
      .slice(0, 10)
  );
  console.log('OVERFLOW MOBILE:', overflowM);
  console.log('TOUCH TARGETS <44px:', JSON.stringify(touchTargets));
});
