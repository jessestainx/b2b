# GITHUB ACTIONS — TRIAGEM startup_failure (2026-07-22)

**Fase:** 1.2 (read-only)  
**Merge SHA analisado:** `df3030ca414004de616d86006e8cd082faea3155`  
**Repo canônico:** `awamotosbrand-prog/magento_b2b_awa`  
**Método:** apenas GET na API GitHub + revisão estática local  
**Proibições respeitadas:** sem `gh workflow run`, re-run, Playwright, npm, Composer, SSH, curl app, deploy, alteração de workflow/secret/branch  

**Produção:** NO-GO  
**Fase 2:** NO-GO  

---

## Resumo executivo

Os `startup_failure` observados após o merge **não** indicam contenção quebrada.

Causa raiz observada: **registro residual de workflow** **`BuildFailed`** (`workflow_id` **305821273**, `state: deleted`, path **`BuildFailed`** — **não** existe em `.github/workflows/` nem como arquivo no tree do merge SHA).

- Criado em **2026-07-02T07:37:28Z** (20 dias antes do merge de contenção).
- **43/43** runs com `conclusion=startup_failure`.
- Em **todos** os runs amostrados (incluindo o do merge): **`jobs.total_count = 0`**.
- Check suite do merge: App **`github-actions`**, **`latest_check_runs_count = 0`**, annotations vazias.
- Falha ocorreu **antes da criação de jobs**; nenhum runner, step, checkout, secret ou conexão externa observada nos metadados.

Validação semântica local dos 8 YAMLs em `main` no merge: **nenhum problema semântico encontrado**.  
Callers de `workflow_call`: **zero** em `main` (canônico) e `ecriativy-git/b2b@main`.

### Classificação final: **P2**

**Terminologia aprovada:** registro residual de workflow com `state=deleted`, gerando check suites `startup_failure` antes da criação de jobs.

Não P0 (contenção intacta). Não P1 (YAMLs da contenção semanticamente válidos; o path `BuildFailed` não é arquivo nosso).

**Não se declara bug definitivo da plataforma GitHub** sem confirmação do GitHub Support.

---

## 1. Tabela de runs (head_sha = merge)

