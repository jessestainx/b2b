#!/usr/bin/env bash
set -Eeuo pipefail
PIPELINE_STAGE="preflight"
SCRIPT_NAME="$(basename "$0")"
# shellcheck source=dev/tools/awa-deploy/lib/common.sh
source "$(cd "$(dirname "$0")" && pwd)/lib/common.sh"
START_TS_UTC="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

usage() {
  cat <<'USAGE'
Uso: awa-release-preflight.sh [--release-id <id>] [--dry-run <0|1>]

Executa checkpoint de preflight em modo seguro, sem publicar.
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

[[ "${BUILD_REAL}" == "0" ]] || die "preflight nao aceita BUILD_REAL=1."
[[ "${PUBLISH_REAL}" == "0" ]] || die "preflight nao aceita PUBLISH_REAL=1."

init_pipeline
require_cmd ps rg diff sleep

CANDIDATES_FILE="${MANIFEST_DIR}/preflight-candidates.txt"
cat > "${CANDIDATES_FILE}" <<'CAND'
app/etc/config.php
app/etc/env.php
pub/static/deployed_version.txt
app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_extend.less
app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-visual-fixes.less
app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-flex-grid-flow.less
app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-head-preload.phtml
var/view_preprocessed/pub/static/app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-head-preload.phtml
pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/styles-l.css
pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/styles-l.css.gz
pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/styles-l.css.br
pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/themes.css
pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/themes.css.gz
pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/themes.css.br
CAND

snapshot() {
  local tag="$1"
  local proc="${MANIFEST_DIR}/processes-${tag}.txt"
  local files_out="${MANIFEST_DIR}/files-${tag}.txt"

  {
    echo "ts=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    ps -eo pid,lstart,user,comm,args | rg -i 'php-fpm|nginx|varnish|redis|magento|node|npm|brotli|gzip'
  } > "${proc}"

  : > "${files_out}"
  while IFS= read -r rel; do
    [[ -z "${rel}" ]] && continue
    local abs="${MAGENTO_ROOT}/${rel}"
    if [[ -e "${abs}" ]]; then
      stat -c 'STAT|%n|%s|%Y|%U|%G|%a|%i' "${abs}" >> "${files_out}"
      sha256sum "${abs}" | awk '{print "SHA256|"$2"|"$1}' >> "${files_out}"
    else
      echo "MISSING|${rel}" >> "${files_out}"
    fi
  done < "${CANDIDATES_FILE}"
}

snapshot first
sleep 15
snapshot second

diff -u "${MANIFEST_DIR}/files-first.txt" "${MANIFEST_DIR}/files-second.txt" > "${MANIFEST_DIR}/files-diff.txt" || true
diff -u "${MANIFEST_DIR}/processes-first.txt" "${MANIFEST_DIR}/processes-second.txt" > "${MANIFEST_DIR}/processes-diff.txt" || true

CONCURRENCY="stable"
if [[ -s "${MANIFEST_DIR}/files-diff.txt" ]]; then
  CONCURRENCY="changed"
fi

MAGENTO_VERSION="$(php "${MAGENTO_ROOT}/bin/magento" --version 2>/dev/null | awk '{print $3}')"
PHP_VERSION="$(php -v | awk 'NR==1{print $2}')"
MODE="$(deploy_mode_of_root)"
DEPLOYED_VERSION="$(php -r "echo trim(file_get_contents('${MAGENTO_ROOT}/pub/static/deployed_version.txt'));" 2>/dev/null || true)"
STATIC_SIGN="$(php "${MAGENTO_ROOT}/bin/magento" config:show dev/static/sign 2>/dev/null || true)"

write_json_kv "${MANIFEST_DIR}/preflight.json" \
  "release_id=${RELEASE_ID}" \
  "stage=${PIPELINE_STAGE}" \
  "started_at_utc=${START_TS_UTC}" \
  "finished_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  "user=$(current_user)" \
  "filesystem_owner=$(fs_owner_user)" \
  "hostname=$(hostname -f 2>/dev/null || hostname)" \
  "magento_root=$(magento_root_realpath)" \
  "magento_version=${MAGENTO_VERSION}" \
  "php_version=${PHP_VERSION}" \
  "deploy_mode=${MODE}" \
  "dev_static_sign=${STATIC_SIGN}" \
  "deployed_version_txt=${DEPLOYED_VERSION}" \
  "concurrency_check=${CONCURRENCY}" \
  "files_diff=${MANIFEST_DIR}/files-diff.txt" \
  "processes_diff=${MANIFEST_DIR}/processes-diff.txt"

cat > "${MANIFEST_DIR}/actions-preflight.csv" <<'CSV'
action,classificacao
coleta_processos,somente_leitura
coleta_hashes_mtines,somente_leitura
geracao_manifest_escrita_release,escrita_release_isolada
CSV

info "Preflight concluido: ${MANIFEST_DIR}/preflight.json"
