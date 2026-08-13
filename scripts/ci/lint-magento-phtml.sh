#!/usr/bin/env bash
# lint-magento-phtml.sh — php -l em PHTML custom (tema filho + GrupoAwamotos).
# Não altera arquivos. Não percorre Rokanthemes, vendor, generated.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TARGETS=(
  "$ROOT_DIR/app/design/frontend/AWA_Custom"
  "$ROOT_DIR/app/code/GrupoAwamotos"
)

declare -a FILES=()
for target in "${TARGETS[@]}"; do
  if [[ -d "$target" ]]; then
    while IFS= read -r -d '' file; do
      FILES+=("$file")
    done < <(find "$target" -type f -name '*.phtml' -print0)
  fi
done

if [[ "${#FILES[@]}" -eq 0 ]]; then
  echo "Nenhum PHTML customizado encontrado."
  exit 0
fi

for file in "${FILES[@]}"; do
  php -l "$file" >/dev/null
done

echo "Sintaxe PHTML validada em ${#FILES[@]} arquivos (Magento custom)."
