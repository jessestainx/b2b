#!/usr/bin/env bash
set -Eeuo pipefail

sg_info() { printf '[stack-guard][INFO] %s\n' "$*" >&2; }
sg_warn() { printf '[stack-guard][WARN] %s\n' "$*" >&2; }
sg_die() { printf '[stack-guard][ERROR] %s\n' "$*" >&2; exit 1; }

sg_require_cmd() {
  local c
  for c in "$@"; do
    command -v "$c" >/dev/null 2>&1 || sg_die "Comando ausente: $c"
  done
}

sg_realpath() {
  python3 - "$1" <<'PY'
import os
import sys
print(os.path.realpath(sys.argv[1]))
PY
}

sg_read_env_value() {
  local file="$1"
  local key="$2"
  python3 - "$file" "$key" <<'PY'
import sys
from pathlib import Path

env_file = Path(sys.argv[1])
key = sys.argv[2]
val = ""
for raw in env_file.read_text(encoding='utf-8').splitlines():
    line = raw.strip()
    if not line or line.startswith('#') or '=' not in line:
        continue
    k, v = line.split('=', 1)
    if k.strip() == key:
        val = v.strip().strip('"').strip("'")
        break
print(val)
PY
}

sg_read_identity_value() {
  local file="$1"
  local key="$2"
  python3 - "$file" "$key" <<'PY'
import sys
from pathlib import Path

identity = Path(sys.argv[1])
key = sys.argv[2]
val = ""
for raw in identity.read_text(encoding='utf-8').splitlines():
    line = raw.strip()
    if not line or line.startswith('#') or '=' not in line:
        continue
    k, v = line.split('=', 1)
    if k.strip() == key:
        val = v.strip()
        break
print(val)
PY
}

sg_detect_polluted_env() {
  local polluted=0
  local vars=(
    "COMPOSE_PROJECT_NAME"
    "MAGENTO_ROOT"
    "ISOLATED_MAGENTO_ROOT"
    "RELEASE_ID"
    "DB_HOST"
    "DB_NAME"
    "REDIS_HOST"
    "OPENSEARCH_HOST"
    "CONTENT_VERSION"
    "CONFIRM_ISOLATED_BUILD"
    "CONFIRM_PRODUCTION_RELEASE"
    "CONFIRM_ACTIVE_ROOT"
  )
  local v
  for v in "${vars[@]}"; do
    if printenv "$v" >/dev/null 2>&1; then
      polluted=1
      sg_warn "Variavel herdada detectada (sera ignorada): ${v}"
    fi
  done
  return "${polluted}"
}

sg_compose() {
  local env_file="$1"
  local compose_file="$2"
  local project="$3"
  shift 3
  # Ambiente sanitizado para impedir vazamento entre execucoes.
  env -i \
    PATH="$PATH" \
    HOME="${HOME:-/root}" \
    LANG="${LANG:-C.UTF-8}" \
    docker compose \
      --env-file "$env_file" \
      -f "$compose_file" \
      -p "$project" \
      "$@"
}

sg_assert_release_root_marker() {
  local identity_file="$1"
  local expected_release="$2"
  [[ -f "$identity_file" ]] || sg_die "Marker de identidade ausente: $identity_file"
  local marker_release
  marker_release="$(sg_read_identity_value "$identity_file" "release_id")"
  [[ -n "$marker_release" ]] || sg_die "release_id ausente no marker: $identity_file"
  [[ "$marker_release" == "$expected_release" ]] || sg_die "Marker release_id diverge: marker=$marker_release esperado=$expected_release"
}

sg_assert_env_release_root() {
  local env_file="$1"
  local expected_root="$2"
  local release_root
  release_root="$(sg_read_env_value "$env_file" "RELEASE_ROOT")"
  [[ -n "$release_root" ]] || sg_die "RELEASE_ROOT ausente em $env_file"
  local env_root_real expected_root_real
  env_root_real="$(sg_realpath "$release_root")"
  expected_root_real="$(sg_realpath "$expected_root")"
  [[ "$env_root_real" == "$expected_root_real" ]] || sg_die "RELEASE_ROOT diverge: env=$env_root_real esperado=$expected_root_real"
}

sg_assert_no_project_collision() {
  local project="$1"
  local release_id="$2"
  local tmp_file
  tmp_file="$(mktemp)"
  docker ps -a --format '{{.ID}} {{.Names}} {{.Label "com.docker.compose.project"}} {{.Label "com.awamotos.release_id"}}' > "$tmp_file"
  python3 - "$tmp_file" "$project" "$release_id" <<'PY'
import sys
from pathlib import Path

rows = Path(sys.argv[1]).read_text(encoding='utf-8').splitlines()
project = sys.argv[2]
release = sys.argv[3]

for row in rows:
    parts = row.split()
    if len(parts) < 4:
        continue
    _, name, c_project, c_release = parts[0], parts[1], parts[2], parts[3]
    if c_project == project and c_release not in ("", release):
        print(f"COLLISION project={project} container={name} release={c_release} esperado={release}")
        sys.exit(3)
print("ok")
PY
  rm -f "$tmp_file"
}

