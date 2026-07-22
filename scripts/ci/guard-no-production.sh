#!/usr/bin/env bash
# Static NO-GO guard (Fase 1). Arquivos locais apenas — zero rede.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

FAILED=0

mapfile -t TARGETS < <(
  find .github/workflows -type f \( -name '*.yml' -o -name '*.yaml' \) 2>/dev/null || true
  find .github -maxdepth 1 -type f \( -name '*.cjs' -o -name '*.js' \) 2>/dev/null || true
  find .github/lighthouse -type f 2>/dev/null || true
  printf '%s\n' package.json tests/e2e/package.json
  [ -f .lighthouserc.js ] && printf '%s\n' .lighthouserc.js
  find tests/e2e/helpers -type f \( -name '*.ts' -o -name '*.js' -o -name '*.mjs' -o -name '*.cjs' \) 2>/dev/null || true
  find tests/e2e -maxdepth 1 -type f \( -name 'playwright.config.ts' -o -name 'pw-*.config.ts' \) 2>/dev/null || true
  [ -f playwright.config.ts ] && printf '%s\n' playwright.config.ts
  [ -f scripts/playwright-qa-safe.sh ] && printf '%s\n' scripts/playwright-qa-safe.sh
)

declare -A SEEN=()
FILES=()
for f in "${TARGETS[@]}"; do
  [ -f "$f" ] || continue
  [ -n "${SEEN[$f]:-}" ] && continue
  SEEN[$f]=1
  FILES+=("$f")
done

check_pattern() {
  local label="$1"
  local pattern="$2"
  local hits
  hits="$(grep -nE "$pattern" "${FILES[@]}" 2>/dev/null || true)"
  if [ -n "$hits" ]; then
    echo "FAIL [$label]: padrao proibido encontrado"
    echo "$hits"
    FAILED=1
  else
    echo "OK   [$label]"
  fi
}

echo "=== guard-no-production (escopo ${#FILES[@]} arquivos) ==="

# URL absolutas / host de producao
check_pattern "url_producao" "https?://[^\"[:space:]]*awamotos\.com"
check_pattern "ip_producao" "72\.61\.94\.22"
# Assignment that enables production (not mere mentions of the env name)
check_pattern "allow_production_true_assign" 'ALLOW_PRODUCTION_VALIDATION([=:][[:space:]]*["'"'"']?true["'"'"']?|=\$\{[^}]*:-true\})'
# SSH / deploy secrets still wired in active job steps (not comments-only quarantine files are ok if no pull_request)
check_pattern "ssh_prod_secret_active" 'ssh-private-key:[[:space:]]*\$\{\{[[:space:]]*secrets\.(PROD_SSH_KEY|STAGING_SSH_KEY)'

# PR workflows must not reference sensitive secrets
while IFS= read -r wf; do
  [ -f "$wf" ] || continue
  if grep -qE '^[[:space:]]*pull_request:' "$wf"; then
    if grep -nE 'secrets\.(B2B_|PROD_|STAGING_|PLAYWRIGHT_BASE_URL)' "$wf" >/dev/null 2>&1; then
      echo "FAIL [pr_secrets]: $wf referencia secrets sensiveis em caminho com pull_request"
      grep -nE 'secrets\.(B2B_|PROD_|STAGING_|PLAYWRIGHT_BASE_URL)' "$wf" || true
      FAILED=1
    fi
    if grep -nE 'deploy-production|deploy-staging' "$wf" >/dev/null 2>&1; then
      # allow quarantine job names only when if: false present nearby — still fail if secrets present
      if grep -nE 'secrets\.(PROD_|STAGING_)' "$wf" >/dev/null 2>&1; then
        echo "FAIL [pr_deploy_secrets]: $wf"
        FAILED=1
      fi
    fi
  fi
done < <(find .github/workflows -type f \( -name '*.yml' -o -name '*.yaml' \) 2>/dev/null || true)

if [ "$FAILED" -ne 0 ]; then
  echo "=== GUARD FAILED — contencao NO-GO violada ==="
  exit 1
fi

echo "=== GUARD OK ==="
exit 0
