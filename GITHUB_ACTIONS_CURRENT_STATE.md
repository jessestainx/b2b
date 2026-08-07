# GITHUB_ACTIONS_CURRENT_STATE

**Data do inventário:** 2026-07-22
**Escopo:** esteira GitHub Actions + dependências locais (npm/Composer/Playwright/scripts)
**Modo:** somente leitura — nenhum workflow, teste ou CI foi executado
**Produção:** **NO-GO** (não alterar secrets/vars/environments; não apontar novos jobs para `awamotos.com`)

---

## Resumo executivo

Existe **uma esteira fragmentada**, não uma esteira limpa: **13 workflows** em `.github/workflows/`, **zero** composite actions em `.github/actions/`, overlap pesado em PR, vários caminhos apontando para **produção hard-coded**, e lacunas que quebram jobs sem precisar “recriar do zero”.

**Princípio deste inventário:** classificar e reutilizar; **não** criar segunda esteira paralela; **não** apagar workflows nesta fase.

---

## 1. Árvore atual (relevantes à esteira)

```
.
├── .github/
│   ├── CODEOWNERS
│   ├── header-tablet-rail-regression.cjs      # helper CI (e2e-pr-smoke)
│   ├── pull_request_template.md
│   ├── ISSUE_TEMPLATE/
│   ├── lighthouse/
│   │   ├── lighthouserc.desktop.cjs
│   │   └── lighthouserc.mobile.cjs
│   ├── workflows/                            # 13 YAMLs (sem actions/)
│   ├── agents/ prompts/ instructions/ skills/  # Copilot/agents (fora do runtime CI)
│   └── (sem dependabot.yml, sem actions/)
├── package.json                              # Lighthouse + scripts ops; SEM Playwright
├── package-lock.json
├── playwright.config.ts                      # root config (aponta p/ tests/e2e)
├── .lighthouserc.js                          # LH hard-coded em awamotos.com
├── .env.example                              # ops locais (OpenSearch/fitment) — NÃO é CI e2e
├── .gitignore
├── phpcs.xml
├── composer.json                             # scripts quality:* + lint:php
├── scripts/
│   ├── ci/                                   # quality-gates / sanity
│   ├── deploy/ pre-deploy.sh
│   ├── backup/ magento-backup.sh
│   ├── monitoring/ health-check.sh
│   ├── warmup/ warm-cache.sh
│   ├── playwright-qa-safe.sh
│   ├── setup-github-secrets.sh               # RISCO: credenciais em texto
│   └── (centenas de scripts ops — maioria fora do CI)
├── tests/
│   ├── e2e/                                  # hub Playwright real
│   │   ├── package.json / package-lock.json
│   │   ├── playwright.config.ts
│   │   ├── pw-*.config.ts                    # 13 configs especializadas
│   │   ├── helpers/                          # resolve-base-url, visual, mcp, etc.
│   │   ├── specs/                            # ~112 specs
│   │   ├── snapshots/ reports/ test-results*
│   │   └── (SEM pasta scripts/ — ver lacuna)
│   ├── unit/                                 # .gitkeep apenas
│   └── integration/                          # .gitkeep apenas
├── e2e/                                      # legado solto (2 arquivos) — NÃO wired ao CI
├── app/code/GrupoAwamotos/B2B/Test/phpunit.xml
└── dev/tests/                                # PHPUnit Magento core (não wired aos workflows)
```

### Itens solicitados — presença

| Item | Status |
|------|--------|
| `.github/workflows/**` | **Presente** (13) |
| `.github/actions/**` | **Ausente** |
| `package.json` / `package-lock.json` | **Presente** (raiz + `tests/e2e`) |
| `playwright.config.*` | **Presente** (raiz + `tests/e2e` + 13 `pw-*.config.ts`) |
| `tests/**` | **Presente** (e2e rico; unit/integration vazios) |
| `test/**` | **Ausente** |
| `e2e/**` (raiz) | **Presente** (legado mínimo) |
| `dev/**` | **Presente** (`dev/tests` Magento) |
| `scripts/**` | **Presente** (ops + `scripts/ci`) |
| `composer.json` | **Presente** |
| `phpunit.xml*` (raiz) | **Ausente** (só módulo B2B + `dev/tests/*.dist`) |
| `phpcs.xml*` | **Presente** |
| `phpstan*` (config raiz) | **Ausente** (pacote em require-dev; sem neon/CI) |
| `.env.example` | **Presente** (não cobre e2e CI) |
| `.gitignore` | **Presente** (artefatos Playwright/LH) |
| `CODEOWNERS` | **Presente** (`.github/CODEOWNERS`) |
| `dependabot.yml` | **Ausente** |

