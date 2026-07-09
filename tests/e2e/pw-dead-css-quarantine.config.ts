import path from 'path';
import { defineConfig, devices } from '@playwright/test';
import { resolveBaseUrl } from './helpers/resolve-base-url';

/**
 * Config dedicada e leve para specs/dead-css-quarantine.spec.ts (Fase 6A).
 * Sem vídeo/trace/screenshot — reduz carga no servidor de produção
 * compartilhado (ver nota em AGENTS.md sobre custo de Chrome headless).
 */
const resolvedBaseUrl = resolveBaseUrl('pw-dead-css-quarantine');

export default defineConfig({
  testDir: path.join(__dirname, 'specs'),
  testMatch: /dead-css-quarantine\.spec\.ts/,
  outputDir: path.join(__dirname, 'test-results'),
  fullyParallel: false,
  workers: 1,
  retries: 1,
  timeout: 55_000,
  reporter: [
    ['list'],
    ['json', { outputFile: path.join(__dirname, 'reports/dead-css-quarantine-results.json') }],
  ],
  use: {
    baseURL: resolvedBaseUrl,
    ignoreHTTPSErrors: true,
    screenshot: 'off',
    video: 'off',
    trace: 'off',
    actionTimeout: 10_000,
    navigationTimeout: 15_000,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    launchOptions: {
      args: [
        '--no-sandbox',
        '--disable-dev-shm-usage',
        '--disable-setuid-sandbox',
        '--js-flags=--max-old-space-size=512',
        '--disable-extensions',
        '--disable-plugins',
      ],
    },
  },
  projects: [
    {
      name: 'notebook-1366',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1366, height: 768 },
      },
    },
  ],
});
