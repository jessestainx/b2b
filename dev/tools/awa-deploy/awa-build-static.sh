#!/usr/bin/env bash
set -Eeuo pipefail
PIPELINE_STAGE="build-static"
SCRIPT_NAME="$(basename "$0")"
# shellcheck source=dev/tools/awa-deploy/lib/common.sh
source "$(cd "$(dirname "$0")" && pwd)/lib/common.sh"
START_TS_UTC="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

usage() {
  cat <<'USAGE'
Uso: awa-build-static.sh [--release-id <id>] [--dry-run <0|1>] [--build-real <0|1>]

Planeja ou executa build estatico em ambiente isolado validado.
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
[[ "${PUBLISH_REAL}" == "0" ]] || die "build-static nao aceita PUBLISH_REAL=1."

init_pipeline
require_cmd nproc rg sort brotli gzip php

if [[ "${BUILD_REAL}" == "1" ]]; then
  assert_isolated_build_guard
fi

DISCOVERY_FILE="${MANIFEST_DIR}/static-discovery.txt"
THEME_PATH="${THEME_PATH:-AWA_Custom/ayo_home5_child}"
LOCALES="${LOCALES:-pt_BR}"
AREAS="frontend"
JOBS="$(nproc)"
if (( JOBS > 4 )); then JOBS=4; fi
STRATEGY="standard"
CONTENT_VERSION="${CONTENT_VERSION_OVERRIDE:-$(date +%s)}"

{
  echo "theme_path=${THEME_PATH}"
  echo "locales=${LOCALES}"
  echo "areas=${AREAS}"
  echo "jobs=${JOBS}"
  echo "strategy=${STRATEGY}"
  echo "content_version=${CONTENT_VERSION}"
} > "${DISCOVERY_FILE}"

HELP_OUT="${MANIFEST_DIR}/static-deploy-help.txt"
php "${MAGENTO_ROOT}/bin/magento" help setup:static-content:deploy > "${HELP_OUT}" 2>&1 || true

CMD=(php "${MAGENTO_ROOT}/bin/magento" setup:static-content:deploy)
if rg -q -- '--area' "${HELP_OUT}"; then CMD+=(--area "${AREAS}"); fi
if rg -q -- '--theme' "${HELP_OUT}"; then CMD+=(--theme "${THEME_PATH}"); fi
if rg -q -- '--jobs' "${HELP_OUT}"; then CMD+=(--jobs "${JOBS}"); fi
if rg -q -- '--strategy' "${HELP_OUT}"; then CMD+=(--strategy "${STRATEGY}"); fi
if rg -q -- '--content-version' "${HELP_OUT}"; then CMD+=(--content-version "${CONTENT_VERSION}"); fi

IFS=',' read -r -a LOCALE_ARR <<< "${LOCALES}"
for locale in "${LOCALE_ARR[@]}"; do
  [[ -n "${locale}" ]] && CMD+=("${locale}")
done

printf '%q ' "${CMD[@]}" > "${MANIFEST_DIR}/static-build-command.sh"
printf '\n' >> "${MANIFEST_DIR}/static-build-command.sh"

clean_tree() {
  local tree="$1"
  local tree_real
  tree_real="$(normalize_abs_path "${tree}")"
  [[ -d "${tree_real}" ]] || mkdir -p "${tree_real}"
  python3 - "${tree_real}" <<'PY'
import os
import shutil
import sys
from pathlib import Path

root = Path(sys.argv[1])
for child in root.iterdir():
    if child.name in {'.htaccess', '.gitkeep'}:
        continue
    if child.is_dir() and not child.is_symlink():
        shutil.rmtree(child)
    else:
        child.unlink(missing_ok=True)
PY
}

BUILD_LOG="${LOG_DIR}/static-build-exec.log"
COMPRESS_LOG="${LOG_DIR}/compression-build.log"
BUILD_DURATION_SEC=0

if [[ "${BUILD_REAL}" == "1" && "${DRY_RUN}" == "0" ]]; then
  info "Limpando derivados do root isolado antes do build real."
  clean_tree "${MAGENTO_ROOT}/pub/static"
  clean_tree "${MAGENTO_ROOT}/var/view_preprocessed"
  clean_tree "${MAGENTO_ROOT}/generated"

  mkdir -p "${MAGENTO_ROOT}/pub/static" "${MAGENTO_ROOT}/var/view_preprocessed" "${MAGENTO_ROOT}/generated"

  local_start="$(date +%s)"
  (
    cd "${MAGENTO_ROOT}"
    "${CMD[@]}"
  ) > "${BUILD_LOG}" 2>&1
  local_end="$(date +%s)"
  BUILD_DURATION_SEC=$(( local_end - local_start ))

  STATIC_TARGET="${MAGENTO_ROOT}/pub/static/frontend/AWA_Custom/ayo_home5_child"
  mkdir -p "${STATIC_TARGET}"

  # Remove compressed artifacts copied by source/static deploy; regenerate only from identity files in this release.
  python3 - "${STATIC_TARGET}" <<'PY'
from pathlib import Path
import sys
base = Path(sys.argv[1])
for p in sorted(base.rglob('*')):
    if p.is_file() and p.suffix in {'.gz', '.br'}:
        p.unlink(missing_ok=True)
PY

  python3 - "${STATIC_TARGET}" "${COMPRESS_LOG}" <<'PY'
import subprocess
import sys
from pathlib import Path

base = Path(sys.argv[1])
log_file = Path(sys.argv[2])
patterns = {'.css', '.js', '.html', '.svg', '.json', '.txt', '.xml', '.woff', '.woff2'}
count = 0

with log_file.open('w', encoding='utf-8') as log:
    for p in sorted(base.rglob('*')):
        if not p.is_file():
            continue
        if p.suffix in {'.gz', '.br'}:
            continue
        if p.suffix.lower() not in patterns:
            continue
        gz = p.with_suffix(p.suffix + '.gz')
        br = p.with_suffix(p.suffix + '.br')
        subprocess.run(['gzip', '-n', '-f', '-k', str(p)], check=True)
        subprocess.run(['brotli', '-f', '-q', '5', '-k', str(p)], check=True)
        log.write(f"compressed|{p}|{gz.exists()}|{br.exists()}\n")
        count += 1
    log.write(f"total_compressed={count}\n")
PY
else
  info "Build real nao executado. BUILD_REAL=${BUILD_REAL} DRY_RUN=${DRY_RUN}."
fi

{
  rg --files "${MAGENTO_ROOT}/app/design/frontend/AWA_Custom/ayo_home5_child/web/css"
  rg --files "${MAGENTO_ROOT}/app/design/frontend/AWA_Custom/ayo_home5_child/web/js"
  rg --files "${MAGENTO_ROOT}/app/code/GrupoAwamotos" -g '*.{less,css,js,phtml,xml}'
} | sort -u > "${MANIFEST_DIR}/static-source-files.txt"

: > "${MANIFEST_DIR}/static-source.sha256"
while IFS= read -r f; do
  [[ -f "${f}" ]] || continue
  sha256sum "${f}" >> "${MANIFEST_DIR}/static-source.sha256"
done < "${MANIFEST_DIR}/static-source-files.txt"

python3 - "${MAGENTO_ROOT}" "${MANIFEST_DIR}/static-derived-compression.csv" <<'PY'
import csv
import hashlib
import subprocess
import sys
from pathlib import Path

root = Path(sys.argv[1])
out = Path(sys.argv[2])
base = root / 'pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR'
rows = []
if base.exists():
    for p in sorted(base.rglob('*.css')) + sorted(base.rglob('*.js')):
        if p.name.endswith(('.gz', '.br')):
            continue
        gz = p.with_suffix(p.suffix + '.gz')
        br = p.with_suffix(p.suffix + '.br')
        state = 'A_REPRODUZIVEL'
        if not gz.exists() or not br.exists():
            state = 'C_STALE'
        logical = hashlib.sha256(p.read_bytes()).hexdigest() if p.exists() else ''
        gz_logical = ''
        br_logical = ''
        if gz.exists():
            gz_logical = hashlib.sha256(subprocess.check_output(['gzip', '-dc', str(gz)])).hexdigest()
        if br.exists():
            br_logical = hashlib.sha256(subprocess.check_output(['brotli', '-dc', str(br)])).hexdigest()
        if logical and ((gz_logical and gz_logical != logical) or (br_logical and br_logical != logical)):
            state = 'B_DIVERGENTE'
        rows.append((str(p.relative_to(root)), state, logical, gz.exists(), br.exists(), gz_logical, br_logical))

with out.open('w', newline='', encoding='utf-8') as fh:
    w = csv.writer(fh)
    w.writerow(['identity', 'classificacao', 'sha256_identity', 'has_gz', 'has_br', 'sha256_gz_logico', 'sha256_br_logico'])
    w.writerows(rows)
PY

DEPLOYED_VERSION="$(php -r "@print(trim(file_get_contents('${MAGENTO_ROOT}/pub/static/deployed_version.txt')));" 2>/dev/null || true)"
PHP_VERSION="$(php -v | awk 'NR==1{print $2}')"
PHP_EXT_HASH="$(php -m | sort | sha256sum | awk '{print $1}')"
TZ="$(date +%Z)"
UMASK_NOW="$(umask)"
SOURCE_DATE_EPOCH_VAL="${SOURCE_DATE_EPOCH:-<unset>}"

write_json_kv "${MANIFEST_DIR}/build-static.json" \
  "release_id=${RELEASE_ID}" \
  "stage=${PIPELINE_STAGE}" \
  "started_at_utc=${START_TS_UTC}" \
  "finished_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  "dry_run=${DRY_RUN}" \
  "build_real=${BUILD_REAL}" \
  "theme=${THEME_PATH}" \
  "locales=${LOCALES}" \
  "areas=${AREAS}" \
  "strategy=${STRATEGY}" \
  "jobs=${JOBS}" \
  "content_version=${CONTENT_VERSION}" \
  "deployed_version_txt=${DEPLOYED_VERSION}" \
  "php_version=${PHP_VERSION}" \
  "php_extensions_sha256=${PHP_EXT_HASH}" \
  "timezone=${TZ}" \
  "umask=${UMASK_NOW}" \
  "source_date_epoch=${SOURCE_DATE_EPOCH_VAL}" \
  "build_duration_sec=${BUILD_DURATION_SEC}" \
  "command_file=${MANIFEST_DIR}/static-build-command.sh" \
  "source_hashes=${MANIFEST_DIR}/static-source.sha256" \
  "derived_compression_inventory=${MANIFEST_DIR}/static-derived-compression.csv" \
  "build_log=${BUILD_LOG}" \
  "compression_log=${COMPRESS_LOG}"

cat > "${MANIFEST_DIR}/actions-build-static.csv" <<'CSV'
action,classificacao
leitura_help_magento,comando_magento_leitura
geracao_manifest,escrita_release_isolada
limpeza_derived_isolado,escrita_build_isolada
setup_static_content_deploy,comando_magento_build
geracao_gzip_brotli,compressao_build_isolada
CSV

info "Build estatico finalizado: ${MANIFEST_DIR}/build-static.json"
