/**
 * Fase 4 — contratos da cascata CSS (HTML + rede).
 *
 * - exatamente 1× align-grid terminal
 * - exatamente 1× visual SSOT
 * - exatamente 1 requisição do footer terminal (home)
 * - fragmentos incompatíveis ausentes em auth
 * - fallback noscript / JS off na home
 *
 * Produção NÃO é o alvo padrão:
 *   PLAYWRIGHT_BASE_URL=https://staging.example \
 *   npx playwright test specs/css-cascade-contract.spec.ts --project=notebook-1366
 *
 * Opt-in produção:
 *   PLAYWRIGHT_BASE_URL=https://awamotos.com ALLOW_PRODUCTION_VALIDATION=true \
 *   npx playwright test specs/css-cascade-contract.spec.ts --project=notebook-1366
 */
import { test, expect, type Page, type Request } from '@playwright/test';

const ALIGN = /awa-align-grid-terminal-2026-06-11/;
const SSOT = /awa-m2-visual-ssot/;
const FOOTER = /awa-footer-terminal-lock-v1/;

const VIEWPORTS = [
  { name: '390', width: 390, height: 844 },
  { name: '768', width: 768, height: 1024 },
  { name: '1366', width: 1366, height: 768 },
  { name: '1920', width: 1920, height: 1080 },
] as const;

const PLP_PATH = process.env.AWA_CASCADE_PLP_PATH || '/carcacas.html';
const PDP_PATH =
  process.env.AWA_CASCADE_PDP_PATH ||
  '/bagageiro-titan-125-modelo-00-04-fan-125-modelo-05-08-cromado-macico-3015.html';

async function countStylesheetMatches(page: Page, re: RegExp): Promise<number> {
  return page.evaluate((source) => {
    const pattern = new RegExp(source, 'i');
    const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
    return links.filter((link) => {
      const href = (link as HTMLLinkElement).href || link.getAttribute('href') || '';
      const marker =
        link.getAttribute('data-awa-bundle') ||
        link.getAttribute('data-awa-m2-visual-ssot') ||
        link.getAttribute('data-awa-align-grid-terminal') ||
        link.getAttribute('data-awa-align-grid-body-terminal') ||
        '';
      return pattern.test(href) || pattern.test(marker);
    }).length;
  }, re.source);
}

async function waitForCascadeSettled(page: Page): Promise<void> {
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(2500);
}

test.describe('CSS cascade contract', () => {
  for (const vp of VIEWPORTS) {
    test(`home @${vp.name}: exatamente 1 align-grid + 1 visual SSOT`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await page.goto('/', { waitUntil: 'domcontentloaded' });
      await waitForCascadeSettled(page);

      const alignCount = await countStylesheetMatches(page, ALIGN);
      const ssotCount = await countStylesheetMatches(page, SSOT);

      expect(alignCount, `align-grid @${vp.name}`).toBe(1);
      expect(ssotCount, `visual SSOT @${vp.name}`).toBe(1);
    });
  }

  test('home: exatamente uma requisição do footer terminal', async ({ page }) => {
    const footerRequests: string[] = [];
    const onRequest = (req: Request) => {
      const url = req.url();
      if (FOOTER.test(url) && /\.css(\?|$)/i.test(url)) {
        footerRequests.push(url);
      }
    };
    page.on('request', onRequest);

    await page.setViewportSize({ width: 1366, height: 768 });
    await page.goto('/', { waitUntil: 'networkidle' });
    await page.waitForTimeout(4000);
    page.off('request', onRequest);

    const unique = [...new Set(footerRequests.map((u) => u.replace(/[?#].*$/, '')))];
    expect(unique.length, `footer requests=${JSON.stringify(footerRequests)}`).toBe(1);

    const linkCount = await countStylesheetMatches(page, FOOTER);
    expect(linkCount, 'footer link no DOM').toBe(1);
  });

  test('PLP/PDP: align-grid e visual SSOT sem duplicata', async ({ page }) => {
    for (const path of [PLP_PATH, PDP_PATH]) {
      await page.goto(path, { waitUntil: 'domcontentloaded' });
      await waitForCascadeSettled(page);
      expect(await countStylesheetMatches(page, ALIGN), `align @ ${path}`).toBe(1);
      expect(await countStylesheetMatches(page, SSOT), `ssot @ ${path}`).toBe(1);
    }
  });

  test('B2B login auth: refine/promax ausentes (lista incompatível)', async ({ page }) => {
    await page.goto('/b2b/account/login/', { waitUntil: 'domcontentloaded' });
    await waitForCascadeSettled(page);

    const forbidden = await page.evaluate(() => {
      const hrefs = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(
        (l) => (l as HTMLLinkElement).href
      );
      return {
        refine: hrefs.filter((h) => /awa-commerce-impeccable-refine/i.test(h)).length,
        promax: hrefs.filter((h) => /awa-ui-promax-bundle/i.test(h)).length,
        plpPolish: hrefs.filter((h) => /awa-plp-final-polish/i.test(h)).length,
      };
    });

    expect(forbidden.refine, 'refine em auth').toBe(0);
    expect(forbidden.promax, 'promax em auth').toBe(0);
    expect(forbidden.plpPolish, 'plp polish em auth').toBe(0);
  });

  test('home JS off: noscript deferred-stack presente e sem duplicata align-grid', async ({
    browser,
  }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.setViewportSize({ width: 1366, height: 768 });
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const html = await page.content();
    expect(html).toMatch(/<noscript>\s*<link[^>]+awa-home-deferred-stack/i);

    const alignInLinks = await countStylesheetMatches(page, ALIGN);
    // Sem JS o gate não reanexa; o HTML SSR/plugin deve manter no máximo 1.
    expect(alignInLinks).toBeLessThanOrEqual(1);

    await context.close();
  });

  test('home guest: customer-data bundle carrega (section/load pode ser idle-deferred)', async ({
    page,
  }) => {
    // Na home, section/load pode ficar além da janela de smoke (DeferHomeScripts).
    // O contrato mínimo: o bundle customer-data entra na rede/DOM para guest.
    const bundlePromise = page.waitForRequest(
      (req) => /Magento_Customer\/js\/customer-data/i.test(req.url()),
      { timeout: 20000 }
    );

    await page.goto('/', { waitUntil: 'domcontentloaded' });
    const hit = await bundlePromise;
    expect(hit.url()).toMatch(/customer-data/i);

    const htmlHas = await page.evaluate(
      () => document.documentElement.innerHTML.includes('Magento_Customer/js/customer-data')
    );
    expect(htmlHas, 'customer-data no HTML/Require').toBeTruthy();
  });
});
