import { resolveBaseUrl } from './resolve-base-url';

let cachedBaseUrl: string | null = null;

const BLOCKED_HOST_MARKERS = [
  ["awa", "motos", ".com"].join(""),
  "72.61.94.22",
] as const;

function isBlockedProductionHost(hostname: string): boolean {
  const host = hostname.toLowerCase();
  return BLOCKED_HOST_MARKERS.some((marker) => host.includes(marker));
}

function assertSafeUrl(rawUrl: string, cfg: string): string {
  let parsed: URL;
  try {
    parsed = new URL(rawUrl);
  } catch {
    throw new Error(`[${cfg}] URL invalida: "${rawUrl}"`);
  }

  if (!['http:', 'https:'].includes(parsed.protocol)) {
    throw new Error(`[${cfg}] Protocolo nao permitido: "${parsed.protocol}"`);
  }

  if (isBlockedProductionHost(parsed.hostname)) {
    throw new Error(
      `[${cfg}] URL de producao bloqueada (NO-GO Fase 1). Use staging isolado.`,
    );
  }

  if (String(process.env.ALLOW_PRODUCTION_VALIDATION || '').toLowerCase() === 'true') {
    throw new Error(
      `[${cfg}] ALLOW_PRODUCTION_VALIDATION rejeitado na Fase 1 (flag unica insuficiente).`,
    );
  }

  return parsed.toString();
}

export function getBaseUrl(cfg = 'target-url'): string {
  if (!cachedBaseUrl) {
    cachedBaseUrl = resolveBaseUrl(cfg);
  }

  return cachedBaseUrl;
}

export function targetUrl(pathOrUrl = '/', cfg = 'target-url'): string {
  if (/^https?:\/\//i.test(pathOrUrl)) {
    return assertSafeUrl(pathOrUrl, cfg);
  }

  const base = `${getBaseUrl(cfg).replace(/\/$/, '')}/`;
  return assertSafeUrl(new URL(pathOrUrl, base).toString(), cfg);
}

export function withCacheBuster(rawUrl: string, key = 'pw'): string {
  const url = new URL(rawUrl);
  url.searchParams.set(key, String(Date.now()));
  return url.toString();
}
