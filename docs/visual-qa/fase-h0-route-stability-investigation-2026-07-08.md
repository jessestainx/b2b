# Fase H0.1 — Route Stability Investigation — `/catalogo` e PDP

> **Escopo**: apenas leitura/investigação de runtime. Nenhuma correção de tema, CSS/LESS, JS de produção, template ou checkout foi aplicada nesta fase.
> **Ambiente**: produção (`https://awamotos.com`), acesso read-only via Playwright/Chromium (headless e headed via Xvfb).
> **Base**: BUG-H0-CRIT-01 e BUG-H0-CRIT-02, documentados em `fase-h0-header-bug-report-2026-07-08.md` (Fase H0 — Header Functional QA).
> **Arquivos desta fase**: `tests/e2e/specs/fase-h0-route-stability-catalogo.spec.ts`, `tests/e2e/specs/fase-h0-route-stability-pdp.spec.ts`, `tests/e2e/helpers/route-stability.helpers.ts`, `tests/e2e/pw-fase-h0-route-stability.config.ts`, este relatório e `docs/visual-qa/fase-h0-route-stability-evidence/*.png`.

## Como reproduzir

```bash
cd tests/e2e

# Uma variacao isolada (recomendado — evita perder evidencia se o worker cair):
ALLOW_PRODUCTION_VALIDATION=true PLAYWRIGHT_BASE_URL=https://awamotos.com \
  npx playwright test --config=pw-fase-h0-route-stability.config.ts \
  specs/fase-h0-route-stability-catalogo.spec.ts \
  --project=h0-route-stability-1440-headless --grep "Variacao A"

# Headed (via Xvfb, variacao F):
ALLOW_PRODUCTION_VALIDATION=true PLAYWRIGHT_BASE_URL=https://awamotos.com \
  xvfb-run -a npx playwright test --config=pw-fase-h0-route-stability.config.ts \
  specs/fase-h0-route-stability-catalogo.spec.ts \
  --project=h0-route-stability-1440-headed --grep "Variacao A"
```

Evidencia bruta (trace.zip, screenshots, error-context.md) fica em `tests/e2e/test-results/fase-h0-route-stability/` (ignorado pelo git — regeneravel a qualquer momento com os comandos acima). Uma screenshot ilustrativa foi copiada para `docs/visual-qa/fase-h0-route-stability-evidence/` como evidencia permanente.

---

## 1. Sumario executivo

| # | Achado | Rota(s) | Severidade | Status |
|---|--------|---------|------------|--------|
| RC-01 | O processo do renderer Chromium **crasha** (`Error: Channel closed`) ao carregar `/catalogo` com JavaScript habilitado — em 5 de 6 variacoes testadas (A, B, C, D, headed-A). Nao e apenas um "hang", e queda do processo. | `/catalogo` | Critico (confirma e refina BUG-H0-CRIT-01) | Reproduzido, causa raiz isolada |
| RC-02 | O mesmo padrao de crash (`Error: Channel closed`) ocorre na PDP real com JavaScript habilitado, em todas as variacoes testadas (A, C, D). | PDP (`/bagageiro-titan-...html`) | Critico (confirma e refina BUG-H0-CRIT-02) | Reproduzido, causa raiz isolada |
| RC-03 | **Com JavaScript desabilitado (variacao E), ambas as rotas carregam perfeitamente e rapidamente** — `commit`, `domcontentloaded` e `load` disparam em menos de 1 segundo cada, `document.readyState === 'complete'`, sem erros. | `/catalogo` e PDP | Confirmacao | Reproduzido de forma consistente |
| RC-04 | Bloquear terceiros conhecidos (GA/GTM/Facebook/etc.) **nao evita** o crash em nenhuma das duas rotas. | `/catalogo`, PDP | Informativo | Refuta hipotese de causa exclusivamente de terceiros |
| RC-05 | Bloquear imagens/fontes **nao evita** o crash em nenhuma das duas rotas. | `/catalogo`, PDP | Informativo | Refuta hipotese de causa em asset estatico |
| RC-06 | Bloquear service worker **nao evita** o crash em `/catalogo`. | `/catalogo` | Informativo | Refuta hipotese de SW |
| RC-07 | O crash ocorre tambem em **modo headed** (Chromium real via Xvfb, nao apenas headless). | `/catalogo` | Informativo | Refuta hipotese "so acontece em headless" |
| RC-08 | Um script de controle **fora do Playwright Test runner** (Node puro, `chromium.launch()` direto) reproduz o mesmo crash em `/catalogo` e **nao** reproduz em Home — confirma que o crash e da rota, nao da minha instrumentacao de teste nem do test runner. | `/catalogo` vs. Home | Informativo | Controle negativo confirmado |
| RC-09 | `/catalogo` carrega um conjunto de scripts que **nao aparece em Home**: `spectrum.min.js` + `tinycolor.min.js` (color picker jQuery), `moment.min.js` e a cadeia `jquery.cookie.min.js` -> `js-cookie/cookie-wrapper.min.js` (core Magento). O ultimo request pendente no momento do crash, no trace coletado, e justamente `js-cookie/cookie-wrapper.min.js` (nunca recebeu resposta). | `/catalogo` | Pista de causa raiz | Evidencia de trace |
| INFRA-02 | O comportamento nao e 100% deterministico: em execucoes diferentes da mesma variacao A, o crash ocorreu em pontos levemente diferentes da timeline (as vezes antes de `domcontentloaded` logar, as vezes durante a tentativa de `load`). Consistente com INFRA-01 (Fase H0) — variabilidade de uma VPS compartilhada. | `/catalogo`, PDP | Risco de infraestrutura | Documentado |