| run ID | workflow ID | name | path | event | status | conclusion | actor | head SHA | head branch | attempt | created_at | updated_at | URL | jobs | steps iniciados | runner |
|--------|-------------|------|------|-------|--------|------------|-------|----------|-------------|---------|------------|------------|-----|------|-----------------|--------|
| 29963661843 | 305821273 | _(vazio)_ | **BuildFailed** | push | completed | startup_failure | awamotosbrand-prog | df3030ca4140… | main | 1 | 2026-07-22T22:40:23Z | 2026-07-22T22:40:23Z | [run](https://github.com/awamotosbrand-prog/magento_b2b_awa/actions/runs/29963661843) | **0** | **0** | nenhum |

Consulta: `GET repos/.../actions/runs?head_sha=df3030ca4140…` → `total_count=1` (somente o acima).

### Runs BuildFailed correlatos no mesmo dia (mesmo workflow_id; 0 jobs)

| run ID | event | head branch | head SHA (12) | created_at |
|--------|-------|-------------|----------------|------------|
| 29962632781 | push | security/ci-containment-fase1-20260722 | 94956f594718 | 22:22:39Z |
| 29962702896 | push | security/… | 1f3c8f6f6bab | 22:23:50Z |
| 29962755305 | push | security/… | b7f9cd5b7c31 | 22:24:43Z |
| 29962773087 | pull_request | security/… | b7f9cd5b7c31 | 22:25:02Z |
| 29962798498 | push | security/… | 88b8ee915116 | 22:25:27Z |
| 29962802180 | pull_request | security/… | 88b8ee915116 | 22:25:31Z |
| 29963522466 | push | security/… | c8794d686e07 | 22:37:53Z |
| 29963525080 | pull_request | security/… | c8794d686e07 | 22:37:56Z |
| **29963661843** | **push** | **main** | **df3030ca4140** | **22:40:23Z** |
| 29963661902 | pull_request | security/… | c8794d686e07 | 22:40:24Z |
| 29963729759 | push | docs/ci-containment-fase1-1b-postmerge | 572aaec8afb2 | 22:41:37Z |
| 29963732190 | pull_request | docs/… | 572aaec8afb2 | 22:41:40Z |

Para cada um dos runs detalhados via API (`/jobs?filter=all`): **`total_count: 0`**.

**Conclusão por run:**  
“Falha ocorreu antes da criação de jobs; nenhum runner ou step foi observado.”

---

## 2. Check suites (merge commit)

| suite ID | app slug | app name | status | conclusion | head SHA | latest check runs | workflow run associado |
|----------|----------|----------|--------|------------|----------|-------------------|------------------------|
| 81177329108 | github-actions | GitHub Actions | completed | startup_failure | df3030ca4140… | **0** | run 29963661843 |

`GET .../check-suites/81177329108/check-runs` → `total_count: 0`  
Annotations: **N/A** (sem check runs).

**Distinção:** GitHub Actions (não Cursor Bugbot; não App externo distinto).  
Não há check run de Bugbot nesta suite do merge.

---

## 3. Prova: jobs / runners / secrets / rede

| Pergunta | Evidência API | Resposta |
|----------|---------------|----------|
| Job criado? | `jobs.total_count=0` | **Não** |
| Runner atribuído? | sem jobs | **Não** |
| Step “Set up job”? | sem steps | **Não** |
| Checkout? | sem steps | **Não** |
| Secret disponibilizado? | sem job/context de execução | **Não observado** |
| Comando executado? | sem steps | **Não** |
| Conexão externa? | sem steps/runner | **Não observado** |
| Minutos consumidos? | `timing.billable={}` / sem jobs | **Não observado** |

---

## 4. Workflow responsável

| Campo | Valor |
|-------|-------|
| workflow ID | 305821273 |
| name | _(vazio)_ |
| path | **BuildFailed** |
| state administrativo | **`deleted`** |
| created_at workflow | 2026-07-22… wait: **2026-07-02T07:37:28Z** |
| Arquivo no merge tree | **Ausente** (`BuildFailed` 404; tree só tem `.github/workflows/*.yml`) |
| html_url badge | aponta para blob `main/BuildFailed` inexistente |

### Workflows reais em `.github/workflows` no merge (estado)

| path | state |
|------|-------|
| cms-blocks-verify.yml | active |
| quality-gates.yml | active |
| sanity.yml | active |
| copilot-setup-steps.yml | active (quarentena `workflow_call`) |
| e2e-pr-smoke.yml | **disabled_manually** |
| e2e-nightly-full.yml | **disabled_manually** |
| e2e-premerge-regression.yml | **disabled_manually** |
| playwright.yml | **disabled_manually** |

**Nota:** `quality-gates` / `sanity` / `cms-blocks-verify` **não** geraram runs com `head_sha=df3030ca…`. O único run desse SHA é o registro residual `BuildFailed`. Isso é consistente com falha de startup ao nível do serviço Actions, não com execução dos YAMLs de contenção.

### Triggers dos quarentenados (versão no merge SHA)

Todos: `on: workflow_call` apenas — sem `workflow_dispatch` / `push` / `pull_request` / `schedule` reais; sem callers; sem `secrets: inherit`.

---

## 5. Validação semântica local (sem executar)

Arquivos revisados em worktree limpo de `origin/main` @ `df3030ca4`:

- cms-blocks-verify.yml  
- quality-gates.yml  
- sanity.yml  
- copilot-setup-steps.yml  
- e2e-pr-smoke.yml  
- e2e-nightly-full.yml  
- e2e-premerge-regression.yml  
- playwright.yml  

Verificações: jobs presentes; `runs-on` ou `uses` job-level; sem `uses`+`steps` inválido; sem reusable em `steps`; `needs` válidos; `workflow_call` com job `runs-on` presente.

### Problemas semânticos encontrados

**Nenhum** (lista vazia).

Portanto: **não** há evidência de “YAML válido porém workflow semanticamente inválido” nos arquivos da contenção que explique o `BuildFailed`.

---

## 6. Quarentena `workflow_call`

| Item | Resultado |
|------|-----------|
| Callers em `main` canônico | **0** |
| Callers em `ecriativy-git/b2b@main` | **0** |
| `workflow_dispatch` nos quarentenados | **ausente** (só comentários) |
| Triggers automáticos extras | **ausentes** |
| `secrets: inherit` | **ausente** |
| Semanticamente válidos como reusable | **sim** (job + `runs-on` + steps) |

“Sem caller” ≠ “arquivo inválido”: aqui os arquivos são válidos **e** sem callers.

---

## 7. Linha do tempo

| Horário (UTC) | Evento |
|---------------|--------|
| 2026-07-02T07:37:28Z | Registro residual de workflow `BuildFailed` (id 305821273) criado; 1º run `schedule` `startup_failure`, 0 jobs |
| 2026-07-02 → 2026-07-22 | 43 runs `BuildFailed`, 100% `startup_failure`, eventos schedule/push/pull_request |
| 2026-07-07 | Até workflows reais (`e2e-nightly-full` via `workflow_dispatch`) retornam `startup_failure` com **0 jobs** |
| 2026-07-22T22:40:21Z | **Merge** PR #3 → `df3030ca4` (committer GitHub) |
| 2026-07-22T22:40:23Z | Check suite 81177329108 + run 29963661843 (`BuildFailed`, push/main) |
| 2026-07-22T22:40:24Z | Run 29963661902 (`BuildFailed`, pull_request na branch do PR) |
| 2026-07-22T22:41:37–40Z | Runs `BuildFailed` no push/PR do PR documental #4 |

Origem do run do merge: **push do merge commit em `main`**, registrado sob o registro residual de workflow `BuildFailed` (App GitHub Actions), **não** sob os YAMLs de e2e/produção.

---

## 8. Classificação

### Por ocorrência (padrão único)

| ID / padrão | Classe | Motivo |
|-------------|--------|--------|
| 29963661843 (merge) e demais BuildFailed | **P2** | App `github-actions`; path `BuildFailed` **deleted**; 0 jobs/runners/check-runs; histórico desde 2026-07-02; YAMLs locais OK; contenção não afetada |

### Critérios P0 (avaliados e **negados**)

- workflow perigoso ativo? **Não** (`disabled_manually` mantido)  
- job iniciou / runner / secret / rede / deploy alcançável? **Não** (metadados)

### Critérios P1 (avaliados e **negados** para os YAMLs da contenção)

- erro semântico/estrutural nos arquivos `.github/workflows` do merge? **Não encontrado**

### Tipo A–E (objetivo)

| Tipo | Aplica? |
|------|---------|
| A) workflow semanticamente inválido (nossos YAMLs) | **Não** |
| B) workflow ativo ainda acionável (perigoso) | **Não** (quarentena + disabled_manually) |
| C) GitHub App / check externo | **Parcial:** App é **GitHub Actions**, via registro registro residual `BuildFailed` |
| D) estado histórico/transitório / registro residual | **Sim (principal):** registro residual `state=deleted` desde 02/07; 0 jobs recorrente — **sujeito a confirmação do GitHub Support** |
| E) outra causa | Registro residual `BuildFailed` + `startup_failure` pré-job |

