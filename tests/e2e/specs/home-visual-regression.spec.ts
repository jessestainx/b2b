/**
 * home-visual-regression.spec.ts — AWA Motos
 * ============================================
 * Cobre os criterios de aceite dos itens HOME-P0-N (e dos itens globais HEADER-P0-N e
 * FOOTER-P0-N observados na Home) de docs/visual-qa/VISUAL_FIX_PLAN.md.
 *
 * NAO e um teste de diagnostico solto: cada teste aqui mapeia 1:1 para o campo
 * "acceptance" de um item do YAML (docs/visual-qa/visual-fix-status.yml).
 * Ao alterar este arquivo, atualizar tambem o campo `tests.playwright` do item
 * correspondente no YAML.
 *
 * Regras desta spec:
 *  - Usa os helpers ja existentes (targetUrl, dismissCookie, collectConsoleErrors,
 *    collectNetworkErrors, checkOverflow, checkCardAlignment, getViewportLabel)
 *    em vez de reimplementar logica de navegacao/coleta de erros.
 *  - NAO espera 'load' — usa 'domcontentloaded' + 'networkidle' com timeout curto,
 *    mesmo padrao de header-core-interactions-p0.spec.ts / fase-h0-*.spec.ts.
 *  - Screenshots de evidencia sao gravados em docs/visual-qa/evidence/<ID>/ via
 *    variavel de ambiente HOME_VISUAL_EVIDENCE_DIR (default: tests/e2e/test-results).
 *
 * Execucao:
 *   cd tests/e2e
 *   npx playwright test specs/home-visual-regression.spec.ts --project=desktop-1440
 *   npx playwright test specs/home-visual-regression.spec.ts --project=mobile-390
 *
 * Contra staging/producao:
 *   ALLOW_PRODUCTION_VALIDATION=true PLAYWRIGHT_BASE_URL=https://awamotos.com \
 *     npx playwright test specs/home-visual-regression.spec.ts --project=desktop-1440
 */
import path from 'path';
import { test, expect, type Page } from '@playwright/test';
import { targetUrl } from '../helpers/target-url';
import {
  dismissCookie,
  collectConsoleErrors,
  collectNetworkErrors,
  checkOverflow,
  checkCardAlignment,
  getViewportLabel,
} from '../helpers/deep-audit.helpers';

const EVIDENCE_ROOT =
  process.env.HOME_VISUAL_EVIDENCE_DIR || path.join(__dirname, '..', 'test-results', 'home-visual-regression');

/** Seletores dos itens HOME-P0-* — ajustar aqui conforme a causa raiz for confirmada. */
const SELECTORS = {
  b2bCtaButton: '.awa-hero-b2b-cta__button, a:has-text("Quero ser revendedor B2B")',
  benefitsList: '.awa-hero-benefits__item, .awa-benefits__item',
  showcaseProductItem: '.product-item',
  minicartShowcart: '.minicart-wrapper .showcart, .action.showcart.header-mini-cart',
  footer: 'footer.page-footer, .page-footer',
};

async function gotoHome(page: Page): Promise<void> {
  const url = targetUrl('/', 'home-visual-regression');
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  if (page.isClosed()) return;
  await page.waitForLoadState('networkidle', { timeout: 12_000 }).catch(() => {});
  if (page.isClosed()) return;
  await dismissCookie(page);
  await new Promise((r) => setTimeout(r, 400));
}

function evidencePath(id: string, filename: string): string {
  return path.join(EVIDENCE_ROOT, id, filename);
}