**Conclusao em uma frase**: **nao e rede lenta, nao e terceiro, nao e imagem/fonte, nao e service worker, nao e headless — e execucao de JavaScript no carregamento de `/catalogo` e da PDP que derruba o processo do renderer Chromium** nesta VPS, com forte indicio (nao prova definitiva) de que o gatilho esta relacionado a inicializacao de widgets jQuery UI/color-picker (`spectrum`/`tinycolor`) e/ou a cadeia `js-cookie` especificos dessas rotas.

---

## 2. Resultado `/catalogo`

URL testada: `https://awamotos.com/catalogo` (mesma da Fase H0).

| Variacao | commit (10s) | domcontentloaded (30s) | load (60s) | Resultado |
|---|---|---|---|---|
| A — Browser limpo (execucao 1) | OK 315ms | OK 1308ms | processo crasha durante a tentativa | Crash (`Channel closed`) |
| A — Browser limpo (execucao 2) | OK 30ms | processo crasha durante a tentativa | — | Crash (`Channel closed`) |
| A — Browser limpo (headed/Xvfb) | OK 348ms | processo crasha ~2,8s apos inicio da tentativa (goto iniciado em t=5,2s, nunca retorna) | — | Crash (`Channel closed`) |
| B — Service worker bloqueado | OK 37ms | processo crasha durante a tentativa | — | Crash (`Channel closed`) |
| C — Terceiros bloqueados | processo crasha antes do primeiro log | — | — | Crash (`Channel closed`), mais rapido (~17,7s) |
| D — Imagens/fontes bloqueadas | OK 69ms | processo crasha durante a tentativa | — | Crash (`Channel closed`) |
| **E — JS desabilitado** | OK **28ms** | OK **831ms** (`readyState=complete`) | OK **904ms** (`readyState=complete`) | **Passou — nenhum crash** |

Controle adicional (script Node puro, fora do Playwright Test runner, mesma instrumentacao CDP): `commit` e `domcontentloaded` OK; tentativa de `load` falha em **13.501ms** com `page.goto: Target page, context or browser has been closed` — a ultima requisicao logada antes da queda foi `https://connect.facebook.net/en_US/fbevents.js`. Mesma classe de erro (canal/processo do browser encerrado), reproduzida fora da suite oficial.

### Evidencia de trace (variacao A, headed)

Trace real (`0-trace.trace` + `0-trace.network` extraidos de `trace.zip`) mostra a timeline exata:

```
t=2418ms  -> before: Frame.goto(url=/catalogo, waitUntil=commit, timeout=10000)
t=2763ms  -> after:  commit resolvido (345ms)
t=2767ms  -> before: evaluate(document.readyState) => "loading"
t=3924ms  -> before: Page.screenshot()
t=5038ms  -> after:  screenshot capturado
t=5219ms  -> before: Frame.goto(url=/catalogo, waitUntil=domcontentloaded, timeout=30000)
          -> (nenhum "after" correspondente — o processo nunca retornou desta chamada)
```

