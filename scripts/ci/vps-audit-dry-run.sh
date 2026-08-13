#!/usr/bin/env bash
# vps-audit-dry-run.sh — lista o que os scripts de limpeza AWA apagariam.
# NÃO apaga, NÃO move, NÃO dá pkill, NÃO lê arquivos sensíveis.
set -euo pipefail

for arg in "$@"; do
  case "$arg" in
    --apply|--delete|--prune|--clean|--force|-f)
      echo "Recusado: este script é somente dry-run. Não há modo de exclusão." >&2
      exit 2
      ;;
  esac
done

ROOT_DIR="${MAGENTO_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
OUT_DIR="${AUDIT_OUT_DIR:-$ROOT_DIR/artifacts/vps-audit-dry-run}"
mkdir -p "$OUT_DIR"
umask 077

KEEP_AYO=(ayo_default ayo_home5)
THEME_ARCHIVE="${AWA_THEME_ARCHIVE:-$ROOT_DIR/_backups/theme-vendor-zips}"

is_sensitive() {
  local p="$1"
  case "$p" in
    */app/etc/env.php*|*/auth.json|*/.git-credentials|*/.env|*/var/session/*|*/pub/media/customer/*|*/pub/media/downloadable/*|*/.ssh/*)
      return 0
      ;;
  esac
  return 1
}

bytes_of() {
  local p="$1"
  if [[ -e "$p" ]]; then
    du -sb "$p" 2>/dev/null | awk '{print $1}'
  else
    echo 0
  fi
}

human() {
  local b="${1:-0}"
  awk -v b="$b" 'BEGIN {
    if (b < 1024) { printf "%dB", b; exit }
    if (b < 1048576) { printf "%.1fKiB", b/1024; exit }
    if (b < 1073741824) { printf "%.1fMiB", b/1048576; exit }
    printf "%.1fGiB", b/1073741824
  }'
}

report="$OUT_DIR/dry-run.md"
tsv="$OUT_DIR/candidates.tsv"
printf 'action\tpath\tbytes\thuman\n' > "$tsv"

{
  echo "# VPS audit dry-run"
  echo ""
  echo "- gerado_em: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "- hostname: \`$(hostname)\`"
  echo "- root: \`$ROOT_DIR\`"
  echo "- modo: **DRY-RUN** (nada será apagado)"
  echo "- scripts espelhados: workspace-cleanup, static-theme-prune, media-cache-prune, theme-backup-prune, e2e-cleanup"
  echo ""
  echo "## Candidatos"
  echo ""
} > "$report"

add_candidate() {
  local action="$1" path="$2"
  if is_sensitive "$path"; then
    echo "- IGNORADO (sensível): \`$path\`" >> "$report"
    return
  fi
  if [[ ! -e "$path" ]]; then
    return
  fi
  local b h
  b="$(bytes_of "$path")"
  h="$(human "$b")"
  printf '%s\t%s\t%s\t%s\n' "$action" "$path" "$b" "$h" >> "$tsv"
  echo "- ${action}: \`$path\` (${h})" >> "$report"
}

ayo_static="$ROOT_DIR/pub/static/frontend/ayo"
if [[ -d "$ayo_static" ]]; then
  for dir in "$ayo_static"/*/; do
    [[ -d "$dir" ]] || continue
    name="$(basename "$dir")"
    keep=0
    for k in "${KEEP_AYO[@]}"; do
      [[ "$name" == "$k" ]] && keep=1 && break
    done
    if [[ $keep -eq 0 ]]; then
      add_candidate "rm-rf-theme-static" "${dir%/}"
    fi
  done
fi
add_candidate "rm-rf-theme-static" "$ROOT_DIR/pub/static/frontend/Magento/luma"
add_candidate "clear-dir" "$ROOT_DIR/pub/static/_cache"

add_candidate "clear-dir" "$ROOT_DIR/pub/media/catalog/product/cache"
add_candidate "rm-rf" "$ROOT_DIR/pub/media/visual-audit"
add_candidate "rm-rf" "$ROOT_DIR/pub/media/awa-audit"

preproc="$ROOT_DIR/var/view_preprocessed/pub/static/frontend/ayo"
if [[ -d "$preproc" ]]; then
  for dir in "$preproc"/*/; do
    [[ -d "$dir" ]] || continue
    name="$(basename "$dir")"
    keep=0
    for k in "${KEEP_AYO[@]}"; do
      [[ "$name" == "$k" ]] && keep=1 && break
    done
    if [[ $keep -eq 0 ]]; then
      add_candidate "rm-rf-preprocessed" "${dir%/}"
    fi
  done
fi

for f in \
  "ayo.zip" \
  "base_package_2.3.x.zip" \
  "awa_v3 (1) (1).pbix" \
  "MegaMenuProforMagento2-2.3.0-CE.zip"
do
  add_candidate "rm-file" "$THEME_ARCHIVE/$f"
done

for p in \
  "$ROOT_DIR/phpunit.phar" \
  "$ROOT_DIR/screenshots" \
  "$ROOT_DIR/test-results" \
  "$ROOT_DIR/playwright-report" \
  "$ROOT_DIR/blob-report" \
  "$ROOT_DIR/tmp" \
  "$ROOT_DIR/var/qa-screens" \
  "$ROOT_DIR/artifacts" \
  "$ROOT_DIR/var/export/awa_audit_screens" \
  "$ROOT_DIR/var/audit" \
  "$ROOT_DIR/.kilo/node_modules" \
  "$ROOT_DIR/tests/e2e/test-results" \
  "$ROOT_DIR/tests/e2e/test-results-root" \
  "$ROOT_DIR/tests/e2e/reports" \
  "$ROOT_DIR/tests/e2e/screenshots"
do
  add_candidate "rm-artifact" "$p"
done

{
  echo ""
  echo "## Totais"
  echo ""
} >> "$report"

python3 -c '
import sys
tsv, report = sys.argv[1], sys.argv[2]
total = 0
n = 0
with open(tsv, encoding="utf-8") as fh:
    next(fh)
    for line in fh:
        parts = line.rstrip("\n").split("\t")
        if len(parts) < 3:
            continue
        n += 1
        try:
            total += int(parts[2])
        except ValueError:
            pass

def human(b):
    if b < 1024:
        return "%dB" % b
    if b < 1048576:
        return "%.1fKiB" % (b / 1024)
    if b < 1073741824:
        return "%.1fMiB" % (b / 1048576)
    return "%.1fGiB" % (b / 1073741824)

with open(report, "a", encoding="utf-8") as fh:
    fh.write("- candidatos: %d\n" % n)
    fh.write("- volume listado: %s\n" % human(total))
    fh.write("\nNada foi apagado. Limpeza real exige autorização explícita, backup e os scripts originais.\n")
print("candidatos=%d volume=%s" % (n, human(total)))
' "$tsv" "$report"

cat "$report"
echo "Relatório: $report"
echo "TSV: $tsv"
