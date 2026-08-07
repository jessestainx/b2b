# Header Core Interactions P0 — Report

## Path Sync / Execution Environment

- **Raiz Git real (terminal):** `/home/user/htdocs/srv1113343.hstgr.cloud`
- **PWD utilizado na sessão:** `/home/jessessh/htdocs/srv1113343.hstgr.cloud` (resolve para a mesma raiz via `readlink -f`)
- **Spec ativo do Playwright:** `/home/user/htdocs/srv1113343.hstgr.cloud/tests/e2e/specs/header-core-interactions-p0.spec.ts`
- **SHA256 do spec ativo:** `ce5eea47cd6d06173b6a0d3d168a9f4236f357c062b42071bf8cfce6ab3fc35e`
- **Duplicidade de arquivo com mesmo nome:** não encontrada (apenas 1 ocorrência em `/home`)

### Configs Playwright avaliadas

1. `npx playwright test --list` (na raiz do repo)
   - Resultado: `0 tests`, com erros globais de discovery de outros specs do suite atual.
2. `npx playwright test --config=tests/e2e/pw-functional.config.ts --list`
   - Resultado: `0 tests`.
3. `npx playwright test --config=tests/e2e/pw-visual-suite.config.ts --list`
   - Resultado: `0 tests`.
4. `cd tests/e2e && npx playwright test specs/header-core-interactions-p0.spec.ts --list`
   - Resultado: **65 testes listados** para `header-core-interactions-p0.spec.ts`.

### Sentinel (prova de arquivo executado)

- Sentinel temporário aplicado no título: `PATH-SYNC-SENTINEL-2026-07-09`.
- Comando que comprovou a descoberta do arquivo correto:
  - `cd tests/e2e && npx playwright test specs/header-core-interactions-p0.spec.ts --list | grep PATH-SYNC-SENTINEL`
- Resultado: sentinel apareceu em todas as entradas listadas.
- Limpeza: sentinel removido; hash restaurado ao valor original.

### Comando correto de execução (atual)

```bash
cd /home/user/htdocs/srv1113343.hstgr.cloud/tests/e2e
PLAYWRIGHT_BASE_URL=https://awamotos.com ALLOW_PRODUCTION_VALIDATION=true \
npx playwright test specs/header-core-interactions-p0.spec.ts --grep 'diagnostico — home' --workers=1 --reporter=list --project=desktop-1440
```

### Smoke mínimo (resultado)

- Execução mínima passou: **1 passed (desktop-1440 / diagnostico — home)**.
- `Killed`: **não ocorreu** no smoke ajustado.
- Recursos durante o smoke:
  - RAM livre ~6.6GiB antes/depois
  - Swap praticamente livre
  - Disco `/` com folga (~300G)
  - `dmesg` sem permissão para leitura (`Operation not permitted`)

### .env / Git hygiene

- `.env` existe localmente e está **ignorado** (`.gitignore` contém `.env` e `.env.*`).
- `.env` não deve ser versionado.
- `.env.example` já existe (placeholder seguro).

### Bloqueios restantes

- Discovery global de `npx playwright test --list` na raiz retorna erros de outros specs do suite atual (não bloqueia execução direta do spec alvo).

### Recomendação para GitHub Actions

- Para este spec, preferir job/step dedicado executando a partir de `tests/e2e` com arquivo explícito.
- Evitar discovery global enquanto houver specs que quebram o `--list` no escopo completo.
