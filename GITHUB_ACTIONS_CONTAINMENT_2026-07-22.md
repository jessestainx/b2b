# GITHUB ACTIONS — CONTENÇÃO FASE 1 / 1.1 (2026-07-22)

**Branch PR:** `security/ci-containment-fase1-20260722` (rebased onto `origin/main`; only containment commits)
**Worktree PR:** `/home/deploy/worktrees/ci-containment-fase1-20260722-pr`
**Canonical:** `awamotosbrand-prog/magento_b2b_awa` default `main`
**Produção:** **NO-GO** | **Fase 2:** **NÃO INICIADA**

# GITHUB ACTIONS — CONTENÇÃO FASE 1 (2026-07-22)

**Branch:** `security/ci-containment-fase1-20260722`  
**Worktree:** `/home/deploy/worktrees/ci-containment-fase1-20260722` (fora do docroot)  
**Produção:** **NO-GO**  
**Fase 2:** **NÃO INICIADA**

---

## 1. Pré-contenção (somente leitura)

### Repositórios e permissões administrativas

| Repo | Admin | Push | Nota |
|------|-------|------|------|
| `awamotosbrand-prog/magento_b2b_awa` (origin) | sim | sim | Branch protection API 403 (plano sem Pro) |
| `ecriativy-git/b2b` | sim | sim | Protection + ruleset ativos |
| `grupoawamarketing-wq/awa` | **não** | não | Somente pull; disable API indisponível |

### Branch protection / rulesets / required checks

| Repo | Protection | Required status checks (nome exato) | Rulesets |
|------|------------|-------------------------------------|----------|
| `ecriativy-git/b2b` `main` | sim | **`Cursor Bugbot`** (único) | `total` (deletion + non_fast_forward; sem required checks) |
| `awamotosbrand-prog/magento_b2b_awa` | indisponível (403 Pro) | nenhum obtido via API | 403 |
| `grupoawamarketing-wq/awa` | 404 / sem acesso | n/d | `[]` |

### Required check vs workflows de risco

| Workflow | Required check? | Impacto se desabilitado |
|----------|-----------------|-------------------------|
| e2e-pr-smoke | **NÃO** | Nenhum bloqueio de merge conhecido |
| product-design-qa | **NÃO** | — |
| menu-regression | **NÃO** | — |
| magento-ci | **NÃO** | — |
| playwright | **NÃO** | — |
| e2e-nightly-full | **NÃO** | — |
| e2e-premerge-regression | **NÃO** | — |

Único required check encontrado: **`Cursor Bugbot`** em `ecriativy-git/b2b`.

### Environments

| Repo | Environments |
|------|--------------|
| `awamotosbrand-prog/magento_b2b_awa` | nenhum |
| `ecriativy-git/b2b` | nenhum |
| `grupoawamarketing-wq/awa` | `copilot` (sem protection rules) |

### Workflows ativos/desabilitados (API, pós-contenção)

**Disable oficial (`disabled_manually`) aplicado em** `magento_b2b_awa` e `ecriativy-git/b2b`:

- e2e-pr-smoke
- e2e-nightly-full
- e2e-premerge-regression
- playwright.yml

`grupoawamarketing-wq/awa`: sem admin — contenção apenas via YAML (triggers removidos).

### Secrets referenciados (nomes apenas; valores NÃO exibidos)

Antes (em YAMLs / repo `magento_b2b_awa`): `B2B_TEST_*`, `COMPOSER_AUTH`; vars: `PLAYWRIGHT_BASE_URL_PR` (**valor era URL de produção** — variável **removida** na contenção).

Após contenção nos YAMLs: **nenhum** `secrets.*` sensível permanece em workflows com `pull_request`.

### Workflows acionáveis manualmente

Após Fase 1: **nenhum** dos 13 mantém `workflow_dispatch` (evita reabertura acidental a produção). Quarentena usa `workflow_call` sem callers.

---

## 2. Classificação dos 13 workflows

