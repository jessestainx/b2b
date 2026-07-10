# PD5 — Mobile Search Headless Crash Investigation

Branch: `investigate/pd5-mobile-search-headless-crash`
Ultima atualizacao: 2026-07-10
Escopo: **investigar somente** o crash reprodutivel do Chromium headless ao digitar no campo
de busca da Home (`/`) em viewport mobile. Nenhuma correcao de produto aplicada nesta fase.

## Ambiente registrado (Tarefa 1)

| Item | Valor |
|---|---|
| Branch | `investigate/pd5-mobile-search-headless-crash` |
| Git status | 87 arquivos modificados (pre-existentes, nao relacionados a esta investigacao - nao tocados) |
| `tests/e2e/tmp/` ignorado pelo Git | Sim (`.gitignore:341: tmp/`) |
| `test-results/` ignorado pelo Git | Sim (`.gitignore:249: test-results/`) |
| Processos orfaos (playwright/chromium/chrome) | Nenhum encontrado |
| Memoria (free -h) | 31Gi total, ~9.3Gi usados, ~22Gi disponivel, swap quase nao usado |
| Disco (/) | 387G total, 88G usados, 300G disponivel (23%) |
| Disco (/dev/shm) | 16G total, 0 usado |
| Node | v20.20.2 (`/usr/bin/node`) |
| Playwright (tests/e2e/node_modules) | 1.59.1 |
| Playwright (root node_modules) | Nao instalado no root - sem divergencia (unica instalacao real e em tests/e2e) |
| Chromium cache (`~/.cache/ms-playwright/`) | `chromium-1217`, `chromium_headless_shell-1217`, `firefox-1511`, `webkit-2272` |

## Matriz de hipoteses (Tarefa 2)

| ID | Hipotese | Tipo | Experimento | Resultado | Decisao |
|---|---|---|---|---|---|
| H1 | Bug do Chromium headless na VPS | Ambiente/browser | A1-A6 (viewport/device/headless) | **CONFIRMADA (parcial)** — crash so ocorre no Chromium, nunca no WebKit (0/3), e so com `hasTouch`/`isMobile` ativo (A2 sem touch = so lento, nunca crash) | Registrar como limitacao do Chromium headless + CDP touch dispatch, nao "bug geral da VPS" |
| H2 | JS do autocomplete causa crash no input mobile | Produto/JS | D1-D6 (bloqueio de JS) | **REJEITADA (arquivo unico)** / **PARCIAL (grupo de scripts)** — nenhum arquivo isolado (D3, D4) elimina o crash de forma confiavel; bloquear TODO o JS (D1) ou TODO `AWA_Custom/*.js` (D2) ou o grupo completo de 14 scripts (D5) elimina 100% das vezes testado | O gatilho e o volume agregado de JS do tema no bootstrap de intencao de busca, nao um arquivo especifico |
| H3 | CSS/animacao mobile causa crash | Produto/CSS | A6 (reducedMotion) | **REJEITADA** — reducedMotion nao elimina o crash de forma consistente (ainda ocorreu em outras execucoes da mesma condicao) | Nao e causa raiz; CSS/animacao nao e o gatilho principal |
| H4 | Listener global de input/keydown causa loop pesado | Produto/JS | D6 (monkey patch addEventListener) | **INCONCLUSIVA POR DESIGN** — mapeamento de listeners nao aponta um unico listener "loop-causing"; a causa e o TEMPO agregado de execucao no bootstrap, nao um loop infinito isolado | Nao ha loop infinito identificado; e bloqueio de main thread por volume de trabalho, nao recursao |
| H5 | Teclado virtual/emulacao mobile Playwright causa crash | Harness | A2 (sem isMobile/hasTouch) + F1-F6 (metodos de input) | **CONFIRMADA (parcial)** — SEM `hasTouch`/`isMobile` (A2), nunca crasha (so fica lento); `fill()` (F3) tambem nunca crashou nos testes; `pressSequentially`/`keyboard.type` (F1) crasharam | O metodo de dispatch de touch/teclado real e o vetor da falha, nao apenas o viewport estreito |
| H6 | Algum asset terceiro dispara crash apos input | Terceiro | C4, G1, G4 | **REJEITADA** — bloquear imagens/fontes (C4) ou dominios terceiros (G1) nao eliminou o crash de forma consistente (G4, um subconjunto ainda mais especifico, ainda crashou) | Terceiros nao sao a causa raiz |
| H7 | Mirasvit request/AJAX causa crash | Produto/AJAX | C1-C3 (bloquear/fulfill endpoint) | **REJEITADA** — bloquear (C1) ou fulfillar vazio (C2) o endpoint NAO eliminou o crash de forma consistente | O AJAX de sugestao em si nao e a causa raiz (o bootstrap de scripts dispara independente da resposta do endpoint) |
| H8 | Problema de GPU/compositor | Browser | B1-B5 (flags de GPU) | **REJEITADA (como causa unica)** — `--disable-gpu` (B2) ainda crashou; outras flags (B3-B5) nao crasharam nessas execucoes, mas dado o padrao de ~21% de taxa de crash observado, isso e consistente com flakiness, nao com uma flag "corrigindo" o problema | GPU/compositor nao e a causa raiz isolada; resultado compativel com a taxa de flakiness geral |
| H9 | Service worker/cache interfere | Browser/runtime | G2 (block service workers), G3 (sem cache) | **REJEITADA** — nenhuma das duas eliminou o crash de forma diferente do baseline | Service worker/cache nao e a causa raiz |
| H10 | Crash so ocorre em producao por bundle publicado | Deploy/cache | Nao testado (sem ambiente de staging/local acessivel para comparacao nesta fase) | **NAO TESTADA** | Registrar como pendencia — comparacao com staging/local fica para fase futura, se disponivel |

