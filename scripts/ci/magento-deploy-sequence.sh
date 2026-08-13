#!/usr/bin/env bash
# magento-deploy-sequence.sh — sequência Magento 2 de produção (DevDocs).
# Padrão: DRY-RUN (só imprime). Nunca git pull, nunca lê env.php.
#
# Uso no servidor de destino (não na CI, salvo listagem):
#   DRY_RUN=1 bash scripts/ci/magento-deploy-sequence.sh
#   APPLY=1   bash scripts/ci/magento-deploy-sequence.sh   # exige MAGENTO_ROOT
set -euo pipefail

APPLY="${APPLY:-0}"
THEME="${MAGENTO_THEME:-AWA_Custom/ayo_home5_child}"
LOCALE="${MAGENTO_LOCALE:-pt_BR}"
ROOT_DIR="${MAGENTO_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"

if [[ "$APPLY" == "1" ]]; then
  echo "APPLY=1 recusado neste script de CI: deploy Magento exige autorização operacional e backup." >&2
  echo "Use o runbook impresso abaixo em janela controlada, no host correto." >&2
  exit 2
fi

cat <<EOF
# Sequência Magento 2.4 produção (dry-run / runbook)
# Tema: ${THEME}
# Locale: ${LOCALE}
# Root: ${ROOT_DIR}
#
# 1. maintenance:enable
# 2. composer install --no-dev --optimize-autoloader
# 3. bin/magento setup:di:compile
# 4. bin/magento setup:static-content:deploy ${LOCALE} -f --theme ${THEME} --jobs=1
# 5. bin/magento setup:upgrade --keep-generated
# 6. bin/magento app:config:import
# 7. bin/magento cache:clean
# 8. maintenance:disable
#
# Não usar setup:upgrade --keep-generated ANTES do compile.
# Não usar setup:static-content:deploy -l sem locale.
# Não apontar staging para o DocumentRoot de produção.
# Não git pull em worktree sujo de produção.
EOF