| Workflow | Trigger (antes) | Required check | Produção | Secrets | Estado atual | Ação de contenção |
|----------|-----------------|----------------|----------|---------|--------------|-------------------|
| quality-gates | PR + push main | Não | Não | — | **SEGURO ESTÁTICO** / **CANDIDATO A REAPROVEITAMENTO** | MANTER + `contents:read` + guard |
| sanity | PR + push ** | Não | Não | — | **SEGURO ESTÁTICO** | MANTER + `contents:read` |
| cms-blocks-verify | PR/push main | Não | Não | — | **SEGURO ESTÁTICO** | MANTER + `contents:read` |
| magento-ci | PR/push/tag/dispatch | Não | Deploy SSH | PROD/STAGING_* | **INATIVO ESTRUTURALMENTE** + **PERIGOSO** | Deploy `if:false` sem secrets; PR só validate estático |
| e2e-pr-smoke | PR | Não | Hard-code | B2B_* | **PERIGOSO — PRODUÇÃO** | Disable API + YAML quarentena |
| product-design-qa | PR + dispatch | Não | Hard-code | B2B_* | **PERIGOSO — PRODUÇÃO** | YAML quarentena (+ divergência mobile-390-chromium documentada) |
| menu-regression | path PR/push | Não | Hard-code | — | **PERIGOSO — PRODUÇÃO** | YAML quarentena |
| playwright-visual-qa | PR + dispatch | Não | via vars | BASE_URL* | **PERIGOSO** (var apontava prod) | YAML quarentena |
| visual-quality-gate | PR/push/schedule/dispatch | Não | auto-allow push/schedule | PLAYWRIGHT_* | **PERIGOSO** | YAML quarentena |
| e2e-nightly-full | cron + dispatch | Não | defaults npm | B2B_* | **QUEBRADO** + **PERIGOSO** | Disable API + quarentena (scripts .mjs ausentes) |
| e2e-premerge-regression | push main + dispatch | Não | defaults npm | B2B_* | **QUEBRADO** + **PERIGOSO** | Disable API + quarentena |
| playwright.yml | PR/push | Não | n/a (quebrado) | — | **QUEBRADO** | Disable API + quarentena (config válida em `tests/e2e`) |
| copilot-setup-steps | path PR/push + dispatch | Não | Não | — | **QUEBRADO** | YAML quarentena |

---

## 3. Workflows contidos e método

| Método | Alvos |
|--------|-------|
| GitHub Actions disable API (`disabled_manually`) | e2e-pr-smoke, nightly, premerge, playwright (2 remotes admin) |
| Remoção de triggers automáticos → `workflow_call` + banner `STATUS: QUARENTENA — NÃO EXECUTAR` | todos os perigosos/quebrados acima |
| `if: ${{ false }}` + secrets removidos | magento-ci deploy-staging, deploy-production, lighthouse legado |
| Delete var `PLAYWRIGHT_BASE_URL_PR` | removida de `magento_b2b_awa` (apontava produção) |
| Guard estático `scripts/ci/guard-no-production.sh` | wired em quality-gates |

**Não apagado:** nenhum arquivo de workflow.

---

## 4. Triggers antes → depois

| Workflow | Antes | Depois |
|----------|-------|--------|
| e2e-pr-smoke | `pull_request` | `workflow_call` (sem callers) |
| product-design-qa | PR + dispatch | `workflow_call` |
| menu-regression | path PR/push + dispatch | `workflow_call` |
| playwright.yml | PR/push main|master | `workflow_call` |
| nightly / premerge | cron/push/dispatch | `workflow_call` |
| visual-quality-gate | PR/push/schedule/dispatch | `workflow_call` |
| playwright-visual-qa | PR + dispatch | `workflow_call` |
| copilot-setup-steps | path + dispatch | `workflow_call` |
| magento-ci | PR + push + tags + dispatch | **somente** `pull_request` (validate estático) |
| quality/sanity/cms | inalterados (estáticos) | + `permissions: contents: read` |

---

## 5. Permissões antes → depois

| Antes | Depois |
|-------|--------|
| Maioria sem `permissions` (default amplo) | Todos os workflows tocados: `permissions: contents: read` |
| — | Sem `id-token`, `packages`, `deployments`, `pull-requests` write |