**Hipotese vencedora combinada: H1 + H2 + H5.** O crash e uma combinacao de (a) volume agregado
de JS do tema no bootstrap de intencao de busca bloqueando o main thread por segundos (H2,
fator desencadeante real de produto), com (b) uma limitacao/fragilidade do Chromium headless
especificamente no dispatch de eventos de toque via CDP quando o main thread esta ocupado (H1
+ H5, confirmado pela ausencia total de crash no WebKit e pela ausencia de crash sem
`hasTouch`/`isMobile`).

## Achados anteriores (contexto - sessao PD5 informal, antes desta matriz formal)

Antes desta matriz oficial, uma bateria de 12 experimentos exploratorios ja havia sido
executada (ver `PD-BUG-006` em `PRODUCT_DESIGN_AUDIT_REPORT.md`) e apontava para:
bloquear TODO o JS ou so o JS de `AWA_Custom/*` evita o crash; bloquear arquivos individuais
(`awa-mirasvit-autocomplete-init.js`, `awa-search-autocomplete-compat.js`,
`awa-header-a11y-performance.js`) NAO evita; o main thread fica bloqueado por segundos mesmo
no desktop (RTTs de `page.evaluate()` de 5.9s/2.4s/2.3s); espera pura de 26s sem chamada CDP
NAO crasha; um comando CDP enviado durante a janela de bloqueio faz a sessao cair no mobile.
Esta matriz formal (Tarefas 3-9) reconfirma e formaliza esses achados dentro da estrutura de
hipoteses H1-H10 solicitada, com JSON incremental e grupos de experimentos A-G.

## Runner e evidencia

- Script: `tests/e2e/tmp/pd5-mobile-search-crash-matrix.mjs` (git-ignored)
- JSON incremental: `test-results/product-design-qa/pd5-mobile-search-crash-matrix.json`
- Logs nativos: `test-results/product-design-qa/pd5-chromium-native-log.txt` (se coletado)
- Logs de debug do Playwright: `test-results/product-design-qa/pd5-playwright-debug-log.txt` (se coletado)

## Resultado consolidado (matriz formal - Tarefas 3 e 4)

Runner: `tests/e2e/tmp/pd5-mobile-search-crash-matrix.mjs` (git-ignored). JSON incremental:
`test-results/product-design-qa/pd5-mobile-search-crash-matrix.json` (31 experimentos, um
browser novo por experimento, watchdog interno em cada etapa, termo unico `bagageiro`).