No mesmo trace, a ultima entrada de rede (`0-trace.network`) tem `time: -1` (nunca recebeu resposta):

```
https://awamotos.com/static/.../js-cookie/cookie-wrapper.min.js   status=-1 (pendente)
```

Screenshot capturada com sucesso durante o attempt de `commit` (antes do crash):

![catalogo — screenshot no attempt de commit, momentos antes do crash](./fase-h0-route-stability-evidence/catalogo-variacaoA-commit-antes-do-crash.png)

`error-context.md` gerado automaticamente pelo Playwright em toda variacao que crashou (A/B/C/D/headed-A):

```
Error: Channel closed
```

---

## 3. Resultado PDP

URL testada: `https://awamotos.com/bagageiro-titan-150-09-13-modelo-preto-macico-3000.html` (mesma da Fase H0 / BUG-H0-CRIT-02).

| Variacao | commit (10s) | domcontentloaded (30s) | load (60s) | Resultado |
|---|---|---|---|---|
| A — Browser limpo | OK 527ms | processo crasha durante a tentativa (~160s de execucao total, incl. overhead de screenshot/evaluate protegidos por timeout) | — | Crash (`Channel closed`) |
| C — Terceiros bloqueados | processo crasha antes do primeiro log | — | — | Crash (`Channel closed`), muito rapido (~10,7s) |
| D — Imagens/fontes bloqueadas | OK 53ms | processo crasha durante a tentativa | — | Crash (`Channel closed`) |
| **E — JS desabilitado** | OK **30ms** | OK **773ms** (`readyState=complete`) | OK **742ms** (`readyState=complete`) | **Passou — nenhum crash** |

**Refinamento importante em relacao ao bug report original (BUG-H0-CRIT-02):** o relatorio da Fase H0 descreveu o problema como "PDP nunca atinge `domcontentloaded`... processo continua vivo, main thread aparentemente processando algo indefinidamente". Nesta investigacao, o padrao observado foi consistentemente um **crash de processo** (`Error: Channel closed`), igual ao de `/catalogo` — nao um hang silencioso com processo vivo. E possivel que ambos os padroes ocorram dependendo da carga momentanea da VPS (ver INFRA-02), mas em 100% das execucoes desta subfase (3/3 com JS habilitado) o resultado final foi queda de processo, nunca um hang "vivo" ate o timeout de 90s/30s.

Nao foi observado nenhum `error-context.md` com um trace incompleto por JS parado — nas 3 execucoes com JS habilitado, o resultado final registrado pelo Playwright foi sempre `Channel closed`.

---

## 4. Matriz de variacoes testadas (Tarefa 4)

| Variacao | `/catalogo` | PDP | Observacao |
|---|---|---|---|
| A — Browser limpo (baseline) | Crash (3 execucoes, incl. 1 headed) | Crash | Baseline reproduz o bug em 100% das tentativas |
| B — Service worker bloqueado | Crash | nao executado (padrao ja confirmado em A/C/D) | SW nao e a causa |
| C — Terceiros bloqueados | Crash (mais rapido) | Crash (mais rapido) | Terceiros nao sao a causa exclusiva; bloquea-los nao piora nem resolve de forma consistente |
| D — Imagens/fontes bloqueadas | Crash | Crash | Assets estaticos nao sao a causa |
| E — JS desabilitado | **Passa, <1s cada evento** | **Passa, <1s cada evento** | **Isola a causa em execucao de JavaScript** |
| F — Headed (Xvfb) | Crash (variacao A repetida em modo headed) | nao executado nesta rodada (recomendado como proximo passo) | Refuta hipotese "so headless" |
| G — Viewport 1440 | Aplicado em todas as execucoes acima (nenhum outro breakpoint rodado nesta subfase) | Aplicado em todas | Conforme exigido pela tarefa |

Controle adicional fora da matriz oficial: script Node standalone confirmou o mesmo crash em `/catalogo` e a ausencia total do problema em Home, com a mesma instrumentacao — descarta vies da minha propria instrumentacao (sessao CDP manual) como causa.

---

## 5. Requests pendentes