---

## 2. Workflows existentes (classificação)

| Workflow | Classificação | Motivo curto |
|----------|---------------|--------------|
| `quality-gates.yml` | **MANTER** (+ **CORRIGIR** leve) | Núcleo estático reutilizável; sem hit em produção |
| `sanity.yml` | **MANTER** | Sintaxe shell/PHP + `composer validate`; overlap parcial com quality-gates |
| `cms-blocks-verify.yml` | **MANTER** (audit estático) / full-audit **DESATIVAR** lógico | Parte útil é grep estático; branch `FULL_BLOCK_AUDIT` morta (scripts ausentes) |
| `e2e-pr-smoke.yml` | **CORRIGIR** | Smoke PR útil, mas hard-code produção + BASE_URL inconsistente |
| `e2e-premerge-regression.yml` | **CORRIGIR** | Pipeline MCP/B2B; defaults npm → produção; scripts npm ausentes |
| `e2e-nightly-full.yml` | **CORRIGIR** | Nightly valioso; mesma dependência quebrada + default prod |
| `playwright-visual-qa.yml` | **MANTER** (+ **CORRIGIR** gating URL) | Melhor padrão de resolve-url + suite smoke/full |
| `visual-quality-gate.yml` | **CORRIGIR** | Bom desenho LH+PW, mas `push`/`schedule` liberam prod automaticamente |
| `product-design-qa.yml` | **CORRIGIR** | QA de produto útil; alvo prod hard-coded; projeto `mobile-390-chromium` inexistente no config e2e |
| `menu-regression.yml` | **CORRIGIR** | Spec isolada ok; alvo prod hard-coded; instala Playwright ad-hoc |
| `playwright.yml` | **DESATIVAR** (ou **SUBSTITUIR** por no-op documentado) | Scaffold genérico: `npm ci` na raiz **sem** Playwright |
| `copilot-setup-steps.yml` | **DESATIVAR** / **CORRIGIR** | Template Copilot; `npx run build` inválido |
| `magento-ci.yml` | **DESATIVAR** deploy + **CORRIGIR**/replanejar validate | Deploy staging/prod via SSH; jobs de deploy estruturalmente mortos; risco alto se reativados |

> **Não apagar** nenhum YAML nesta fase. “DESATIVAR” = `if: false` / remover triggers perigosos / documentar retirement — só após aprovação.

---

## 3. Triggers

| Workflow | Triggers |
|----------|----------|
| `quality-gates` | `pull_request`, `push` → `main` |
| `sanity` | `push` → `**`, `pull_request` |
| `cms-blocks-verify` | `push`/`pull_request` → `main` |
| `e2e-pr-smoke` | `pull_request` |
| `e2e-premerge-regression` | `push` → `main`, `workflow_dispatch` |
| `e2e-nightly-full` | `schedule` `0 4 * * *`, `workflow_dispatch` |
| `playwright-visual-qa` | `pull_request`, `workflow_dispatch` (base_url/suite/allow_prod) |
| `visual-quality-gate` | `pull_request`/`push` → `main`, `schedule` `0 6 * * *`, `workflow_dispatch` |
| `product-design-qa` | `pull_request`, `workflow_dispatch` |
| `menu-regression` | path-filter push/PR + `workflow_dispatch` |
| `playwright` | push/PR → `main`/`master` |
| `copilot-setup-steps` | push/PR no próprio YAML + `workflow_dispatch` |
| `magento-ci` | PR → `main`/`develop`, push `main`, tags `v*`, `workflow_dispatch` |

**Overlap em PR (ruído/custo):** até ~11 workflows podem disparar no mesmo PR (quality, sanity, cms, e2e-pr-smoke, playwright-visual-qa, visual-quality-gate, product-design-qa, playwright, menu-regression path, magento-ci validate, copilot se path).

