#!/usr/bin/env bash
set -Eeuo pipefail
PIPELINE_STAGE="build-phtml"
SCRIPT_NAME="$(basename "$0")"
# shellcheck source=dev/tools/awa-deploy/lib/common.sh
source "$(cd "$(dirname "$0")" && pwd)/lib/common.sh"
START_TS_UTC="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

usage() {
  cat <<'USAGE'
Uso: awa-build-phtml.sh [--release-id <id>] [--dry-run <0|1>] [--build-real <0|1>]

Valida sintaxe PHTML e mapeia equivalencia com var/view_preprocessed.
USAGE
  usage_common_flags
}

parse_common_args "$@" || rc=$?
rc="${rc:-0}"
if [[ "$rc" == "2" ]]; then
  usage
  exit 0
fi
[[ "$rc" == "0" ]] || die "Argumento invalido."
[[ "${PUBLISH_REAL}" == "0" ]] || die "build-phtml nao aceita PUBLISH_REAL=1."

init_pipeline
require_cmd rg php python3 awk

if [[ "${BUILD_REAL}" == "1" ]]; then
  assert_isolated_build_guard
fi

PHTML_LIST="${MANIFEST_DIR}/phtml-source-files.txt"
rg --files "${MAGENTO_ROOT}/app/design/frontend/AWA_Custom/ayo_home5_child" -g '*.phtml' | sort -u > "${PHTML_LIST}"

SYNTAX_LOG="${MANIFEST_DIR}/phtml-syntax.log"
: > "${SYNTAX_LOG}"
while IFS= read -r f; do
  php -l "${f}" >> "${SYNTAX_LOG}"
done < "${PHTML_LIST}"

MAP_CSV="${MANIFEST_DIR}/phtml-preprocessed-map.csv"
python3 - "${MAGENTO_ROOT}" "${PHTML_LIST}" "${MAP_CSV}" <<'PY'
import csv
import hashlib
import sys
from pathlib import Path

root = Path(sys.argv[1])
list_file = Path(sys.argv[2])
out = Path(sys.argv[3])
rows = []
for line in list_file.read_text(encoding='utf-8').splitlines():
    src = Path(line)
    rel = src.relative_to(root / 'app/design/frontend/AWA_Custom/ayo_home5_child')
    tgt = root / 'var/view_preprocessed/pub/static/app/design/frontend/AWA_Custom/ayo_home5_child' / rel
    src_sha = hashlib.sha256(src.read_bytes()).hexdigest() if src.exists() else ''
    tgt_sha = hashlib.sha256(tgt.read_bytes()).hexdigest() if tgt.exists() else ''
    if not tgt.exists():
        status = 'D_ORFAO_DERIVADO_AUSENTE'
    elif src_sha == tgt_sha:
        status = 'A_REPRODUZIVEL'
    else:
        status = 'C_STALE'
    tgt_rel = str(tgt.relative_to(root)) if tgt.exists() else str(tgt).replace(str(root) + '/', '')
    rows.append((str(src.relative_to(root)), tgt_rel, status, src_sha, tgt_sha))

with out.open('w', newline='', encoding='utf-8') as fh:
    w = csv.writer(fh)
    w.writerow(['source_phtml', 'preprocessed_phtml', 'classificacao', 'sha256_source', 'sha256_preprocessed'])
    w.writerows(rows)
PY

COUNTS_FILE="${MANIFEST_DIR}/phtml-status-counts.txt"
awk -F, 'NR>1{c[$3]++} END{for (k in c) print k"="c[k]}' "${MAP_CSV}" | sort > "${COUNTS_FILE}"

write_json_kv "${MANIFEST_DIR}/build-phtml.json" \
  "release_id=${RELEASE_ID}" \
  "stage=${PIPELINE_STAGE}" \
  "started_at_utc=${START_TS_UTC}" \
  "finished_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  "dry_run=${DRY_RUN}" \
  "build_real=${BUILD_REAL}" \
  "magento_root=$(magento_root_realpath)" \
  "syntax_log=${SYNTAX_LOG}" \
  "map_csv=${MAP_CSV}" \
  "status_counts=${COUNTS_FILE}"

cat > "${MANIFEST_DIR}/actions-build-phtml.csv" <<'CSV'
action,classificacao
php_lint_phtml,somente_leitura_ou_build_isolada
mapa_preprocessed,escrita_release_isolada
CSV

info "Build PHTML concluido: ${MANIFEST_DIR}/build-phtml.json"
