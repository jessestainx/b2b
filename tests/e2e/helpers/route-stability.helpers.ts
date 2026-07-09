/**
 * route-stability.helpers.ts — AWA Motos
 * ========================================
 * Instrumentacao compartilhada para a Fase H0.1 — Route Stability Investigation.
 *
 * Read-only. Nao aplica nenhuma correcao de produto — apenas observa e registra
 * o comportamento de rede/renderer durante a navegacao para rotas conhecidas
 * como instaveis (ver docs/visual-qa/fase-h0-header-bug-report-2026-07-08.md,
 * BUG-H0-CRIT-01 e BUG-H0-CRIT-02).
 *
 * Todas as chamadas que podem travar caso o main thread do renderer esteja
 * bloqueado (page.evaluate, CDP session, screenshot) sao protegidas por
 * withTimeout() para nunca pendurar o worker do Playwright indefinidamente —
 * mesmo padrao de "rede de seguranca" usado em fase-h0-header-functional-qa.spec.ts
 * (safeWait/safeCount).
 */
import fs from 'fs';
import path from 'path';
import type { Page, BrowserContext, Request, CDPSession } from '@playwright/test';
import { blockOptionalThirdParty } from './third-party-block';

/* ──────────────────────────────────────────────────────────────────────
 * Utilitarios genericos
 * ────────────────────────────────────────────────────────────────────── */

/** Nunca deixa uma promise pendurar o teste indefinidamente. */
export async function withTimeout<T>(promise: Promise<T>, ms: number, fallback: T): Promise<T> {
  return Promise.race<T>([
    promise.catch(() => fallback),
    new Promise<T>((resolve) => setTimeout(() => resolve(fallback), ms)),
  ]);
}

export function ensureDir(dir: string): void {
  fs.mkdirSync(dir, { recursive: true });
}

/* ──────────────────────────────────────────────────────────────────────
 * Tipos de evidencia
 * ────────────────────────────────────────────────────────────────────── */
export interface RequestEvent {
  seq: number;
  attempt: string;
  tMs: number;
  event: 'started' | 'finished' | 'failed';
  url: string;
  method: string;
  resourceType: string;
  status?: number;
  failureText?: string;
}

export interface ConsoleEntry {
  attempt: string;
  tMs: number;
  type: string;
  text: string;
}

export interface PageErrorEntry {
  attempt: string;
  tMs: number;
  message: string;
  stack?: string;
}

export interface CdpLifecycleEvent {
  attempt: string;
  tMs: number;
  name: string;
  cdpTimestamp: number;
}

export interface PendingRequestInfo {
  url: string;
  method: string;
  resourceType: string;
  ageMs: number;
}

export interface PendingSnapshot {
  attempt: string;
  label: string;
  tMs: number;
  pendingCount: number;
  pending: PendingRequestInfo[];
}

/* ──────────────────────────────────────────────────────────────────────
 * Recorder — anexa listeners de page/network e produz evidencia serializavel
 * ────────────────────────────────────────────────────────────────────── */
export class RouteStabilityRecorder {
  readonly requestEvents: RequestEvent[] = [];
  readonly consoleEntries: ConsoleEntry[] = [];
  readonly pageErrors: PageErrorEntry[] = [];
  readonly cdpLifecycle: CdpLifecycleEvent[] = [];

  private readonly inFlight = new Map<Request, number>();
  private t0 = 0;
  private seq = 0;
  private currentAttempt = 'unset';
  private cdpSession: CDPSession | null = null;

  constructor(private readonly page: Page) {}

  /** Marca t=0 e o rotulo da tentativa atual (chamar antes de cada page.goto). */
  markStart(attemptLabel: string): void {
    this.t0 = Date.now();
    this.currentAttempt = attemptLabel;
  }

  private tMs(): number {
    return Date.now() - this.t0;
  }

  attachNetworkAndConsole(): void {
    this.page.on('request', (req) => {
      this.inFlight.set(req, Date.now());
      this.requestEvents.push({
        seq: this.seq++, attempt: this.currentAttempt, tMs: this.tMs(), event: 'started',
        url: req.url(), method: req.method(), resourceType: req.resourceType(),
      });
    });

    this.page.on('requestfinished', (req) => {
      this.inFlight.delete(req);
      const tMs = this.tMs();
      const seq = this.seq++;
      const attempt = this.currentAttempt;
      req.response().then((res) => {
        this.requestEvents.push({
          seq, attempt, tMs, event: 'finished',
          url: req.url(), method: req.method(), resourceType: req.resourceType(),
          status: res?.status(),
        });
      }).catch(() => {
        this.requestEvents.push({
          seq, attempt, tMs, event: 'finished',
          url: req.url(), method: req.method(), resourceType: req.resourceType(),
        });
      });
    });

    this.page.on('requestfailed', (req) => {
      this.inFlight.delete(req);
      this.requestEvents.push({
        seq: this.seq++, attempt: this.currentAttempt, tMs: this.tMs(), event: 'failed',
        url: req.url(), method: req.method(), resourceType: req.resourceType(),
        failureText: req.failure()?.errorText,
      });
    });

    this.page.on('console', (msg) => {
      const type = msg.type();
      if (type === 'error' || type === 'warning') {
        this.consoleEntries.push({ attempt: this.currentAttempt, tMs: this.tMs(), type, text: msg.text() });
      }
    });

    this.page.on('pageerror', (err) => {
      this.pageErrors.push({ attempt: this.currentAttempt, tMs: this.tMs(), message: err.message, stack: err.stack });
    });
  }