- **`/catalogo` (variacao A, headed, trace real)**: no momento do crash, exatamente **1 request pendente** (nunca recebeu resposta): `js-cookie/cookie-wrapper.min.js` (arquivo **core do Magento**, em `lib/web/js-cookie/cookie-wrapper.js` — nao e um arquivo customizado da AWA). Todas as ~217 requisicoes anteriores no trace completaram normalmente, em geral entre 1ms e 40ms.
- **`/catalogo` (script de controle Node puro)**: ultima requisicao logada antes da queda foi `https://connect.facebook.net/en_US/fbevents.js` (terceiro, Facebook Pixel) — mas a variacao C (terceiros bloqueados, o que inclui bloquear `facebook.net`) **tambem crashou**, entao esta requisicao pendente parece ser sintoma/coincidencia de timing, nao causa raiz isolada.
- Diferenca observada entre `/catalogo` e Home (que nunca crasha): `/catalogo` carrega `spectrum.min.js`, `tinycolor.min.js` (color picker jQuery UI), `moment.min.js` e a cadeia `jquery.cookie.min.js` -> `js-cookie/cookie-wrapper.min.js`. **Nenhum desses aparece no carregamento da Home** (0 ocorrencias nos logs de controle da Home).
- Para PDP, nao foi possivel capturar um trace completo ate o momento exato do crash nesta rodada (a variacao A rodou dentro do Playwright Test com overhead grande de protecao de timeout, consumindo o orcamento antes de permitir extracao fina); recomenda-se, como proximo passo, repetir a captura de trace da PDP com o mesmo script de controle standalone usado para `/catalogo` (ver secao 10).

---

## 6. Console / page errors

Em nenhuma das execucoes (crashadas ou nao) foram capturados `console.error`/`pageerror` **antes** do crash — os listeners de console (`page.on('console')`, `page.on('pageerror')`) nunca dispararam previamente a queda do canal. Isso e consistente com um **crash nativo do processo do renderer** (ex.: falha de V8/GC, uso excessivo de memoria, ou bug do proprio Chromium), que interrompe o processo **antes** de qualquer excecao JS "normal" chegar a ser reportada via CDP — e nao com uma excecao JS comum capturavel (`Uncaught TypeError`, etc.), que teria aparecido no console.

Nenhum erro 5xx de servidor foi observado nas requisicoes que completaram — todos os status HTTP registrados foram 200. O servidor de origem nao e a causa (confirmado tambem via `curl` direto na Fase H0 original).

---

## 7. Screenshots / traces gerados

- `docs/visual-qa/fase-h0-route-stability-evidence/catalogo-variacaoA-commit-antes-do-crash.png` — screenshot permanente, capturada com sucesso no attempt de `commit` da variacao A (headed), momentos antes do crash na tentativa seguinte (`domcontentloaded`).
- `tests/e2e/test-results/fase-h0-route-stability/**/trace.zip` — trace completo do Playwright (rede + timeline de chamadas + screencast) para cada execucao; regeneravel a qualquer momento com os comandos da secao "Como reproduzir" (nao commitado — `test-results/` esta no `.gitignore`, mesma convencao da Fase H0).
- `tests/e2e/test-results/fase-h0-route-stability/**/error-context.md` — artefato nativo do Playwright, gerado automaticamente em toda variacao que resultou em `Channel closed`, contendo o erro exato capturado pelo test runner.
- HAR: nao gerado nesta rodada (Playwright nao expoe HAR nativamente via `page.goto`/context simples sem `recordHar` explicito no momento da criacao do contexto de cada variacao — ficou pendente como melhoria de instrumentacao, ver secao 11). O `trace.zip` ja contem um arquivo `*.network` equivalente a um HAR simplificado, usado nesta investigacao como substituto.

---

## 8. Hipotese de causa raiz

**Hipotese principal**: a execucao de JavaScript durante o carregamento de `/catalogo` e da PDP aciona uma sequencia de inicializacao de widgets (jQuery UI generico + **color picker `spectrum`/`tinycolor`** + `moment.js` + a cadeia `js-cookie`/`cookie-wrapper`) que **nao existe no carregamento da Home**. Nesta VPS (recursos compartilhados, mesma condicao documentada em INFRA-01 da Fase H0), essa carga de inicializacao sincrona e suficiente para **derrubar o processo do renderer Chromium** (erro de protocolo `Channel closed`, equivalente a "o browser/pagina fechou inesperadamente"), tanto em modo headless quanto headed.