---

## 4. Permissões

| Workflow | `permissions:` explícitas |
|----------|---------------------------|
| `visual-quality-gate.yml` | `contents: read` |
| `playwright-visual-qa.yml` | `contents: read` |
| `copilot-setup-steps.yml` | job: `contents: read` |
| Demais | **implícitas (default GITHUB_TOKEN)** — gap de least-privilege |

Nenhum workflow declara `id-token`, `packages`, `deployments` ou `pull-requests: write` explicitamente.

---

## 5. Environments

| Environment | Onde | Observação |
|-------------|------|------------|
| `staging` | `magento-ci.yml` → job `deploy-staging` | Referenciado; job efetivamente **inalcançável** (ver §10/§16) |
| `production` | `magento-ci.yml` → job `deploy-production` | Idem; **NO-GO** |

Demais workflows **não** usam GitHub Environments (proteções de aprovação ausentes nos jobs e2e/LH).

---

## 6. Secrets e vars referenciados

### Secrets

| Secret | Workflows |
|--------|-----------|
| `B2B_TEST_USER` / `B2B_TEST_PASS` | e2e-pr-smoke, e2e-premerge, e2e-nightly, product-design-qa |
| `B2B_TEST_PENDING_USER` / `B2B_TEST_PENDING_PASS` | e2e-pr-smoke, e2e-premerge, e2e-nightly |
| `PLAYWRIGHT_BASE_URL_PR` | visual-quality-gate, playwright-visual-qa (fallback) |
| `BASE_URL` | playwright-visual-qa (fallback) |
| `STAGING_SSH_KEY` / `STAGING_USER` / `STAGING_HOST` | magento-ci deploy-staging |
| `PROD_SSH_KEY` / `PROD_USER` / `PROD_HOST` | magento-ci deploy-production |

### Vars

| Var | Workflows |
|-----|-----------|
| `PLAYWRIGHT_BASE_URL_PR` | visual-quality-gate, playwright-visual-qa |
| `PLAYWRIGHT_BASE_URL_NIGHTLY` | visual-quality-gate |
| `PLAYWRIGHT_BASE_URL_PUSH` | visual-quality-gate |
| `BASE_URL` | playwright-visual-qa |

### Script auxiliar de secrets (não é workflow)

- `scripts/setup-github-secrets.sh` — **RISCO:** define secrets via `gh` com valores em texto no repositório (contas B2B de teste + host). Classificação: **CORRIGIR** (remover valores; usar prompts/`gh secret set` interativo) ou **DESATIVAR** do uso operacional até sanear.

> Inventário **não** leu nem alterou secrets reais no GitHub.

---

## 7. Runners

| Runner | Workflows |
|--------|-----------|
| `ubuntu-latest` | maioria |
| `ubuntu-22.04` | `magento-ci.yml` |

Sem self-hosted runners declarados nos YAMLs.

---

## 8. Scripts executados (cadeia)

### Composer (`composer.json` scripts)

| Script | Usado por CI? | Classificação |
|--------|---------------|---------------|
| `quality:php-syntax` → `scripts/ci/lint-custom-php.sh` | quality-gates (direto bash) | **MANTER** |
| `quality:governance` → `validate-governance.sh` | quality-gates | **MANTER** |
| `quality:commits` → `check-conventional-commits.sh` | quality-gates | **MANTER** |
| `quality:docs` → `validate-doc-links.sh` | quality-gates | **MANTER** |
| `quality:install-hooks` | local | **MANTER** |
| `lint:php` / `lint:php:fix` (phpcs) | **não** nos workflows atuais | **MANTER** (candidato a gate futuro) |
| `format:php:check` | **não** | **MANTER** (futuro) |

### Root `package.json`

| Script | CI | Classificação |
|--------|-----|---------------|
| `lighthouse*` / `audit:*` | magento-ci lighthouse (quebrado/não-bloqueante) | **CORRIGIR** alvo URL |
| `deploy:check` / `health` / `backup` / `warm` | magento-ci SSH remoto | **DESATIVAR** em CI até staging isolado |
| `pw:qa-safe` | não (local) | **MANTER** |

