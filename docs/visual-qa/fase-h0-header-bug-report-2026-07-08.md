# Fase H0 — Header Functional QA — Relatório de Bugs Reproduzidos

> **Escopo**: apenas leitura/reprodução. Nenhuma correção foi aplicada nesta fase.
> **Ambiente**: produção (https://awamotos.com), acesso read-only via Playwright (Chromium headless).
> **Inventário de arquivos**: ver `fase-h0-header-inventario-2026-07-08.md` (Tarefa 1).
> **Spec/config usados**: `tests/e2e/specs/fase-h0-header-functional-qa.spec.ts` + `tests/e2e/pw-fase-h0-header.config.ts`.

## Como reproduzir

```bash
cd tests/e2e
ALLOW_PRODUCTION_VALIDATION=true PLAYWRIGHT_BASE_URL=https://awamotos.com \
  npx playwright test --config=pw-fase-h0-header.config.ts --project=h0-1440
```

Trocar `--project` por qualquer um de: `h0-1440`, `h0-1366`, `h0-1024`, `h0-768`, `h0-430`, `h0-360`.

---

## Sumário executivo

| # | Bug | Severidade | Rota(s) | Status |
|---|-----|------------|---------|--------|
| CRIT-01 | `/catalogo` nunca estabiliza (`load` nunca dispara) e eventualmente derruba o processo do browser headless | 🔴 Crítico | `/catalogo` | Reproduzido, isolado |
| CRIT-02 | PDP real nunca atinge `domcontentloaded` em Chromium headless (confirmado com timeout de 90s) | 🔴 Crítico | PDP (`/bagageiro-titan-...html`) | Reproduzido, isolado |
| H0-BUG-03 | Botão fechar da topbar B2B não oculta a topbar e não persiste o dismissal | 🟠 Alto | Todas (topbar global) | Reproduzido |
| H0-BUG-04 | Menu principal (`.top-menu.top-menu-sticky`) não fica visível no breakpoint desktop (1440) | 🟠 Alto | Home / desktop | Reproduzido |
| H0-BUG-05 | Nenhum controle de abertura do minicart visível no breakpoint 1440 | 🟠 Alto | Home / desktop | Reproduzido |
| INFRA-01 | Instabilidade do ambiente de execução (VPS) para suítes longas de Playwright — processo do runner morre sem log de causa (sem OOM, sem segfault, sem apparmor deny) | ⚠️ Risco de infraestrutura | N/A (execução da suíte) | Documentado, não é bug de produto |

---

## CRIT-01 — `/catalogo` trava o renderer e derruba o browser headless

**Reprodução isolada (fora da suite principal, script dedicado):**

1. `page.goto('/catalogo', { waitUntil: 'domcontentloaded' })` **resolve normalmente** (~3s).
2. A partir daí, a página nunca atinge o evento `load`. Rastreamento de rede mostrou 5 requisições presas por 30s+ sem nunca finalizar:
   - `js-cookie/js.cookie.min.js`
   - `css/awa-cookie-consent-fix.min.css`
   - `js/awa-header-minicart-ui-v2.js`
   - `js/awa-header-a11y-performance.min.js`
   - `https://connect.facebook.net/en_US/fbevents.js` (terceiro)
3. As 4 primeiras são do **próprio domínio** e respondem instantaneamente via `curl` (200 OK, poucos KB, <40ms) — ou seja, o servidor não é o problema.
4. Chamadas subsequentes de `page.evaluate()` (ex.: ler `document.readyState`) também travam — indício de que a **main thread do renderer fica bloqueada**, não apenas a rede.
5. Eventualmente a sessão do Chromium quebra com `Error: Channel closed`, e isso já foi observado derrubando o worker inteiro do Playwright (suíte completa parava sem processar as rotas seguintes).

**Impacto real de usuário (hipótese a validar em profiling futuro, fora do escopo H0):** se o mesmo bloqueio de main thread ocorrer em dispositivos reais mais lentos (mobile de entrada, CPU throttled), o usuário pode perceber a página como "travada" ao entrar em `/catalogo` — apesar do HTML inicial chegar rápido.

**Ação tomada nesta fase:** rota marcada como `knownUnstable: true` na spec e pulada automaticamente (`test.skip`) para não derrubar a suíte. Pode ser forçada com `AWA_H0_ALLOW_UNSTABLE=true`.

**Próximo passo recomendado (fora do escopo H0):** profiling de performance (Chrome DevTools Performance tab / Lighthouse) especificamente em `/catalogo`, focado em long tasks entre `domcontentloaded` e `load`.

---

## CRIT-02 — PDP real nunca atinge `domcontentloaded`

**Reprodução isolada:**

1. `page.goto(pdpUrl, { waitUntil: 'commit' })` resolve em **156ms** — a resposta HTTP chega instantaneamente (confirmado também via `curl`: 200 OK, 645KB, <40ms).
2. `page.goto(pdpUrl, { waitUntil: 'domcontentloaded', timeout: 90000 })` **nunca resolve** — timeout consumido por completo (90s), sem o evento `domcontentloaded` disparar e **sem o browser reportar crash** (processo continua vivo, main thread aparentemente processando algo indefinidamente durante o parse/execução do HTML inicial).
3. Diferente do CRIT-01, aqui o bloqueio ocorre **antes mesmo do parse do HTML terminar** — mais severo.
4. Scripts inline early-head da PDP foram revisados (`awa-web-vitals-rum`, `awa-impeccable-loopback-guard-v2`, fila de CSS via `requestAnimationFrame`) — nenhum contém loop óbvio; a causa exata não foi isolada dentro do escopo desta fase (ver "Próximo passo").

**Ação tomada nesta fase:** rota marcada como `knownUnstable: true` e pulada automaticamente nos testes H0-00 e H0-03 (que usam PDP na matriz de rotas). O teste de Sticky Header (H0-13), que também usava `/catalogo` como página de teste, foi trocado para usar a Home (comportamento sticky não é específico de rota).

**Próximo passo recomendado (fora do escopo H0):** bisecção script-a-script (comentar/isolar cada `<script>` do `<head>` da PDP) para achar o bloqueio exato; abrir a mesma PDP num Chrome real com DevTools Performance para comparar.

---

## H0-BUG-03 — Botão fechar da topbar B2B não funciona

**Teste:** `H0-01/02 — Topbar B2B e botao fechar › botao fechar oculta a topbar e persiste apos reload` (breakpoint 1440, rota Home) — **FALHOU** (2/2 tentativas).

**Evidências coletadas pelo teste:**
- Clicar no botão fechar (`#awa-b2b-promo-close`) **não ocultou a topbar**.
- Fechar a topbar **não gravou nenhuma flag de dismissal** em `localStorage`/`sessionStorage`.
- Após reload da página, a topbar **reaparece** mesmo tendo sido fechada — ou seja, a persistência do "fechei isso" está quebrada (ou nunca foi implementada no botão atual).

**Nota:** o teste de visibilidade padrão (topbar visível e CTA aponta para `/b2b/register`) passou normalmente — o bug é especificamente no fluxo de fechar/persistir.

---

## H0-BUG-04 — Menu principal não visível no desktop (1440)

**Teste:** `H0-07 — Menu principal › nav principal contem links navegaveis quando visivel no breakpoint` — **FALHOU** (2/2 tentativas) no projeto `h0-1440`.

**Evidência:** o seletor `.top-menu.top-menu-sticky` (nav principal) não está visível no breakpoint desktop 1440, onde o teste espera que a navegação principal esteja sempre visível (não é o menu mobile).

**Observação:** o teste H0-06 (trigger do menu Departamentos abre/fecha o painel) passou normalmente na mesma rota/breakpoint — o problema parece isolado à nav principal `.top-menu.top-menu-sticky`, não ao menu de departamentos.

---

## H0-BUG-05 — Minicart sem controle de abertura visível (1440)

**Teste:** `H0-11 — Minicart › abre e fecha o painel do minicart` — **FALHOU** no projeto `h0-1440`.

**Evidência:** nenhum controle de abertura do minicart (`.awa-header-cart-fallback` / `.awa-header-cart-link`) está visível no breakpoint 1440 para acionar o painel.

---

## Testes que passaram (breakpoint 1440, evidência coletada)

- H0-00 (estrutura por rota): home, bauletos, nossas-marcas, about-us, lancamentos, lancamentos.html, b2b/register, customer/account/login, busca "guidao" — todos OK (sem overflow horizontal, header presente conforme variante esperada auth/default).
- H0-01/02: topbar visível por padrão + CTA aponta para `/b2b/register` — OK.
- H0-03 (logo): home, b2b/register, customer-login — OK (imagem carrega, link aponta para home).
- H0-04/05 (busca + autocomplete): input aceita foco/digitação — OK; autocomplete abre com resultados/estado vazio e fecha com Escape — OK.
- H0-06 (menu Departamentos): trigger desktop abre e fecha o painel — OK.
- H0-08 (link Lançamentos): redirect `/lancamentos.html` → `/lancamentos` — OK; `/lancamentos` carrega com header completo — OK.
- H0-09 (painel B2B/status do cliente): abre com dados do cliente e fecha corretamente — OK.
- H0-10 (dropdown de conta, guest): links de login/cadastro acessíveis para visitante — OK.

**Cobertura parcial:** por causa da instabilidade documentada em INFRA-01, não foi possível concluir com confiança a matriz completa (6 breakpoints × 11 rotas × 14 componentes) numa única execução. Os resultados acima são da execução mais completa obtida (breakpoint 1440). H0-12 (drawer mobile), H0-13 (sticky) e H0-14 (PWA install) têm testes escritos na spec mas não foram confirmados nesta rodada por interrupção do runner antes de alcançá-los — recomenda-se reexecutar em lotes pequenos (ver INFRA-01).

---

## INFRA-01 — Instabilidade do ambiente de execução (não é bug de produto)

Durante a Fase H0, execuções da suíte completa (180 testes, 6 breakpoints) sofreram **término abrupto do processo do Playwright** em pontos variáveis e não determinísticos (às vezes no teste #2, às vezes no #9, às vezes no #29), sem:

- Mensagens de erro no stdout/stderr do próprio Playwright;
- Entradas de OOM killer no `dmesg` (`oom_kill: 0` confirmado no cgroup `system.slice/ssh.service`);
- Segfaults, sinais ou negações do AppArmor no `dmesg`;
- Limite de memória/cgroup atingido (memória disponível ampla durante os testes: ~22GB livres).

Tentativas de mitigação aplicadas: troca de Firefox para Chromium com `--no-sandbox --disable-dev-shm-usage --disable-gpu`; execução em foreground direto (sem `nohup`/`disown` manual); isolamento das rotas problemáticas (`catalogo`, PDP). Nenhuma eliminou o problema por completo — execuções isoladas e curtas (1 teste, ou poucos testes) são consistentemente confiáveis; execuções longas (dezenas de testes em sequência) eventualmente morrem sem causa identificável nesta VPS compartilhada.

**Recomendação:** para a Fase H1 em diante, rodar a suíte via GitHub Actions (ambiente dedicado, sem contenção de recursos com outros processos da VPS) em vez desta VPS de desenvolvimento, ou dividir a execução em lotes pequenos por rota/breakpoint (`--grep`) executados sequencialmente com re-tentativa automática entre lotes.

---

## Arquivos alterados nesta fase (infraestrutura de teste, sem alteração de produto)

- `tests/e2e/pw-fase-h0-header.config.ts` — troca de browser (Firefox → Chromium) com flags de estabilidade; comentário de contexto atualizado.
- `tests/e2e/specs/fase-h0-header-functional-qa.spec.ts`:
  - Campo `knownUnstable` adicionado ao tipo `RouteDef`.
  - Rotas `catalogo` e `pdp` marcadas como `knownUnstable: true` com nota referenciando este relatório.
  - `test.skip` automático para rotas `knownUnstable` em H0-00 e H0-03 (pode ser sobrescrito com `AWA_H0_ALLOW_UNSTABLE=true`).
  - H0-13 (Sticky header) trocado de `/catalogo` para Home como página de teste.

Nenhum arquivo de tema, template, LESS/CSS ou JS de produção foi alterado nesta fase — apenas infraestrutura de teste (`tests/e2e/`) e este relatório.