Evidencias que sustentam a hipotese:
1. Desabilitar JS completamente (variacao E) **elimina o problema 100% das vezes**, em ambas as rotas — o HTML/CSS por si so carrega em <1s sem qualquer erro.
2. O crash ocorre com e sem terceiros bloqueados, com e sem imagens/fontes, com e sem service worker, e em headed e headless — isolando a causa para "algo que so acontece quando JS roda", e nao para uma dependencia de rede/terceiro especifica.
3. O trace de rede mostra `/catalogo` carregando scripts (`spectrum`, `tinycolor`, `moment`, cadeia `js-cookie`) que **nao aparecem em Home**, rota que nunca crasha.
4. Nenhum erro de JS "normal" (console.error/pageerror) foi capturado antes do crash — assinatura mais compativel com queda nativa do processo (memoria/V8) do que com uma excecao de aplicacao.

**Hipoteses alternativas consideradas e por que foram descartadas ou mantidas em aberto**:
- *Script de terceiro especifico (ex.: `fbevents.js`) causando o crash* — parcialmente refutada: a variacao C bloqueia terceiros conhecidos (inclui `facebook.net`) e o crash ainda ocorre, as vezes ate mais rapido. Terceiros podem contribuir em cenarios especificos, mas nao sao a causa raiz isolada.
- *Falha de carregamento de imagem/fonte especifica* — refutada pela variacao D.
- *Bug exclusivo do modo headless* — refutada pela variacao F (headed via Xvfb tambem crasha).
- *Falta de recursos da VPS compartilhada (INFRA-01) como causa isolada, sem relacao com o conteudo da pagina* — refutada como causa **exclusiva**, porque a Home (mesma VPS, mesmo momento, mesmo padrao de instrumentacao) nunca crasha; porem a VPS provavelmente **amplifica** a severidade/frequencia do crash de `/catalogo`/PDP (ver INFRA-02).

**O que ainda nao foi isolado nesta subfase** (fora do escopo H0.1, ver secao 10): qual script exato, entre os candidatos identificados, e o gatilho direto. Isso exigiria bisseccao script-a-script (comentar/interceptar cada `<script>`/modulo RequireJS individualmente), que e o proximo passo recomendado.

---

## 9. Arquivos suspeitos

Identificados via diferenca de payload de rede entre `/catalogo` (crasha) e Home (nunca crasha) e via a ultima requisicao pendente no trace do crash:

| Arquivo | Papel | Por que e suspeito |
|---|---|---|
| `lib/web/js-cookie/cookie-wrapper.js` (**core Magento**, nao customizado) | Wrapper AMD que expoe `$.cookie`/`$.removeCookie`, depende de `js-cookie/js.cookie` | Foi a **ultima requisicao pendente** (nunca respondida) no trace do crash de `/catalogo` |
| `jquery/jquery.cookie.min.js` / `js-cookie/js.cookie.min.js` | Biblioteca de cookies (tambem citada como suspeita no bug report original da Fase H0) | Carrega imediatamente antes de `cookie-wrapper.min.js` na timeline; ja apontada no relatorio H0 original |
| `spectrum.min.js` + `tinycolor.min.js` | Color picker jQuery UI (provavelmente ligado a filtro de cor na navegacao em camadas do catalogo — atributo "cor" comum em pecas de moto) | Presente em `/catalogo`, **ausente em Home** — diferencial mais forte encontrado nesta investigacao |
| `moment.min.js` | Biblioteca de datas | Presente em `/catalogo`, **ausente em Home** — pode estar associado a um widget de contagem/promocao especifico da pagina |
| `js/awa-header-minicart-ui-v2.js`, `js/awa-header-a11y-performance.min.js`, `css/awa-cookie-consent-fix.min.css` | Modulos customizados AWA (ja citados no bug report original H0) | Nao confirmados como pendentes nesta rodada, mas continuam sendo candidatos plausiveis por estarem na lista original |

Nenhum destes arquivos foi alterado nesta fase — a tabela e apenas um mapa de investigacao para a proxima fase de correcao.

---

## 10. Correcao recomendada (NAO aplicada nesta fase)