### `tests/e2e/package.json` (hub real)

| Script npm | Workflow | Classificação |
|------------|----------|---------------|
| `test:b2b-regression` | e2e-pr-smoke | **CORRIGIR** (BASE_URL) |
| `test:b2b-regression:all` | e2e-premerge, e2e-nightly | **CORRIGIR** |
| `test:mcp-visual` / `pipeline:mcp-visual` / `report:mcp-visual` | nightly / premerge | **CORRIGIR** — default prod + **scripts `.mjs` ausentes** |
| `guard:visual-baselines*` | pipeline mcp | **CORRIGIR** (alvo ausente) |
| demais `test:visual-*` / `test:functional` / `test:deep-audit` | não wired ou via visual-quality-gate CLI | **MANTER** |

### Shell CI

| Arquivo | Classificação |
|---------|---------------|
| `scripts/ci/*.sh` (5) | **MANTER** |
| `scripts/playwright-qa-safe.sh` | **MANTER** (local/safe) |
| `scripts/deploy|backup|monitoring|warmup/*` | **MANTER** ops; **DESATIVAR** de CI de produção |

### Helpers JS no `.github`

| Arquivo | Classificação |
|---------|---------------|
| `.github/header-tablet-rail-regression.cjs` | **CORRIGIR** (default `https://awamotos.com`) |
| `.github/lighthouse/*.cjs` | **MANTER** (parametrizados por `LHCI_BASE_URL`) |

---

## 9. URLs e IPs

| Alvo | Onde aparece | Risco |
|------|--------------|-------|
| `https://awamotos.com` | e2e-pr-smoke, product-design-qa, menu-regression, npm defaults mcp-visual, `.lighthouserc.js`, header helper | **Produção — NO-GO** |
| `https://staging.awamotos.com` | magento-ci health; texto help playwright-visual-qa | Staging (se existir) |
| `https://pwa.awamotos.com` | root `audit:pwa` | Produção PWA |
| `127.0.0.1` MySQL/OpenSearch | cms-blocks (branch morta), `.env.example` | Local only |
| Vars `PLAYWRIGHT_BASE_URL_*` | visual-quality-gate / playwright-visual-qa | **Depende do valor configurado no GitHub** (não inventariado aqui) |

**IPs explícitos em workflows:** apenas exemplos `127.0.0.1` no full-audit CMS (desligado).

---

## 10. Riscos de produção (NO-GO)

1. **Hard-code de produção em PR gates:** `e2e-pr-smoke`, `product-design-qa`, `menu-regression`.
2. **Defaults npm** `test:mcp-visual*` → `PLAYWRIGHT_BASE_URL=https://awamotos.com` + `ALLOW_PRODUCTION_VALIDATION=true`.
3. **`visual-quality-gate`:** em `push` e `schedule`, o resolver **libera** host `awamotos.com` sem input (`allow_prod` automático).
4. **`magento-ci` deploy-production:** SSH + `maintenance:enable` + `git pull` + compile — **perigoso** se secrets existirem e o grafo de jobs for “consertado” sem isolamento.
5. **`scripts/setup-github-secrets.sh`:** credenciais de teste e host em plaintext no repo.
6. **Carga:** suites Playwright paralelas em PR contra a mesma loja viva → risco de CPU/sessões/cache (mesmo read-only).
7. **Root `.lighthouserc.js`:** URLs de produção fixas (usado por magento-ci lighthouse se job rodar).

---

## 11. Testes bloqueantes vs não bloqueantes

### Bloqueantes (falha do step falha o job; sem `continue-on-error`)

| Camada | Exemplos |
|--------|----------|
| Estático | quality-gates (PHP lint, governance, commits, docs), sanity, cms placeholder grep |
| E2E PR | e2e-pr-smoke (B2B smoke, CLS, header frames, tablet rail, dead-css) |
| Visual | playwright-visual-qa (smoke/full), product-design-qa desktop-1440 |
| LH | visual-quality-gate lighthouse assertions (error thresholds) |
| Menu | menu-regression |

### Não bloqueantes / soft-fail

