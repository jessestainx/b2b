#!/usr/bin/env bash
set -Eeuo pipefail

SELF_DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=dev/tools/awa-deploy/stack_guard.lib.sh
source "${SELF_DIR}/stack_guard.lib.sh"

ACTION=""
STACK_ROOT=""
COMPOSE_FILE=""
ENV_FILE=""
RELEASE_ID=""
RELEASE_ROOT=""
PROJECT_NAME=""
IDENTITY_FILE=""
HOST_PORT=""
DRY_RUN="1"
CONFIRM_RELEASE=""

usage() {
  cat <<'USAGE'
Uso:
  awa-stack-guard.sh --action <validate|status|up|down|restart> \
    --stack-root <dir> \
    --compose-file <file> \
    --env-file <file> \
    --release-id <id> \
    --release-root <path> \
    --project-name <project> \
    --identity-file <file> \
    --host-port <porta> \
    [--dry-run <0|1>] \
    [--confirm-release <id>]

Regras:
  - Nunca depende de basename de diretorio para projeto Compose.
  - Sempre roda docker compose com env sanitizado (env -i + --env-file explicito).
  - Aborta em colisao de projeto, porta, marker ou mount inesperado.
USAGE
}

parse_args() {
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --action) shift; ACTION="${1:-}" ;;
      --stack-root) shift; STACK_ROOT="${1:-}" ;;
      --compose-file) shift; COMPOSE_FILE="${1:-}" ;;
      --env-file) shift; ENV_FILE="${1:-}" ;;
      --release-id) shift; RELEASE_ID="${1:-}" ;;
      --release-root) shift; RELEASE_ROOT="${1:-}" ;;
      --project-name) shift; PROJECT_NAME="${1:-}" ;;
      --identity-file) shift; IDENTITY_FILE="${1:-}" ;;
      --host-port) shift; HOST_PORT="${1:-}" ;;
      --dry-run) shift; DRY_RUN="${1:-1}" ;;
      --confirm-release) shift; CONFIRM_RELEASE="${1:-}" ;;
      -h|--help) usage; exit 0 ;;
      *) sg_die "Argumento invalido: $1" ;;
    esac
    shift || true
  done
}

require_nonempty() {
  local k="$1"
  local v="$2"
  [[ -n "$v" ]] || sg_die "Parametro obrigatorio ausente: ${k}"
}

validate_inputs() {
  sg_require_cmd docker python3
  require_nonempty "action" "$ACTION"
  require_nonempty "stack-root" "$STACK_ROOT"
  require_nonempty "compose-file" "$COMPOSE_FILE"
  require_nonempty "env-file" "$ENV_FILE"
  require_nonempty "release-id" "$RELEASE_ID"
  require_nonempty "release-root" "$RELEASE_ROOT"
  require_nonempty "project-name" "$PROJECT_NAME"
  require_nonempty "identity-file" "$IDENTITY_FILE"
  require_nonempty "host-port" "$HOST_PORT"

  [[ -d "$STACK_ROOT" ]] || sg_die "stack-root inexistente: $STACK_ROOT"
  [[ -f "$COMPOSE_FILE" ]] || sg_die "compose-file inexistente: $COMPOSE_FILE"
  [[ -f "$ENV_FILE" ]] || sg_die "env-file inexistente: $ENV_FILE"
  [[ -d "$RELEASE_ROOT" ]] || sg_die "release-root inexistente: $RELEASE_ROOT"
}

guard_validate_all() {
  if sg_detect_polluted_env; then
    :
  else
    sg_die "Ambiente herdado contaminado detectado. Limpe variaveis e reexecute."
  fi

  sg_assert_release_root_marker "$IDENTITY_FILE" "$RELEASE_ID"
  sg_assert_env_release_root "$ENV_FILE" "$RELEASE_ROOT"
  sg_assert_nonprod_endpoints "$ENV_FILE"
  sg_assert_no_project_collision "$PROJECT_NAME" "$RELEASE_ID"
  sg_assert_port_owner "$HOST_PORT" "$PROJECT_NAME"
  sg_assert_running_mounts "$PROJECT_NAME" "$RELEASE_ROOT"

  local db_host redis_host os_host
  db_host="$(sg_read_env_value "$ENV_FILE" "DB_HOST")"; db_host="${db_host:-<na>}"
  redis_host="$(sg_read_env_value "$ENV_FILE" "REDIS_HOST")"; redis_host="${redis_host:-<na>}"
  os_host="$(sg_read_env_value "$ENV_FILE" "OPENSEARCH_HOST")"; os_host="${os_host:-<na>}"
  sg_print_context "$RELEASE_ID" "$(sg_realpath "$RELEASE_ROOT")" "$PROJECT_NAME" "$(sg_realpath "$COMPOSE_FILE")" "$(sg_realpath "$ENV_FILE")" "$HOST_PORT" "$db_host" "$redis_host" "$os_host"
}

do_up() {
  if [[ "$DRY_RUN" == "1" ]]; then
    sg_info "DRY_RUN=1: pular up real."
    return 0
  fi
  sg_compose "$ENV_FILE" "$COMPOSE_FILE" "$PROJECT_NAME" up -d
}

do_down() {
  [[ -n "$CONFIRM_RELEASE" ]] || sg_die "down/restart exige --confirm-release"
  [[ "$CONFIRM_RELEASE" == "$RELEASE_ID" ]] || sg_die "--confirm-release diverge do release-id"
  if [[ "$DRY_RUN" == "1" ]]; then
    sg_info "DRY_RUN=1: pular down real."
    return 0
  fi
  sg_compose "$ENV_FILE" "$COMPOSE_FILE" "$PROJECT_NAME" down
}

do_status() {
  sg_compose "$ENV_FILE" "$COMPOSE_FILE" "$PROJECT_NAME" ps
}

main() {
  parse_args "$@"
  validate_inputs
  guard_validate_all

  case "$ACTION" in
    validate)
      sg_info "VALIDATE OK: guardrails satisfeitos."
      ;;
    status)
      do_status
      ;;
    up)
      do_up
      ;;
    down)
      do_down
      ;;
    restart)
      do_down
      do_up
      ;;
    *)
      sg_die "Action invalida: $ACTION"
      ;;
  esac
}

main "$@"
