import { test, expect } from '@playwright/test';
import { targetUrl, withCacheBuster } from '../helpers/target-url';
import { blockOptionalThirdParty } from '../helpers/third-party-block';

const PLP_URL = targetUrl(process.env.PLP_SMOKE_URL || '/carcacas.html', 'category-plp');

test.describe('PLP smoke — harden regressions', () => {
  test.describe.configure({ timeout: 90_000 });

  test.beforeEach(async ({ page }) => {
    await blockOptionalThirdParty(page.context());
    await page.goto(withCacheBuster(PLP_URL, 'plp-smoke'), {
      waitUntil: 'commit',
      timeout: 90_000,
    });
    await page.waitForSelector('.toolbar.toolbar-products', { timeout: 60_000 });
    await page.waitForSelector('li.item-product', { timeout: 60_000 });
  });

  test('toolbar slots expose unique a11y IDs', async ({ page }) => {
    await expect(page.locator('#paging-label')).toHaveCount(0);
    await expect(page.locator('#toolbar-amount-top')).toHaveCount(1);
    await expect(page.locator('#toolbar-amount-bottom')).toHaveCount(1);
    await expect(page.locator('#modes-label-top')).toHaveCount(1);
    await expect(page.locator('#modes-label-bottom')).toHaveCount(0);

    const pagingTop = page.locator('#paging-label-top');
    const pagingBottom = page.locator('#paging-label-bottom');
    const pagingTopCount = await pagingTop.count();
    const pagingBottomCount = await pagingBottom.count();

    expect(pagingTopCount + pagingBottomCount).toBeGreaterThan(0);
    if (pagingTopCount === 1) {
      await expect(pagingTop).toBeAttached();
      await expect(page.locator('ul.pages-items[aria-labelledby="paging-label-top"]')).toHaveCount(1);
    }
    if (pagingBottomCount === 1) {
      await expect(pagingBottom).toBeAttached();
      await expect(page.locator('ul.pages-items[aria-labelledby="paging-label-bottom"]')).toHaveCount(1);
    }
  });

  test('product cards use h2 headings (no h1→h3 skip)', async ({ page }) => {
    const productHeadings = page.locator('li.item-product h2.product-name');
    await expect(productHeadings.first()).toBeVisible();
    const h3ProductNames = page.locator('li.item-product h3.product-name');
    await expect(h3ProductNames).toHaveCount(0);
  });

  test('ERP names use PT-BR accents on category and products', async ({ page }) => {
    const heroTitle = await page.locator('.awa-category-hero__title').innerText();
    expect(heroTitle).toMatch(/ç|ã|õ|á|é|í|ó|ú/i);

    const firstProduct = await page.locator('li.item-product h2.product-name a').first().innerText();
    expect(firstProduct.toLowerCase()).not.toMatch(/\bcarcaca\b/);
    expect(firstProduct).toMatch(/ç|ã|õ|á|é|í|ó|ú/i);
  });

  test('hero product count matches toolbar total', async ({ page }) => {
    const heroCountText = await page.locator('.awa-category-hero__count').first().innerText();
    const toolbarAmountText = await page.locator('#toolbar-amount-top').innerText();
    const heroMatch = heroCountText.match(/(\d+)/);
    const toolbarMatch = toolbarAmountText.match(/de\s+(\d+)/i) || toolbarAmountText.match(/(\d+)/);

    expect(heroMatch).not.toBeNull();
    expect(toolbarMatch).not.toBeNull();
    expect(heroMatch![1]).toBe(toolbarMatch![1]);
  });

  test('at most one B2B gate banner on PLP', async ({ page }) => {
    await expect(page.locator('div.awa-plp-b2b-gate-banner[role="region"]')).toHaveCount(1);
  });

  test('page renders product grid without server error', async ({ page }) => {
    await expect(page.locator('li.item-product').first()).toBeVisible();
    const title = await page.title();
    expect(title.toLowerCase()).not.toContain('error');
    expect(title.toLowerCase()).not.toContain('404');
  });

  test('mobile hero fits title and count without clipping', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.reload({ waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);

    const heroFit = await page.evaluate(() => {
      const hero = document.querySelector('.awa-category-hero') as HTMLElement | null;
      const count = document.querySelector('.awa-category-hero__count') as HTMLElement | null;
      if (!hero || !count) {
        return { ok: false, reason: 'missing-elements' };
      }
      const heroRect = hero.getBoundingClientRect();
      const countRect = count.getBoundingClientRect();
      const minHero = parseFloat(getComputedStyle(hero).minHeight) || 0;
      return {
        ok: countRect.bottom <= heroRect.bottom + 1 && heroRect.height >= 64,
        heroH: Math.round(heroRect.height),
        minHero,
        clipped: countRect.bottom > heroRect.bottom + 1,
      };
    });
    expect(heroFit.ok).toBe(true);
    expect(heroFit.heroH).toBeGreaterThanOrEqual(64);
    expect(heroFit.clipped).toBe(false);
  });

  test('no horizontal overflow on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    const overflowX = await page.evaluate(() => {
      const doc = document.documentElement;
      return Math.max(0, document.body.scrollWidth - doc.clientWidth);
    });
    expect(overflowX).toBeLessThanOrEqual(1);
  });

  test('mobile hides filters until expanded', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.reload({ waitUntil: 'commit' });
    await page.waitForSelector('.toolbar.toolbar-products', { timeout: 60_000 });

    await expect(page.locator('.block.filter').first()).toBeHidden();
  });

  test('sorter select meets 44px touch target', async ({ page }) => {
    const sorter = page.locator('.toolbar-products .sorter-options').first();
    await expect(sorter).toBeVisible();
    await expect
      .poll(async () => {
        return sorter.evaluate((el) => {
          const style = window.getComputedStyle(el);
          return Math.max(parseFloat(style.minHeight) || 0, el.getBoundingClientRect().height);
        });
      })
      .toBeGreaterThanOrEqual(44);
  });

  test('PLP skips non-essential global bundles', async ({ page }) => {
    await expect(page.locator('link[href*="awa-shelf-carousel"]')).toHaveCount(0);
    await expect(page.locator('link[href*="awa-ui-simplify-terminal"]')).toHaveCount(0);
    await expect(page.locator('link[href*="awa-bundle-async-distill-lock"]')).toHaveCount(0);
    await expect(page.locator('link[href*="awa-head-tail-bundle"]')).toHaveCount(0);
    await expect(page.locator('style#awa-shelf-carousel-critical')).toHaveCount(0);
  });

  test('pager links meet 44px touch target', async ({ page }) => {
    const pageLink = page.locator('.toolbar.toolbar-products--bottom-slim .pages .items.pages-items .item a').first();
    await expect(pageLink).toBeVisible();
    await expect
      .poll(async () => {
        return pageLink.evaluate((el) => {
          const style = window.getComputedStyle(el);
          return Math.max(
            parseFloat(style.minHeight) || 0,
            el.getBoundingClientRect().height,
          );
        });
      })
      .toBeGreaterThanOrEqual(44);
  });

  test('PLP critical CSS parses completely (no truncated ruleset)', async ({ page }) => {
    await page.waitForTimeout(2500);
    const ruleCount = await page.evaluate(() => {
      const sheet = [...document.styleSheets].find((s) => s.href?.includes('awa-plp-critical-fixes'));
      if (!sheet) {
        return 0;
      }
      try {
        return sheet.cssRules.length;
      } catch {
        return 0;
      }
    });
    expect(ruleCount).toBeGreaterThanOrEqual(50);
  });

  test('PLP exposes screen-reader live region for filter updates', async ({ page }) => {
    await page.waitForTimeout(3500);
    const live = page.locator('#awa-plp-live-region');
    await expect(live).toHaveCount(1);
    await expect(live).toHaveAttribute('aria-live', 'polite');
  });

  test('long product names do not overflow card width', async ({ page }) => {
    const card = page.locator('li.item-product').first();
    const link = card.locator('.product-item-link').first();
    await expect(link).toBeVisible();
    const overflows = await link.evaluate((el) => {
      const cardEl = el.closest('.item-product') as HTMLElement | null;
      if (!cardEl) {
        return true;
      }
      const cardRect = cardEl.getBoundingClientRect();
      const linkRect = el.getBoundingClientRect();
      return linkRect.width > cardRect.width + 2;
    });
    expect(overflows).toBe(false);
  });

  test('desktop visual standard: compact toolbar, 8px card radius, hidden top pager', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.reload({ waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);

    const topPagesDisplay = await page.locator('.shop-tab-select .toolbar.toolbar-products .pages').first().evaluate((el) => {
      return window.getComputedStyle(el).display;
    });
    expect(topPagesDisplay).toBe('none');

    const toolbarH = await page.locator('.shop-tab-select .toolbar.toolbar-products').first().evaluate((el) => {
      return Math.round(el.getBoundingClientRect().height);
    });
    expect(toolbarH).toBeLessThanOrEqual(56);

    const modesH = await page.locator('.shop-tab-select .toolbar .modes').first().evaluate((el) => {
      return Math.round(el.getBoundingClientRect().height);
    });
    expect(modesH).toBeLessThanOrEqual(48);

    const cardRadius = await page.locator('.wrapper.grid.products-grid .item-product').first().evaluate((el) => {
      return window.getComputedStyle(el).borderRadius;
    });
    expect(cardRadius).toMatch(/8px/);

    const chromeRadii = await page.evaluate(() => {
      const br = (sel: string) => {
        const el = document.querySelector(sel);
        return el ? window.getComputedStyle(el).borderRadius : '';
      };
      return {
        toolbar: br('.shop-tab-select .toolbar.toolbar-products:not(.toolbar-products--bottom-slim)'),
        filter: br('#layered-ajax-filter-block.block.filter'),
        b2b: br('.awa-plp-b2b-gate-banner'),
      };
    });
    expect(chromeRadii.toolbar).toMatch(/8px/);
    expect(chromeRadii.filter).toMatch(/8px/);
    expect(chromeRadii.b2b).toMatch(/8px/);

    const typeSizes = await page.evaluate(() => {
      const px = (sel: string) => {
        const el = document.querySelector(sel);
        return el ? parseFloat(window.getComputedStyle(el).fontSize) : 0;
      };
      return {
        b2bTitle: px('.awa-plp-b2b-gate-banner__title'),
        b2bDesc: px('.awa-plp-b2b-gate-banner__desc'),
        modesLabel: px('.toolbar .modes .modes-label'),
      };
    });
    expect(typeSizes.b2bTitle).toBeGreaterThanOrEqual(13);
    expect(typeSizes.b2bDesc).toBeGreaterThanOrEqual(12);
    expect(typeSizes.modesLabel).toBeGreaterThanOrEqual(12);

    const chromeType = await page.evaluate(() => {
      const px = (sel: string) => {
        const el = document.querySelector(sel);
        return el ? parseFloat(window.getComputedStyle(el).fontSize) : 0;
      };
      return {
        toolbar: px('.shop-tab-select .toolbar.toolbar-products:not(.toolbar-products--bottom-slim)'),
        filterOpt: px('.filter-options-title'),
        sorterLabel: px('.toolbar-sorter .sorter-label'),
      };
    });
    expect(chromeType.toolbar).toBeGreaterThanOrEqual(13);
    expect(chromeType.filterOpt).toBeGreaterThanOrEqual(12);
    expect(chromeType.sorterLabel).toBeGreaterThanOrEqual(12);

    const contract = await page.evaluate(() => {
      const br = (sel: string) => {
        const el = document.querySelector(sel);
        return el ? window.getComputedStyle(el).borderRadius : '';
      };
      return {
        sorter: br('.toolbar-sorter .sorter-options'),
        pager: br('.toolbar.toolbar-products--bottom-slim .pages .item a'),
      };
    });
    expect(contract.sorter).toMatch(/4px|8px/); // chrome 8px; selects usam --awa-radius-sm (4px)
    expect(contract.pager).toMatch(/6px|8px/);

    const surfaces = await page.evaluate(() => {
      const bg = (sel: string) => {
        const el = document.querySelector(sel);
        return el ? window.getComputedStyle(el).backgroundColor : '';
      };
      const isWhite = (c: string) =>
        c === 'rgb(255, 255, 255)' ||
        c === '#ffffff' ||
        c === 'rgba(0, 0, 0, 0)' ||
        c.startsWith('oklch(0.99');
      return {
        toolbar: bg('.shop-tab-select .toolbar.toolbar-products:not(.toolbar-products--bottom-slim)'),
        filter: bg('#layered-ajax-filter-block'),
        card: bg('.item-product'),
        b2b: bg('.awa-plp-b2b-gate-banner'),
        hero: bg('.awa-category-hero'),
        allWhite: ['.awa-category-hero', '.shop-tab-select .toolbar.toolbar-products:not(.toolbar-products--bottom-slim)', '#layered-ajax-filter-block', '.item-product', '.awa-plp-b2b-gate-banner']
          .every((sel) => isWhite(bg(sel))),
      };
    });
    expect(surfaces.allWhite).toBe(true);

    await expect(page.locator('.toolbar.toolbar-products--bottom-slim .pages .item a').first()).toBeVisible();
  });

  test('tablet hides duplicate top pager and compacts toolbar', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.reload({ waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);

    const topPagesDisplay = await page.locator('.shop-tab-select .toolbar.toolbar-products .pages').first().evaluate((el) => {
      return window.getComputedStyle(el).display;
    });
    expect(topPagesDisplay).toBe('none');

    const toolbarH = await page.locator('.shop-tab-select .toolbar.toolbar-products').first().evaluate((el) => {
      return Math.round(el.getBoundingClientRect().height);
    });
    expect(toolbarH).toBeLessThanOrEqual(200);

    await expect(page.locator('.toolbar.toolbar-products--bottom-slim .pages .item a').first()).toBeVisible();
  });

  test('filter overlay is hidden by default', async ({ page }) => {
    const overlay = page.locator('#layered_ajax_overlay');
    await expect(overlay).toHaveAttribute('aria-hidden', 'true');
    const visible = await overlay.evaluate((el) => {
      const style = window.getComputedStyle(el);
      return style.display !== 'none' && style.visibility !== 'hidden' && el.getBoundingClientRect().height > 0;
    });
    expect(visible).toBe(false);
  });
});

test.describe('PLP harden — empty state', () => {
  test('filtered category with zero results shows accessible empty state', async ({ page }) => {
    const emptyUrl =
      targetUrl(process.env.PLP_EMPTY_URL || '/carcacas.html?price=999999-9999999', 'category-plp-empty');
    await blockOptionalThirdParty(page.context());
    await page.goto(withCacheBuster(emptyUrl, 'plp-empty'), {
      waitUntil: 'commit',
      timeout: 90_000,
    });
    const empty = page.locator('.awa-empty-plp');
    await expect(empty).toBeVisible({ timeout: 60_000 });
    await expect(empty).toHaveAttribute('role', 'status');
    await expect(empty.locator('.awa-empty-plp__title')).toContainText(/nenhum produto/i);
  });
});