| Item | Mecanismo |
|------|-----------|
| PD6B mobile search (webkit/chromium) | `continue-on-error: true` em product-design-qa |
| PD5 crash matrix | `continue-on-error` + script em `tmp/` gitignored |
| Accessibility step em visual-quality-gate | `\|\| echo "::warning::..."` |
| magento-ci: phpcs / composer audit / pwa lint / lighthouse assert | `\|\| true` / echo non-blocking |
| cms full-audit | `if: FULL_BLOCK_AUDIT==1` (nunca ligado) |

### Efetivamente mortos / quebrados (não entregam sinal útil)

| Item | Motivo |
|------|--------|
| `playwright.yml` | raiz sem `@playwright/test` |
| `copilot-setup-steps` | `npx run build` inválido |
| `magento-ci` deploy-* | `needs: validate` + validate só em PR |
| nightly/premerge `report:mcp-visual` / `pipeline:mcp-visual` | `tests/e2e/scripts/*.mjs` **ausentes** |
| product-design-qa `mobile-390-chromium` | projeto só no `playwright.config.ts` **raiz**; job usa cwd `tests/e2e` |

---

## 12. Skips críticos

- **Helper de segurança:** `tests/e2e/helpers/resolve-base-url.ts` bloqueia `awamotos.com` sem `ALLOW_PRODUCTION_VALIDATION=true` — boa base; **contornada** por workflows/npm que setam o flag.
- **Specs:** dezenas de `test.skip()` condicionais (renderer crash, rota instável, UI ausente) — especialmente `visual-audit-mobile-interactions`, `visual-audit-cart-checkout-404`, `fase-h0-*`, `plp-visual-baseline`, `header-core-interactions-p0`.
- **Skip permanente:** `test.describe.skip('Account Premium', ...)` — “sem credenciais”.
- **Fase H0:** rotas `knownUnstable` skipadas salvo `AWA_H0_ALLOW_UNSTABLE=true`.
- **Job PD5:** documentado como scaffold; arquivo `tmp/pd5-...mjs` **não versionado**.

---

## 13. Artifacts e caches

### Artifacts (`actions/upload-artifact@v4`)

| Workflow | Nome / path | Retention |
|----------|-------------|-----------|
| e2e-pr-smoke | `e2e-pr-smoke-artifacts` → test-results/reports/playwright-report | default |
| e2e-premerge | `e2e-premerge-regression-artifacts` | default |
| e2e-nightly | `e2e-nightly-full-artifacts` | default |
| product-design-qa | report + evidence | 30d / 14d (PD5) |
| playwright-visual-qa | `playwright-visual-qa-{run_id}` | 5d |
| visual-quality-gate | playwright + `.lighthouseci/**` | 14d |
| playwright.yml | `playwright-report/` (raiz — desalinhado) | 30d |
| magento-ci | `.lighthouseci/manifest.json` | default |

### Caches

- **npm cache** via `actions/setup-node` + `cache-dependency-path: tests/e2e/package-lock.json` nos workflows e2e/visual/product/nightly/premerge.
- Sem `actions/cache` explícito para Playwright browsers (reinstall `--with-deps` a cada run).
- Sem cache Composer nos quality-gates.

### `.gitignore` (artefatos locais)

Ignora `tests/e2e/reports/`, `test-results/`, `.playwright-mcp/`, `/playwright-report/`, auth state, audits temporários — alinhado a upload-only-in-CI.

---

## 14. Recursos reaproveitáveis (prioridade alta)

1. **`scripts/ci/*` + `quality-gates.yml`** — base estática estável.
2. **`tests/e2e/`** — package lock, configs `pw-*.config.ts`, helpers (`resolve-base-url`, `third-party-block`, visual/mcp helpers), specs maduras.
3. **`playwright-visual-qa.yml`** — padrão de `resolve-url` + suite smoke/full + block third-party.
4. **`.github/lighthouse/*.cjs`** — budgets parametrizados por env.
5. **`resolve-base-url.ts`** — política única de URL (reforçar em **todos** os entrypoints).
6. **`composer quality:*`** — espelho local dos gates.
7. **CODEOWNERS + PR/issue templates** — governança já coberta por validate-governance.
8. **Root `playwright.config.ts` + `scripts/playwright-qa-safe.sh`** — modo local safe-mode (workers=1); unificar com e2e config depois.

