import { expect, test, type Page } from '@playwright/test';
import { targetUrl } from '../helpers/target-url';
import { collectConsoleErrors, collectNetworkErrors, dismissCookie } from '../helpers/deep-audit.helpers';
import { awaSelectors } from '../support/awa-selectors';

const MIRA_AUTOCOMPLETE_SELECTOR = '.mst-searchautocomplete__autocomplete';

async function safeGoto(page: Page): Promise<number> {
  const response = await page.goto(targetUrl('/', 'pd6b-mobile-search-ci'), {
    waitUntil: 'domcontentloaded',
    timeout: 30_000,
  }).catch(() => null);

  if (page.isClosed()) {
    return -1;
  }

  await page.waitForLoadState('networkidle', { timeout: 12_000 }).catch(() => {});
  if (page.isClosed()) {
    return -1;
  }

  await dismissCookie(page);
  await page.waitForTimeout(250);

  return response?.status() ?? 0;
}

async function waitMirasvitAutocomplete(page: Page, timeoutMs = 12_000): Promise<boolean> {
  const startedAt = Date.now();
  const panel = page.locator(MIRA_AUTOCOMPLETE_SELECTOR).first();

  while (Date.now() - startedAt < timeoutMs) {
    const visible = await panel.isVisible().catch(() => false);
    const items = await page.locator(
      `${MIRA_AUTOCOMPLETE_SELECTOR} .product-item, ${MIRA_AUTOCOMPLETE_SELECTOR} .mst-searchautocomplete__item`,
    ).count().catch(() => 0);

    if (visible && items > 0) {
      return true;
    }

    await page.waitForTimeout(250);
  }

  return false;
}

test.describe('PD6B — mobile search CI stabilization', () => {
  test('home: autocomplete Mirasvit abre ao digitar (mobile)', async ({ page }, testInfo) => {
    const consoleErrors = collectConsoleErrors(page);
    const networkErrors = collectNetworkErrors(page);

    const status = await safeGoto(page);
    expect(status, `HTTP status inesperado na home (status=${status})`).toBeGreaterThanOrEqual(200);

    const searchInput = page.locator(awaSelectors.search.input).first();
    await expect(searchInput).toBeVisible({ timeout: 12_000 });

    let typed = false;
    for (let attempt = 1; attempt <= 3; attempt += 1) {
      const currentInput = page.locator(awaSelectors.search.input).first();
      const visible = await currentInput.isVisible().catch(() => false);
      if (!visible) {
        await page.waitForTimeout(350);
        continue;
      }

      await currentInput.click({ timeout: 8_000 }).catch(() => {});
      await currentInput.fill('').catch(() => {});
      await page.keyboard.type('bagageiro', { delay: 28 }).catch(() => {});

      const value = await currentInput.inputValue().catch(() => '');
      if (value.toLowerCase().includes('bagageiro')) {
        typed = true;
        break;
      }

      await page.waitForTimeout(350);
    }

    expect(typed, 'Não foi possível digitar no campo de busca mobile').toBeTruthy();

    const opened = await waitMirasvitAutocomplete(page, 12_000);

    const evidence = {
      route: '/',
      project: testInfo.project.name,
      pageUrl: page.url(),
      status,
      autocomplete: {
        selector: MIRA_AUTOCOMPLETE_SELECTOR,
        opened,
      },
      consoleErrorsCount: consoleErrors.length,
      networkErrorsCount: networkErrors.length,
      capturedAt: new Date().toISOString(),
    };

    await testInfo.attach('pd6b-mobile-search-evidence.json', {
      body: JSON.stringify(evidence, null, 2),
      contentType: 'application/json',
    });

    console.log(`[PD6B] ${JSON.stringify(evidence).slice(0, 4000)}`);

    expect(opened, 'Autocomplete Mirasvit não abriu no tempo esperado').toBeTruthy();
  });
});