---

## 6. Hardcodes removidos

- URLs absolutas de produção em workflows de PR
- `ALLOW_PRODUCTION_VALIDATION=true` / defaults `${...:-true}` nos scripts npm MCP
- Defaults `PLAYWRIGHT_BASE_URL=https://[prod]` nos scripts MCP
- URLs absolutas em `mcp-visual-ops.helpers.ts` → paths relativos
- `page.goto('https://[prod]...')` em `visual-audit.helpers.ts` → paths relativos
- Bypass por flag única em `resolve-base-url.ts` / `target-url.ts` (flag agora **rejeitada**)
- `audit:home` / `audit:pwa` no `package.json` raiz (passam a falhar fechado)
- Var GitHub `PLAYWRIGHT_BASE_URL_PR` (produção)

---

## 7. Secrets que deixaram de ser referenciados nos YAMLs

- `B2B_TEST_USER` / `B2B_TEST_PASS` / `B2B_TEST_PENDING_*`
- `PROD_SSH_KEY` / `PROD_USER` / `PROD_HOST`
- `STAGING_SSH_KEY` / `STAGING_USER` / `STAGING_HOST`
- `PLAYWRIGHT_BASE_URL_PR` / `BASE_URL` (como secrets em workflows)

> Secrets **não foram apagados** do GitHub (exceto a **variable** `PLAYWRIGHT_BASE_URL_PR`). Apenas deixaram de ser referenciados pelos workflows contidos.

---

## 8. Divergência documentada (sem correção funcional)

- Projeto Playwright `mobile-390-chromium` existe em `playwright.config.ts` (raiz) e **não** em `tests/e2e/playwright.config.ts`.
- Config válida de CI: **`tests/e2e/`**.
- Scripts ausentes (não criados stubs):  
  `tests/e2e/scripts/mcp-visual-report.mjs`, `mcp-visual-autofix.mjs`, `visual-baseline-guard.mjs`.

---

## 9. Commits (isolados)

Branch `security/ci-containment-fase1-20260722` (worktree fora do docroot):

1. `chore(ci): quarantine production workflows`
2. `security(ci): remove production targets from pull requests`
3. `security(test): disable production defaults`
4. `docs(ci): record containment and reactivation criteria`

---

## 10. Rollback

```bash
# Worktree
cd /home/deploy/worktrees/ci-containment-fase1-20260722
git log --oneline -5
git revert --no-edit <sha4> <sha3> <sha2> <sha1>   # ou reset local se ainda não pushed

# Reabilitar workflows na API (somente com aprovação explícita — NÃO fazer agora)
# gh api --method PUT repos/<repo>/actions/workflows/<file>/enable
```

Reativar produção exige: staging isolado + environment com reviewers + guard verde + ticket + aprovação Fase 2+.

---

## 11. Riscos residuais

1. `grupoawamarketing-wq/awa` ainda pode listar workflows “active” na UI até o YAML ser mergeado (sem admin para disable API).
2. Secrets B2B/SSH **ainda existem** no GitHub (não referenciados) — rotacionar recomendado fora desta fase.
3. Specs em `tests/e2e/specs/**` podem ainda mencionar host de produção (fora do escopo do guard atual).
4. `magento-ci` validate em PR ainda existe (estático) — job skipped de deploy **não** prova segurança.
5. Docs/prompts históricos fora do escopo do guard ainda citam produção.

---

## 12. Itens ainda quebrados (intencional)

- nightly/premerge (scripts .mjs ausentes)
- playwright.yml raiz
- copilot-setup-steps
- product-design-qa / visual suites (quarentena)
- mobile-390-chromium desalinhado

---

## 13. Critérios de aceite Fase 1

| Critério | Status |
|----------|--------|
| Nenhum PR acessa produção | **OK** (workflows perigosos sem `pull_request`; guard; helpers hard-block) |
| Nenhum workflow automático faz deploy | **OK** (`if:false` + sem secrets + sem push/tag no magento-ci) |
| Nenhum default aponta produção | **OK** (npm/helpers) |
| Nenhum job de PR recebe secrets de produção | **OK** |
| Quebrados em quarentena clara | **OK** |
| magento-ci incapaz de deploy | **OK** |
| Nenhuma chamada externa nesta fase de validação | **OK** (sem act/gh workflow run/npm test/playwright/curl/ssh) |
| Produção NO-GO | **OK** |

