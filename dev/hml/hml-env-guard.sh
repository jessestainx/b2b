#!/usr/bin/env bash
# Fail-fast guard for Magento HML write operations.
# Abort before any config/cache/DB mutation if this looks like production.
set -euo pipefail

ROOT="${MAGENTO_ROOT:-$(cd "$(dirname "$0")/../.." && pwd)}"
cd "$ROOT"

fail() {
  echo "HML-GUARD ABORT: $*" >&2
  exit 1
}

HOST_FQDN="$(hostname -f 2>/dev/null || hostname)"
HOST_SHORT="$(hostname -s 2>/dev/null || hostname)"

if [[ "${HOST_FQDN}" == "awamotos.com" || "${HOST_SHORT}" == "awamotos.com" ]]; then
  fail "hostname is production (${HOST_FQDN})"
fi

# Approved HML hostname must be set formally before writes:
#   export HML_APPROVED_HOST=hml.example.internal
if [[ -z "${HML_APPROVED_HOST:-}" ]]; then
  fail "HML_APPROVED_HOST is not set (refusing writes without formal HML hostname)"
fi

eval "$(php -r '
$e = include "app/etc/env.php";
$db = $e["db"]["connection"]["default"] ?? [];
$redis = $e["cache"]["frontend"]["default"]["backend_options"] ?? [];
echo "export GUARD_DB_NAME=" . escapeshellarg((string)($db["dbname"] ?? "")) . "\n";
echo "export GUARD_DB_HOST=" . escapeshellarg((string)($db["host"] ?? "")) . "\n";
echo "export GUARD_REDIS_SERVER=" . escapeshellarg((string)($redis["server"] ?? "")) . "\n";
echo "export GUARD_REDIS_DB=" . escapeshellarg((string)($redis["database"] ?? "")) . "\n";
' 2>/dev/null)" || fail "cannot read app/etc/env.php"

BASE_URL="$(php bin/magento config:show web/secure/base_url 2>/dev/null || true)"
BASE_URL_UNSECURE="$(php bin/magento config:show web/unsecure/base_url 2>/dev/null || true)"
COMBINED_URL="${BASE_URL} ${BASE_URL_UNSECURE}"

if echo "${COMBINED_URL}" | grep -qiE 'awamotos\.com'; then
  if [[ "${HML_APPROVED_HOST}" != *awamotos.com ]] || ! echo "${COMBINED_URL}" | grep -qiF "${HML_APPROVED_HOST}"; then
    fail "base URL contains awamotos.com without approved HML host (${HML_APPROVED_HOST}); urls=${COMBINED_URL}"
  fi
fi

# Known production DB name(s) — extend via PROD_DB_NAMES if needed
PROD_DB_NAMES="${PROD_DB_NAMES:-magento}"
for prod_db in ${PROD_DB_NAMES}; do
  if [[ "${GUARD_DB_NAME}" == "${prod_db}" ]]; then
    fail "database name matches production (${GUARD_DB_NAME})"
  fi
done

if [[ "${GUARD_REDIS_DB}" == "0" || "${GUARD_REDIS_DB}" == "1" || "${GUARD_REDIS_DB}" == "2" ]]; then
  if [[ "${HML_ALLOW_PROD_REDIS_DB_NUMBERS:-0}" != "1" ]]; then
    fail "Redis DB ${GUARD_REDIS_DB} looks like production mapping (0/1/2); set dedicated HML DBs"
  fi
fi

php -r '
$e = include "app/etc/env.php";
$blocked = [];
foreach (["erp", "sectra", "whatsapp", "smtp", "payment"] as $k) {
  if (!empty($e[$k])) {
    $blocked[] = $k . " key present in env.php";
  }
}
if ($blocked) {
  fwrite(STDERR, "HML-GUARD ABORT: " . implode("; ", $blocked) . "\n");
  exit(1);
}
' || fail "integration credential check failed"

echo "HML-GUARD OK: host=${HOST_FQDN} approved=${HML_APPROVED_HOST} db=${GUARD_DB_NAME} redis_db=${GUARD_REDIS_DB}"
