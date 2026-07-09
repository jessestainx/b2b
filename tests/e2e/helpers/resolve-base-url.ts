/**
 * resolve-base-url.ts — AWA Motos
 * Shared helper used by ALL Playwright config files.
 *
 * Reads the target URL from env vars; rejects empty values and blocks
 * production (awamotos.com) unless ALLOW_PRODUCTION_VALIDATION=true.
 *
 * Usage in a config file:
 *   import { resolveBaseUrl } from "./helpers/resolve-base-url";
 *   const resolvedBaseUrl = resolveBaseUrl("my-config-name");
 *
 * Run against production (explicit opt-in):
 *   PLAYWRIGHT_BASE_URL=https://awamotos.com ALLOW_PRODUCTION_VALIDATION=true npx playwright test
 */
export function resolveBaseUrl(cfg: string): string {
  const raw = process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || "";
  const allowProd =
    String(process.env.ALLOW_PRODUCTION_VALIDATION || "").toLowerCase() === "true";

  if (!raw) {
    throw new Error(
      "[" + cfg + "] BASE_URL ausente.\n" +
      "Defina PLAYWRIGHT_BASE_URL ou BASE_URL antes de rodar este config.\n" +
      "Exemplo: PLAYWRIGHT_BASE_URL=https://awamotos.com ALLOW_PRODUCTION_VALIDATION=true npx playwright test"
    );
  }

  let parsed: URL;
  try {
    parsed = new URL(raw);
  } catch {
    throw new Error(
      "[" + cfg + "] BASE_URL invalida: \"" + raw + "\". Informe uma URL http/https completa."
    );
  }

  if (!["http:", "https:"].includes(parsed.protocol)) {
    throw new Error(
      "[" + cfg + "] Protocolo nao permitido: \"" + parsed.protocol + "\". Use http ou https."
    );
  }

  const host = parsed.hostname.toLowerCase();
  if (host.includes("awamotos.com") && !allowProd) {
    throw new Error(
      "[" + cfg + "] URL de producao bloqueada por seguranca.\n" +
      "Para usar producao explicitamente, defina: ALLOW_PRODUCTION_VALIDATION=true\n" +
      "URL recebida: " + host
    );
  }

  return parsed.toString().replace(/\/$/, "");
}
