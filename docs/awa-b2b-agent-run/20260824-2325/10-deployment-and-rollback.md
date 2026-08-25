# Implantação e rollback

## Esta branch NÃO deve ir para produção automaticamente

Código foi escrito no worktree `/home/deploy/.cursor/worktrees/awa-b2b-e2e-20260824-2325`.  
DocumentRoot de produção **não** recebeu os arquivos.

## Pré-requisitos de deploy (processo oficial)

1. Revisar o PR; não fazer merge daqui.
2. `setup:di:compile` no ambiente de destino (novos plugins GraphQL + método de interface).
3. `setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child` (JS coach + PHTML cadastro).
4. Copiar PHTML para `var/view_preprocessed` se o fluxo AWA exigir.
5. Invalidação mínima: `block_html`, `full_page`, `config`, `layout`. **Não** flush amplo como primeiro passo.
6. **Não** rodar `setup:upgrade` em produção só por esta onda (sem data patch EAV novo).

## Config Playwright pós-merge CI

```
TARGET_ENV=production_readonly
ALLOW_PRODUCTION_VALIDATION=true   # só jobs visuais já existentes
PLAYWRIGHT_BASE_URL=...
```

Piloto fiscal:

```
TARGET_ENV=pilot
AWA_PILOT_TEST=true
PILOT_* preenchidos com allowlist interna
```

## Rollback

Reverter o merge/commit da branch. Os plugins GraphQL são aditivos; rollback restaura o vazamento P0 — planejar hotfix se produção já estiver corrigida.

Não usar `git reset --hard` no worktree sujo de produção.

## Monitoramento pós-deploy

- GraphQL guest: `price_range.minimum_price.final_price.value` deve ser null.
- `/b2b/register` etapas + checkboxes privacy/whatsapp.
- Admin Request Review sem exception.log.
- Fila Sectra: contagens `ready_for_import` / `import_failed`.
- exception.log nas primeiras 30 min.
