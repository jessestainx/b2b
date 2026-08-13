#!/usr/bin/env bash
# lint-magento-xml.sh — XML bem-formado em módulos e tema filho.
# Não aplica XSD Magento (exige Magento instalado). Só well-formedness.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TARGETS=(
  "$ROOT_DIR/app/design/frontend/AWA_Custom"
  "$ROOT_DIR/app/code/GrupoAwamotos"
)

if ! command -v xmllint >/dev/null 2>&1; then
  echo "xmllint ausente. Instale libxml2-utils."
  exit 1
fi

declare -a FILES=()
for target in "${TARGETS[@]}"; do
  if [[ -d "$target" ]]; then
    while IFS= read -r -d '' file; do
      FILES+=("$file")
    done < <(find "$target" -type f -name '*.xml' -print0)
  fi
done

if [[ "${#FILES[@]}" -eq 0 ]]; then
  echo "Nenhum XML customizado encontrado."
  exit 0
fi

for file in "${FILES[@]}"; do
  xmllint --noout "$file"
done

echo "XML bem-formado em ${#FILES[@]} arquivos (Magento custom)."
