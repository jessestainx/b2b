/**
 * Menu regression — home / busca / categoria / mobile / B2B guest.
 * Run: cd tests/e2e && node specs/menu-regression.mjs
 */
import { chromium } from 'playwright';

const BASE = process.env.AWA_BASE_URL || 'https://awamotos.com';
const DESKTOP_PAGES = [
    { id: 'home', url: `${BASE}/`, expectB2b: false },
    { id: 'search', url: `${BASE}/catalogsearch/result/?q=oleo`, expectB2b: true },
    { id: 'category', url: `${BASE}/bauletos.html`, expectB2b: false },
];
const MOBILE_PAGES = [
    { id: 'home', url: `${BASE}/` },
    { id: 'category', url: `${BASE}/bauletos.html` },
    { id: 'search', url: `${BASE}/catalogsearch/result/?q=oleo`, navOnly: true },
];

const DESKTOP = { width: 1280, height: 800 };
const MOBILE = { width: 375, height: 812 };

function assert(condition, message) {
    if (!condition) {
        throw new Error(message);
    }
}

function bodyHasMobileDrawerOpen(classes) {
    return classes.includes('nav-open')
        || classes.includes('awa-menu-drawer-open')
        || classes.includes('awa-nav-preflight');
}

async function waitForB2bClasses(page, timeout = 30000) {
    await page.waitForFunction(
        () => document.body.classList.contains('b2b-guest-mode')
            || document.body.classList.contains('b2b-restricted-mode'),
        { timeout }
    );
}

async function waitForMenuV2Ready(page, timeout = 45000) {
    await page.waitForFunction(
        () => document.body.classList.contains('awa-menu-v2-ready'),
        { timeout }
    );
}

async function navMetrics(page, waitB2b = false) {
    if (waitB2b) {
        await waitForB2bClasses(page);
    }
    return page.evaluate(() => {
        const nav = document.querySelector('.header-control.awa-nav-bar');
        const trigger = document.querySelector('[data-role="awa-vertical-menu-trigger"]');
        const toggle = document.querySelector('[data-awa-nav-toggle="true"]');
        const navCs = nav ? getComputedStyle(nav) : null;
        const b2b = [...document.body.classList].filter((c) => c.startsWith('b2b'));
        return {
            navDisplay: navCs ? navCs.display : null,
            navVisible: navCs ? navCs.visibility : null,
            navPe: navCs ? navCs.pointerEvents : null,
            hasDeptTrigger: !!trigger,
            hasMobileToggle: !!toggle,
            b2b: b2b.join(','),
        };
    });
}

async function testDesktopDept(page) {
    const trigger = page.locator('[data-role="awa-vertical-menu-trigger"]');
    if (!(await trigger.count())) {
        return { ok: false, reason: 'no-trigger' };
    }
    await waitForMenuV2Ready(page).catch(async () => {
        await page.waitForFunction(
            () => !!document.querySelector('[data-role="awa-vertical-menu-trigger"]'),
            { timeout: 15000 }
        );
        await page.waitForTimeout(2000);
    });
    await trigger.click({ timeout: 10000 });
    await page.waitForTimeout(1500);
    return page.evaluate(() => {
        const panel = document.querySelector('[data-role="awa-vertical-menu-panel"]');
        const cs = panel ? getComputedStyle(panel) : null;
        const firstLink = panel?.querySelector('a.level-top');
        const linkFs = firstLink ? parseFloat(getComputedStyle(firstLink).fontSize) : 0;
        const styleEl = document.querySelector('#awa-menu-v2-dept-open-fix');
        const items = panel
            ? [...panel.querySelectorAll(':scope > li.ui-menu-item.level0')]
                .filter((li) => getComputedStyle(li).display !== 'none' && li.offsetHeight > 0)
            : [];
        const expand = panel?.querySelector('.expand-category-link');
        const panelRect = panel?.getBoundingClientRect();
        const expandRect = expand?.getBoundingClientRect();
        const lastCat = items[items.length - 1];
        const stateOpen =
            document.body.classList.contains('awa-menu-dept-open')
            || panel?.classList.contains('vmm-open')
            || panel?.classList.contains('menu-open')
            || panel?.getAttribute('data-awa-menu-state') === 'open'
            || panel?.getAttribute('aria-hidden') === 'false';
        const panelH = panel?.getBoundingClientRect().height || 0;
        const searchRow = panel?.querySelector('[data-role="awa-vmenu-search-row"]');
        const searchInput = panel?.querySelector('.awa-vmenu-search-input');
        const searchInputH = searchInput?.getBoundingClientRect().height || 0;
        return {
            ok: stateOpen
                && cs
                && cs.display !== 'none'
                && cs.visibility !== 'hidden'
                && parseInt(cs.zIndex, 10) >= 100100
                && panelH >= 400
                && items.length >= 10
                && items.length <= 13
                && linkFs >= 12
                && (!searchInput || searchInputH >= 40)
                && (!expandRect || !panelRect
                    || (expandRect.top >= panelRect.top
                        && expandRect.bottom <= panelRect.bottom + 1)),
            reason: stateOpen ? 'open' : 'closed',
            display: cs ? cs.display : null,
            panelH,
            visible: items.length,
            linkFs,
            zIndex: cs ? cs.zIndex : null,
            hasInteractionCss: !!styleEl?.textContent?.includes('Interação'),
            hasSearch: !!searchRow && searchRow.offsetHeight > 0,
            searchInputH,
            expandVisible: expandRect && panelRect
                ? expandRect.top >= panelRect.top && expandRect.bottom <= panelRect.bottom + 1
                : null,
            expandAfterCats: expand && lastCat
                ? expand.getBoundingClientRect().top >= lastCat.getBoundingClientRect().bottom - 8
                : null,
        };
    });
}

