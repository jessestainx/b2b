import { defineConfig, devices } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { resolveBaseUrl } from './helpers/resolve-base-url';

/** Carrega tests/e2e/.env para TEST_USER, TEST_PASS, etc. (sem sobrescrever env do shell). */
function loadEnvFile(filePath: string): void {
  if (!fs.existsSync(filePath)) {
    return;
  }

  for (const line of fs.readFileSync(filePath, 'utf8').split('\n')) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) {
      continue;
    }

    const eq = trimmed.indexOf('=');
    if (eq <= 0) {
      continue;
    }

    const key = trimmed.slice(0, eq).trim();
    const raw = trimmed.slice(eq + 1).trim();
    const value = raw.replace(/^['"]|['"]$/g, '');

    if (!(key in process.env)) {
      process.env[key] = value;
    }
  }
}

loadEnvFile(path.join(__dirname, '.env'));

/** Caminho fixo legado (CI deploy); se inexistente, Playwright usa o browser bundled. */
function resolveChromiumHeadlessShell(): string | undefined {
  const candidates = [
    process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH,
    '/home/deploy/.cache/ms-playwright/chromium_headless_shell-1208/chrome-headless-shell-linux64/chrome-headless-shell',
    path.join(process.env.HOME || '', '.cache/ms-playwright/chromium_headless_shell-1208/chrome-headless-shell-linux64/chrome-headless-shell'),
  ].filter((p): p is string => !!p && p.length > 0);
  for (const p of candidates) {
    try {
      if (fs.existsSync(p)) {
        return p;
      }
    } catch {
      /* ignore */
    }
  }
  return undefined;
}

const chromiumShell = resolveChromiumHeadlessShell();

/**
 * launchOptions exclusivas para projetos Chromium.
 * NÃO aplicar ao Firefox/WebKit — os args são Chrome-specific e executablePath
 * aponta para chrome-headless-shell, incompatível com geckodriver/webkit.
 */
const chromiumLaunchOptions = {
  ...(chromiumShell ? { executablePath: chromiumShell } : {}),
  timeout: 300_000,
  args: [
    '--no-sandbox',
    '--disable-dev-shm-usage',
    '--disable-setuid-sandbox',
    // Limita RAM para não OOM o servidor de produção (~512MB por renderer)
    '--js-flags=--max-old-space-size=512',
    '--disable-extensions',
    '--disable-plugins',
    '--renderer-process-limit=2',
  ],
};

/**
 * Playwright Config — AWA Motos Header Visual Tests
 * Breakpoints: Tablet (768–1024px), Notebook (1024–1366px)
 */
const resolvedBaseUrl = resolveBaseUrl('playwright');

export default defineConfig({
  testDir: path.join(__dirname, 'specs'),
  // Evitar conflito entre sessões root (VS Code server) e deploy (terminal).
  // Root usa /tmp/pw-root-results para não conflitar com test-results/ do deploy.
  outputDir: process.env.PW_OUTPUT_DIR
    ? path.resolve(process.env.PW_OUTPUT_DIR)
    : process.getuid != null && process.getuid() === 0
      ? '/tmp/pw-root-results'
      : path.join(__dirname, 'test-results'),
  snapshotDir: path.join(__dirname, 'snapshots'),

  /* Timeout controlado: evita jobs presos no CI, mantendo margem local para debug. */
  timeout: process.env.CI ? 60_000 : 120_000,
  expect: {
    timeout: 8_000,
    /* Tolerância para comparação de screenshots: 0.3% de pixels diferentes */
    toHaveScreenshot: {
      maxDiffPixelRatio: 0.003,
      animations: 'disabled',
    },
  },

  fullyParallel: false, // serial para comparações visuais consistentes
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 1 : 2,

  /* Cleanup garantido ao final — evita processos Chrome órfãos no servidor */
  globalTeardown: path.join(__dirname, 'helpers/global-teardown.ts'),

  reporter: [
    ['html', { outputFolder: path.join(__dirname, 'reports/html'), open: 'never' }],
    ['json', { outputFile: path.join(__dirname, 'reports/results.json') }],
    ['list'],
  ],

  use: {
    baseURL: resolvedBaseUrl,
    /* Ignora erros TLS caso o certificado seja auto-assinado em staging */
    ignoreHTTPSErrors: true,
    /* Captura evidências automáticas para falhas reais e análise no Trace Viewer */
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'on-first-retry',
    actionTimeout: 10_000,
    navigationTimeout: 20_000,
    /* Locale BR para renderização correta de fontes/datas */
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    // Nota: launchOptions com executablePath/args Chrome-specific ficam nos projetos
    // Chromium abaixo — não aqui, para não corromper Firefox/WebKit.
    launchOptions: {
      timeout: 300_000,
    },
  },

  projects: [
    /* ── DESKTOP ───────────────────────────────────────────── */
    {
      name: 'desktop-1440',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 1000 },
        launchOptions: chromiumLaunchOptions,
      },
    },
    {
      name: 'desktop-1920',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1920, height: 1080 },
        launchOptions: chromiumLaunchOptions,
      },
    },

    /* ── TABLET ────────────────────────────────────────────── */
    {
      name: 'tablet-768',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 768, height: 1024 },
        launchOptions: chromiumLaunchOptions,
      },
    },
    {
      name: 'tablet-960',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 960, height: 768 },
        launchOptions: chromiumLaunchOptions,
      },
    },
    {
      name: 'tablet-1024',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1024, height: 768 },
        launchOptions: chromiumLaunchOptions,
      },
    },

    /* ── NOTEBOOK (telas pequenas) ──────────────────────────── */
    {
      name: 'notebook-1024',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1024, height: 600 },
        launchOptions: chromiumLaunchOptions,
      },
    },
    {
      name: 'notebook-1280',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 800 },
        launchOptions: chromiumLaunchOptions,
      },
    },
    {
      name: 'notebook-1366',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1366, height: 768 },
        launchOptions: chromiumLaunchOptions,
      },
    },

    /* ── FIREFOX (cross-browser) ─────────────────────────────── */
    {
      name: 'firefox-tablet-768',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 768, height: 1024 },
      },
    },
    {
      name: 'firefox-notebook-1280',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 1280, height: 720 },
      },
    },

    /* ── WEBKIT/SAFARI ──────────────────────────────────────── */
    {
      name: 'webkit-tablet-768',
      use: {
        ...devices['Desktop Safari'],
        viewport: { width: 768, height: 1024 },
      },
    },
    {
      name: 'webkit-notebook-1280',
      use: {
        ...devices['Desktop Safari'],
        viewport: { width: 1280, height: 720 },
      },
    },

    /* ── MOBILE ─────────────────────────────────────────────── */
    {
      name: 'mobile-375',
      use: {
        ...devices['iPhone SE'],
        viewport: { width: 375, height: 667 },
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
