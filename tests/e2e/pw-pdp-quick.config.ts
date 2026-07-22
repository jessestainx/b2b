import { defineConfig, devices } from '@playwright/test';

function resolveCiBaseUrl(cfg) {
  const raw = process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || '';
  if (!raw) {
    throw new Error(`[${cfg}] BASE_URL ausente (staging obrigatorio; producao NO-GO Fase 1)`);
  }
  const u = new URL(raw);
  const host = u.hostname.toLowerCase();
  if (host.includes(['awa','motos','.com'].join('')) || host === ['72','61','94','22'].join('.')) {
    throw new Error(`[${cfg}] URL de producao bloqueada (NO-GO Fase 1)`);
  }
  if (String(process.env.ALLOW_PRODUCTION_VALIDATION || '').toLowerCase() === 'true') {
    throw new Error(`[${cfg}] ALLOW_PRODUCTION_VALIDATION rejeitado na Fase 1`);
  }
  return raw.replace(/\/$/, '');
}
export default defineConfig({
  testDir: './specs',
  timeout: 180_000,
  expect: { timeout: 8_000 },
  retries: 1,
  workers: 1,
  reporter: [['list']],
  use: {
    baseURL: resolveCiBaseUrl('pw-pdp-quick.config.ts'),
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