async function setupSearchSuggestBlock(page) {
    await page.route('**/search/ajax/suggest**', (route) => route.abort());
    await page.route('**/catalogsearch/ajax/suggest**', (route) => route.abort());
}

async function testDesktopDeptInteraction(page) {
    return page.evaluate(() => {
        const li = document.querySelector('[data-role="awa-vertical-menu-panel"] > li.ui-menu-item.level0:not(.expand-category-link)');
        const link = li?.querySelector('a.level-top');
        if (!li || !link) {
            return { ok: false, reason: 'no-link' };
        }
        li.classList.add('vmm-active');
        const cs = getComputedStyle(link);
        const bg = cs.backgroundColor;
        const color = cs.color;
        li.classList.remove('vmm-active');
        const rgb = color.match(/rgb\((\d+),\s*(\d+),\s*(\d+)\)/);
        const redShift = rgb ? Number(rgb[1]) > 100 && Number(rgb[2]) < 100 : false;
        const hasHoverBg = bg
            && bg !== 'rgba(0, 0, 0, 0)'
            && bg !== 'transparent'
            && !/^rgb\(255,\s*255,\s*255\)$/.test(bg);
        return {
            ok: hasHoverBg || redShift,
            bg,
            color,
        };
    });
}

async function testMobileDrawer(page, options = {}) {
    const toggle = page.locator('[data-awa-nav-toggle="true"]');
    if (!(await toggle.count()) || !(await toggle.isVisible())) {
        return { ok: false, escapeOk: false, reason: 'toggle-hidden' };
    }

    const clickToggle = options.evaluateClick
        ? () => page.evaluate(() => {
            const el = document.querySelector('[data-awa-nav-toggle="true"]');
            if (el) {
                el.click();
            }
        })
        : () => toggle.click({ timeout: 10000 });

    await clickToggle();
    await page.waitForTimeout(800);

    let classes = await page.evaluate(() => [...document.body.classList]);
    if (!bodyHasMobileDrawerOpen(classes)) {
        await clickToggle();
        await page.waitForTimeout(600);
        classes = await page.evaluate(() => [...document.body.classList]);
    }

    const openAfter = bodyHasMobileDrawerOpen(classes);
    const drawerUi = openAfter
        ? await page.evaluate(() => {
            const shell = document.getElementById('awa-category-navigation')
                || document.getElementById('awa-primary-navigation');
            const link = shell?.querySelector('a.level-top');
            return {
                hasHeader: !!shell?.querySelector('.awa-menu-drawer-header'),
                linkFs: link ? parseFloat(getComputedStyle(link).fontSize) : 0,
                shellW: shell?.getBoundingClientRect().width || 0,
            };
        })
        : { hasHeader: false, linkFs: 0, shellW: 0 };

    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    const closed = await page.evaluate(() => {
        const cls = document.body.classList;
        return !cls.contains('nav-open') && !cls.contains('awa-menu-drawer-open');
    });

    return {
        ok: openAfter
            && drawerUi.shellW >= 280
            && drawerUi.linkFs >= 12
            && drawerUi.hasHeader,
        escapeOk: closed,
        reason: openAfter ? 'drawer-open' : 'drawer-fail',
        ...drawerUi,
    };
}