---

## 15. Recursos a desativar (após aprovação; sem apagar arquivo)

| Recurso | Ação sugerida |
|---------|---------------|
| `magento-ci.yml` jobs `deploy-staging` / `deploy-production` | `if: false` + comentário NO-GO até staging isolado |
| `playwright.yml` | desligar triggers ou marcar `if: false` |
| `copilot-setup-steps.yml` | desligar até build real existir |
| Defaults npm mcp → produção | remover default prod; exigir env explícito |
| Branch `FULL_BLOCK_AUDIT` em cms-blocks | deixar morta ou remover steps órfãos |
| `scripts/setup-github-secrets.sh` com plaintext | retirar do uso; rotacionar secrets se já aplicados |
| Hard-codes `ALLOW_PRODUCTION_VALIDATION: "true"` em PR | remover; só `workflow_dispatch` com ack |

---

## 16. Lacunas da arquitetura

1. **Sem `.github/actions/`** — setup Node/Playwright duplicado ~8 vezes.
2. **Dois package.json Playwright** (raiz vs e2e) + workflow raiz quebrado.
3. **`tests/e2e/scripts/` ausente** mas referenciado por npm (`mcp-visual-report`, `autofix`, `visual-baseline-guard`).
4. **Projeto `mobile-390-chromium`** no config raiz, não no config e2e usado pelo CI.
5. **Sem Dependabot**; pins mistos (`checkout` SHA vs `@v4`).
6. **Sem phpstan config / sem PHPUnit wired** aos workflows (apesar de require-dev e `dev/tests`).
7. **Sem environment de staging real** ligado aos e2e (vars podem estar vazias → fail-fast ou fallback perigoso).
8. **Grafo magento-ci inconsistente** (`needs: validate` impede deploy em push/tag).
9. **Permissões default** amplas na maioria dos workflows.
10. **`tests/unit` e `tests/integration` vazios**; `e2e/` raiz legado.
11. **Over-trigger em PR** — custo e flakiness sem matriz de ownership.
12. **Credenciais de teste documentadas em script** no tree.

---

## 17. Plano incremental de migração (sem segunda esteira)

> Objetivo: **consolidar a esteira existente**, não criar pipeline paralelo. Cada fase exige aprovação. Produção permanece NO-GO.

### Fase 0 — Inventário (esta entrega)
- [x] Mapear workflows/scripts/configs
- [x] Classificar MANTER / CORRIGIR / DESATIVAR / SUBSTITUIR / CRIAR
- [ ] **Aguardar aprovação**

### Fase 1 — Contenção NO-GO (só edits mínimos em YAML existentes)
1. Desativar triggers/jobs de **deploy produção/staging** em `magento-ci.yml` (`if: false`).
2. Desativar `playwright.yml` e `copilot-setup-steps.yml` (triggers ou `if: false`).
3. Remover/condicionar hard-codes de `awamotos.com` em PR workflows (`e2e-pr-smoke`, `product-design-qa`, `menu-regression`).
4. Ajustar `visual-quality-gate` para **não** auto-allow prod em `push`/`schedule`.
5. **Não** criar workflows novos.

### Fase 2 — Reparar a cadeia existente (reuso)
1. Restaurar ou realocar `tests/e2e/scripts/{mcp-visual-report,mcp-visual-autofix,visual-baseline-guard}.mjs` **ou** apontar npm scripts para paths reais.
2. Unificar config: ou promover `mobile-390-chromium` para `tests/e2e/playwright.config.ts`, ou corrigir o job.
3. Exigir `PLAYWRIGHT_BASE_URL` / vars de staging em todos os e2e; defaults npm **sem** produção.
4. Extrair composite action **única** `.github/actions/setup-playwright-e2e` (CRIAR) e **substituir** steps duplicados nos workflows existentes (não clonar esteira).

### Fase 3 — Consolidar gates de PR (ainda nos mesmos arquivos)
1. Definir papéis claros:
   - **Bloqueante leve:** `quality-gates` (+ sanity merge ou absorb)
   - **Bloqueante e2e smoke:** um único entry (`e2e-pr-smoke` **ou** `playwright-visual-qa` smoke) — não ambos full
   - **Não bloqueante / nightly:** product-design-qa extras, mcp full, LH deep
