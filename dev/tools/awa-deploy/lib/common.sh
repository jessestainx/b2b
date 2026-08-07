#!/usr/bin/env bash
set -Eeuo pipefail

: "${MAGENTO_ACTIVE_ROOT:=/home/user/htdocs/srv1113343.hstgr.cloud}"
: "${MAGENTO_ROOT:=${MAGENTO_ACTIVE_ROOT}}"
: "${ISOLATED_MAGENTO_ROOT:=}"
: "${RELEASES_ROOT:=/home/deploy/awa-releases}"

: "${LOCK_FILE_AUDIT:=/tmp/awa-deploy-audit.lock}"
: "${LOCK_FILE_BUILD:=/tmp/awa-deploy-build.lock}"
: "${LOCK_FILE_PUBLISH:=/tmp/awa-deploy-publish.lock}"

: "${DRY_RUN:=1}"
: "${BUILD_REAL:=0}"
: "${PUBLISH_REAL:=0}"

SCRIPT_NAME="${SCRIPT_NAME:-$(basename "${0}")}"
PIPELINE_STAGE="${PIPELINE_STAGE:-unknown}"
RELEASE_ID="${RELEASE_ID:-}"

RELEASE_DIR=""
LOG_DIR=""
LOG_FILE=""
MANIFEST_DIR=""
TMP_DIR=""

log() {
  local level="$1"
  shift
  local now
  now="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf '[%s] [%s] [%s] %s\n' "${now}" "${SCRIPT_NAME}" "${level}" "$*" | tee -a "${LOG_FILE:-/dev/stderr}" >&2
}

die() {
  log "ERROR" "$*"
  exit 1
}

warn() { log "WARN" "$*"; }
info() { log "INFO" "$*"; }

require_cmd() {
  local missing=0
  local cmd
  for cmd in "$@"; do
    if ! command -v "${cmd}" >/dev/null 2>&1; then
      warn "Comando ausente: ${cmd}"
      missing=1
    fi
  done
  (( missing == 0 )) || die "Dependencias obrigatorias ausentes."
}

new_release_id() {
  local git_short
  git_short="$(git -C "${MAGENTO_ACTIVE_ROOT}" rev-parse --short HEAD 2>/dev/null || true)"
  git_short="${git_short:-nogit}"
  printf '%s-%s\n' "$(date +%Y%m%d-%H%M%S)" "${git_short}"
}

validate_release_id() {
  [[ "${RELEASE_ID}" =~ ^[0-9]{8}-[0-9]{6}-[A-Za-z0-9._-]+$ ]] || die "release-id invalido: ${RELEASE_ID}"
}

normalize_abs_path() {
  local p="$1"
  python3 - "$p" <<'PY'
import os
import sys
print(os.path.realpath(sys.argv[1]))
PY
}

ensure_magento_root() {
  [[ -d "${MAGENTO_ROOT}" ]] || die "MAGENTO_ROOT inexistente: ${MAGENTO_ROOT}"
  [[ -f "${MAGENTO_ROOT}/bin/magento" ]] || die "bin/magento nao encontrado em ${MAGENTO_ROOT}"
  [[ -f "${MAGENTO_ROOT}/app/etc/env.php" ]] || die "app/etc/env.php ausente em ${MAGENTO_ROOT}"
}

active_root_realpath() {
  normalize_abs_path "${MAGENTO_ACTIVE_ROOT}"
}

magento_root_realpath() {
  normalize_abs_path "${MAGENTO_ROOT}"
}

fs_owner_user() {
  stat -c '%U' "${MAGENTO_ROOT}"
}

current_user() {
  id -un
}

validate_execution_user() {
  local owner user
  owner="$(fs_owner_user)"
  user="$(current_user)"
  if [[ "${user}" != "${owner}" ]]; then
    if [[ "${BUILD_REAL}" == "1" ]]; then
      warn "BUILD_REAL=1 com usuario ${user} diferente do owner atual (${owner}); validacao final ocorrera no root isolado."
      return 0
    fi
    if [[ "${DRY_RUN}" == "1" ]]; then
      warn "Usuario atual (${user}) difere do owner (${owner}); permitido apenas por DRY_RUN=1."
    else
      die "Execute como owner do filesystem (${owner}) para DRY_RUN=0."
    fi
  fi
}

deploy_mode_of_root() {
  php "${MAGENTO_ROOT}/bin/magento" deploy:mode:show 2>/dev/null \
    | awk -F': ' '/Current application mode/{print $2}' \
    | awk '{print $1}' \
    | tr -d '.' \
    | tr '[:upper:]' '[:lower:]' \
    | xargs
}

validate_production_mode() {
  local mode
  mode="$(deploy_mode_of_root)"
  [[ -n "${mode}" ]] || die "Nao foi possivel ler deploy mode."
  [[ "${mode}" == "production" ]] || die "Deploy mode invalido para pipeline: ${mode}"
}

