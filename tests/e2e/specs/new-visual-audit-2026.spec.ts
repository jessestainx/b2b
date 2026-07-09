import { test } from '@playwright/test';
import path from 'path';
import fs from 'fs';
import { targetUrl } from '../helpers/target-url';
import { blockOptionalThirdParty } from '../helpers/third-party-block';

/**
 * AWA Motos — New Visual Audit 2026
 * 
 * Performs a comprehensive visual audit across critical routes and breakpoints.
 * Resolves BUG-QA-SCREENSHOTS-007.
 */

const BREAKPOINTS = [
  { name: 'desktop-1440', width: 1440, height: 900 },
  { name: 'tablet-1024', width: 1024, height: 768 },
  { name: 'tablet-768', width: 768, height: 1024 },
  { name: 'mobile-390', width: 390, height: 844 },
  { name: 'mobile-360', width: 360, height: 780 },
];

const ROUTES = [
  { name: 'home', path: '/' },
  { name: 'plp', path: '/bagageiros.html' },
  { name: 'pdp', path: '/bagageiro-titan-125-modelo-00-04-fan-125-modelo-05-08-cromado-macico-3015.html' },
  { name: 'search', path: '/catalogsearch/result/?q=bauleto' },
  { name: 'login', path: '/customer/account/login/' },
  { name: 'cart', path: '/checkout/cart/' },
];

const TIMESTAMP = '20260706_012419';
const SCREENSHOT_DIR = path.join(process.cwd(), 'audit', `screenshots-${TIMESTAMP}`);

test.describe('AWA Motos — New Visual Audit 2026', () => {
  test.beforeAll(async () => {
    if (!fs.existsSync(SCREENSHOT_DIR)) {
      fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
      console.log(`[AUDIT] Created directory: ${SCREENSHOT_DIR}`);
    }
  });

  for (const route of ROUTES) {
    test.describe(`Route: ${route.name}`, () => {
      for (const bp of BREAKPOINTS) {
        test(`Audit @ ${bp.name} (${bp.width}x${bp.height})`, async ({ browser }) => {
          const context = await browser.newContext({
            viewport: { width: bp.width, height: bp.height },
            ignoreHTTPSErrors: true,
            locale: 'pt-BR',
            userAgent: bp.width < 768 
              ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.2 Mobile/15E148 Safari/604.1'
              : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
          });
          
          await blockOptionalThirdParty(context);
          const page = await context.newPage();
          
          const url = targetUrl(route.path, 'new-visual-audit');
          console.log(`[AUDIT] [${route.name}] Navigating to ${url} @ ${bp.name}`);
          
          try {
            // AWA Motos can be slow, using 60s timeout
            await page.goto(url, { waitUntil: 'load', timeout: 60000 });
            
            // Wait for network to be somewhat idle
            await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
            
            // Handle cookie consent
            const cookieBtn = page.locator('.cookie-btn-accept, #btn-cookie-allow, .allow').first();
            if (await cookieBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
              await cookieBtn.click();
              await page.waitForTimeout(500);
            }
            
            // Ensure any lazy loading or dynamic scripts have settled
            await page.waitForTimeout(3000);
            
            // Diagnostic check for white-out or layout collapse
            const diagnostics = await page.evaluate(() => {
              const style = window.getComputedStyle(document.body);
              const header = document.querySelector('header');
              const footer = document.querySelector('footer');
              return {
                clipPath: style.clipPath,
                opacity: style.opacity,
                scrollWidth: document.documentElement.scrollWidth,
                clientWidth: document.documentElement.clientWidth,
                headerVisible: !!header && header.offsetHeight > 0,
                footerVisible: !!footer && footer.offsetHeight > 0,
              };
            });
            
            if (diagnostics.clipPath.includes('inset(50%)') || diagnostics.opacity === '0') {
              console.error(`[AUDIT] [FAIL] [${route.name}] [${bp.name}] White-out detected (clip-path/opacity)`);
              console.error(`[AUDIT] [DEBUG] Full Style: ${JSON.stringify(diagnostics, null, 2)}`);
              
              // TRACE: find the rule applying this
              const ruleTrace = await page.evaluate(() => {
                const el = document.body;
                return [...document.styleSheets]
                  .flatMap(sheet => {
                    try { return [...sheet.cssRules]; } catch { return []; }
                  })
                  .filter((rule): rule is CSSStyleRule => rule instanceof CSSStyleRule && el.matches(rule.selectorText) && rule.style.clipPath.includes('inset(50%)'))
                  .map(rule => ({
                    selector: rule.selectorText,
                    css: rule.style.cssText,
                    source: rule.parentStyleSheet?.href || 'inline'
                  }));
              });
              console.error(`[AUDIT] [TRACE] Rules found: ${JSON.stringify(ruleTrace, null, 2)}`);
            }
            
            if (diagnostics.scrollWidth > diagnostics.clientWidth + 5) {
              console.warn(`[AUDIT] [WARN] [${route.name}] [${bp.name}] Horizontal overflow detected: ${diagnostics.scrollWidth} > ${diagnostics.clientWidth}`);
            }
  
            if (!diagnostics.headerVisible) {
              console.warn(`[AUDIT] [WARN] [${route.name}] [${bp.name}] Header not visible or missing height.`);
            }
            
            const fileName = `${route.name}-${bp.name}.png`;
            const filePath = path.join(SCREENSHOT_DIR, fileName);
            
            await page.screenshot({ path: filePath, fullPage: true });
            console.log(`[AUDIT] [SUCCESS] [${route.name}] [${bp.name}] Screenshot saved: ${fileName}`);
            
          } catch (err: any) {
            console.error(`[AUDIT] [ERROR] [${route.name}] [${bp.name}] Failed: ${err.message}`);
          } finally {
            await context.close();
          }
        });
      }
    });
  }
});
