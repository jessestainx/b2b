#!/usr/bin/env bash
set -Eeuo pipefail
PIPELINE_STAGE="rollback"
SCRIPT_NAME="$(basename "$0")"
# shellcheck source=dev/tools/awa-deploy/lib/common.sh
source "$(cd "$(dirname "$0")" && pwd)/lib/common.sh"

usage() {
  cat <<'USAGE'
Uso: awa-rollback-release.sh [--release-id <id>] [--dry-run <0|1>] [--publish-real <0|1>]

Rollback real permanece bloqueado na DEPLOY-1.
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
[[ "${BUILD_REAL}" == "0" ]] || die "rollback nao aceita BUILD_REAL=1."

init_pipeline

PLAN_FILE="${MANIFEST_DIR}/rollback-plan.txt"
cat > "${PLAN_FILE}" <<PLAN
stage=rollback
release_id=${RELEASE_ID}
dry_run=${DRY_RUN}
publish_real=${PUBLISH_REAL}
required_flags=PUBLISH_REAL=1,CONFIRM_PRODUCTION_RELEASE,CONFIRM_MANIFEST_SHA256,CONFIRM_ACTIVE_ROOT
steps=lock,restore_hash_verified_backup,verify_http,unlock
PLAN

if [[ "${DRY_RUN}" == "1" ]]; then
  info "DRY_RUN ativo. Nenhum rollback sera executado. Plano: ${PLAN_FILE}"
  exit 0
fi

assert_publish_guard
die "Rollback real bloqueado na DEPLOY-1 por politica de seguranca."