2. Path filters / `concurrency` já existentes — estender, não duplicar.
3. Wire opcional `composer lint:php` como gate não-prod.

### Fase 4 — Staging isolado (pré-requisito para reabilitar e2e sério)
1. Garantir URL staging + secrets/vars **sem** apontar para produção.
2. Só então reabilitar nightly/premerge contra staging.
3. Deploy via `magento-ci` permanece NO-GO até runbook + environment protections + dry-run.

### Fase 5 — Higiene
1. Dependabot (CRIAR).
2. Least-privilege `permissions` em todos os YAMLs.
3. Remover plaintext de `setup-github-secrets.sh`; rotacionar se necessário.
4. Documentar matriz “bloqueante vs informativo” em `docs/testing-strategy.md` (atualizar, não fork).

### Itens CRIAR (somente quando a fase pedir)

| Criar | Fase | Nota |
|-------|------|------|
| `.github/actions/setup-playwright-e2e` | 2 | Evita duplicação; não é “segunda esteira” |
| `dependabot.yml` | 5 | |
| `tests/e2e/scripts/*` (se confirmado missing vs histórico) | 2 | Restaurar, não reinventar |
| `phpstan.neon` / phpunit CI job | depois | Só se houver suíte real wired |
| Workflows novos paralelos | **PROIBIDO** | Consolidar nos 13 existentes |

### Cadeia de dependência alvo (estado desejado)

```
GitHub Workflow (existente)
  → composite setup-playwright-e2e (criar na Fase 2)
  → npm ci (tests/e2e/package-lock.json)
  → Playwright config (tests/e2e/*.config.ts)
  → helpers/resolve-base-url.ts  [staging only]
  → specs/
  → runner ubuntu-latest
  → artifacts (test-results, reports, lighthouse)
```

---

## Matriz rápida de classificação (artefatos-chave)

| Artefato | Classificação |
|----------|---------------|
| `.github/workflows/quality-gates.yml` | MANTER |
| `.github/workflows/sanity.yml` | MANTER |
| `.github/workflows/cms-blocks-verify.yml` | MANTER / DESATIVAR steps mortos |
| `.github/workflows/e2e-pr-smoke.yml` | CORRIGIR |
| `.github/workflows/e2e-premerge-regression.yml` | CORRIGIR |
| `.github/workflows/e2e-nightly-full.yml` | CORRIGIR |
| `.github/workflows/playwright-visual-qa.yml` | MANTER / CORRIGIR |
| `.github/workflows/visual-quality-gate.yml` | CORRIGIR |
| `.github/workflows/product-design-qa.yml` | CORRIGIR |
| `.github/workflows/menu-regression.yml` | CORRIGIR |
| `.github/workflows/playwright.yml` | DESATIVAR |
| `.github/workflows/copilot-setup-steps.yml` | DESATIVAR |
| `.github/workflows/magento-ci.yml` | DESATIVAR deploy; CORRIGIR validate depois |
| `.github/actions/**` | CRIAR (Fase 2) |
| `tests/e2e/**` hub | MANTER |
| `playwright.config.ts` (raiz) | MANTER local / CORRIGIR alinhamento CI |
| `package.json` raiz | MANTER (LH/ops) |
| `tests/e2e/package.json` | CORRIGIR defaults + scripts missing |
| `scripts/ci/*` | MANTER |
| `phpcs.xml` + composer lint | MANTER (ainda não no CI) |
| `dependabot.yml` | CRIAR |
| `CODEOWNERS` | MANTER |
| `.env.example` | MANTER (fora do e2e) |
| `e2e/` raiz legado | DESATIVAR / ignorar |
| `tests/unit|integration` | CRIAR conteúdo depois (hoje placeholder) |

---

## Parada obrigatória

Inventário concluído. **Nenhuma alteração de workflow, secret, environment ou execução de CI/teste foi feita além da criação deste relatório.**

**Aguardando aprovação** para iniciar a **Fase 1 (contenção NO-GO)** nos workflows existentes — sem criar esteira paralela e sem apagar YAMLs.
