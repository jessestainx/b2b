# Auditoria segura — GitHub Actions, Lighthouse e VPS dry-run

Estrutura para auditorias e automações **sem deploy** e **sem impacto operacional** na produção. Nada neste documento autoriza `cache:flush`, `setup:upgrade`, SSH de deploy ou exclusão de arquivos.

Repositório canônico: `https://github.com/jessestainx/b2b`.

## 1. O que já existia

### Workflows (`.github/workflows/`)

| Workflow | Acionamento | Risco |
|---|---|---|
| `magento-ci.yml` | PR, push `main`, tag `v*` | Contém **deploy staging e produção** via SSH |
| `visual-quality-gate.yml` | PR, push `main`, cron, dispatch | Playwright + Lighthouse; produção só com opt-in |
| `e2e-pr-smoke.yml` | PR | Smoke E2E; `awamotos.com` bloqueado sem opt-in |
| `e2e-nightly-full.yml` | cron 04:00 UTC + dispatch | Suite E2E completa |
| `e2e-premerge-regression.yml` | push `main` + dispatch | Regressão E2E |
| `quality-gates.yml` | PR + push `main` | Sintaxe PHP/shell e governança |
| `sanity.yml` | push + PR | `bash -n` / `php -l` em `scripts/` |
| `playwright.yml` / `playwright-visual-qa.yml` / `product-design-qa.yml` / `menu-regression.yml` / `cms-blocks-verify.yml` / `copilot-setup-steps.yml` | vários | QA / setup; sem SSH de produção neste mapa |

### MCP

| Origem | Conteúdo |
|---|---|
| `.cursor/mcp.json` | Perfil leve: Hostinger MCP + Codacy (`concurrency: 1`) |
| `.vscode/mcp.json` | Legado vazio (anti-duplicata); arquivo gitignored |
| `.vscode/mcp.heavy.json` | Playwright MCP + filesystem — **não ativar em produção** |
| `scripts/mcp-performance.sh` | Diagnóstico RAM/MCP; `heavy-on` sobe Chrome |

### Scripts de limpeza já presentes (destrutivos se executados)

- `scripts/workspace-cleanup.sh`
- `scripts/static-theme-prune.sh`
- `scripts/media-cache-prune.sh`
- `scripts/theme-backup-prune.sh`
- `scripts/e2e-cleanup.sh`

Estes scripts **apagavam** artefatos, static de temas Ayo não usados e cache de imagem. Não os execute sem autorização, backup e janela.

### Lighthouse já presente

- `.github/lighthouse/lighthouserc.desktop.cjs` / `lighthouserc.mobile.cjs` — limites **error** (mais rígidos)
- `.lighthouseci/lighthouserc.json` — autorun local com `temporary-public-storage` (evitar em CI; vaza HTML)
- `package.json` — `@lhci/cli` e `lighthouse`

## 2. O que foi adicionado

| Arquivo | Função |
|---|---|
| `.github/workflows/audit-readonly.yml` | Auditoria estática, `workflow_dispatch`, artifact |
| `.github/workflows/lighthouse-ci-audit.yml` | Lighthouse conservador, `workflow_dispatch`, artifact |
| `.github/lighthouse/lighthouserc.audit-conservative.cjs` | Orçamentos em `warn`, 1 run, 3 URLs públicas |
| `scripts/ci/audit-repo-readonly.sh` | Inventário + varredura de padrões (sem imprimir segredos) |
| `scripts/ci/vps-audit-dry-run.sh` | Lista o que a limpeza apagaria; recusa `--apply` |
| `docs/ci-audit-automation.md` | Este guia |

## 3. Comandos exatos

### GitHub Actions (depois do push destes arquivos)

```bash
gh workflow list --repo jessestainx/b2b
gh workflow run audit-readonly.yml --repo jessestainx/b2b
gh workflow run lighthouse-ci-audit.yml --repo jessestainx/b2b \
  -f base_url=https://awamotos.com \
  -f allow_production_traffic=true
gh run list --repo jessestainx/b2b --workflow=audit-readonly.yml --limit 5
gh run download <RUN_ID> --repo jessestainx/b2b
```

Na UI: Actions → `audit-readonly` → Run workflow.

### Lighthouse local (não usa Magento CLI)

```bash
export LHCI_BASE_URL=https://awamotos.com
export LHCI_RUNS=1
npx lhci autorun --config=.github/lighthouse/lighthouserc.audit-conservative.cjs
```

Relatórios em `.lighthouseci/audit-conservative/`. Não usar `temporary-public-storage`.

### Dry-run VPS (somente listar)

