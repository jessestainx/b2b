import path from 'path';
import { defineConfig, devices } from '@playwright/test';
import { resolveBaseUrl } from './helpers/resolve-base-url';

const resolvedBaseUrl = resolveBaseUrl('pw-visual-core');

export default defineConfig({
  testDir: path.join(__dirname, 'specs'),
  testMatch: /visual-audit-core-regression\.spec\.ts/,
  outputDir: path.join(__dirname, 'test-results'),
  snapshotDir: path.join(__dirname, 'snapshots'),
  globalSetup: path.join(__dirname, 'helpers/global-setup.ts'),
  globalTeardown: path.join(__dirname, 'helpers/global-teardown.ts'),
  fullyParallel: false,
  workers: 1,
  retries: 1,
  timeout: 180_000,
  reporter: [
    ['list'],
    ['json', { outputFile: path.join(__dirname, 'reports/visual-core-results.json') }],
    ['html', { outputFolder: path.join(__dirname, 'reports/visual-core-html'), open: 'never' }],
  ],
  use: {
    baseURL: resolvedBaseUrl,
    ignoreHTTPSErrors: true,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
    actionTimeout: 15_000,
    navigationTimeout: 60_000,
  },
  projects: [
    {
      name: 'core-firefox-desktop-1280',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 1280, height: 800 },
      },
    },
    {
      name: 'core-firefox-mobile-375',
      use: {
        browserName: 'firefox',
        viewport: { width: 375, height: 667 },
        userAgent: 'Mozilla/5.0 (Android 11; Mobile; rv:109.0) Gecko/109.0 Firefox/109.0',
      },
    },
  ],
});
