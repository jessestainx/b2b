/**
 * resolve-base-url.ts — AWA Motos
 * Shared helper used by ALL Playwright config files.
 *
 * TARGET_ENV is obrigatório. Produção só entra com allowlist explícita:
 * - production_readonly: GET visual, sem escrita
 * - pilot: exige AWA_PILOT_TEST=true e PILOT_* preenchidos
 */
export type TargetEnv = 'local' | 'staging' | 'ci' | 'production_readonly' | 'pilot';

const PRODUCTION_HOSTS = new Set(['awamotos.com', 'www.awamotos.com', 'www1.awamotos.com']);
const PILOT_REQUIRED_KEYS = [
  'PILOT_CUSTOMER_CNPJ_ALLOWLIST',
  'PILOT_CUSTOMER_EMAIL_ALLOWLIST',
  'PILOT_CUSTOMER_PHONE_ALLOWLIST',
  'PILOT_SKU_ALLOWLIST',
  'PILOT_PAYMENT_METHOD_ALLOWLIST',
  'PILOT_SHIPPING_METHOD_ALLOWLIST',
] as const;

export function resolveTargetEnv(cfg: string): TargetEnv {
  const raw = String(process.env.TARGET_ENV || '').trim().toLowerCase();
  if (!raw) {
    throw new Error(
      '[' + cfg + '] TARGET_ENV ausente.\n' +
      'Defina TARGET_ENV=local|staging|ci|production_readonly|pilot antes de rodar Playwright.'
    );
  }

  const allowed: TargetEnv[] = ['local', 'staging', 'ci', 'production_readonly', 'pilot'];
  if (!allowed.includes(raw as TargetEnv)) {
    throw new Error('[' + cfg + '] TARGET_ENV inválido: "' + raw + '". Use local, staging, ci, production_readonly ou pilot.');
  }

  return raw as TargetEnv;
}

export function isProductionHost(hostname: string): boolean {
  const host = hostname.toLowerCase();
  return PRODUCTION_HOSTS.has(host) || host.endsWith('.awamotos.com');
}

export function isPilotAllowlistConfigured(): boolean {
  return PILOT_REQUIRED_KEYS.every((key) => {
    const value = String(process.env[key] || '').trim();
    return value !== '' && !value.includes('PREENCHER') && !value.startsWith('<') && !value.endsWith('>');
  });
}

export function assertWriteTestAllowed(cfg: string, origin: string, taggedPilot: boolean): void {
  const env = resolveTargetEnv(cfg);
  const host = new URL(origin).hostname.toLowerCase();

  if (!isProductionHost(host)) {
    return;
  }

  if (env !== 'pilot' || !taggedPilot) {
    throw new Error(
      '[' + cfg + '] Teste de escrita bloqueado em produção.\n' +
      'Somente TARGET_ENV=pilot com tag @pilot pode alterar awamotos.com.\n' +
      'Host: ' + host
    );
  }
}

export function resolveBaseUrl(cfg: string): string {
  const targetEnv = resolveTargetEnv(cfg);
  const raw = process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || '';
  const allowProd =
    String(process.env.ALLOW_PRODUCTION_VALIDATION || '').toLowerCase() === 'true';

  if (!raw) {
    throw new Error(
      '[' + cfg + '] BASE_URL ausente.\n' +
      'Defina PLAYWRIGHT_BASE_URL ou BASE_URL antes de rodar este config.'
    );
  }

  let parsed: URL;
  try {
    parsed = new URL(raw);
  } catch {
    throw new Error(
      '[' + cfg + '] BASE_URL invalida: "' + raw + '". Informe uma URL http/https completa.'
    );
  }

  if (!['http:', 'https:'].includes(parsed.protocol)) {
    throw new Error(
      '[' + cfg + '] Protocolo nao permitido: "' + parsed.protocol + '". Use http ou https.'
    );
  }

  const host = parsed.hostname.toLowerCase();
  const production = isProductionHost(host);

  if (targetEnv === 'local' || targetEnv === 'ci') {
    const localOk = host === 'localhost' || host === '127.0.0.1' || host === '::1'
      || host.endsWith('.local') || host.endsWith('.test') || host.endsWith('.invalid');
    if (!localOk) {
      throw new Error('[' + cfg + '] TARGET_ENV=' + targetEnv + ' não pode usar o host ' + host);
    }
  }

  if (targetEnv === 'staging' && production) {
    throw new Error('[' + cfg + '] TARGET_ENV=staging não pode apontar para produção (' + host + ').');
  }

  if (production && !allowProd) {
    throw new Error(
      '[' + cfg + '] URL de producao bloqueada por seguranca.\n' +
      'Para usar producao explicitamente, defina: ALLOW_PRODUCTION_VALIDATION=true\n' +
      'URL recebida: ' + host
    );
  }

  if (production && targetEnv !== 'production_readonly' && targetEnv !== 'pilot') {
    throw new Error('[' + cfg + '] Host de produção exige TARGET_ENV=production_readonly ou pilot.');
  }

  if (targetEnv === 'pilot') {
    if (String(process.env.AWA_PILOT_TEST || '').toLowerCase() !== 'true') {
      throw new Error('[' + cfg + '] TARGET_ENV=pilot exige AWA_PILOT_TEST=true.');
    }
    if (!isPilotAllowlistConfigured()) {
      throw new Error(
        '[' + cfg + '] Pedido piloto bloqueado: preencha todos os campos PILOT_* sem placeholders.'
      );
    }
  }

  return parsed.toString().replace(/\/$/, '');
}
