#!/usr/bin/env bash
set -Eeuo pipefail
PIPELINE_STAGE="status"
SCRIPT_NAME="$(basename "$0")"
# shellcheck source=dev/tools/awa-deploy/lib/common.sh
source "$(cd "$(dirname "$0")" && pwd)/lib/common.sh"

usage() {
  cat <<'USAGE'
Uso: awa-release-status.sh [--dry-run <0|1>]

Mostra releases e manifestos gerados pela pipeline.
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

require_cmd ls stat
mkdir -p "${RELEASES_ROOT}"

printf 'releases_root=%s\n' "${RELEASES_ROOT}"

for d in "${RELEASES_ROOT}"/*; do
  [[ -d "${d}" ]] || continue
  rid="$(basename "${d}")"
  manifest_count="$(python3 - <<'PYCOUNT' "${d}"
import sys
from pathlib import Path
p = Path(sys.argv[1]) / "manifests"
print(len(list(p.glob("*.json"))) if p.exists() else 0)
PYCOUNT
)"
  latest_manifest="$(python3 - <<'PYLATEST' "${d}"
import sys
from pathlib import Path
p = Path(sys.argv[1]) / "manifests"
files = sorted(p.glob("*.json"), key=lambda x: x.stat().st_mtime, reverse=True) if p.exists() else []
print(files[0] if files else "")
PYLATEST
)"
  printf '%s|manifests=%s|latest=%s\n' "${rid}" "${manifest_count}" "${latest_manifest:-N/A}"
done
