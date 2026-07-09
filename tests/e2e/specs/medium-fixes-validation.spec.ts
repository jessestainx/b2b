/**
 * Medium Visual Fixes Validation
 * -----------------------------------------------------------------------------
 * Valida os fixes aplicados em _medium-visual-fixes.less.
 * Roda LOCAL com: BASE_URL=http://localhost
 * 
 * Uso:
 *   cd tests/e2e
 *   AWA_BASE_URL=http://localhost npx playwright test medium-fixes-validation.spec.ts
 */

import { test, expect } from '@playwright/test';

const BASE = process.env.AWA_BASE_URL || 'http://localhost';

const VIEWPORTS = [
    { name: 'mobile-390', width: 390, height: 844 },
    { name: 'tablet-768', width: 768, height: 1024 },
    { name: 'desktop-1440', width: 1440, height: 900 },
];

const PAGES = [
    { name: 'home', path: '/' },
    { name: 'plp', path: '/carcacas.html' },
    { name: 'pdp', path: '/bagageiro-titan-150-09-13-modelo-preto-macico-3000.html' },
    { name: 'cart', path: '/checkout/cart/' },
    { name: 'b2b-login', path: '/b2b/account/login/' },
    { name: 'b2b-register', path: '/b2b/register/' },
    { name: 'search', path: '/catalogsearch/result/?q=test' },
];

for (const vp of VIEWPORTS) {
    test.describe(`Viewport ${vp.name} (${vp.width}x${vp.height})`, () => {
        test.use({ viewport: { width: vp.width, height: vp.height } });

        for (const page of PAGES) {
            test(`${page.name}: sem overflow horizontal`, async ({ page: p }) => {
                const url = `${BASE}${page.path}`;
                const response = await p.goto(url, { waitUntil: 'networkidle' });

                if (!response || !response.ok()) {
                    test.skip(true, `${url} não retornou 2xx`);
                    return;
                }

                // Verifica overflow horizontal
                const overflowX = await p.evaluate(() => {
                    return document.documentElement.scrollWidth > document.documentElement.clientWidth;
                });

                expect(overflowX, `overflow horizontal em ${vp.name}/${page.name}`).toBe(false);
            });

            test(`${page.name}: header presente`, async ({ page: p }) => {
                const url = `${BASE}${page.path}`;
                const response = await p.goto(url, { waitUntil: 'networkidle' });

                if (!response || !response.ok()) {
                    test.skip(true, `${url} não retornou 2xx`);
                    return;
                }

                const headerVisible = await p.locator('.awa-site-header, header.page-header').first().isVisible();
                expect(headerVisible, `header não visível em ${vp.name}/${page.name}`).toBeTruthy();
            });

            test(`${page.name}: sem erros no console`, async ({ page: p }) => {
                const errors: string[] = [];
                p.on('console', (msg) => {
                    if (msg.type() === 'error') {
                        errors.push(msg.text());
                    }
                });

                const url = `${BASE}${page.path}`;
                const response = await p.goto(url, { waitUntil: 'networkidle' });

                if (!response || !response.ok()) {
                    test.skip(true, `${url} não retornou 2xx`);
                    return;
                }

                // Filtrar erros conhecidos (third-party)
                const critical = errors.filter(e =>
                    !e.includes('favicon') &&
                    !e.includes('gtag') &&
                    !e.includes('analytics') &&
                    !e.includes('recaptcha') &&
                    !e.includes('gtm.js')
                );

                expect(critical, `erros no console em ${vp.name}/${page.name}: ${critical.join(', ')}`).toHaveLength(0);
            });
        }
    });
}
