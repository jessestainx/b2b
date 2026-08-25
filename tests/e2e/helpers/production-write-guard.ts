import { resolveTargetEnv, assertWriteTestAllowed, isProductionHost } from './resolve-base-url';

const WRITE_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

/**
 * Falha imediatamente se um teste comum tentar alterar produção.
 * Testes @pilot só passam com TARGET_ENV=pilot e allowlist PILOT_*.
 */
export function installProductionWriteGuard(page: {
  on: (event: 'request', listener: (request: { method: () => string; url: () => string }) => void) => void;
}, options?: { taggedPilot?: boolean }): void {
  const env = resolveTargetEnv('production-write-guard');
  const taggedPilot = !!options?.taggedPilot;

  page.on('request', (request) => {
    const method = request.method().toUpperCase();
    if (!WRITE_METHODS.has(method)) {
      return;
    }

    const url = request.url();
    let host = '';
    try {
      host = new URL(url).hostname.toLowerCase();
    } catch {
      return;
    }

    if (!isProductionHost(host)) {
      return;
    }

    if (env === 'production_readonly' || env !== 'pilot' || !taggedPilot) {
      throw new Error(
        'Bloqueio de escrita em produção: ' + method + ' ' + url +
        ' (TARGET_ENV=' + env + ', pilot=' + String(taggedPilot) + ')'
      );
    }

    assertWriteTestAllowed('production-write-guard', url, taggedPilot);
  });
}