ensure_disk_space_kb() {
  local min_kb="$1"
  local avail_kb
  avail_kb="$(df -Pk "${RELEASES_ROOT}" | awk 'NR==2{print $4}')"
  [[ -n "${avail_kb}" ]] || die "Nao foi possivel ler espaco em disco."
  (( avail_kb >= min_kb )) || die "Espaco insuficiente em ${RELEASES_ROOT}: ${avail_kb}KB < ${min_kb}KB"
}

acquire_stage_lock() {
  local lf="${LOCK_FILE_AUDIT}"
  case "${PIPELINE_STAGE}" in
    build-static|build-phtml|verify) lf="${LOCK_FILE_BUILD}" ;;
    publish-static|publish-phtml|rollback) lf="${LOCK_FILE_PUBLISH}" ;;
  esac
  mkdir -p "$(dirname "${lf}")"
  exec 9>"${lf}"
  flock -n 9 || die "Lock ocupado para stage ${PIPELINE_STAGE}: ${lf}"
}

setup_release_layout() {
  RELEASE_DIR="${RELEASES_ROOT}/${RELEASE_ID}"
  LOG_DIR="${RELEASE_DIR}/logs"
  MANIFEST_DIR="${RELEASE_DIR}/manifests"
  TMP_DIR="${RELEASE_DIR}/tmp"
  mkdir -p \
    "${RELEASE_DIR}/source-snapshot" \
    "${RELEASE_DIR}/generated" \
    "${RELEASE_DIR}/static" \
    "${RELEASE_DIR}/preprocessed" \
    "${MANIFEST_DIR}" \
    "${RELEASE_DIR}/backups" \
    "${LOG_DIR}" \
    "${TMP_DIR}"
  LOG_FILE="${LOG_DIR}/${PIPELINE_STAGE}.log"
  touch "${LOG_FILE}"
}

run_cmd() {
  if [[ "${DRY_RUN}" == "1" ]]; then
    info "DRY_RUN: $*"
  else
    info "RUN: $*"
    "$@"
  fi
}

safe_tmp_cleanup() {
  if [[ -n "${TMP_DIR}" && -d "${TMP_DIR}" ]]; then
    rm -rf -- "${TMP_DIR:?}/"*
  fi
}

register_signal_traps() {
  trap 'die "Execucao interrompida por sinal."' INT TERM
  trap 'safe_tmp_cleanup' EXIT
}

git_branch_or_na() {
  git -C "${MAGENTO_ACTIVE_ROOT}" branch --show-current 2>/dev/null || echo "N/A"
}

git_commit_or_na() {
  git -C "${MAGENTO_ACTIVE_ROOT}" rev-parse HEAD 2>/dev/null || echo "N/A"
}

git_dirty_or_na() {
  git -C "${MAGENTO_ACTIVE_ROOT}" status --porcelain 2>/dev/null | awk 'END { if (NR==0) print "clean"; else print "dirty" }' || echo "N/A"
}

write_json_kv() {
  local output="$1"
  shift
  python3 - "$output" "$@" <<'PYJSON'
import json
import sys

path = sys.argv[1]
pairs = sys.argv[2:]
obj = {}
for p in pairs:
    k, v = p.split("=", 1)
    obj[k] = v
with open(path, "w", encoding="utf-8") as fh:
    json.dump(obj, fh, ensure_ascii=True, indent=2, sort_keys=True)
PYJSON
}

collect_root_fingerprint() {
  local root="$1"
  local out="$2"
  local real
  real="$(normalize_abs_path "${root}")"
  {
    echo "root=${root}"
    echo "realpath=${real}"
    stat -c 'stat=%d|%i|%U|%G|%a|%s|%Y|%n' "${real}"
    stat -c 'bin_magento=%d|%i|%U|%G|%a|%s|%Y|%n' "${real}/bin/magento"
    stat -c 'env_php=%d|%i|%U|%G|%a|%s|%Y|%n' "${real}/app/etc/env.php"
  } > "${out}"
}

validate_root_guardrails() {
  local target="$1"
  local target_real active_real
  target_real="$(normalize_abs_path "${target}")"
  active_real="$(active_root_realpath)"

  [[ -n "${target_real}" ]] || die "Nao foi possivel resolver realpath de ${target}."
  [[ "${target_real}" != "/" ]] || die "Path invalido: /"
  [[ "${target_real}" != "${active_real}" ]] || die "Path invalido: coincide com Magento ativo."
  [[ "${target_real}" != "${active_real}/pub" ]] || die "Path invalido: pub ativo."
  [[ "${target_real}" != "${active_real}/var" ]] || die "Path invalido: var ativo."
  [[ "${target_real}" != "${active_real}/generated" ]] || die "Path invalido: generated ativo."
  [[ "${target_real}" == *"/pub" ]] && die "Path invalido: raiz nao pode ser somente pub."

  [[ -f "${target_real}/.awa-isolated-build-root" ]] || die "Marker de isolamento ausente: ${target_real}/.awa-isolated-build-root"

  if [[ "${target_real}" == "${active_real}"* ]]; then
    die "Isolamento invalido: caminho alvo dentro da raiz ativa."
  fi

  python3 - "${target_real}" "${active_real}" <<'PY'
import os
import sys
from pathlib import Path

target = Path(sys.argv[1])
active = Path(sys.argv[2])

for p in target.rglob('*'):
    if p.is_symlink():
        try:
            resolved = p.resolve(strict=False)
        except OSError:
            continue
        if str(resolved).startswith(str(active)):
            print(f"Symlink atravessa para producao: {p} -> {resolved}")
            sys.exit(4)
print("ok")
PY
}

