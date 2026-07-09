import path from 'path';
import { defineConfig, devices } from '@playwright/test';
import { resolveBaseUrl } from './helpers/resolve-base-url';

const resolvedBaseUrl = resolveBaseUrl('pw-mcp-visual.safe');

export default defineConfig({
  testDir: path.join(__dirname, 'specs'),
  testMatch: /mcp-visual-ops\.spec\.ts/,
  outputDir: path.join(__dirname, 'test-results-safe'),
  globalSetup: path.join(__dirname, 'helpers/global-setup.ts'),
  globalTeardown: path.join(__dirname, 'helpers/global-teardown.ts'),
  fullyParallel: false,
  workers: 1,
  retries: 1,
  timeout: 180_000,
  reporter: [
    ['list'],
    ['json', { outputFile: path.join(__dirname, 'reports/mcp-visual-playwright-results-safe.json') }],
  ],
  use: {
    baseURL: resolvedBaseUrl,
    ignoreHTTPSErrors: true,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    screenshot: 'only-on-failure',
    trace: 'off',
    actionTimeout: 15_000,
    navigationTimeout: 60_000,
  },
  projects: [
    {
      name: 'firefox-desktop-1366',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 1366, height: 768 },
      },
    },
    {
      name: 'firefox-mobile-390',
      use: {
        browserName: 'firefox',
        userAgent:
          'Mozilla/5.0 (Android 11; Mobile; rv:109.0) Gecko/109.0 Firefox/109.0',
        viewport: { width: 390, height: 844 },
      },
    },
  ],
});