sg_assert_port_owner() {
  local host_port="$1"
  local project="$2"
  local tmp_file
  tmp_file="$(mktemp)"
  docker ps -a --format '{{.Names}}|{{.Ports}}|{{.Label "com.docker.compose.project"}}|{{.Label "com.awamotos.release_id"}}' > "$tmp_file"
  python3 - "$tmp_file" "$host_port" "$project" <<'PY'
import re
import sys
from pathlib import Path

rows = Path(sys.argv[1]).read_text(encoding='utf-8').splitlines()
port = sys.argv[2]
project = sys.argv[3]
pat = re.compile(rf'127\.0\.0\.1:{re.escape(port)}->')

for row in rows:
    parts = row.split('|')
    if len(parts) != 4:
        continue
    name, ports, c_project, c_release = parts
    if pat.search(ports) and c_project != project:
        print(f"PORT_CONFLICT port={port} container={name} project={c_project} release={c_release}")
        sys.exit(4)
print("ok")
PY
  rm -f "$tmp_file"
}

sg_assert_running_mounts() {
  local project="$1"
  local release_root="$2"
  local release_real
  release_real="$(sg_realpath "$release_root")"
  local cid tmp_file
  tmp_file="$(mktemp)"
  docker ps -a --filter "label=com.docker.compose.project=${project}" --format '{{.ID}}' > "$tmp_file"
  while IFS= read -r cid; do
    [[ -n "$cid" ]] || continue
    docker inspect "$cid" --format '{{.Name}}|{{json .Mounts}}' >> "${tmp_file}.inspect"
  done < "$tmp_file"
  python3 - "$release_real" "${tmp_file}.inspect" <<'PY'
import json
import sys
from pathlib import Path

release_root = Path(sys.argv[1]).resolve()
inspect_file = Path(sys.argv[2])
if not inspect_file.exists():
    print("ok")
    sys.exit(0)

for raw in inspect_file.read_text(encoding='utf-8').splitlines():
    if not raw.strip() or "|" not in raw:
        continue
    name, mounts_json = raw.split("|", 1)
    mounts = json.loads(mounts_json)
    for m in mounts:
        dst = m.get("Destination", "")
        src = m.get("Source", "")
        if dst == "/var/www/html":
            src_real = Path(src).resolve()
            if src_real != release_root:
                print(f"MOUNT_MISMATCH container={name} dst={dst} src={src_real} esperado={release_root}")
                sys.exit(5)
print("ok")
PY
  rm -f "$tmp_file" "${tmp_file}.inspect"
}

sg_assert_nonprod_endpoints() {
  local env_file="$1"
  local db_host redis_host os_host base_url
  db_host="$(sg_read_env_value "$env_file" "DB_HOST")"
  redis_host="$(sg_read_env_value "$env_file" "REDIS_HOST")"
  os_host="$(sg_read_env_value "$env_file" "OPENSEARCH_HOST")"
  base_url="$(sg_read_env_value "$env_file" "BASE_URL")"

  local banned_pat='(awamotos\.com|srv1113343\.hstgr\.cloud|prod|production)'
  if [[ -n "$db_host" && "$db_host" =~ $banned_pat ]]; then
    sg_die "DB_HOST suspeito de producao: $db_host"
  fi
  if [[ -n "$redis_host" && "$redis_host" =~ $banned_pat ]]; then
    sg_die "REDIS_HOST suspeito de producao: $redis_host"
  fi
  if [[ -n "$os_host" && "$os_host" =~ $banned_pat ]]; then
    sg_die "OPENSEARCH_HOST suspeito de producao: $os_host"
  fi
  if [[ -n "$base_url" && "$base_url" =~ $banned_pat ]]; then
    sg_die "BASE_URL suspeita de producao: $base_url"
  fi
}

sg_print_context() {
  local release_id="$1"
  local release_root="$2"
  local project="$3"
  local compose_file="$4"
  local env_file="$5"
  local host_port="$6"
  local db_host="$7"
  local redis_host="$8"
  local os_host="$9"
  printf '%s\n' "----- STACK CONTEXT -----"
  printf 'release_id=%s\n' "$release_id"
  printf 'release_root=%s\n' "$release_root"
  printf 'compose_project=%s\n' "$project"
  printf 'compose_file=%s\n' "$compose_file"
  printf 'env_file=%s\n' "$env_file"
  printf 'port=%s\n' "$host_port"
  printf 'db_host=%s\n' "$db_host"
  printf 'redis_host=%s\n' "$redis_host"
  printf 'opensearch_host=%s\n' "$os_host"
  printf '%s\n' "-------------------------"
}
