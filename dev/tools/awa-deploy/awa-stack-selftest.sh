#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
GUARD="${ROOT_DIR}/awa-stack-guard.sh"

S1_STACK="/home/deploy/awa-deploy-source1qa/20260729-072550/stack"
S1_COMPOSE="${S1_STACK}/docker-compose.gate1.yml"
S1_ENV="${S1_STACK}/.env.gate1"
S1_RELEASE_ID="20260729-032550-source1menu"
S1_RELEASE_ROOT="/home/deploy/awa-releases/20260729-032550-source1menu/isolated-magento"
S1_PROJECT_CURRENT="awa_source1qa"
S1_PROJECT_HARDENED="awa-source1-20260729"
S1_IDENTITY="/home/deploy/awa-stack-identities/20260729-032550-source1menu/.awa-release-identity"
S1_PORT="18089"

A_STACK="/home/deploy/awa-deploy-phase2/20260729-001516/stack"
A_COMPOSE="${A_STACK}/docker-compose.gate1.yml"
A_ENV="${A_STACK}/.env.gate1"
A_RELEASE_ID="20260728-231217-realA"
A_RELEASE_ROOT="/home/deploy/awa-releases/20260728-231217-realA/isolated-magento"
A_PROJECT_CURRENT="stack"
A_PROJECT_HARDENED="awa-reala-20260728"
A_IDENTITY="/home/deploy/awa-stack-identities/20260728-231217-realA/.awa-release-identity"
A_PORT="18088"

run_case() {
  local name="$1"
  local expected_rc="$2"
  shift 2
  printf '[selftest] case=%s expected_rc=%s\n' "$name" "$expected_rc"
  set +e
  "$@"
  local rc=$?
  set -e
  printf '[selftest] case=%s got_rc=%s\n' "$name" "$rc"
  if [[ "$expected_rc" == "NONZERO" ]]; then
    if [[ "$rc" == "0" ]]; then
      printf '[selftest][FAIL] %s expected=NONZERO got=%s\n' "$name" "$rc" >&2
      return 1
    fi
    printf '[selftest][OK] %s\n' "$name"
    return 0
  fi
  if [[ "$rc" != "$expected_rc" ]]; then
    printf '[selftest][FAIL] %s expected=%s got=%s\n' "$name" "$expected_rc" "$rc" >&2
    return 1
  fi
  printf '[selftest][OK] %s\n' "$name"
}

run_guard_validate() {
  env -i PATH="$PATH" HOME="${HOME:-/root}" LANG="${LANG:-C.UTF-8}" "$GUARD" \
    --action validate \
    --stack-root "$1" \
    --compose-file "$2" \
    --env-file "$3" \
    --release-id "$4" \
    --release-root "$5" \
    --project-name "$6" \
    --identity-file "$7" \
    --host-port "$8" \
    --dry-run 1
}

run_guard_validate_polluted() {
  COMPOSE_PROJECT_NAME="stack" "$GUARD" \
    --action validate \
    --stack-root "$1" \
    --compose-file "$2" \
    --env-file "$3" \
    --release-id "$4" \
    --release-root "$5" \
    --project-name "$6" \
    --identity-file "$7" \
    --host-port "$8" \
    --dry-run 1
}

run_guard_validate_with_temp_env() {
  local stack="$1"
  local compose="$2"
  local src_env="$3"
  local release_id="$4"
  local release_root="$5"
  local project="$6"
  local identity="$7"
  local port="$8"
  local replace_key="$9"
  local replace_val="${10}"
  local tmp_env
  tmp_env="$(mktemp)"
  cp "$src_env" "$tmp_env"
  python3 - "$tmp_env" "$replace_key" "$replace_val" <<'PY'
from pathlib import Path
import sys
p = Path(sys.argv[1])
key = sys.argv[2]
val = sys.argv[3]
lines = p.read_text(encoding='utf-8').splitlines()
out = []
found = False
for line in lines:
    if line.startswith(key + "="):
        out.append(f"{key}={val}")
        found = True
    else:
        out.append(line)
if not found:
    out.append(f"{key}={val}")
p.write_text("\\n".join(out) + "\\n", encoding='utf-8')
PY
  run_guard_validate "$stack" "$compose" "$tmp_env" "$release_id" "$release_root" "$project" "$identity" "$port"
  local rc=$?
  rm -f "$tmp_env"
  return "$rc"
}

run_guard_down_invalid_confirm() {
  env -i PATH="$PATH" HOME="${HOME:-/root}" LANG="${LANG:-C.UTF-8}" "$GUARD" \
    --action down \
    --stack-root "$1" \
    --compose-file "$2" \
    --env-file "$3" \
    --release-id "$4" \
    --release-root "$5" \
    --project-name "$6" \
    --identity-file "$7" \
    --host-port "$8" \
    --dry-run 1 \
    --confirm-release "$9"
}

