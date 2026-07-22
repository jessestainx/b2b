#!/usr/bin/env bash
# HML-only: T1 validate Meu Desempenho with legacy visible, then T2 toggle legacy_menu_visible.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

GUARD="$ROOT/dev/hml/hml-env-guard.sh"
[[ -x "$GUARD" ]] || { echo "missing guard"; exit 1; }

: "${HML_APPROVED_HOST:?set HML_APPROVED_HOST}"
"$GUARD"

MAGENTO_USER="${MAGENTO_FS_OWNER:-$(whoami)}"
run_m() {
  if [[ "$(whoami)" == "$MAGENTO_USER" ]]; then
    php bin/magento "$@"
  else
    sudo -u "$MAGENTO_USER" php bin/magento "$@"
  fi
}

echo "=== Baseline ==="
UNIFIED="$(run_m config:show grupoawamotos_b2b/platform/unified_menu_enabled || true)"
LEGACY_BEFORE="$(run_m config:show grupoawamotos_b2b/platform/legacy_menu_visible || true)"
echo "unified_menu_enabled=${UNIFIED}"
echo "legacy_menu_visible(before)=${LEGACY_BEFORE}"

if [[ "${UNIFIED}" != "1" ]]; then
  echo "ABORT: unified_menu_enabled is '${UNIFIED}', expected 1. Not auto-setting." >&2
  exit 1
fi

echo "=== T1: ensure legacy visible for Meu Desempenho check ==="
if [[ "${LEGACY_BEFORE}" != "1" ]]; then
  echo "NOTE: legacy is not 1; T1 expects human validation with legacy=1 first." >&2
fi
echo "MANUAL: login as attendant_self user; confirm Meu Desempenho; open URL."
echo "Press Enter to continue to T2 (or Ctrl+C to stop)..."
read -r _

echo "=== T2: set legacy_menu_visible=0 (default scope) ==="
run_m config:set --scope=default --scope-code=0 grupoawamotos_b2b/platform/legacy_menu_visible 0
run_m cache:clean config
# Optionally: run_m cache:clean compiled_config

echo "MANUAL: logout/login; validate personas; URL; ACL; logs."
echo "Press Enter to ROLLBACK to previous value (${LEGACY_BEFORE})..."
read -r _

echo "=== Rollback ==="
run_m config:set --scope=default --scope-code=0 grupoawamotos_b2b/platform/legacy_menu_visible "${LEGACY_BEFORE}"
run_m cache:clean config
echo "MANUAL: confirm legacy menus returned."
echo "DONE"
