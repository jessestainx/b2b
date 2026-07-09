import { defineConfig, devices } from '@playwright/test';
import { resolveBaseUrl } from './helpers/resolve-base-url';

const resolvedBaseUrl = resolveBaseUrl('pw-pdp-quick');
export default defineConfig({
  testDir: './specs',
  timeout: 180_000,
  expect: { timeout: 8_000 },
  retries: 1,
  workers: 1,
  reporter: [['list']],
  use: {
    baseURL: resolvedBaseUrl,
    ignoreHTTPSErrors: true,
    screenshot: 'on',
  },
  projects: [
    {
      name: 'desktop-1280',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } },
      testMatch: /pdp-(layout|audit)\.spec\.ts/,
    },
    {
      name: 'mobile-375',
      use: { ...devices['Pixel 5'] },
      testMatch: /pdp-(layout|audit)\.spec\.ts/,
    },
  ],
});
