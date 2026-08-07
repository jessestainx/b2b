#!/usr/bin/env bash
set -Eeuo pipefail
PIPELINE_STAGE="verify"
SCRIPT_NAME="$(basename "$0")"
# shellcheck source=dev/tools/awa-deploy/lib/common.sh
source "$(cd "$(dirname "$0")" && pwd)/lib/common.sh"
START_TS_UTC="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

COMPARE_A=""
COMPARE_B=""

usage() {
  cat <<'USAGE'
Uso:
  awa-verify-release.sh [--release-id <id>] [--dry-run <0|1>] [--build-real <0|1>]
  awa-verify-release.sh --compare <manifest-a> <manifest-b>

Valida release e compara manifestos para determinismo de conteudo.
USAGE
  usage_common_flags
}

parse_args() {
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --compare)
        shift
        COMPARE_A="${1:-}"
        shift
        COMPARE_B="${1:-}"
        ;;
      --release-id)
        shift
        RELEASE_ID="${1:-}"
        ;;
      --dry-run)
        shift
        DRY_RUN="${1:-1}"
        ;;
      --build-real)
        shift
        BUILD_REAL="${1:-0}"
        ;;
      --publish-real)
        shift
        PUBLISH_REAL="${1:-0}"
        ;;
      -h|--help)
        return 2
        ;;
      *)
        echo "ARG_UNPARSED=${1}"
        return 1
        ;;
    esac
    shift || true
  done
  return 0
}

parse_args "$@" || rc=$?
rc="${rc:-0}"
if [[ "$rc" == "2" ]]; then
  usage
  exit 0
fi
[[ "$rc" == "0" ]] || die "Argumento invalido."

if [[ -n "${COMPARE_A}" || -n "${COMPARE_B}" ]]; then
  [[ -n "${COMPARE_A}" && -n "${COMPARE_B}" ]] || die "--compare exige dois manifestos."
  python3 - "${COMPARE_A}" "${COMPARE_B}" <<'PY'
import hashlib
import json
import sys
from pathlib import Path


def file_hash(path_value: str):
    p = Path(path_value)
    if p.is_file():
        return hashlib.sha256(p.read_bytes()).hexdigest()
    return None


def normalize(manifest):
    ignored = {'release_id', 'started_at_utc', 'finished_at_utc', 'build_log', 'compression_log'}
    path_like = {
        'derived_compression_inventory', 'source_hashes', 'map_csv',
        'status_counts', 'syntax_log', 'compression_csv', 'empty_files',
        'files_diff', 'processes_diff', 'compressed_orphans_csv'
    }
    out = {}
    for k, v in manifest.items():
        if k in ignored:
            continue
        if k in path_like and isinstance(v, str):
            h = file_hash(v)
            out[k] = f'file_sha256:{h}' if h else v
        else:
            out[k] = v
    return out

ma = json.load(open(sys.argv[1], encoding='utf-8'))
mb = json.load(open(sys.argv[2], encoding='utf-8'))
na = normalize(ma)
nb = normalize(mb)

if na == nb:
    print('DETERMINISTICO')
    sys.exit(0)

print('NAO_DETERMINISTICO')
for k in sorted(set(na) | set(nb)):
    if na.get(k) != nb.get(k):
        print(f'- {k}: {na.get(k)!r} != {nb.get(k)!r}')
sys.exit(3)
PY
  exit 0
fi

[[ "${PUBLISH_REAL}" == "0" ]] || die "verify nao aceita PUBLISH_REAL=1."

init_pipeline
require_cmd python3 brotli gzip rg

if [[ "${BUILD_REAL}" == "1" ]]; then
  assert_isolated_build_guard
fi

VERIFY_CSV="${MANIFEST_DIR}/compression-verify.csv"
ORPHAN_CSV="${MANIFEST_DIR}/compressed-orphans.csv"
EMPTY_FILE_LIST="${MANIFEST_DIR}/empty-files.txt"