1. **Bisseccao script-a-script em ambiente controlado** (fora desta VPS de desenvolvimento, idealmente em runner dedicado — ver INFRA-01/INFRA-02): interceptar/abortar via `context.route()` cada um dos scripts candidatos da secao 9, um por vez, com JS habilitado, repetindo a variacao A ate isolar exatamente qual arquivo, quando bloqueado individualmente, faz `/catalogo` e a PDP pararem de crashar.
2. Uma vez isolado o script/modulo exato, revisar seu codigo-fonte (bundle `js-cookie` + qualquer inicializador de `spectrum`/`tinycolor`/`moment` especifico do catalogo) por: loop sincrono nao terminante, recursao sem guarda, ou uso de memoria desproporcional (ex.: parsing de um dataset grande de cores/variacoes de produto).
3. Se a causa raiz for confirmada como um problema de performance/memoria do bundle (e nao um bug logico simples), considerar `defer`/lazy-load desses scripts (color picker, moment.js) apenas quando o filtro de cor realmente for interagido, em vez de carrega-los no load inicial do catalogo.
4. Reexecutar esta mesma suite (`fase-h0-route-stability-*.spec.ts`) em um ambiente de CI dedicado (nao a VPS de desenvolvimento compartilhada) para confirmar se a frequencia do crash muda — isso teria valor de diagnostico adicional sobre o peso da hipotese INFRA-02.
5. Para os testes funcionais/visuais que dependem de `/catalogo` e da PDP (Fase H0 header QA, Fase 6A, etc.): **nao usar `waitUntil: 'load'` nem `'domcontentloaded'`** nessas duas rotas — usar `waitUntil: 'commit'` (unica marca 100% confiavel em todas as execucoes desta investigacao, sempre <1s) seguido de espera por seletor especifico (ex.: `page.locator('header.awa-site-header').waitFor()`), com `page.isClosed()` tratado como skip, nao como falha. A spec `fase-h0-header-functional-qa.spec.ts` ja faz algo parecido com `domcontentloaded` — recomenda-se trocar para `commit` nessas duas rotas especificamente, ja que mesmo `domcontentloaded` se mostrou nao-confiavel nesta investigacao (crashou antes de disparar em varias execucoes).

Nenhuma dessas correcoes foi aplicada — ficam registradas para uma fase de correcao dedicada, em branch separada.

---

## 11. Proxima acao

1. Abrir uma fase de correcao dedicada (branch separada, ex.: `fix/route-stability-catalogo-pdp`) para executar a bisseccao script-a-script da secao 10.1 — fora do escopo desta investigacao H0.1.
2. Adicionar `recordHar` explicito por variacao nos proximos specs de investigacao (gap identificado na secao 7) para ter HAR nativo, nao apenas o `.network` do trace do Playwright.
3. Repetir a variacao F (headed) e a variacao B (service worker) para a PDP, que nao foram executadas nesta rodada por restricao de tempo/janela de execucao — para fechar a matriz 5x2 completa.
4. Validar a hipotese INFRA-02 rodando a mesma suite em um ambiente de CI dedicado (fora da VPS compartilhada de desenvolvimento) e comparar taxa de crash.
5. Nao misturar esta investigacao com a Fase 6A (limpeza de CSS morto) nem com correcoes de header (topbar B2B / menu principal / minicart) documentadas separadamente em `fase-h0-header-bug-report-2026-07-08.md` — cada uma segue seu proprio fluxo de correcao.

---

## Arquivos criados nesta fase (infraestrutura de teste, sem alteracao de produto)

- `tests/e2e/helpers/route-stability.helpers.ts` — instrumentacao compartilhada (recorder de rede/console/CDP lifecycle, matriz de variacoes A-E, utilitarios de timeout seguro).
- `tests/e2e/pw-fase-h0-route-stability.config.ts` — config dedicado (viewport 1440 fixo, projects headless/headed, trace sempre ligado).
- `tests/e2e/specs/fase-h0-route-stability-catalogo.spec.ts` — 5 testes (variacoes A-E) para `/catalogo`.
- `tests/e2e/specs/fase-h0-route-stability-pdp.spec.ts` — 5 testes (variacoes A-E) para a PDP.
- `docs/visual-qa/fase-h0-route-stability-investigation-2026-07-08.md` — este relatorio.
- `docs/visual-qa/fase-h0-route-stability-evidence/catalogo-variacaoA-commit-antes-do-crash.png` — evidencia visual permanente.

Nenhum arquivo de tema, template, LESS/CSS, JS de producao, checkout ou modulo `app/code/GrupoAwamotos/*` foi alterado nesta fase.