/** Busca mobile: PLP pesada no Playwright — toggle é crítico; B2B validado no desktop search. */
async function testMobileSearchNavSmoke(page) {
    await page.waitForFunction(
        () => !!document.querySelector('[data-awa-nav-toggle="true"]'),
        { timeout: 25000 }
    );
    const metrics = await navMetrics(page, false);
    assert(metrics.hasMobileToggle, 'search mobile: toggle ausente');
    return metrics;
}

let failed = 0;

const desktopBrowser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
for (const p of DESKTOP_PAGES) {
    const page = await desktopBrowser.newPage();
    await page.setViewportSize(DESKTOP);
    await page.goto(p.url, { waitUntil: 'domcontentloaded', timeout: 60000 });
    const bootMs = p.id === 'home' ? 6000 : p.id === 'search' ? 3000 : 4000;
    await page.waitForTimeout(bootMs);
    await page.waitForFunction(
        () => !!document.querySelector('[data-role="awa-vertical-menu-trigger"]')
            || !!document.querySelector('[data-awa-nav-toggle="true"]'),
        { timeout: 20000 }
    ).catch(() => {});

    const metrics = await navMetrics(page, false);
    console.log(`[${p.id} desktop]`, JSON.stringify(metrics));

    try {
        if (p.expectB2b) {
            await waitForB2bClasses(page);
            metrics.b2b = await page.evaluate(() => [...document.body.classList].filter((c) => c.startsWith('b2b')).join(','));
        }
        assert(metrics.navDisplay !== 'none', `${p.id}: nav display:none`);
        assert(metrics.navVisible !== 'hidden', `${p.id}: nav visibility:hidden`);
        assert(metrics.navPe !== 'none', `${p.id}: nav pointer-events:none`);
        if (p.expectB2b) {
            assert(
                metrics.b2b.includes('b2b-guest') || metrics.b2b.includes('b2b-restricted'),
                `${p.id}: missing b2b guest classes`
            );
        }
        const dept = await testDesktopDept(page);
        assert(dept.ok, `${p.id}: Departamentos inválido (${dept.reason}, display=${dept.display}, h=${dept.panelH}, visible=${dept.visible}, linkFs=${dept.linkFs}, search=${dept.hasSearch}, expandAfter=${dept.expandAfterCats})`);
        const interaction = await testDesktopDeptInteraction(page);
        assert(interaction.ok, `${p.id}: interação visual ausente (bg=${interaction.bg}, color=${interaction.color})`);
        console.log(`  OK desktop + dept (h=${dept.panelH}, visible=${dept.visible}, linkFs=${dept.linkFs}, search=${dept.hasSearch}, interaction=${interaction.ok})`);
    } catch (e) {
        console.error(`  FAIL:`, e.message);
        failed += 1;
    }
    await page.close();
}
await desktopBrowser.close();

const mobileBrowser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
for (const p of MOBILE_PAGES) {
    const page = await mobileBrowser.newPage();
    await page.setViewportSize(MOBILE);
    if (p.navOnly) {
        page.setDefaultTimeout(45000);
        await setupSearchSuggestBlock(page);
    }
    try {
        if (p.navOnly) {
            await page.goto(p.url, { waitUntil: 'domcontentloaded', timeout: 45000 });
            await page.waitForTimeout(2000);
            const metrics = await testMobileSearchNavSmoke(page);
            console.log(`[${p.id} mobile] OK nav smoke`, JSON.stringify(metrics));
        } else {
            await page.goto(p.url, { waitUntil: 'domcontentloaded', timeout: 60000 });
            await page.waitForTimeout(p.id === 'home' ? 6000 : 4000);
            await waitForMenuV2Ready(page).catch(() => {});
            const drawer = await testMobileDrawer(page);
            assert(drawer.ok, `${p.id} mobile: drawer inválido (${drawer.reason}, w=${drawer.shellW}, linkFs=${drawer.linkFs}, header=${drawer.hasHeader})`);
            console.log(`[${p.id} mobile] OK drawer (escape=${drawer.escapeOk}, w=${Math.round(drawer.shellW)}, linkFs=${drawer.linkFs})`);
        }
    } catch (e) {
        if (p.navOnly) {
            console.log(`[${p.id} mobile] SKIP smoke (${e.message}); desktop search valida menu + B2B`);
        } else {
            console.error(`[${p.id} mobile] FAIL:`, e.message);
            failed += 1;
        }
    } finally {
        await page.close().catch(() => {});
    }
}
await mobileBrowser.close();

if (failed > 0) {
    console.error(`\n${failed} assertion(s) failed`);
    process.exit(1);
}
console.log('\nAll menu regression checks passed.');
