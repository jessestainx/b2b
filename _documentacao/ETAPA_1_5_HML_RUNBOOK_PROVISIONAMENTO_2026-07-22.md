# Etapa 1.5 — Runbook de provisionamento HML

**Status:** NÃO EXECUTAR até o gate de aprovação estar completo  
**Produção:** proibida como alvo  
**Pré-requisito:** `ETAPA_1_5_GATE_HML_APROVACAO_2026-07-22.md` preenchido

## 0. Fail-fast

Em **todo** comando de escrita no Magento HML:

```bash
export HML_APPROVED_HOST="<hostname-aprovado>"
export PROD_DB_NAMES="magento"
./dev/hml/hml-env-guard.sh   # deve imprimir HML-GUARD OK
```

Abortar se o hostname for `awamotos.com` ou se o guard falhar.

## 1. Host separado

1. Provisionar VM/VPS **novo** (não o de produção).
2. Registrar hostname + IP no gate.
3. Criar usuário **file-system owner** dedicado (ex.: `magento-hml`), grupo web do pool HML.
4. Instalar: nginx, PHP 8.4 CLI+FPM (pool próprio), Composer 2.10.x, MySQL 8.4, Redis 7.0.x (espelho), Elasticsearch 7.17.x (espelho da 1ª HML), mailcatcher/mailhog.
5. **Não** instalar/ligar crontab Magento inicialmente.
6. Consumers Magento: não iniciar.
7. Firewall: deny egress para IPs/hosts de ERP Sectra, SMTP prod, WhatsApp/Z-API, gateways de pagamento; allow só o necessário (OS updates, etc.).
8. Acesso admin: Basic Auth **ou** VPN **ou** allowlist IP + `X-Robots-Tag: noindex, nofollow`.

## 2. Código

```bash
# No host HML, como FS owner HML:
git clone <repo> /var/www/hml-awamotos   # path exemplo
cd /var/www/hml-awamotos
git checkout feature/admin-menu-unification   # contém Meu Desempenho
# composer install --no-dev  (se vendor não vier no clone)
```

Não copiar `app/etc/env.php` de produção sem reescrever.

## 3. Banco (cópia sanitizada)

1. Em **produção**, somente leitura: dump lógico (aprovação LGPD).
2. Importar em DB **`magento_hml`** (nome ≠ `magento`).
3. **Antes do primeiro boot Magento HML:**
   - Colocar crypt/key **do dump** no secret store da HML → `env.php` HML (fora do git).
   - **Não** gerar crypt/key nova ainda.
   - Substituir todas as credenciais (DB já HML; Redis HML; ERP/WhatsApp/SMTP/payment → dummy ou vazio).
   - Sanitizar PII (e-mails, telefones, documentos) conforme política aprovada.
   - Desativar **todos** `admin_user` copiados (`is_active=0`).
   - Limpar tokens OAuth / integration keys / tokens de API.
4. Só depois do ambiente isolado e boot estável: rotacionar crypt/key **somente na HML** pelo procedimento Magento suportado.

### Inventário Encrypted (custom) a invalidar/substituir na HML

Ver lista em `ETAPA_1_5_HOMOLOGACAO_GAP_ATENDENTE_2026-07-22.md` §3.2.

## 4. `env.php` HML (obrigatório)

- `db` → `magento_hml` + user HML
- Redis/session → host/DB numbers **não** 0/1/2 de produção (ou instância Redis dedicada)
- Search engine → cluster/credencial HML; prefixo de índice distinto
- Sem brokers/credenciais de produção
- `MAGE_MODE` = developer ou production isolado (decisão ops)
- Cron: não agendar

## 5. Bootstrap Magento HML

Como **FS owner da HML** (não root; não o owner de produção):

```bash
./dev/hml/hml-env-guard.sh
php bin/magento setup:upgrade --keep-generated   # se aplicável ao modo
php bin/magento config:set --scope=default --scope-code=0 web/secure/base_url "https://${HML_APPROVED_HOST}/"
php bin/magento config:set --scope=default --scope-code=0 web/unsecure/base_url "https://${HML_APPROVED_HOST}/"
# Desligar integrações (paths conforme system.xml) — ERP, WhatsApp, payments, SMTP real
php bin/magento cache:clean config
```

Criar **um** admin HML exclusivo + forçar 2FA. Não reativar users do dump.

## 6. Critérios de aceite da HML

- [ ] Guard OK com `HML_APPROVED_HOST`
- [ ] Homepage e Admin sob auth/VPN
- [ ] noindex presente
- [ ] Zero egress controlado para ERP/WhatsApp/SMTP/payment prod
- [ ] Redis/OS/DB isolados
- [ ] Cron/consumers off
- [ ] Item **Meu Desempenho** presente na branch
- [ ] Logs em path próprio da HML

## 7. Em seguida

Executar `dev/hml/hml-test-menu-flags.sh` (T1 → T2 → rollback).
