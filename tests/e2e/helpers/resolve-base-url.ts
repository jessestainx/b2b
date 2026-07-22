/**
 * resolve-base-url.ts — AWA Motos
 * Shared helper used by ALL Playwright config files.
 *
 * Reads the target URL from env vars; rejects empty values and blocks
 * production hosts. Fase 1 containment: production is NO-GO.
 * A single ALLOW_PRODUCTION_VALIDATION flag is NOT sufficient to unlock
 * production — that path is disabled until staging + approved environment.
 *
 * Usage:
 *   import { resolveBaseUrl } from "./helpers/resolve-base-url";
 *   const resolvedBaseUrl = resolveBaseUrl("my-config-name");
 *
 * Staging example:
 *   PLAYWRIGHT_BASE_URL=https://staging.example.local npx playwright test
 */

const BLOCKED_HOST_MARKERS = [
  // Split markers avoid accidental enablement via trivial env toggles.
  ["awa", "motos", ".com"].join(""),
  "72.61.94.22",
] as const;

function isBlockedProductionHost(hostname: string): boolean {
  const host = hostname.toLowerCase();
  return BLOCKED_HOST_MARKERS.some((marker) => host.includes(marker));
}

export function resolveBaseUrl(cfg: string): string {
  const raw = process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || "";

  if (!raw) {
    throw new Error(
      "[" + cfg + "] BASE_URL ausente.\n" +
      "Defina PLAYWRIGHT_BASE_URL ou BASE_URL (staging). Producao permanece NO-GO.\n" +
      "Exemplo: PLAYWRIGHT_BASE_URL=https://staging.example.local npx playwright test"
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

  if (isBlockedProductionHost(parsed.hostname)) {
    throw new Error(
      "[" + cfg + "] URL de producao bloqueada (NO-GO Fase 1).\n" +
      "Use staging isolado. ALLOW_PRODUCTION_VALIDATION sozinho NAO reabilita producao.\n" +
      "Host recebido: " + parsed.hostname
    );
  }

  // Defense-in-depth: ignore allow flag if somehow set
  if (String(process.env.ALLOW_PRODUCTION_VALIDATION || "").toLowerCase() === "true") {
    throw new Error(
      "[" + cfg + "] ALLOW_PRODUCTION_VALIDATION rejeitado na Fase 1.\n" +
      "Reativacao exige environment aprovado + ticket (ver GITHUB_ACTIONS_CONTAINMENT_2026-07-22.md)."
    );
  }

  return parsed.toString().replace(/\/$/, "");
}
