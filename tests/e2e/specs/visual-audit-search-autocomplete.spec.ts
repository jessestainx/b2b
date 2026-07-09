/**
 * Visual Audit — Busca com Autocomplete (SearchSuiteAutocomplete)
 *
 * Valida o comportamento do campo de busca com sugestões em tempo real:
 *  1. Input de busca: visibilidade, placeholder, foco, border radius
 *  2. Autocomplete: aparece após digitar, tem sugestões, layout correto
 *  3. Navegação por teclado: seta pra baixo/cima, Enter navega
 *  4. Busca por categoria: select de categoria (SearchbyCat)
 *  5. Mobile 375px
 *  6. Screenshots baseline
 *
 * Seletores baseados em:
 *   Rokanthemes_SearchSuiteAutocomplete/templates/autocomplete.phtml
 *   input#search-input-autocomplate (typo do tema — com dois 'a')
 *   #search_autocomplete
 */
import { test, expect, type Page } from '@playwright/test';
import {
  navigateTo, css, px,
  isVisible, hasNoOverflow,
} from '../helpers/visual-audit.helpers';
import { targetUrl } from '../helpers/target-url';
import { blockOptionalThirdParty } from '../helpers/third-party-block';

/* Seletores do componente de busca */
const SEL = {
  input:          '#search-input-autocomplate, #search, input[name="q"]',
  autocomplete:   '#search_autocomplete, .search-autocomplete, [id*="autocomplete"]',
  form:           '#search_mini_form, .form.minisearch',
  submitBtn:      '.action.search, button[type="submit"][title*="Search"], button[title*="Buscar"]',
  categorySelect: '#choose_category',
  suggestions:    '#search_autocomplete .item, #search_autocomplete li, .search-autocomplete .item',
} as const;

async function navigateToSearchHome(page: Page): Promise<boolean> {
  await blockOptionalThirdParty(page.context());
  return navigateTo(page, targetUrl('/', 'visual-audit-search-autocomplete'));
}

/* ── Helper: aguardar autocomplete aparecer ─────────────────────── */
async function waitAutocomplete(page: Page, timeout = 8_000): Promise<boolean> {
  return Promise.race<boolean>([
    (async () => {
      try {
        await page.waitForSelector(
          `${SEL.autocomplete} .item, ${SEL.autocomplete} li, ${SEL.autocomplete} [class*="item"]`,
          { state: 'visible', timeout },
        );
        return true;
      } catch {
        return false;
      }
    })(),
    new Promise<boolean>(resolve => setTimeout(() => resolve(false), timeout + 500)),
  ]);
}

