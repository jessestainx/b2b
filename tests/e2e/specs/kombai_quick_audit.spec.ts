import { test, expect } from '@playwright/test';

test('capture home page screenshots', async ({ page }) => {
    const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'https://srv1113343.hstgr.cloud/';
    
    // Desktop
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(baseURL, { waitUntil: 'networkidle' });
    await page.screenshot({ path: 'tests/e2e/tmp/home_desktop.png', fullPage: true });
    
    // Mobile
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(baseURL, { waitUntil: 'networkidle' });
    await page.screenshot({ path: 'tests/e2e/tmp/home_mobile.png', fullPage: true });
});