  /**
   * Liga o CDP Page domain para capturar lifecycle events (init, DOMContentLoaded,
   * load, networkAlmostIdle, networkIdle) direto do browser process — funciona
   * mesmo que o main thread do renderer da pagina esteja bloqueado, pois o
   * domain Page do CDP roda no processo do browser, nao no processo/thread do
   * renderer da pagina. Protegido por timeout: se a sessao CDP nao conseguir
   * ser criada (renderer morto/canal fechado), falha silenciosamente e fica
   * registrado como cdpAvailable=false na evidencia.
   */
  async attachCdpLifecycle(): Promise<boolean> {
    const session = await withTimeout(
      this.page.context().newCDPSession(this.page) as Promise<CDPSession>,
      8_000,
      null as unknown as CDPSession,
    );
    if (!session) return false;
    this.cdpSession = session;
    try {
      await withTimeout(session.send('Page.enable').then(() => undefined), 5_000, undefined);
      session.on('Page.lifecycleEvent', (e: { name: string; timestamp: number }) => {
        this.cdpLifecycle.push({ attempt: this.currentAttempt, tMs: this.tMs(), name: e.name, cdpTimestamp: e.timestamp });
      });
      return true;
    } catch {
      return false;
    }
  }

  async detachCdp(): Promise<void> {
    if (!this.cdpSession) return;
    await withTimeout(this.cdpSession.detach(), 3_000, undefined as unknown as void);
    this.cdpSession = null;
  }

  snapshotPending(label: string): PendingSnapshot {
    const now = Date.now();
    const pending = Array.from(this.inFlight.entries()).map(([req, startedAt]) => ({
      url: req.url(), method: req.method(), resourceType: req.resourceType(), ageMs: now - startedAt,
    }));
    return { attempt: this.currentAttempt, label, tMs: this.tMs(), pendingCount: pending.length, pending };
  }
}

/**
 * Agenda snapshots de "requests pendentes" em marcas de tempo fixas (ex.: 10s,
 * 30s, 60s) relativas ao t0 da tentativa atual do recorder. Usa setTimeout do
 * Node — funciona mesmo que a promise do page.goto() esteja pendurada, porque
 * os listeners de rede (page.on('request'/'requestfinished'/'requestfailed'))
 * sao entregues via protocolo CDP de forma assincrona e independente da
 * resolucao do goto.
 */
export function scheduleSnapshots(
  recorder: RouteStabilityRecorder,
  marksMs: number[],
  out: PendingSnapshot[],
): { cancel: () => void } {
  const timers = marksMs.map((ms) =>
    setTimeout(() => {
      out.push(recorder.snapshotPending(`t+${ms}ms`));
    }, ms),
  );
  return {
    cancel: () => timers.forEach((t) => clearTimeout(t)),
  };
}

/* ──────────────────────────────────────────────────────────────────────
 * Captura de evidencia auxiliar (protegida por timeout)
 * ────────────────────────────────────────────────────────────────────── */
export async function safeScreenshot(page: Page, filePath: string): Promise<boolean> {
  ensureDir(path.dirname(filePath));
  const ok = await withTimeout(
    page.screenshot({ path: filePath, timeout: 8_000 }).then(() => true),
    10_000,
    false,
  );
  return ok;
}

export async function safePerformanceEntries(page: Page): Promise<unknown> {
  return withTimeout(
    page.evaluate(() => {
      try {
        return {
          navigation: performance.getEntriesByType('navigation'),
          resourceCount: performance.getEntriesByType('resource').length,
          readyState: document.readyState,
        };
      } catch (e) {
        return { error: String(e) };
      }
    }),
    6_000,
    { error: 'TIMEOUT_EVALUATE' },
  );
}

export async function safeReadyState(page: Page): Promise<string> {
  return withTimeout(page.evaluate(() => document.readyState), 5_000, 'TIMEOUT_EVALUATE');
}

/* ──────────────────────────────────────────────────────────────────────
 * Bloqueio de recursos para isolamento progressivo (Tarefa 4)
 * ────────────────────────────────────────────────────────────────────── */

/** Variacao D — bloqueia imagens e fontes (qualquer origem), so para diagnostico. */
export async function blockImagesAndFonts(target: BrowserContext): Promise<void> {
  await target.route('**/*', async (route) => {
    const type = route.request().resourceType();
    if (type === 'image' || type === 'font') {
      await route.abort('blockedbyclient');
      return;
    }
    await route.continue();
  });
}

