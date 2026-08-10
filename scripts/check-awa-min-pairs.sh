#!/usr/bin/env bash
# =============================================================================
# check-awa-min-pairs.sh — falha se algum .min.css servido estiver stale vs. fonte
# (Fase P1 — build reproduzível; ver _documentacao/ARQUITETURA_VISUAL_ALVO_MODERNIZACAO_2026-08-10.md)
#
# Uso: bash scripts/check-awa-min-pairs.sh
# Exit 0 = todos frescos (exceções FROZEN apenas listadas); 1 = stale.
# =============================================================================
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
THEME_CSS="${ROOT}/app/design/frontend/AWA_Custom/ayo_home5_child/web/css"

# Stems servidos como .min.css em produção (loaders PHTML/XML/PHP).
# Fonte da lista: referências em app/design/frontend/AWA_Custom/ayo_home5_child
# (Magento_Theme/templates/html/*.phtml, */layout/*.xml) + OptimizeHeadStylesPlugin.
SERVED_STEMS=(
  awa-super-global-20260611m
  awa-defer-global-bundle
  awa-third-party-bundle
  awa-layout-bundle-20260611m
  awa-commerce-impeccable-refine
  awa-carousel-bundle
  awa-head-tail-bundle
  awa-m2-visual-ssot
  awa-head-preload-critical-home
  awa-home-polish-critical
  awa-home-launches-toggle-fix
  awa-home-deferred-stack
  awa-home-critical-stack-2026-06-11
  awa-plp-critical-fixes
  awa-impeccable-layout-2026-06-16
  awa-visual-bugfix
  awa-visual-fixes-2026-06-29-final
  awa-header-visual-audit-fixes-20260630
  awa-home-b2b-ops-density-20260805
  awa-checkout-layout-lock
  awa-cookie-fab-collision-fix-2026-07-08
  awa-design-system
)

# Exceções congeladas (trabalho concorrente não commitado) — revisar a cada fase.
FROZEN_STEMS=(
  awa-align-grid-terminal-2026-06-11
)

fail=0
for stem in "${SERVED_STEMS[@]}"; do
  src="${THEME_CSS}/${stem}.css"
  min="${THEME_CSS}/${stem}.min.css"
  [[ -f "$src" ]] || { echo "WARN: fonte ausente, pulando: ${stem}"; continue; }
  if [[ ! -f "$min" ]]; then
    echo "STALE(missing): ${stem}.min.css"
    fail=1
    continue
  fi
  if [[ "$src" -nt "$min" ]]; then
    echo "STALE(mtime): ${stem} — fonte mais nova que o min"
    fail=1
    continue
  fi
  if ! bash "${ROOT}/scripts/build-awa-css-min-pair.sh" --check "$stem" >/dev/null 2>&1; then
    echo "STALE(content): ${stem} — min diverge do build determinístico"
    fail=1
  fi
done

for stem in "${FROZEN_STEMS[@]}"; do
  echo "FROZEN(skip): ${stem} — congelado, verificação adiada"
done

if [[ "$fail" -eq 1 ]]; then
  echo "ERRO: mins stale. Regenere com: scripts/build-awa-css-min-pair.sh --write <stem>" >&2
  exit 1
fi
echo "OK: todos os pares min sincronizados (exceções congeladas listadas acima)."
exit 0