---

## 9. Risco de produção

**Baixo / não materializado neste incidente.**

- Nenhum job/runner.
- Nenhum step.
- Secrets de B2B/SSH **não** referenciados pelos workflows de PR ativos; não há evidência de injeção em job.
- Workflows e2e perigosos permanecem `disabled_manually`.
- Produção permanece **NO-GO**.

---

## 10. PR corretivo?

| Opção | Recomendação |
|-------|--------------|
| PR alterando workflows da contenção | **Não necessário** para este incidente |
| PR documental #4 | Incluir esta conclusão (somente Markdown) |
| Ação operacional futura (fora da 1.2) | Investigar remoção/limpeza do workflow **deleted** `BuildFailed` (id 305821273) com suporte GitHub / UI Actions — **sem** reabilitar e2e; **sem** Fase 2 |

Não misturar limpeza de fantasma com Fase 2 nem com Magento.

---

## 11. Decisão Fase 2

### **NO-GO**

A triagem **não** libera Fase 2. Produção permanece NO-GO.

---

## 12. Critérios de conclusão da Fase 1.2

| Critério | Status |
|----------|--------|
| Cada startup_failure associado a origem | **OK** → workflow_id 305821273 `BuildFailed` (deleted) |
| Jobs iniciaram? | **Comprovado: não** |
| Runners atribuídos? | **Comprovado: não** |
| Secrets disponíveis em job? | **Comprovado: não observado / sem job** |
| Conexão externa? | **Comprovado: não observado / sem job** |
| Erros semânticos por arquivo:linha | **Nenhum nos YAMLs da contenção** |
| Nenhuma execução nova iniciada por nós | **OK** (somente GET) |
| Produção NO-GO | **OK** |