export type WaitUntilEvent = 'commit' | 'domcontentloaded' | 'load';

export interface WaitUntilAttemptResult {
  waitUntil: WaitUntilEvent;
  timeoutMs: number;
  fired: boolean;
  elapsedMs: number;
  httpStatus: number | null;
  errorMessage: string | null;
  readyStateAtEnd: string;
  pendingSnapshots: PendingSnapshot[];
  screenshotPath: string | null;
}

/**
 * Executa uma tentativa de navegacao com um waitUntil/timeout especifico,
 * coletando pending snapshots em 10s/30s/60s (o que couber dentro do timeout)
 * e um screenshot no momento do timeout (se nao disparar).
 *
 * Cada tentativa e uma NOVA navegacao (page.goto independente) — nao uma
 * continuacao da tentativa anterior. Isso reflete o script de reproducao
 * isolado usado no bug report original (BUG-H0-CRIT-01/02): commit,
 * domcontentloaded e load foram medidos como 3 goto() distintos, nao como
 * marcas progressivas de uma unica navegacao.
 */
export async function attemptNavigation(opts: {
  page: Page;
  recorder: RouteStabilityRecorder;
  url: string;
  waitUntil: WaitUntilEvent;
  timeoutMs: number;
  screenshotPath: string | null;
}): Promise<WaitUntilAttemptResult> {
  const { page, recorder, url, waitUntil, timeoutMs, screenshotPath } = opts;
  recorder.markStart(waitUntil);

  const allMarks = [10_000, 30_000, 60_000].filter((ms) => ms < timeoutMs);
  const pendingSnapshots: PendingSnapshot[] = [];
  const { cancel } = scheduleSnapshots(recorder, allMarks, pendingSnapshots);

  const startedAt = Date.now();
  let fired = true;
  let httpStatus: number | null = null;
  let errorMessage: string | null = null;

  try {
    const resp = await page.goto(url, { waitUntil, timeout: timeoutMs });
    httpStatus = resp?.status() ?? null;
  } catch (e) {
    fired = false;
    errorMessage = e instanceof Error ? e.message : String(e);
  }
  const elapsedMs = Date.now() - startedAt;
  cancel();

  // Snapshot final sempre (util tanto em sucesso quanto em timeout).
  pendingSnapshots.push(recorder.snapshotPending(fired ? 'final-resolved' : 'final-timeout'));

  const readyStateAtEnd = page.isClosed() ? 'PAGE_CLOSED' : await safeReadyState(page);

  let capturedScreenshotPath: string | null = null;
  if (screenshotPath && !page.isClosed()) {
    const ok = await safeScreenshot(page, screenshotPath);
    capturedScreenshotPath = ok ? screenshotPath : null;
  }

  return {
    waitUntil,
    timeoutMs,
    fired,
    elapsedMs,
    httpStatus,
    errorMessage,
    readyStateAtEnd,
    pendingSnapshots,
    screenshotPath: capturedScreenshotPath,
  };
}

/* ──────────────────────────────────────────────────────────────────────
 * Matriz de variacoes — Tarefa 4 (isolamento progressivo)
 * F (headed/headless) e G (viewport 1440) sao controlados pelo Playwright
 * project (pw-fase-h0-route-stability.config.ts), nao por esta matriz.
 * ────────────────────────────────────────────────────────────────────── */
export type VariationId = 'A' | 'B' | 'C' | 'D' | 'E';

export interface VariationDef {
  id: VariationId;
  label: string;
  description: string;
  javaScriptEnabled?: boolean;
  serviceWorkers?: 'allow' | 'block';
  applyRouting?: (context: BrowserContext) => Promise<void>;
  /** Load tende a nao fazer sentido nesta variacao (ex.: JS off ainda carrega recursos normalmente, mantido por completude). */
  skipLoadWait?: boolean;
}

export const VARIATIONS: VariationDef[] = [
  {
    id: 'A',
    label: 'Browser limpo (baseline)',
    description: 'Contexto novo por tentativa, sem storage anterior, sem bloqueios de rede.',
  },
  {
    id: 'B',
    label: 'Service worker bloqueado',
    description: 'Contexto criado com serviceWorkers: "block" (Playwright nunca registra/ativa SW).',
    serviceWorkers: 'block',
  },
  {
    id: 'C',
    label: 'Terceiros bloqueados',
    description: 'Bloqueia hosts de terceiros conhecidos (GA/GTM/Facebook/Hotjar/etc., ver helpers/third-party-block.ts) via context.route.',
    applyRouting: blockOptionalThirdParty,
  },
  {
    id: 'D',
    label: 'Imagens/fontes bloqueadas',
    description: 'Bloqueia resourceType image e font via context.route — diagnostico apenas, nao reflete UX real.',
    applyRouting: blockImagesAndFonts,
  },
  {
    id: 'E',
    label: 'JS desabilitado',
    description: 'Contexto criado com javaScriptEnabled: false — diagnostico do HTML/CSS base sem nenhuma execucao de script.',
    javaScriptEnabled: false,
  },
];