## Status para iniciar Fase 2

### **NO-GO** para Fase 2 até aprovação explícita.

Pré-requisitos sugeridos para futura Fase 2 (não iniciada):

1. Staging isolado com URL **não** produtiva em vars
2. Environment GitHub com required reviewers
3. Decisão sobre restore dos scripts `tests/e2e/scripts/*.mjs` (sem stubs vazios)
4. Reabilitação seletiva de **um** smoke visual contra staging apenas


---

---

# FASE 1.1 — FECHAMENTO (atualizado pós-PR)

## Call graph

```
(nenhum caller)
     X---- uses: ./.github/workflows/{e2e-pr-smoke,e2e-nightly-full,e2e-premerge-regression,playwright,copilot-setup-steps}
     X---- uses: owner/repo/.github/workflows/...
     X---- secrets: inherit / secrets explícitos para quarentenados

Quarentenados (workflow_call, sem callers) no PR:
  e2e-pr-smoke.yml | e2e-nightly-full.yml | e2e-premerge-regression.yml
  playwright.yml | copilot-setup-steps.yml

Ausentes em main (não reintroduzidos):
  magento-ci.yml | menu-regression.yml | product-design-qa.yml
  playwright-visual-qa.yml | visual-quality-gate.yml

PR ativos após merge (somente estáticos):
  quality-gates.yml (+ guard-no-production)
  sanity.yml
  cms-blocks-verify.yml
```

## Callers

**Ausência comprovada** em:
- `awamotosbrand-prog/magento_b2b_awa@main` (canônico)
- `ecriativy-git/b2b@main`
- `grupoawamarketing-wq/awa@ci/visual-quality-gate-autonomous`
- worktree do PR

Nenhum caller removido (não existia).

## Secrets transmitidos

Nenhum. Workflows do PR: **zero** referências `secrets.*`.

## PR

- **URL:** https://github.com/awamotosbrand-prog/magento_b2b_awa/pull/3
- **Base:** `main` (canônico `awamotosbrand-prog/magento_b2b_awa`)
- **Head:** `security/ci-containment-fase1-20260722`
- **Merge automático:** não
- **Reviewers:** nenhum solicitado automaticamente (revisão humana pendente)
- **SHA do merge:** _pendente revisão humana_

## Commits no PR (sobre origin/main)

```
b7f9cd5b7 fix(ci): make no-production URL/IP patterns fail-closed
1f3c8f6f6 fix(ci): correct no-production guard regex escaping
94956f594 docs(ci): record containment and reactivation criteria
7275bc888 security(test): disable production defaults
9738b357c security(ci): remove production targets from pull requests
c2129f8c3 chore(ci): quarantine production workflows
```

## Revisão estática (executada localmente; CI Actions NÃO disparada por nós)

| Check | Resultado |
|-------|-----------|
| Diff YAML | OK — só workflows/guard/helpers/docs |
| YAML parse | OK (8 workflows) |
| guard-no-production | OK + prova fail-closed |
| URLs/IP produção | sem literais `https://…awamotos.com` / IP; deny-list via join |
| secrets | NONE nos workflows |
| workflow_call callers | NONE |
| permissions | `contents: read` nos workflows tocados |
| triggers | quarentena=`workflow_call`; ativos=quality/sanity/cms |

## Escopo — não altera

Magento storefront CSS/JS, banco, config app, docroot produção, credentials, environments, branch protection: **confirmado pelo name-only do PR**.

## Pós-merge (aguardar humano)

1. Verificar commits em `main`
2. Revalidar `disabled_manually`
3. Rodar só `bash scripts/ci/guard-no-production.sh`
4. Confirmar ausência de triggers perigosos
5. Registrar SHA do merge neste relatório

## Decisão Fase 2

### NO-GO

Produção permanece NO-GO. Não iniciar Fase 2 automaticamente.