O script **escreve relatório** em `artifacts/vps-audit-dry-run/` (gitignored). Não apaga nada. Não ler `app/etc/env.php`.

```bash
# sintaxe
bash -n scripts/ci/vps-audit-dry-run.sh

# dry-run no checkout (CI ou cópia local)
AUDIT_OUT_DIR=./artifacts/vps-audit-dry-run bash scripts/ci/vps-audit-dry-run.sh

# recusa explícita de modo destrutivo
bash scripts/ci/vps-audit-dry-run.sh --apply   # exit 2
```

Não execute o dry-run na produção até haver GO escrito. Mesmo dry-run gera `du` em diretórios grandes (`pub/static`, cache de mídia).

### Auditoria de repositório local

```bash
AUDIT_OUT_DIR=./artifacts/audit-readonly bash scripts/ci/audit-repo-readonly.sh
```

## 4. Limites Lighthouse conservadores

Comparado ao gate visual existente (`error` / LCP 2800 ms), o perfil de auditoria usa **warn**:

| Métrica | Conservador (warn) | Gate visual desktop (error) |
|---|---|---|
| Performance | ≥ 0.35 | ≥ 0.65 |
| LCP | ≤ 6000 ms | ≤ 2800 ms |
| FCP | ≤ 4000 ms | ≤ 1800 ms |
| CLS | ≤ 0.25 | ≤ 0.10 |
| TBT | ≤ 800 ms | ≤ 300 ms |
| Runs | 1 | 1–3 |
| URLs | `/`, `/bagageiros.html`, `/b2b/account/login/` | home, PLP, PDP |

Produção só com `allow_production_traffic=true`. São 3 GETs com throttling simulado.

## 5. Codex CLI (proposta, não habilitado aqui)

Rodar Codex **fora da VPS de produção**, em worktree clone:

```bash
git clone git@github.com:jessestainx/b2b.git /tmp/awa-b2b-audit
cd /tmp/awa-b2b-audit
codex --ask-for-approval --sandbox read-only
```

Não passar `app/etc/env.php`, tokens GitHub, Redis ou SSH. Não montar o DocumentRoot da loja como workspace de escrita do Codex.

## 6. Rollback

Backup desta mudança: `/tmp/awa-audit-automation-20260813T222222Z/` (gitignore e `quality-gates.yml` originais).

Remover os arquivos novos:

```bash
rm -f \
  .github/workflows/audit-readonly.yml \
  .github/workflows/lighthouse-ci-audit.yml \
  .github/lighthouse/lighthouserc.audit-conservative.cjs \
  scripts/ci/audit-repo-readonly.sh \
  scripts/ci/vps-audit-dry-run.sh \
  docs/ci-audit-automation.md
cp /tmp/awa-audit-automation-20260813T222222Z/.gitignore .gitignore
cp /tmp/awa-audit-automation-20260813T222222Z/quality-gates.yml .github/workflows/quality-gates.yml
```

Se já tiverem sido commitados:

```bash
git restore --source=HEAD -- \
  .gitignore \
  .github/workflows/quality-gates.yml
git rm -f \
  .github/workflows/audit-readonly.yml \
  .github/workflows/lighthouse-ci-audit.yml \
  .github/lighthouse/lighthouserc.audit-conservative.cjs \
  scripts/ci/audit-repo-readonly.sh \
  scripts/ci/vps-audit-dry-run.sh \
  docs/ci-audit-automation.md
```

Nenhum cache Magento, Redis, Varnish ou serviço precisa ser tocado: estes arquivos não entram na cascata CSS nem no PHP da loja.

## 7. O que esta estrutura não faz

- Não faz deploy (`magento-ci.yml` continua sendo o único workflow com SSH de deploy).
- Não limpa `pub/static` nem cache de mídia.
- Não lê `env.php`, sessões, `pub/media/customer` nem credenciais.
- Não envia relatórios Lighthouse para storage público do Google.
- Não dispara sozinho: ambos os workflows novos são **somente manuais**.

## 8. Próximos passos

1. Commit e push para `jessestainx/b2b` (branch de trabalho, não tag `v*`).
2. Rodar `audit-readonly` uma vez e baixar o artifact.
3. Decidir se Lighthouse em `awamotos.com` tem GO (3 GETs).
4. Isolar `magento-ci.yml` jobs `deploy-staging` / `deploy-production` atrás de `environment` GitHub com reviewer (hoje um tag `v*` dispara deploy).
5. Adicionar `dry-run` aos scripts destrutivos existentes em vez de um espelho separado.
6. Codex CLI só em clone, sandbox `read-only`.