python3 - "${MAGENTO_ROOT}" "${VERIFY_CSV}" "${ORPHAN_CSV}" "${EMPTY_FILE_LIST}" <<'PY'
import csv
import hashlib
import subprocess
import sys
from pathlib import Path

root = Path(sys.argv[1])
verify_out = Path(sys.argv[2])
orphan_out = Path(sys.argv[3])
empty_out = Path(sys.argv[4])
base = root / 'pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR'

rows = []
orphans = []
empty_files = []

if base.exists():
    for p in sorted(base.rglob('*')):
        if p.is_file() and p.stat().st_size == 0:
            empty_files.append(str(p.relative_to(root)))

    for p in sorted(base.rglob('*.css')) + sorted(base.rglob('*.js')):
        if p.name.endswith(('.gz', '.br')):
            continue
        gz = p.with_suffix(p.suffix + '.gz')
        br = p.with_suffix(p.suffix + '.br')
        if not p.exists() or not gz.exists() or not br.exists():
            rows.append((str(p.relative_to(root)), 'C_STALE', 'missing_identity_or_compressed'))
            continue
        identity = hashlib.sha256(p.read_bytes()).hexdigest()
        gz_h = hashlib.sha256(subprocess.check_output(['gzip', '-dc', str(gz)])).hexdigest()
        br_h = hashlib.sha256(subprocess.check_output(['brotli', '-dc', str(br)])).hexdigest()
        status = 'A_REPRODUZIVEL' if identity == gz_h == br_h else 'B_DIVERGENTE'
        rows.append((str(p.relative_to(root)), status, f'{identity}|{gz_h}|{br_h}'))

    for p in sorted(base.rglob('*')):
        if not p.is_file() or p.suffix not in {'.gz', '.br'}:
            continue
        identity = p.with_suffix('')
        if not identity.exists():
            orphans.append((str(p.relative_to(root)), str(identity.relative_to(root)), p.suffix))

with verify_out.open('w', newline='', encoding='utf-8') as fh:
    w = csv.writer(fh)
    w.writerow(['asset', 'classificacao', 'logical_hashes'])
    w.writerows(rows)

with orphan_out.open('w', newline='', encoding='utf-8') as fh:
    w = csv.writer(fh)
    w.writerow(['compressed_file', 'missing_identity', 'encoding'])
    w.writerows(orphans)

with empty_out.open('w', encoding='utf-8') as fh:
    for e in empty_files:
        fh.write(e + '\n')
PY

STATUS="DETERMINISTICO"
if rg -q ',B_DIVERGENTE,' "${VERIFY_CSV}"; then
  STATUS="NAO_DETERMINISTICO"
fi

if [[ -s "${ORPHAN_CSV}" ]] && [[ "$(wc -l < "${ORPHAN_CSV}")" -gt 1 ]]; then
  STATUS="NAO_DETERMINISTICO"
fi

write_json_kv "${MANIFEST_DIR}/verify.json" \
  "release_id=${RELEASE_ID}" \
  "stage=${PIPELINE_STAGE}" \
  "started_at_utc=${START_TS_UTC}" \
  "finished_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  "dry_run=${DRY_RUN}" \
  "build_real=${BUILD_REAL}" \
  "compression_csv=${VERIFY_CSV}" \
  "compressed_orphans_csv=${ORPHAN_CSV}" \
  "empty_files=${EMPTY_FILE_LIST}" \
  "determinism=${STATUS}"

cat > "${MANIFEST_DIR}/actions-verify.csv" <<'CSV'
action,classificacao
validacao_hash_identity_gzip_br,verificacao_build_isolada
deteccao_orfaos_compactados,verificacao_build_isolada
deteccao_arquivos_vazios,verificacao_build_isolada
CSV

info "Verificacao concluida: ${MANIFEST_DIR}/verify.json"
