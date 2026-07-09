import { defineConfig, devices } from './tests/e2e/node_modules/@playwright/test';
import path from 'path';
import { resolveBaseUrl } from './tests/e2e/helpers/resolve-base-url';

const safeModeEnabled = process.env.PW_SAFE_MODE !== '0';
const defaultWorkers = safeModeEnabled ? 1 : 2;
const configuredWorkers = Number(process.env.PW_WORKERS ?? (process.env.CI ? 1 : defaultWorkers));

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
// import dotenv from 'dotenv';
// import path from 'path';
// dotenv.config({ path: path.resolve(__dirname, '.env') });

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  testDir: './tests/e2e/specs',
  outputDir: './tests/e2e/test-results-root',
  /* Magento não deve receber suíte visual paralela por padrão. */
  fullyParallel: false,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: configuredWorkers,
  timeout: process.env.CI ? 60_000 : 120_000,
  expect: {
    timeout: 8_000,
  },
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: [
    ['list'],
    ['html', { outputFolder: path.join('tests', 'e2e', 'reports', 'root-html'), open: 'never' }],
  ],
  snapshotPathTemplate: '{testDir}/snapshots/{testFilePath}/{arg}{ext}',
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    baseURL: resolveBaseUrl('root-playwright'),

    /* Salva evidências automáticas para depuração de falhas. */
    screenshot: 'only-on-failure',
    video: process.env.PW_VIDEO === '1' ? 'retain-on-failure' : 'off',
    trace: process.env.PW_TRACE === '1' ? 'on-first-retry' : 'off',
    actionTimeout: 10_000,
    navigationTimeout: 20_000,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'desktop-1440',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 1000 },
      },
    },
    {
      name: 'notebook-1280',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 800 },
      },
    },
    {
      name: 'mobile-390',
      use: {
        ...devices['iPhone 14'],
        viewport: { width: 390, height: 844 },
      },
    },
  ],
});