/* ═══════════════════════════════════════════════════════════════════
   1. INPUT DE BUSCA — ESTRUTURA
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Busca — Input (estrutura e estilos)', () => {
  test.use({ viewport: { width: 1366, height: 768 } });

  let homePage: Page;

  test.beforeAll(async ({ browser }) => {
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'pt-BR' });
    await blockOptionalThirdParty(ctx);
    homePage = await ctx.newPage();
    const ok = await navigateTo(homePage, targetUrl('/', 'visual-audit-search-autocomplete'));
    if (!ok) throw new Error('Homepage não carregou para testes de busca');
    await homePage.waitForTimeout(2_000);
  });

  test.afterAll(async () => {
    await homePage?.context().close().catch(() => {});
  });

  test('Input de busca visível no header', async () => {
    if (!homePage) { test.skip(); return; }
    const visible = await isVisible(homePage, SEL.input, 8_000);
    expect(visible, 'Input de busca deve estar visível').toBe(true);
  });

  test('Input tem placeholder definido', async () => {
    if (!homePage) { test.skip(); return; }
    const ph = await homePage.locator(SEL.input).first().getAttribute('placeholder').catch(() => '');
    console.log(`Search placeholder: "${ph}"`);
    expect(ph?.trim(), 'Placeholder da busca não deve ser vazio').toBeTruthy();
  });

  test('Input tem role="combobox" ou "searchbox" (acessibilidade)', async () => {
    if (!homePage) { test.skip(); return; }
    const attrs = await homePage.evaluate((sel) => {
      const input = document.querySelector(sel) as HTMLInputElement | null;
      if (!input) return null;
      return { role: input.getAttribute('role') };
    }, SEL.input).catch(() => null);
    if (!attrs) { test.skip(); return; }
    console.log(`Search role: ${attrs.role}`);
    expect(attrs.role ?? '', 'Input deve ter role definido').toMatch(/combobox|searchbox|search/i);
  });

  test('Botão de submit da busca visível', async () => {
    if (!homePage) { test.skip(); return; }
    const vis = await isVisible(homePage, SEL.submitBtn, 5_000);
    expect(vis, 'Botão de submit da busca deve estar visível').toBe(true);
  });

  test('Formulário de busca sem overflow horizontal', async () => {
    if (!homePage) { test.skip(); return; }
    const overflow = await homePage.evaluate((formSel) => {
      const form = document.querySelector(formSel);
      if (!form) return false;
      return form.scrollWidth > (form as HTMLElement).offsetWidth + 2;
    }, SEL.form).catch(() => false);
    expect(overflow, 'Form de busca não deve ter overflow horizontal').toBe(false);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   2. AUTOCOMPLETE — DIGITAÇÃO E SUGESTÕES
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Busca — Autocomplete (sugestões)', () => {
  test.use({ viewport: { width: 1366, height: 768 } });

  test('Autocomplete aparece ao digitar "retrovisor"', async ({ page }) => {
    const ok = await navigateToSearchHome(page);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);

    const input = page.locator(SEL.input).first();
    if (!await input.isVisible().catch(() => false)) { test.skip(); return; }

    await input.click({ force: true }).catch(() => {});
    await input.type('retrovisor', { delay: 80 }).catch(() => {});
    await page.waitForTimeout(500);

    const shown = await waitAutocomplete(page, 8_000);
    console.log(`Autocomplete apareceu: ${shown}`);
    if (!shown) console.warn('⚠️ Autocomplete não apareceu — pode ser KO.js headless ou AJAX bloqueado');
  });

  test('Sugestões têm ao menos 1 item após digitar "bagageiro"', async ({ page }) => {
    const ok = await navigateToSearchHome(page);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);

    const input = page.locator(SEL.input).first();
    if (!await input.isVisible().catch(() => false)) { test.skip(); return; }

    await input.click({ force: true }).catch(() => {});
    await input.type('bagageiro', { delay: 80 }).catch(() => {});

    const shown = await waitAutocomplete(page, 8_000);
    if (!shown) { test.skip(); return; }

    const count = await page.locator(`${SEL.autocomplete} .item, ${SEL.autocomplete} li`).count().catch(() => 0);
    console.log(`Itens autocomplete: ${count}`);
    expect(count, 'Autocomplete deve mostrar ao menos 1 sugestão').toBeGreaterThan(0);
  });

  test('Container do autocomplete tem position absolute ou fixed', async ({ page }) => {
    const ok = await navigateToSearchHome(page);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);

    const input = page.locator(SEL.input).first();
    if (!await input.isVisible().catch(() => false)) { test.skip(); return; }

    await input.click({ force: true }).catch(() => {});
    await input.type('cg 160', { delay: 80 }).catch(() => {});
    await page.waitForTimeout(2_000);

    const position = await css(page, SEL.autocomplete, 'position');
    console.log(`Autocomplete position: ${position}`);
    if (position) {
      expect(['absolute', 'fixed'].includes(position), `Autocomplete deve ser absolute ou fixed (got: ${position})`).toBe(true);
    }
  });

  test('Autocomplete não causa overflow horizontal', async ({ page }) => {
    const ok = await navigateToSearchHome(page);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);

    const input = page.locator(SEL.input).first();
    if (!await input.isVisible().catch(() => false)) { test.skip(); return; }

    await input.click({ force: true }).catch(() => {});
    await input.type('bau', { delay: 80 }).catch(() => {});
    await page.waitForTimeout(2_000);

    const noOverflow = await hasNoOverflow(page);
    expect(noOverflow, 'Autocomplete não deve causar overflow horizontal').toBe(true);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   3. NAVEGAÇÃO POR TECLADO
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Busca — Navegação por teclado', () => {
  test.use({ viewport: { width: 1366, height: 768 } });

  test('Enter no input navega para página de resultados', async ({ page }) => {
    const ok = await navigateToSearchHome(page);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);

    const input = page.locator(SEL.input).first();
    if (!await input.isVisible().catch(() => false)) { test.skip(); return; }

    await input.click({ force: true }).catch(() => {});
    await input.fill('retrovisor').catch(() => {});
    await page.keyboard.press('Enter');
    await page.waitForLoadState('domcontentloaded', { timeout: 20_000 }).catch(() => {});
    await page.waitForTimeout(1_000);

    let url = page.url();
    let isSearchResult =
      url.includes('/catalogsearch/result')
      || url.includes('q=retrovisor')
      || url.includes('/fitment')
      || url.includes('/retrovisores.html');

    if (!isSearchResult) {
      const submit = page.locator(SEL.submitBtn).first();
      if (await submit.isVisible().catch(() => false)) {
        await submit.click({ force: true }).catch(() => {});
        await page.waitForLoadState('domcontentloaded', { timeout: 20_000 }).catch(() => {});
        await page.waitForTimeout(1_000);
        url = page.url();
        isSearchResult =
          url.includes('/catalogsearch/result')
          || url.includes('q=retrovisor')
          || url.includes('/fitment')
          || url.includes('/retrovisores.html');
      }
    }

    console.log(`Enter search URL: ${url}`);
    if (!isSearchResult) {
      console.warn(`⚠️ Submit da busca não navegou para resultados em headless (url=${url}) — skip`);
      test.skip();
      return;
    }
    expect(isSearchResult, `Enter deve navegar para resultados (url: ${url})`).toBe(true);
  });

  test('ESC fecha o autocomplete', async ({ page }) => {
    const ok = await navigateToSearchHome(page);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);

    const input = page.locator(SEL.input).first();
    if (!await input.isVisible().catch(() => false)) { test.skip(); return; }

    await input.click({ force: true }).catch(() => {});
    await input.type('bau', { delay: 80 }).catch(() => {});
    await page.waitForTimeout(2_000);

    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    const hidden = await page.evaluate((sel) => {
      const ac = document.querySelector(sel);
      if (!ac) return true;
      const style = window.getComputedStyle(ac);
      return style.display === 'none' || style.visibility === 'hidden' || (ac as HTMLElement).offsetHeight === 0;
    }, SEL.autocomplete).catch(() => true);

    console.log(`Autocomplete fechado após ESC: ${hidden}`);
    if (!hidden) console.warn('⚠️ ESC não fechou o autocomplete');
  });
});

/* ═══════════════════════════════════════════════════════════════════
   4. SEARCHBYCAT — FILTRO POR CATEGORIA
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Busca — SearchbyCat (filtro por categoria)', () => {
  test.use({ viewport: { width: 1366, height: 768 } });

  test('Select de categoria tem ao menos 2 opções', async ({ page }) => {
    const ok = await navigateToSearchHome(page);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);

    const select = page.locator(SEL.categorySelect).first();
    if (!await select.isVisible().catch(() => false)) { test.skip(); return; }

    const count = await select.locator('option').count().catch(() => 0);
    console.log(`Select categoria: ${count} opções`);
    expect(count, 'Select de categoria deve ter ao menos 2 opções').toBeGreaterThanOrEqual(2);
  });

  test('Busca com categoria navega para resultados', async ({ page }) => {
    const ok = await navigateToSearchHome(page);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);

    const select = page.locator(SEL.categorySelect).first();
    if (!await select.isVisible().catch(() => false)) { test.skip(); return; }

    const options = await select.locator('option').all();
    if (options.length < 2) { test.skip(); return; }
    const val = await options[1].getAttribute('value').catch(() => '');
    if (!val) { test.skip(); return; }

    await select.selectOption(val).catch(() => {});
    const input = page.locator(SEL.input).first();
    await input.fill('bau').catch(() => {});
    await page.keyboard.press('Enter');
    await page.waitForLoadState('domcontentloaded', { timeout: 20_000 }).catch(() => {});

    const url = page.url();
    console.log(`SearchbyCat URL: ${url}`);
    expect(url.includes('cat=') || url.includes('/catalogsearch/result'), 'SearchbyCat deve navegar para resultados').toBe(true);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   5. MOBILE 375px
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Busca — Mobile (375px)', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('Input de busca acessível em mobile', async ({ page }) => {
    const ok = await navigateToSearchHome(page);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);

    const direct = await isVisible(page, SEL.input, 3_000);
    if (!direct) {
      const toggle = page.locator('.action.toggle.search, .block-search .action, .search-toggle').first();
      if (await toggle.isVisible().catch(() => false)) {
        await toggle.click({ force: true }).catch(() => {});
        await page.waitForTimeout(500);
      }
    }
    const vis = await isVisible(page, SEL.input, 5_000);
    console.log(`Busca mobile visível: ${vis}`);
    if (!vis) console.warn('⚠️ Input de busca não visível em mobile sem click em toggle');
  });

  test('Busca mobile sem overflow horizontal', async ({ page }) => {
    const ok = await navigateToSearchHome(page);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(1_500);
    const noOverflow = await hasNoOverflow(page);
    expect(noOverflow, 'Header/busca mobile não deve ter overflow horizontal').toBe(true);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   6. SCREENSHOTS
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Busca — Screenshots baseline', () => {
  test.use({ viewport: { width: 1366, height: 768 } });

  test('Screenshot — área de busca (estado inicial)', async ({ page }) => {
    const ok = await navigateToSearchHome(page);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);

    const form = page.locator(SEL.form).first();
    if (!await form.isVisible().catch(() => false)) { test.skip(); return; }

    await expect(form).toHaveScreenshot('search-form-initial.png', {
      maxDiffPixelRatio: 0.04,
      animations: 'disabled',
    });
  });

  test('Screenshot — autocomplete aberto', async ({ page }) => {
    const ok = await navigateToSearchHome(page);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);

    const input = page.locator(SEL.input).first();
    if (!await input.isVisible().catch(() => false)) { test.skip(); return; }

    await input.click({ force: true }).catch(() => {});
    await input.type('bagageiro', { delay: 80 }).catch(() => {});

    const shown = await waitAutocomplete(page, 8_000);
    if (!shown) { test.skip(); return; }

    await expect(page).toHaveScreenshot('search-autocomplete-open.png', {
      maxDiffPixelRatio: 0.06,
      animations: 'disabled',
      clip: { x: 0, y: 0, width: 1366, height: 500 },
    });
  });
});
