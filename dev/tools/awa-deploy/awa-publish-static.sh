#!/usr/bin/env bash
set -Eeuo pipefail
PIPELINE_STAGE="publish-static"
SCRIPT_NAME="$(basename "$0")"
# shellcheck source=dev/tools/awa-deploy/lib/common.sh
source "$(cd "$(dirname "$0")" && pwd)/lib/common.sh"

usage() {
  cat <<'USAGE'
Uso: awa-publish-static.sh [--release-id <id>] [--dry-run <0|1>] [--publish-real <0|1>]

Publicacao real permanece bloqueada na DEPLOY-1.
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
[[ "${BUILD_REAL}" == "0" ]] || die "publish-static nao aceita BUILD_REAL=1."

init_pipeline

PLAN_FILE="${MANIFEST_DIR}/publish-static-plan.txt"
cat > "${PLAN_FILE}" <<PLAN
stage=publish-static
release_id=${RELEASE_ID}
dry_run=${DRY_RUN}
publish_real=${PUBLISH_REAL}
required_flags=PUBLISH_REAL=1,CONFIRM_PRODUCTION_RELEASE,CONFIRM_MANIFEST_SHA256,CONFIRM_ACTIVE_ROOT
steps=validate_manifest,lock_publish,backup,atomic_promote,restricted_invalidation,http_verify,unlock
PLAN

if [[ "${DRY_RUN}" == "1" ]]; then
  info "DRY_RUN ativo. Nenhuma publicacao sera executada. Plano: ${PLAN_FILE}"
  exit 0
fi

assert_publish_guard
die "Publicacao real bloqueada na DEPLOY-1 por politica de seguranca."