main() {
  run_case "A-validate-ok-runtime-atual" "0" run_guard_validate "$A_STACK" "$A_COMPOSE" "$A_ENV" "$A_RELEASE_ID" "$A_RELEASE_ROOT" "$A_PROJECT_CURRENT" "$A_IDENTITY" "$A_PORT"
  run_case "S1-validate-ok-runtime-atual" "0" run_guard_validate "$S1_STACK" "$S1_COMPOSE" "$S1_ENV" "$S1_RELEASE_ID" "$S1_RELEASE_ROOT" "$S1_PROJECT_CURRENT" "$S1_IDENTITY" "$S1_PORT"

  run_case "A-to-S1-env-file-vazado" "NONZERO" run_guard_validate "$S1_STACK" "$S1_COMPOSE" "$A_ENV" "$S1_RELEASE_ID" "$S1_RELEASE_ROOT" "$S1_PROJECT_HARDENED" "$S1_IDENTITY" "$S1_PORT"
  run_case "S1-to-A-env-file-vazado" "NONZERO" run_guard_validate "$A_STACK" "$A_COMPOSE" "$S1_ENV" "$A_RELEASE_ID" "$A_RELEASE_ROOT" "$A_PROJECT_HARDENED" "$A_IDENTITY" "$A_PORT"

  run_case "project-name-antigo" "NONZERO" run_guard_validate "$S1_STACK" "$S1_COMPOSE" "$S1_ENV" "$S1_RELEASE_ID" "$S1_RELEASE_ROOT" "$A_PROJECT_CURRENT" "$S1_IDENTITY" "$S1_PORT"

  run_case "porta-antiga" "NONZERO" run_guard_validate "$S1_STACK" "$S1_COMPOSE" "$S1_ENV" "$S1_RELEASE_ID" "$S1_RELEASE_ROOT" "$S1_PROJECT_HARDENED" "$S1_IDENTITY" "$A_PORT"

  run_case "release-root-antigo" "NONZERO" run_guard_validate "$S1_STACK" "$S1_COMPOSE" "$S1_ENV" "$S1_RELEASE_ID" "$A_RELEASE_ROOT" "$S1_PROJECT_HARDENED" "$S1_IDENTITY" "$S1_PORT"

  run_case "identity-divergente" "NONZERO" run_guard_validate "$S1_STACK" "$S1_COMPOSE" "$S1_ENV" "$A_RELEASE_ID" "$S1_RELEASE_ROOT" "$S1_PROJECT_HARDENED" "$S1_IDENTITY" "$S1_PORT"

  run_case "variavel-herdada-bloqueia" "NONZERO" run_guard_validate_polluted "$S1_STACK" "$S1_COMPOSE" "$S1_ENV" "$S1_RELEASE_ID" "$S1_RELEASE_ROOT" "$S1_PROJECT_HARDENED" "$S1_IDENTITY" "$S1_PORT"

  run_case "db-host-producao-bloqueado" "NONZERO" run_guard_validate_with_temp_env "$S1_STACK" "$S1_COMPOSE" "$S1_ENV" "$S1_RELEASE_ID" "$S1_RELEASE_ROOT" "$S1_PROJECT_HARDENED" "$S1_IDENTITY" "$S1_PORT" "DB_HOST" "awamotos.com"
  run_case "redis-host-producao-bloqueado" "NONZERO" run_guard_validate_with_temp_env "$S1_STACK" "$S1_COMPOSE" "$S1_ENV" "$S1_RELEASE_ID" "$S1_RELEASE_ROOT" "$S1_PROJECT_HARDENED" "$S1_IDENTITY" "$S1_PORT" "REDIS_HOST" "production-redis.awamotos.com"
  run_case "base-url-producao-bloqueada" "NONZERO" run_guard_validate_with_temp_env "$S1_STACK" "$S1_COMPOSE" "$S1_ENV" "$S1_RELEASE_ID" "$S1_RELEASE_ROOT" "$S1_PROJECT_HARDENED" "$S1_IDENTITY" "$S1_PORT" "BASE_URL" "https://awamotos.com/"
  run_case "down-stack-errada-bloqueado" "NONZERO" run_guard_down_invalid_confirm "$S1_STACK" "$S1_COMPOSE" "$S1_ENV" "$S1_RELEASE_ID" "$S1_RELEASE_ROOT" "$S1_PROJECT_HARDENED" "$S1_IDENTITY" "$S1_PORT" "$A_RELEASE_ID"
}

main "$@"