assert_isolated_build_guard() {
  [[ "${BUILD_REAL}" == "1" ]] || die "BUILD_REAL deve ser 1 para build real."
  [[ "${DRY_RUN}" == "0" ]] || die "DRY_RUN deve ser 0 quando BUILD_REAL=1."
  [[ -n "${CONFIRM_ISOLATED_BUILD:-}" ]] || die "CONFIRM_ISOLATED_BUILD obrigatorio."
  [[ "${CONFIRM_ISOLATED_BUILD}" == "${RELEASE_ID}" ]] || die "CONFIRM_ISOLATED_BUILD diverge do release-id."
  [[ -n "${ISOLATED_MAGENTO_ROOT:-}" ]] || die "ISOLATED_MAGENTO_ROOT obrigatorio."

  validate_root_guardrails "${ISOLATED_MAGENTO_ROOT}"

  MAGENTO_ROOT="${ISOLATED_MAGENTO_ROOT}"
  ensure_magento_root

  collect_root_fingerprint "${MAGENTO_ACTIVE_ROOT}" "${MANIFEST_DIR}/active-root-fingerprint.txt"
  collect_root_fingerprint "${MAGENTO_ROOT}" "${MANIFEST_DIR}/isolated-root-fingerprint.txt"
}

assert_publish_guard() {
  [[ "${PUBLISH_REAL}" == "1" ]] || die "PUBLISH_REAL deve ser 1 para publicacao real."
  [[ "${DRY_RUN}" == "0" ]] || die "DRY_RUN deve ser 0 para publicacao real."
  [[ -n "${CONFIRM_PRODUCTION_RELEASE:-}" ]] || die "CONFIRM_PRODUCTION_RELEASE obrigatorio."
  [[ "${CONFIRM_PRODUCTION_RELEASE}" == "${RELEASE_ID}" ]] || die "CONFIRM_PRODUCTION_RELEASE diverge do release-id."
  [[ -n "${CONFIRM_MANIFEST_SHA256:-}" ]] || die "CONFIRM_MANIFEST_SHA256 obrigatorio."
  [[ -n "${CONFIRM_ACTIVE_ROOT:-}" ]] || die "CONFIRM_ACTIVE_ROOT obrigatorio."
  [[ "$(normalize_abs_path "${CONFIRM_ACTIVE_ROOT}")" == "$(active_root_realpath)" ]] || die "CONFIRM_ACTIVE_ROOT diverge da raiz ativa esperada."
}

record_semantics_summary() {
  local out="${MANIFEST_DIR}/semantics-${PIPELINE_STAGE}.txt"
  {
    echo "stage=${PIPELINE_STAGE}"
    echo "dry_run=${DRY_RUN}"
    echo "build_real=${BUILD_REAL}"
    echo "publish_real=${PUBLISH_REAL}"
    echo "magento_active_root=$(active_root_realpath)"
    echo "magento_root=$(magento_root_realpath)"
    echo "release_id=${RELEASE_ID}"
  } > "${out}"
}

init_pipeline() {
  require_cmd php python3 sha256sum stat awk flock df
  mkdir -p "${RELEASES_ROOT}"
  if [[ -z "${RELEASE_ID}" ]]; then
    RELEASE_ID="$(new_release_id)"
  fi
  validate_release_id
  setup_release_layout
  acquire_stage_lock
  register_signal_traps

  ensure_magento_root
  validate_execution_user
  if [[ "${BUILD_REAL}" == "1" ]]; then
    info "BUILD_REAL=1: validacao deploy:mode no root isolado foi pulada para evitar dependencia de DB ativo."
  else
    validate_production_mode
  fi
  ensure_disk_space_kb 1048576

  record_semantics_summary

  info "Pipeline inicializada. release-id=${RELEASE_ID} stage=${PIPELINE_STAGE} dry_run=${DRY_RUN} build_real=${BUILD_REAL} publish_real=${PUBLISH_REAL}"
}

usage_common_flags() {
  cat <<'USAGE'
Flags comuns:
  --release-id <id>    Define release id (formato: YYYYMMDD-HHMMSS-sufixo)
  --dry-run <0|1>      Sobrescreve DRY_RUN (padrao: 1)
  --build-real <0|1>   Sobrescreve BUILD_REAL (padrao: 0)
  --publish-real <0|1> Sobrescreve PUBLISH_REAL (padrao: 0)
  -h, --help           Exibe ajuda
USAGE
}

parse_common_args() {
  while [[ $# -gt 0 ]]; do
    case "$1" in
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