**FASE 1.2 — CONCLUÍDA (read-only).**

---

## Encerramento formal (2026-07-22 23:15 UTC)

**Terminologia aprovada (P2):** registro residual de workflow com `state=deleted`, gerando check suites `startup_failure` antes da criação de jobs.

Não se declara bug definitivo da plataforma GitHub sem confirmação do GitHub Support.

### Preservação de evidência

Não apagar / não re-run:

- workflow runs (incl. 29963661843 e série BuildFailed);
- check suites (incl. 81177329108);
- comentários dos PRs #3 e #4;
- este relatório e `GITHUB_ACTIONS_CONTAINMENT_2026-07-22.md`;
- respostas sanitizadas da API já registradas.

### Baseline de monitoramento (leitura apenas)

| Métrica | Valor | Momento |
|---------|-------|---------|
| Runs do workflow_id 305821273 (`BuildFailed`) | **45** | 2026-07-22 23:15 UTC (GET `.../actions/workflows/305821273/runs`) |

- **Não** criar workflow de monitoramento.
- **Não** polling automático.
- Recontar somente em próximo ciclo administrativo aprovado (GET read-only).

### Rascunho para GitHub Support — **NÃO ENVIAR** sem aprovação humana

```text
Subject: Actions startup_failure for deleted workflow path "BuildFailed" (0 jobs)

Repository: awamotosbrand-prog/magento_b2b_awa (private)

We observe recurring GitHub Actions runs that never create jobs.

Details:
- workflow_id: 305821273
- workflow state: deleted
- workflow path/name reported by API: BuildFailed
- No corresponding file on the default branch (main); path is not under .github/workflows/
- First observed occurrence (API created_at): 2026-07-02T07:37:28Z
- At triage time: 43/43 runs with conclusion=startup_failure (baseline recount at close: 45)
- Merge-related run id: 29963661843
  - head_sha: df3030ca414004de616d86006e8cd082faea3155
  - event: push (main)
  - jobs.total_count: 0
  - no runners assigned
  - associated check suite latest_check_runs_count: 0
- No manual workflow_dispatch / re-run was used during investigation (read-only GET only)
- Our repository containment workflows remain intact (dangerous e2e workflows disabled_manually; no job execution observed for these startup_failure runs)

Please advise whether this deleted/residual workflow registration can be cleared and why startup_failure check suites continue to be created without jobs.

(No tokens, auth headers, secrets, or personal data included.)
```

### Status final das fases

| Fase | Status |
|------|--------|
| FASE 1 | **CONCLUÍDA** |
| FASE 1.1 | **CONCLUÍDA** |
| FASE 1.2 | **CONCLUÍDA — P2** |
| FASE 2 | **NO-GO** |
| PRODUÇÃO | **NO-GO** |
