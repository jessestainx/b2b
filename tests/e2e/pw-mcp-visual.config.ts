import path from 'path';
import { defineConfig, devices } from '@playwright/test';
import { resolveBaseUrl } from './helpers/resolve-base-url';

const resolvedBaseUrl = resolveBaseUrl('pw-mcp-visual');

export default defineConfig({
  testDir: path.join(__dirname, 'specs'),
  testMatch: /mcp-visual-ops\.spec\.ts/,
  outputDir: path.join(__dirname, 'test-results'),
  globalSetup: path.join(__dirname, 'helpers/global-setup.ts'),
  globalTeardown: path.join(__dirname, 'helpers/global-teardown.ts'),
  fullyParallel: false,
  workers: 1,
  retries: 1,
  timeout: 180_000,
  reporter: [
    ['list'],
    ['json', { outputFile: path.join(__dirname, 'reports/mcp-visual-playwright-results.json') }],
    ['html', { outputFolder: path.join(__dirname, 'reports/mcp-visual-html'), open: 'never' }],
  ],
  use: {
    baseURL: resolvedBaseUrl,
    ignoreHTTPSErrors: true,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    actionTimeout: 15_000,
    navigationTimeout: 60_000,
  },
  projects: [
    // NOTE: Chromium/Chrome cannot load awamotos.com reliably on this server —
    // the renderer freezes for 120s+ due to CSS/JS load complexity.
    // Firefox loads the page in <1s and is used for all visual QA projects.
    {
      name: 'firefox-desktop-1366',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 1366, height: 768 },
      },
    },
    {
      name: 'firefox-desktop-1280',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 1280, height: 800 },
      },
    },
    {
      name: 'firefox-mobile-390',
      use: {
        browserName: 'firefox',
        headless: true,
        viewport: { width: 390, height: 844 },
        userAgent: 'Mozilla/5.0 (Android 11; Mobile; rv:109.0) Gecko/109.0 Firefox/109.0',
        isMobile: false,
        hasTouch: false,
        deviceScaleFactor: 2,
        launchOptions: {
          timeout: 60_000,
        },
      },
    },
  ],
});