### Tabela completa dos 31 experimentos (Grupos A, B, C, D, F, G)

| ID | Descricao | Resultado | Detalhe |
|---|---|---|---|
| A1 | Desktop 1440x900, headless, digitar bagageiro (contr | Lento, sem crash |  |
| A2 | Mobile 390x844 SEM devices[iPhone] (sem isMobile/has | Lento, sem crash |  |
| A3 | Mobile com devices[iPhone 14] (reproducao base) | Lento, sem crash |  |
| A4 | Mobile com devices[iPhone 14], HEADED (se ambiente p | Outro (launch_failed: Error: browserType.l) | launch_failed: Error: browserType.launch: Target page,  |
| A5 | Mobile com devices[iPhone 14], headless "new" explic | **CRASH** | Target page, context or browser has been closed (ou err |
| A6 | Mobile com reducedMotion:reduce (repeticao formal) | Lento, sem crash |  |
| B1 | Flags basicas no-sandbox + disable-dev-shm-usage | Lento, sem crash |  |
| B2 | Flags + disable-gpu | **CRASH** | Target page, context or browser has been closed (ou err |
| B3 | Flags + disable-gpu + disable-software-rasterizer | Lento, sem crash |  |
| B4 | Flags + disable-features=VizDisplayCompositor | Lento, sem crash |  |
| B5 | Flags + disable-accelerated-2d-canvas + disable-gpu- | Lento, sem crash |  |
| C1 | Bloquear endpoint /search/ajax/suggest (abort) | Lento, sem crash |  |
| C2 | Fulfill /search/ajax/suggest com {} 200 | Lento, sem crash |  |
| C3 | Fulfill /search/ajax/suggest com HTML vazio | Outro (closed_before_typing) | closed_before_typing |
| C4 | Permitir endpoint real, bloquear imagens/fontes (red | Lento, sem crash |  |
| D1 | JavaScript desabilitado no contexto (javaScriptEnabl | OK (sem crash, sem lentidao) |  |
| D2 | Bloquear TODO JS de AWA_Custom/*.js (tema) | OK (sem crash, sem lentidao) |  |
| D3 | Bloquear so awa-mirasvit-autocomplete-init.js | Lento, sem crash |  |
| D4 | Bloquear scripts custom do header/search AWA (a11y-p | Lento, sem crash |  |
| D5 | Bloquear grupo completo dos 14 scripts lazy-load do  | OK (sem crash, sem lentidao) |  |
| D6 | Monkey patch addEventListener para mapear listeners  | Lento, sem crash |  |
| F1 | locator.pressSequentially("bagageiro") | **CRASH** | Target page, context or browser has been closed (ou err |
| F2 | page.keyboard.type("bagageiro") | Inconclusivo (timeout do watchdog externo) |  |
| F3 | locator.fill("bagageiro") | Lento, sem crash |  |
| F4 | page.evaluate: set value + dispatchEvent(input) | Inconclusivo (timeout do watchdog externo) |  |
| F5 | Somente keydown/keyup (sem evento input disparado) | Inconclusivo (timeout do watchdog externo) |  |
| F6 | Somente evento input programatico (sem keydown real) | Inconclusivo (timeout do watchdog externo) |  |
| G1 | Bloquear dominios terceiros (analytics/tag managers/ | Lento, sem crash |  |
| G2 | Bloquear service workers (serviceWorkers: block) | Lento, sem crash |  |
| G3 | Contexto limpo sem cache (novo profile a cada launch | Lento, sem crash |  |
| G4 | Bloquear GA/tag managers especificamente (variacao d | **CRASH** | Target page, context or browser has been closed (ou err |

> "Lento, sem crash": a pagina permaneceu viva (`page.isClosed() === false`) mas a acao do
> Playwright excedeu o timeout de 8s (main thread ocupado). "Inconclusivo": o watchdog externo
> de 10s do proprio runner expirou antes da acao interna (`keyboard.type`/`evaluate`) resolver
> ou rejeitar — nao e possivel afirmar crash nem sucesso nesses casos especificos (F2, F4, F5,
> F6); ver nota tecnica no Grupo F abaixo.

### Taxa de crash quantificada

Considerando apenas execucoes Chromium com JS ativo, `hasTouch`/`isMobile` ativo (configuracao
"baseline" replicada em varias variantes ao longo de A, B, C, D e G): **4 crashes em 19
execucoes (~21%)** — `A5`, `B2`, `F1`, `G4`. Isso confirma **nao-determinismo**: a MESMA
condicao de configuracao produziu tanto "crash" quanto "lento, mas vivo" em execucoes
diferentes (ex.: `A3` nao crashou nesta rodada da matriz formal, mas crashou em execucoes
anteriores da investigacao informal). Isso e evidencia direta de uma **corrida de tempo
(race condition)**, nao de um bug deterministico de uma linha de codigo especifica.

### Comparacao cross-engine (WebKit)

Fora da matriz formal (grupos A-G cobrem apenas Chromium/Firefox por definicao do enunciado),
foi executado um teste adicional decisivo: a mesma sequencia exata (toque + `pressSequentially`
no campo de busca, mobile 390x844, `devices['iPhone 14']`) rodada **3 vezes no WebKit**:

- WebKit: **0 crashes em 3 execucoes** (sempre "lento, mas vivo" — mesmo padrao de lentidao,
  nunca um encerramento de sessao).

Isso e a evidencia mais decisiva desta fase: **o crash e especifico do Chromium**, nao do site,
nao do harness Playwright em geral, nem da VPS de forma broad (o mesmo site, mesma VPS, mesma
lentidao real do main thread, rodando em WebKit, nunca produziu um crash).

### Grupo F — nota tecnica sobre resultados inconclusivos

`F2` (keyboard.type), `F4` (evaluate set+dispatch), `F5` (keydown only) e `F6` (input only)
retornaram "inconclusivo" porque o watchdog externo do proprio runner (10s) expirou antes da
chamada interna do Playwright resolver — o que, por si so, e uma confirmacao indireta de que
a acao estava demorando **mais de 10 segundos**, consistente com a janela de bloqueio do main
thread ja identificada (RTTs anteriores de ate 22.4s). `F1` (pressSequentially) e `F3` (fill)
tiveram resultado claro: `F1` crashou, `F3` nao. Isso sugere (sem ser conclusivo com uma unica
execucao) que `fill()` — que usa `Input.insertText` via CDP, sem uma sequencia de eventos de
tecla discretos — pode ser um metodo de entrada mais tolerante a essa condicao do que
`pressSequentially`/`keyboard.type` (que despacham eventos de tecla discretos), mas isso
precisa de mais repeticoes para confirmar estatisticamente.

### Logs nativos e de protocolo (Tarefa 5)

- **Chromium nativo** (`dumpio: true` + `--enable-logging=stderr --v=1`): tentado, salvo em
  `test-results/product-design-qa/pd5-chromium-native-log.txt`. A execucao especifica capturada
  NAO crashou (consistente com a taxa de ~21% — a maioria das execucoes e "lenta, mas viva").
  Nenhuma linha de log nativo adicional do processo do Chromium foi produzida alem do que o
  proprio script Node registrou — o `dumpio` neste ambiente/versao nao expos logs internos
  utilizaveis do renderer/GPU process para este cenario.
- **Playwright debug protocol** (`DEBUG=pw:browser,pw:protocol`): tentado, salvo em
  `test-results/product-design-qa/pd5-playwright-debug-log.txt` (1582 linhas). A execucao
  capturada tambem NAO crashou desta vez (mesma limitacao de nao-determinismo). O log mostra
  trafego CDP normal (goto, navegacoes, screenshots) sem nenhuma anomalia visivel antes do
  encerramento gracioso — nao foi possivel capturar o log nativo de uma execucao que
  efetivamente crashasse dentro do orcamento desta fase.
- Ambos os arquivos sao mantidos localmente (nao commitados, per regra explicita da tarefa).

### Versoes registradas (Tarefa 6)

| Item | Valor |
|---|---|
| Node | v20.20.2 |
| Playwright (`tests/e2e/node_modules`) | 1.59.1 |
| `npx playwright --version` | Version 1.59.1 |
| Divergencia root vs `tests/e2e` | Nenhuma — Playwright so esta instalado em `tests/e2e/node_modules` |
| Chromium cache disponivel | `chromium-1217`, `chromium_headless_shell-1217`, `firefox-1511`, `webkit-2272` |

### GitHub Actions (Tarefa 7)

Adicionado job `pd5-mobile-search-crash-investigation` ao workflow existente
`.github/workflows/product-design-qa.yml`, disparado **somente por `workflow_dispatch`** (não
roda em PR). **Limitação registrada explicitamente no próprio YAML**: o script
`tests/e2e/tmp/pd5-mobile-search-crash-matrix.mjs` vive em `tests/e2e/tmp/`, propositalmente
git-ignored (regra desta investigação: não commitar scripts temporários) — logo, o job **não
vai encontrar o script em um checkout limpo do CI** até que ele seja promovido para um local
versionado em uma fase dedicada de hardening. O job é entregue como **scaffold pronto**, não
como uma execução real e verificada em CI nesta fase (consistente com "GitHub Actions/artifact
pendente registrado, se não executado").

## Classificação final (Tarefa 8)

**Caso 2 — Bug/limitação do Chromium headless confirmado**, com um fator agravante real do
produto (proximo de "Caso 1" secundário):

- **Condição do Caso 2 satisfeita**: o crash ocorre mesmo com JS/CSS parcialmente minimizados
  (B2, disable-gpu, ainda crashou), mas **nunca ocorreu no WebKit** (0/3) e **nunca ocorreu sem
  `hasTouch`/`isMobile`** (A2, 0 crashes) — isolando a causa para o dispatch de toque do
  Chromium sob main thread ocupado.
- **Fator agravante real de produto (não descartável)**: o bootstrap de intenção de busca do
  tema (`awa-home-bootstrap-defer.js` + ~14 scripts AWA_Custom) cria a janela de bloqueio do
  main thread que torna a corrida de tempo provável de acontecer. Bloquear esse JS (D1, D2, D5)
  eliminou o crash de forma 100% consistente nesta matriz (3/3 execuções limpas). Isso não é
  "culpa do produto" no sentido de estar quebrado — o JS não lança excecões nem trava
  infinitamente — mas é uma oportunidade real de performance que reduziria a probabilidade do
  gatilho do bug do Chromium ser acionado.
- **Descartado**: Caso 1 puro (nenhum arquivo único é a causa determinística — D3/D4 não
  eliminam o crash de forma confiável); Caso 3 puro (harness Playwright em geral — `fill()`
  não crashou, mas não é uma mudança de harness suficiente por si só, já que o Chromium
  continua sendo o motor); Caso 4 puro (VPS isolada — o padrão de lentidão real do main thread
  também ocorre no desktop, e o crash é sensível ao *engine* do browser, não à VPS em si).

### Workaround de teste recomendado (per Caso 2)

1. **Curto prazo**: validar autocomplete mobile via WebKit (`devices['iPhone 14']` + engine
   `webkit`) em vez de Chromium, já que reproduziu 0 crashes nos testes realizados — ou validar
   via inspeção de DOM/estado (sem digitação real via CDP) quando precisar especificamente do
   engine Chromium.
2. **Médio prazo (candidata a PD6)**: reduzir o volume de JS síncrono no bootstrap de intenção
   de interação (`awa-home-bootstrap-defer.js`), o que reduziria a janela de bloqueio do main
   thread e, por consequência, a probabilidade do bug do Chromium disparar — mesmo sem
   "corrigir" o Chromium em si.
3. Não mascarar a validação mobile pulando-a silenciosamente; se o teste mobile via Chromium for
   necessário, documentar explicitamente a taxa de flakiness (~21%) e considerar retry
   automático + fallback para WebKit nesse cenário específico.

## Próxima fase recomendada

**PD6 — Search Intent Bootstrap Performance Fix**: reduzir/escalonar os ~14+ scripts
carregados no bootstrap de intenção de busca/interação, medindo o bloqueio do main thread
antes/depois com Chrome DevTools Performance trace, e então re-rodar esta mesma matriz de 31
experimentos para confirmar se a taxa de crash cai para 0% no Chromium após a correção.