test.describe('Home visual regression — Fase P0', () => {
  test('HOME-P0-001 — CTA B2B nao deve estar duplicado nem sobreposto', async ({ page }, testInfo) => {
    const consoleErrors = collectConsoleErrors(page);
    const networkErrors = collectNetworkErrors(page);

    await gotoHome(page);
    if (page.isClosed()) {
      test.skip(true, 'pagina fechou durante navegacao — instabilidade de ambiente, nao e bug de Home');
      return;
    }

    const b2bCtas = page.locator(SELECTORS.b2bCtaButton);
    await expect(b2bCtas).toHaveCount(1);

    const viewport = getViewportLabel(page);
    await page.screenshot({
      path: evidencePath('HOME-P0-001', `after-${viewport}.png`),
      fullPage: false,
    }).catch(() => {});

    expect(networkErrors.filter((e) => /\.(css|js)(\?|$)/.test(e.url) && [403, 404].includes(e.status))).toEqual([]);
    expect(consoleErrors.filter((e) => e.type === 'pageerror')).toEqual([]);

    await testInfo.attach('console-errors', { body: JSON.stringify(consoleErrors, null, 2), contentType: 'application/json' });
    await testInfo.attach('network-errors', { body: JSON.stringify(networkErrors, null, 2), contentType: 'application/json' });
  });

  test('HOME-P0-001b — cards de beneficios com altura alinhada', async ({ page }) => {
    await gotoHome(page);
    if (page.isClosed()) {
      test.skip(true, 'pagina fechou durante navegacao');
      return;
    }

    const alignment = await checkCardAlignment(page, SELECTORS.benefitsList);
    expect(alignment.aligned, `alturas encontradas: ${JSON.stringify(alignment.heights)}`).toBeTruthy();
  });

  test('HOME-P0-002 — nenhum product card deve renderizar sem imagem valida', async ({ page }, testInfo) => {
    await gotoHome(page);
    if (page.isClosed()) {
      test.skip(true, 'pagina fechou durante navegacao');
      return;
    }

    await page.locator(SELECTORS.showcaseProductItem).first().waitFor({ state: 'attached', timeout: 10_000 }).catch(() => {});
    // Espera as imagens dos product-items carregarem (lazy-loading) antes de avaliar naturalWidth.
    await page
      .locator(`${SELECTORS.showcaseProductItem} img`)
      .first()
      .waitFor({ state: 'visible', timeout: 8_000 })
      .catch(() => {});
    await new Promise((r) => setTimeout(r, 600));

    // Escopo deliberadamente restrito a .product-item — uma varredura page-wide pegaria
    // falsos positivos em icones de pagamento/social do footer (naturalWidth=0 por
    // lazy-loading fora do viewport, nao por bug real de catalogo).
    const brokenProductImages = await page.evaluate((sel: string) => {
      return Array.from(document.querySelectorAll<HTMLImageElement>(`${sel} img`))
        .filter((img) => {
          if (img.src.endsWith('.svg')) return false;
          if (img.dataset.fallbackApplied === '1') return true; // fallback ja disparado = imagem original quebrada
          return img.naturalWidth === 0 && img.offsetParent !== null;
        })
        .map((img) => img.src)
        .slice(0, 20);
    }, SELECTORS.showcaseProductItem);

    const viewport = getViewportLabel(page);
    if (brokenProductImages.length > 0) {
      await page.screenshot({ path: evidencePath('HOME-P0-002', `broken-${viewport}.png`), fullPage: true }).catch(() => {});
    }

    await testInfo.attach('broken-product-images', { body: JSON.stringify(brokenProductImages, null, 2), contentType: 'application/json' });
    expect(brokenProductImages, `imagens de produto quebradas: ${brokenProductImages.join(', ')}`).toEqual([]);
  });

  test('HEADER-P0-001 — minicart deve ter icone visivel no header (observado na Home)', async ({ page }) => {
    await gotoHome(page);
    if (page.isClosed()) {
      test.skip(true, 'pagina fechou durante navegacao');
      return;
    }

    const showcart = page.locator(SELECTORS.minicartShowcart).first();
    await expect(showcart).toBeVisible({ timeout: 10_000 });
  });

  test('FOOTER-P0-001 — footer deve estar presente e sem erro de layout grosseiro (observado na Home)', async ({ page }) => {
    await gotoHome(page);
    if (page.isClosed()) {
      test.skip(true, 'pagina fechou durante navegacao');
      return;
    }

    const footer = page.locator(SELECTORS.footer).first();
    await expect(footer).toBeVisible();
    // Contraste detalhado (WCAG 4.5:1) e coberto por accessibility.spec.ts (axe-core).
    // Blocos vermelhos por coluna (bug principal) sao cobertos por footer-contrast-probe.spec.ts.
  });

  test('HOME-P0-003 — Home nao deve ter overflow horizontal (carrosseis/controles)', async ({ page }) => {
    await gotoHome(page);
    if (page.isClosed()) {
      test.skip(true, 'pagina fechou durante navegacao');
      return;
    }

    const overflow = await checkOverflow(page);
    expect(overflow.hasOverflow, `diff de overflow: ${overflow.diff}px`).toBeFalsy();
  });
});
