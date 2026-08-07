import { test } from '@playwright/test';
import path from 'path';

const BASE_URL = 'https://awamotos.com';
const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'mobile', width: 375, height: 812 }
];

const PAGES = [
  { name: 'home', path: '/' },
  { name: 'plp', path: '/bauletos.html' },
  { name: 'pdp', path: '/bauletos/bauleto-awa-modelo-proos-34-litros-dourado-340-dr.html' }
];

for (const vp of VIEWPORTS) {
  for (const pg of PAGES) {
    test(`capture snapshot ${pg.name} ${vp.name}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      console.log(`[SNAPSHOT] Navigating to ${pg.name} (${vp.name})...`);
      
      try {
        await page.goto(`${BASE_URL}${pg.path}`, {
          waitUntil: 'networkidle',
          timeout: 60000,
        });
      } catch (e) {
        console.log(`[SNAPSHOT] Timeout on ${pg.name}, proceeding anyway...`);
      }

      // Wait for KO.js and lazy images
      await page.waitForTimeout(5000);

      // Scroll to trigger all lazy loads
      await page.evaluate(async () => {
        const delay = (ms: number) => new Promise(resolve => setTimeout(resolve, ms));
        const scrollStep = 500;
        const totalHeight = document.body.scrollHeight;
        for (let current = 0; current < totalHeight; current += scrollStep) {
          window.scrollTo(0, current);
          await delay(100);
        }
        window.scrollTo(0, 0);
      });

      const whatsappProbe = await page.evaluate(() => {
        const el = document.querySelector('.awa-whatsapp-float');
        if (!el) return 'NOT_FOUND';
        const style = window.getComputedStyle(el);
        return {
          exists: true,
          display: style.display,
          visibility: style.visibility,
          opacity: style.opacity,
          bottom: style.bottom,
          zIndex: style.zIndex,
          content: el.textContent?.trim(),
          bbox: el.getBoundingClientRect()
        };
      });
      console.log(`[PROBE] WhatsApp button on ${pg.name} (${vp.name}):`, JSON.stringify(whatsappProbe, null, 2));

      await page.waitForTimeout(2000);

      const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
      const filename = `snapshot-${pg.name}-${vp.name}-${timestamp}.png`;
      const fullPath = path.join(process.env.KOMBAI_BROWSER_TEMP_DIR || '/tmp', filename);
      
      await page.screenshot({ path: fullPath, fullPage: true });
      console.log(`[SNAPSHOT] Saved: ${fullPath}`);
    });
  }
}
