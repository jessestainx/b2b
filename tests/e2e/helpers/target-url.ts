import { resolveBaseUrl } from './resolve-base-url';

let cachedBaseUrl: string | null = null;

function productionAllowed(): boolean {
  return String(process.env.ALLOW_PRODUCTION_VALIDATION || '').toLowerCase() === 'true';
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

  if (parsed.hostname.toLowerCase().includes('awamotos.com') && !productionAllowed()) {
    throw new Error(
      `[${cfg}] URL de producao bloqueada por seguranca. ` +
      'Defina ALLOW_PRODUCTION_VALIDATION=true apenas para QA read-only.',
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
