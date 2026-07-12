# Product Design Audit Report — AWA Motos (Fase PD0)

Última atualização: 2026-07-10
Fonte de evidência: execução real do smoke `design QA — home` via
`tests/e2e/specs/product-design-qa.spec.ts` (`--project=desktop-1440`, exit code 0,
"1 passed", sem `Killed`). Screenshots em `test-results/product-design-qa/`.

> Nenhuma correção foi aplicada nesta fase. Todos os bugs abaixo estão classificados
> como `REPRODUCED` (evidência real coletada) ou `TODO` (ainda não testado em todas
> as rotas/breakpoints).

---

## PD-BUG-001 — Imagens "quebradas": FALSO POSITIVO do harness de teste (nao e bug de produto)

Status: RESOLVED_TEST_HARNESS (nao era bug do tema/CMS/dado/catalogo)
Prioridade: P0 -> reclassificado apos investigacao PD3
Página: Home (`/`)
Componente: Product card (vitrine/carrossel) + Footer (selos de pagamento/logo) — ambos no teste, nao no produto
Viewport: desktop-1440 (1440x1000)
Estado: guest

### Investigacao PD3 (causa raiz real, nao assumida)

Seguindo a mesma disciplina da PD1/PD2 — nada foi assumido antes de provar:

1. **Checagem HTTP direta (curl) das 11 URLs**: todas retornam `200 OK`, `content-type`
   correto (`image/jpeg`/`image/png`) e tamanho de arquivo plausivel (2-20KB). Nenhuma
   404/403/erro de servidor. Isso descarta imediatamente: imagem ausente, permissao,
   cache corrompido, static-deploy quebrado, path errado.
2. **Reproducao com o helper exato (`findBrokenImages`) na mesma posicao do spec real**:
   as mesmas 11 URLs sao reportadas como "quebradas" de forma 100% reprodutivel — a
   investigacao nao descartou o achado, apenas a causa assumida original.
3. **Inspecao de runtime, item por item**:
   - **9 imagens do footer** (`logo_rodape`, `visa`, `mastercard`, `elo`, `amex`,
     `diners-club`, `boleto`, `pix`, `logo_bluu`): todas usam `loading="lazy"` nativo do
     navegador. No momento em que `findBrokenImages` roda (logo apos o load, antes de
     qualquer scroll), o navegador ainda nao iniciou o fetch (`complete:false`,
     `naturalWidth:0`) — nao e "quebrada", e "ainda nao carregada". Apos rolar a pagina
     ate o fim, TODAS carregam corretamente (`complete:true`, dimensoes corretas, ex.
     142x81, 80x57, 88x46).
   - **2+ imagens de produto em carrossel** (`10350_2.jpg`, `10401_2.jpg`, e mais
     encontradas apos scroll-through: `11996_1.jpg`, `11974_1.jpg`, `10188_2.jpg`,
     `11786_2.jpg`): todas dentro de slides `.awa-carousel-card-slot` "fora de palco"
     (bounding box `0x0` ate o carrossel ativa-las — posicionadas fora da viewport
     horizontalmente, ex. `x:1642` com viewport de `1440px`). Scroll vertical nao
     resolve (o carrossel e horizontal/controlado por JS). Confirmado com
     `scrollIntoViewIfNeeded()` individual: TODAS carregam corretamente
     (`complete:true`, `naturalWidth:600`, `naturalHeight:600`) quando efetivamente
     colocadas na viewport.

### Causa raiz confirmada
`findBrokenImages` nao distinguia "imagem que falhou ao carregar" de "imagem lazy que
ainda nao foi solicitada pelo navegador" nem de "clone de carrossel fora de palco com
bounding box 0x0". As 11 imagens sao 100% funcionais — o produto/tema/catalogo/CMS estao
corretos. **Este e um falso-positivo do harness de teste**, no mesmo padrao da PD1 (menu
vertical), nao um bug de produto.

### Correção aplicada (escopo local, sem tocar tema/CMS/catalogo/dado)
Arquivo: `tests/e2e/specs/product-design-qa.spec.ts` (unico arquivo alterado). O helper
compartilhado `tests/e2e/helpers/deep-audit.helpers.ts` (`findBrokenImages`) **NAO foi
alterado** — e usado por 6+ outros specs (`smoke/home.spec.ts`, `smoke/footer.spec.ts`,
`smoke/product.spec.ts`, `smoke/category.spec.ts`, `deep-visual/mobile.visual.spec.ts`,
`impeccable-visual-deep-audit.spec.ts`) fora do escopo desta branch.

Duas funcoes locais adicionadas, usadas apenas neste spec:
- `triggerLazyImages(page)`: faz um scroll-through vertical (topo -> fim -> topo, com
  `behavior:'instant'` e confirmacao de `window.scrollY` de volta a 0) antes de checar
  imagens quebradas, disparando o lazy-load nativo de imagens verticais (resolve o caso
  do footer).
- `findRealBrokenImages(page)`: mesma logica do helper original, mas exige
  **bounding box maior que 0x0** alem de `complete && naturalWidth===0` — uma imagem sem
  caixa de layout (clone de carrossel fora de palco, ou imagem lazy nunca solicitada) nao
  e classificada como "quebrada".

### Deploy
Nenhum — correcao e apenas no spec de teste, nao ha alteracao de fonte de tema/CMS para
publicar.

### Evidência — antes (falso-positivo reproduzido)
- `brokenImages` com as mesmas 11 URLs, 100% reprodutivel via `findBrokenImages` na
  posicao exata do spec.

### Evidência — depois (corrigido no teste)
- `product-design-qa.spec.ts` (rota `home`): `"brokenImages":[]` — nenhuma imagem
  reportada.
- `autocomplete.opened:true` (fix da PD2 preservado, sem regressao).
- Screenshot: `test-results/product-design-qa/desktop-1440__home-fullpage.png`.
- Regressao: `header-core-interactions-p0.spec.ts` (`diagnostico — home`) permanece
  passando (`1 passed`, exit code 0) apos a mudanca.

### Efeito colateral corrigido durante a implementacao
A primeira versao de `triggerLazyImages` deixou a posicao de scroll ligeiramente
diferente de 0 apos o scroll-through (provavel `scroll-behavior:smooth` do tema
interferindo em `scrollTo`), causando bounding boxes com Y negativo em checagens
subsequentes na mesma execucao. Corrigido usando `behavior:'instant'` explicito +
polling de confirmacao de `window.scrollY` antes de prosseguir. Confirmado: header bbox
volta a `{x:80, y:0, width:1280, height:156}` normalmente.

### Critério de aceite
- [x] Causa raiz identificada e confirmada com evidencia direta (nao assumida)
- [x] HTTP 200 confirmado via curl para as 11 URLs (antes de qualquer alteracao)
- [x] Renderizacao visual confirmada (`naturalWidth`/`naturalHeight` corretos quando em
      viewport)
- [x] `findRealBrokenImages` retorna `[]` na Home (desktop)
- [x] Playwright local passou (`product-design-qa.spec.ts` e `header-core-interactions-p0.spec.ts`)
- [x] Sem erro de console/rede novo
- [ ] Execução em GitHub Actions com artifact (pendente — não fechar como CLOSED sem isso)

---

## PD-BUG-002 — Radius do input de busca não segue a régua PD0

Status: REPRODUCED
Prioridade: P1
Página: Home (`/`)
Componente: Input (busca)
Viewport: desktop-1440
Estado: guest

### Problema
`getComputedStyle(...).borderRadius` do input de busca retornou `0px`. A régua PD0 define `@awa-radius-md` (8px) como padrão para inputs.

### Evidência
- JSON attach: `pd0-home.json` (campo `radius.input.borderRadius = "0px"`)
- Screenshot: `test-results/product-design-qa/desktop-1440__home-fullpage.png`

### Causa provável
CSS/LESS do bloco de busca do header (candidato: arquivo(s) que estilizam `#search_mini_form`/`input#search` no tema `AWA_Custom/ayo_home5_child`).

### Correção recomendada
Fase PD1 (Header): aplicar `border-radius: var(--awa-radius-md)` no input de busca, respeitando a cascata documentada em `.github` copilot-instructions (bundles `awa-bundle-core`).

### Critério de aceite
- [ ] `border-radius` computado = 8px em desktop e mobile
- [ ] Playwright
- [ ] Sem regressão visual no restante do header

---

## PD-BUG-003 — Menu vertical (Departamentos): FALSO POSITIVO do harness de teste (nao e bug de produto)

Status: RESOLVED_TEST_HARNESS (nao era bug do tema/produto)
Prioridade: P0 -> reclassificado apos investigacao PD1
Página: Home (`/`)
Componente: Menu vertical (teste, nao produto)
Viewport: desktop-1440
Estado: guest

### Investigacao PD1 (causa raiz real)

Reproduzido com 3 metodos independentes, na ordem:

1. Script Playwright ad-hoc fora do test runner (`node` puro): clique no trigger
   `[data-role="awa-vertical-menu-trigger"]` -> painel `.togge-menu.list-category-dropdown`
   recebeu `aria-expanded="true"`, classes `vmm-open menu-open`, `aria-hidden="false"`,
   `data-awa-menu-state="open"`, `display:flex`, `visibility:visible`, `opacity:1`,
   bbox `304x560`. Sem erros de console/rede.
2. Spec de diagnostico temporario dentro do test runner real (`--project=desktop-1440`):
   mesmo resultado — painel abre corretamente (`RAW_STATE` via `page.evaluate` confirma
   classes/estilos computados corretos).
3. Comparacao lado a lado NA MESMA execucao: estado real do DOM (`page.evaluate`) mostrava
   o painel aberto (`display:flex`, `304x560`) tanto antes quanto depois de chamar o helper
   `isVisible()` de `tests/e2e/helpers/header.helpers.ts` — mas o helper retornou `false`.
4. Isolamento final: `locator.isVisible()` (API nativa do Playwright) retornou `true`
   corretamente; `locator.waitFor({ state: 'visible', timeout: 3000 })` (usado internamente
   pelo helper `isVisible()`) **estourou o timeout** mesmo com o elemento genuinamente visivel.

### Causa raiz confirmada
O helper `isVisible()`/`getBBox()` em `tests/e2e/helpers/header.helpers.ts` usa
`locator.waitFor({ state: 'visible' })`, que se mostrou nao-confiavel para este painel
especifico: apos abrir, `awa-menu-controller.js` (`DeptMenu.prototype.schedulePanelHeight`)
reajusta `height`/`max-height` via `requestAnimationFrame` em ate 5 quadros consecutivos
para calcular a altura final do painel. Essa mutacao continua de estilo inline no elemento
parece impedir o `waitFor({state:'visible'})` do Playwright de resolver como visivel dentro
do timeout, enquanto a checagem instantanea `locator.isVisible()` (sem polling de
estabilidade) reflete o estado real corretamente.

**Conclusao: o menu vertical do produto funciona corretamente em producao.** O bug estava
no helper de teste criado na fase PD0, nao no tema/JS/CSS do Magento.

### Correção aplicada
Escopo: **apenas** `tests/e2e/specs/product-design-qa.spec.ts` (bloco de verificacao do
menu vertical na rota `home`). Nenhum arquivo de tema (`app/design/...`), template, LESS
ou JS do menu vertical foi alterado — nao havia defeito la.

Troca pontual de `isVisible(page, awaSelectors.verticalMenu.list)` /
`getBBox(page, awaSelectors.verticalMenu.list)` por chamada direta
`page.locator(awaSelectors.verticalMenu.list).first().isVisible()` /
`.boundingBox()`, que reflete o estado real sem depender do polling de estabilidade do
`waitFor`. O helper compartilhado `tests/e2e/helpers/header.helpers.ts` **nao foi
modificado** (fora de escopo desta branch — pode ter o mesmo efeito em outras verificacoes
dinamicas, como autocomplete/PD-BUG-004, mas isso fica para validacao em fase futura,
conforme instrucao explicita de nao corrigir autocomplete nesta branch).

### Evidência — antes (PD0, com o bug do harness)
- JSON: `verticalMenu = {"visible":false,"bbox":null,"withinViewport":null}` (execucao PD0,
  `desktop-1440__home-fullpage.png`)
- Screenshot dedicado do painel aberto: nao foi gerado no PD0 (evidencia do proprio bug de
  harness).

### Evidência — depois (PD1, apos a correção do spec)
- JSON: `verticalMenu = {"visible":true,"bbox":{"x":103,"y":152,"width":304,"height":560},"withinViewport":true}`
- Screenshot: `test-results/product-design-qa/desktop-1440__menu-vertical-aberto.png`
  (gerado com sucesso, ~1MB, mostra o painel de departamentos aberto)
- Playwright: `design QA — home` passou (`1 passed`, exit code 0, `--workers=1`,
  `--project=desktop-1440`)
- Regressao: `header-core-interactions-p0.spec.ts` (`diagnostico — home`) tambem passou
  (`1 passed`, exit code 0) apos a mudanca — nao ha impacto no spec de header, que nao foi
  alterado.

### Critério de aceite
- [x] Menu vertical abre e é detectado como visível pelo spec (corrigido no harness)
- [x] Screenshot `menu-vertical-aberto` gerado com sucesso
- [x] Playwright local passou (`product-design-qa.spec.ts` e `header-core-interactions-p0.spec.ts`)
- [x] Sem erro console/rede novo
- [ ] Execução em GitHub Actions com artifact (pendente — não fechar como CLOSED sem isso)

---

## PD-BUG-004 — Autocomplete: race condition real no bootstrap lazy-load (Mirasvit)

Status: FIXED_SOURCE (bug real de produto, corrigido)
Prioridade: P0 (reclassificado de P1 apos confirmar impacto real no usuario)
Página: Home (`/`)
Componente: Busca / Autocomplete (Mirasvit_SearchAutocomplete)
Viewport: desktop-1440
Estado: guest

### Investigacao PD2 (causa raiz real, nao assumida)

Reproduzido com metodos independentes, seguindo a mesma disciplina da PD1:

1. Script Playwright puro (clique + digitacao imediata, sem espera): **nenhuma requisicao
   de rede disparada** para `/search/ajax/suggest` nem endpoints Mirasvit. Painel permanece
   fechado (`display:none`, 0 filhos).
2. Mesmo script, mas com **3 segundos de espera entre o clique e a digitacao**: a cadeia
   completa dispara (`awa-mirasvit-autocomplete-init.min.js` -> fetch de
   `mirasvit-ac-templates.pt_BR.html` -> require de `Mirasvit_SearchAutocomplete/js/autocomplete`
   e `.../typeahead` -> AJAX `search/ajax/suggest` + `searchautocomplete/ajax/typeahead` ->
   painel abre com classes `is-open active has-results`). Confirma que o **backend/endpoint
   funciona corretamente** quando o frontend tem tempo de inicializar.
3. Inspecao do codigo-fonte (`awa-mirasvit-autocomplete-init.js`): na Home
   (`cfg.isHomePage`), o bootstrap do autocomplete e **deferido por design** (otimizacao de
   performance) ate o primeiro sinal de intencao de busca (`focusin`/`pointerdown`/`touchstart`
   no `#search_mini_form`). O bootstrap entao faz **ate 4 requisicoes de rede sequenciais**
   (JS de init -> HTML de templates -> 2 modulos JS do Mirasvit) antes de o componente de
   autocomplete estar pronto para reagir a digitacao. O tema tambem desabilita
   intencionalmente o `quickSearch` nativo do Magento
   (`$('#search_mini_form').prop('minSearchLength', 10000)`), entao **nao ha nenhum
   fallback** enquanto o Mirasvit ainda esta carregando — se o usuario digitar antes do
   bootstrap terminar, as teclas sao perdidas silenciosamente, sem nenhum indicador visual
   de carregamento.

### Causa raiz confirmada
Race condition real no tema: `bootstrap()` (deferido, disparado por intencao de foco) nao
tinha nenhum mecanismo de "replay" do valor ja digitado apos terminar de inicializar. Um
usuario real que comeca a digitar logo apos clicar no campo de busca da Home (comportamento
comum) pode nunca ver sugestoes, sem qualquer feedback de que a busca esta carregando.
**Este e um bug real de produto**, nao um falso-negativo do harness de teste (diferente da
PD1).

### Correção aplicada (fonte canonica do tema, sem tocar vendor/)
Arquivo: `app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-mirasvit-autocomplete-init.js`
(+ `.min.js` regerado com `terser -c -m`, mesma ferramenta ja usada no projeto —
`scripts/tier1_js_minification.sh`).

Adicionada funcao `replayPendingQuery($searchInput)`: apos o componente Mirasvit
(`InPage`/`autocomplete`/`typeahead`) terminar de inicializar, se o input ja tiver uma
query com 2+ caracteres, dispara um evento `input`/`keyup` sintetico no proprio elemento
para que o componente (ja inicializado) processe o valor pendente — sem chamar nenhuma API
interna do modulo Mirasvit (que e vendor), apenas simulando o mesmo evento DOM que o usuario
geraria ao continuar digitando.

Nao foi alterado nenhum arquivo em `vendor/`, nem Luma/Blank, nem `pub/static`/
`var/view_preprocessed` como fonte (apenas republicados via `setup:static-content:deploy`
apos a mudanca real na fonte do tema).

### Deploy realizado
- `terser` para regerar `.min.js` a partir da fonte corrigida.
- `bin/magento setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child`.
- Sincronizacao manual do `.min.js` em `pub/static` (mesmo padrao ja usado no projeto para
  bundles CSS) + regeneracao de `.br`/`.gz`.
- `cache:flush`, `redis FLUSHDB` (DB1 cache + DB2 FPC), `PURGE` no Varnish (full page +
  asset estatico especifico), `systemctl restart nginx` (limpar `open_file_cache`).
- Confirmado via `curl` direto no asset publico que o novo conteudo esta servido.

### Evidência — antes (bug reproduzido)
- `NET_LOG: []` (nenhuma requisicao de suggest/typeahead disparada com digitacao imediata)
- Painel: `display:none`, `childCount:0` mesmo apos 6+ segundos de espera.

### Evidência — depois (corrigido)
- Com clique + digitacao imediata (mesmo cenario que falhava antes): painel abre com
  classes `is-open active has-results` e conteudo real de resultado. **Correcao de
  registro (ver PD4):** a mensagem `<div class="no-result">Nenhum resultado
  encontrado.</div>` capturada em uma das evidencias vinha do painel nativo vestigial
  `#search_autocomplete` (alimentado pelo endpoint `search/ajax/suggest`, que retorna
  `[]` porque o `quickSearch` nativo esta intencionalmente desabilitado via
  `minSearchLength: 10000`). O painel real do Mirasvit
  (`.mst-searchautocomplete__autocomplete`) mostrou corretamente 273 produtos para
  "bagageiro" — ver PD4 para a investigacao completa que corrigiu esse registro.
  Requisicao `search/ajax/suggest?q=bagageiro` -> `200` (endpoint nativo, resposta `[]`
  esperada por design).
- Via Playwright real (`product-design-qa.spec.ts`, rota `home`): `autocomplete.opened: true`
  (spec ajustado de wait fixo de 600ms para polling de ate 6s, refletindo a latencia real
  medida de ate ~2.6-3.5s da cadeia de bootstrap — ver nota tecnica no proprio spec).
- Regressao: `header-core-interactions-p0.spec.ts` (`diagnostico — home`) permanece
  passando (`1 passed`, exit code 0) apos a mudanca de JS.

### Achado secundario (nao corrigido nesta fase, fora de escopo PD2)
- ~~Query de teste "bagageiro" retornou "Nenhum resultado encontrado"~~ — **investigado
  e descartado na PD4**: era leitura do painel nativo vestigial, nao do Mirasvit real.
  Sem bug de indexacao/catalogo. Ver `PD-BUG-005` abaixo.
- `verticalMenu.bbox` retornou `null` (com `visible:true`) em uma das execucoes desta
  sessao — pode ser timing residual da mesma familia de problema do PD1, mas nao foi
  investigado nesta branch (regra explicita: nao mexer em outros bugs).

### Reconfirmacao — PD2-recheck (3 termos, 3 metodos independentes, 18 pontos de diagnostico)

Nova rodada de verificacao rigorosa (sem assumir a causa ja registrada), usando os termos
`bagageiro`, `bauleto`, `retrovisor` e tres metodos independentes por termo:

- **Metodo A — DOM/runtime direto** (`page.evaluate`): `formExists`, `inputExists`,
  `inputValue`, `activeElement`, `dropdownCandidates`, `computedStyles`, `boundingBoxes`,
  `bodyOverflowX`.
- **Metodo B — Locators Playwright diretos** (`isVisible()`, `boundingBox()`, `count()`,
  sem depender de `waitFor({state:'visible'})` compartilhado).
- **Metodo C — Network** (`page.on('request'|'response'|'requestfailed')`), confirmando
  chamadas reais aos endpoints de sugestao/busca.

**Resultado desktop-1440 (3/3 termos, dois runs independentes):**

| Termo | Dropdown Mirasvit abre | Enter navega para |
|---|---|---|
| bagageiro | `true` | `/bagageiros.html` |
| bauleto | `true` | `/bauletos.html` |
| retrovisor | `true` | `/retrovisores.html` |

Zero requisicoes com 4xx/5xx, zero console errors, zero page errors em todas as execucoes
desktop bem-sucedidas. Reconfirmado tambem pelo harness oficial
(`product-design-qa.spec.ts`, rota `home`): `autocomplete.opened: true`.

**Classificacao da causa raiz (taxonomia A-J solicitada): `A. Falso negativo do teste`** —
ja corrigido na fonte real na PD2 (`replayPendingQuery`); esta rodada apenas reconfirma
com evidencia fresca e mais termos que o autocomplete funciona corretamente em producao
para buscas reais.

### Novo achado — crash reprodutivel do Chromium headless ao digitar em viewport mobile (390x844)

Durante a tentativa de validar o mesmo fluxo em mobile (`390x844`, emulacao `devices['iPhone 14']`
com `isMobile`/`hasTouch`), o processo do navegador Chromium fecha/crasha de forma consistente
e reprodutivel especificamente ao digitar no campo de busca — nao ao focar, nao ao clicar/tocar.

Isolado com 6 experimentos independentes:

1. Controle (`example.com`, mesmo viewport 390x844) — sem crash.
2. Apenas `focus()` no input (sem clique, sem digitacao) — sem crash.
3. Apenas `click()`/`tap()` no input (sem digitacao) — sem crash.
4. `click()` + 1 caractere digitado — **crash** (`Target page, context or browser has been closed`).
5. Emulacao de dispositivo correta (`devices['iPhone 14']`, `tap()` + `keyboard.type()`) — **crash** (mesmo padrao, descarta erro de configuracao do script).
6. `reducedMotion: 'reduce'` (para descartar transicoes/animacoes CSS pesadas) — **crash** persiste.

Nenhum `console error`/`pageerror` foi capturado antes de qualquer um dos crashes (consistente
com um crash no nivel do processo do navegador, que derruba a conexao CDP antes de qualquer
mensagem JS poder ser relayada). Nenhum crash dump foi localizado em `/tmp` ou `dmesg`. Um
evento adicional (nao reprodutivel isoladamente) de fechamento tambem ocorreu no desktop na
3a chamada consecutiva de `chromium.launch()` no mesmo processo Node, mas isso nao se repetiu
em retry isolado — indicio de flakiness generica de lancamento repetido, distinto do crash
mobile (esse sim 100% reprodutivel em 3 tentativas separadas).

**Este achado nao se encaixa em nenhuma das classificacoes A-J solicitadas** (nao e falso
negativo do teste, nao e endpoint com erro, nao e CSS escondendo markup — e um crash do
processo do navegador headless). Registrado como um novo item de backlog (`PD-BUG-006`,
abaixo) e como pendencia explicita: **mobile foi testado, mas o resultado e um bloqueio de
harness, nao uma validacao limpa** — consistente com o criterio de aceite que permite
"mobile testado ou registrado como pendencia".

### Critério de aceite
- [x] Causa raiz identificada e confirmada com evidencia direta (nao assumida)
- [x] Correção aplicada na fonte canonica do tema (nao em `vendor/`, `pub/static` ou
      `var/view_preprocessed` como fonte)
- [x] Screenshot depois (`autocomplete-aberto` gerado com painel de resultados reais)
- [x] Playwright local passou (`product-design-qa.spec.ts` e `header-core-interactions-p0.spec.ts`)
- [x] Sem erro de console/rede novo
- [ ] Execução em GitHub Actions com artifact (pendente — não fechar como CLOSED sem isso)

---

## PD-BUG-005 — `.b2b-btn-entrar` não encontrado na Home (guest)

Status: REPRODUCED
Prioridade: P2
Página: Home (`/`)
Componente: Button (CTA B2B)
Viewport: desktop-1440
Estado: guest

### Problema
O seletor `.b2b-btn-entrar` (usado para checagem de altura mínima de botão) não retornou bounding box na Home para usuário guest — esperado, pois esse botão pertence à tela de login B2B, não à Home. **Ajuste necessário no spec** (não é bug de produto, é falso-positivo do PD0): remover `.b2b-btn-entrar` da checagem de altura mínima da Home e mantê-lo apenas nas rotas `b2b-login`/`b2b-register`.

### Evidência
- JSON attach: `pd0-home.json` (campo `buttonHeights[".b2b-btn-entrar"].bbox = null`)

### Causa provável
Falso-positivo de escopo do spec, não do produto.

### Correção recomendada
Ajustar `product-design-qa.spec.ts` (fase de refinamento do próprio PD0, sem impacto em código da loja) para restringir a checagem por rota.

### Critério de aceite
- [ ] Spec não reporta falso-positivo para seletores fora de escopo da rota

---

## PD-BUG-005 — Investigação PD4: busca "bagageiro" NAO tem gap de indexação (achado da PD2 corrigido)

Status: NOT_A_BUG (investigado e descartado)
Prioridade: n/a
Página: Home (`/`) + `/catalogsearch/result/?q=bagageiro`
Componente: Busca / Autocomplete / Resultado de busca
Viewport: desktop-1440
Estado: guest

### Contexto
Durante a PD2, uma das evidências de autocomplete mostrou
`<div class="no-result">Nenhum resultado encontrado.</div>` para a query "bagageiro",
registrado como achado secundário não investigado ("possível gap de indexação").

### Investigação PD4 (causa raiz real, não assumida)

1. **Endpoint nativo Magento** (`GET /search/ajax/suggest?q=bagageiro`): retorna `[]`
   (array vazio). Isso é **esperado por design** — o `quickSearch` nativo está
   intencionalmente desabilitado via `$('#search_mini_form').prop('minSearchLength', 10000)`
   em `awa-mirasvit-autocomplete-init.js` (ver PD2). O painel visual `#search_autocomplete`
   ainda existe no DOM (vestigial) e mostra "Nenhum resultado encontrado" quando alimentado
   por esse endpoint — foi ESSE painel vestigial que a evidência da PD2 capturou, não o
   autocomplete real usado pelos usuários.
2. **Endpoint real Mirasvit** (`GET /searchautocomplete/ajax/suggest/?q=bagageiro&store_id=1&...`):
   retorna `totalItems: 319`, com `magento_catalog_product.totalItems: 273` — 273 produtos
   reais, incluindo `"BAGAGEIRO CB 300 2009/2015 - CROMADO - ( MACIÇO )"` (SKU 3011).
3. **Página de resultado completa** (`/catalogsearch/result/?q=bagageiro`, verificado via
   Playwright real): **redireciona automaticamente para `/bagageiros.html`** (categoria
   "Bagageiros"), mostrando `"Itens 1-12 de 22"` — 22 produtos. Esse é um comportamento
   legítimo de "busca-para-categoria" (search-to-category redirect), uma funcionalidade
   comum de e-commerce quando a query corresponde fortemente a uma categoria existente —
   não é um bug, é uma melhoria de UX (evita uma página de resultado genérica quando a
   categoria já responde exatamente à intenção de busca).

### Causa raiz confirmada
**Não há bug de indexação, catálogo ou busca.** O registro original na PD2 foi uma leitura
equivocada de qual painel de autocomplete estava sendo inspecionado (o vestigial, não o
real). A busca por "bagageiro" funciona corretamente em todos os níveis: autocomplete
(273 resultados), busca completa com redirect inteligente para categoria (22 itens).

### Correção aplicada
Nenhuma — não há defeito de produto, tema, dado ou teste a corrigir. Apenas correção do
registro/evidência anterior em `PD-BUG-004` (ver acima) e documentação desta investigação
para fechar o item do backlog de forma rastreável.

### Evidência
- `curl https://awamotos.com/searchautocomplete/ajax/suggest/?q=bagageiro&store_id=1&currency=BRL&customer_group_id=0`
  → `totalItems: 319`, `magento_catalog_product.totalItems: 273`.
- Playwright real em `/catalogsearch/result/?q=bagageiro` → `location.href` resolve para
  `/bagageiros.html`, `toolbar-amount` = `"Itens 1-12 de 22"`.

### Critério de aceite
- [x] Causa raiz identificada e confirmada com evidência direta (não assumida)
- [x] Nenhuma alteração de código necessária (confirmado que não é bug)
- [x] Registro anterior (PD2/PD-BUG-004) corrigido com a explicação real
- [ ] Execução em GitHub Actions com artifact (não aplicável — não há mudança de código)

---

## PD-BUG-006 — Bootstrap de busca bloqueia o main thread por 5-11s+ (crash em mobile, degradacao severa em desktop)

Status: REPRODUCED (causa raiz confirmada — sem correcao aplicada nesta fase)
Prioridade: P1 (bloqueia validacao visual do autocomplete em mobile; degradacao de performance real tambem em desktop)
Pagina: Home (`/`)
Componente: Busca / Autocomplete (bootstrap deferido "search intent" do tema AWA_Custom)
Viewport: mobile (390x844, 360x740, 430x932 — todos com `hasTouch`/`isMobile`) + desktop (1440x900, controle)
Estado: guest

### Investigacao PD5 (branch `investigate/pd5-mobile-search-headless-crash`)

Fase dedicada de bissecao para isolar a causa exata do crash registrado no PD2-recheck.
Uma variavel por experimento, resultado de cada um registrado abaixo.

**Confirmacao do gatilho exato:**

| # | Experimento | Resultado |
|---|---|---|
| 1 | Baseline (tap + digitar em `input#search`, mobile 390x844) | Crash reconfirmado, sempre na digitacao |
| 2 | Digitar em input DIFERENTE na mesma Home (`#newsletter`, footer), sem nunca tocar o search | **Sem crash** — descarta "qualquer digitacao mobile crasha" |
| 3 | Bloquear TODO o JS (`page.route` abort `*.js`) | **Sem crash** — confirma que a causa e JS, nao CSS/rendering puro |
| 4 | Bloquear TODO o JS de `AWA_Custom/*.js` (mantendo Magento core + Mirasvit vendor) | **Sem crash** — confirma que a causa esta no tema (AWA_Custom), nao no core/vendor |
| 5 | Bloquear so `awa-mirasvit-autocomplete-init.js` | Ainda crasha — nao e o unico/direto culpado |
| 6 | Bloquear so `awa-search-autocomplete-compat.js` | Ainda crasha — nao e o unico/direto culpado |
| 7 | Bloquear so `awa-header-a11y-performance.js` | Ainda crasha — nao e o unico/direto culpado |
| 8 | Diff de requests "antes vs depois do tap" no search | Identificado grupo de **14 scripts AWA_Custom carregados so apos o toque** no campo de busca (bootstrap "search intent" generico, dispara para qualquer interacao, nao exclusivo do search) |
| 9 | Tap no search + espera PURA de 26s (sem nenhuma chamada CDP durante a espera) | **Sem crash** — a pagina sobrevive sozinha; o problema so aparece quando um comando CDP (evaluate/type) e enviado |
| 10 | Tap no search + polling de `page.evaluate()` a cada 300ms (mobile) | Primeira chamada `evaluate()` leva **22.4s** antes de retornar `"Target page, context or browser has been closed"` |
| 11 | Mesmo polling de `page.evaluate()`, porem no **desktop** (click de mouse, sem touch) | **Sem crash** — mas RTTs de **5.9s, 2.4s e 2.3s** nas primeiras chamadas (main thread bloqueado por varios segundos), estabilizando para <15ms depois de ~13s |
| 12 | Repeticao em 430x932 (mobile, touch) | Mesmo crash reproduzido |

### Causa raiz confirmada

O foco/toque no campo de busca (`#search`, dentro de `#search_mini_form`) dispara um bootstrap
generico de "intencao de interacao" (`awa-home-bootstrap-defer.js`, eventos
`pointerdown`/`keydown`/`touchstart`) que carrega e executa **~14+ scripts do tema AWA_Custom
de forma concentrada** (`awa-header-a11y-performance`, `awa-header-runtime-bootstrap`,
`awa-scroll-reveal`, `awa-card-enhance`, `awa-qty-control`, `awa-ux-enhancements`,
`awa-customer-sections-bootstrap`, `awa-home-deferred-widgets-bootstrap`,
`awa-header-minicart-ui-v2`, `awa-css-gate`, `cookie-consent`, `google-analytics`, `awa-toast`,
`awa-messages-interceptor`), além do bootstrap do Mirasvit (`awa-mirasvit-autocomplete-init.js`
+ `Mirasvit_SearchAutocomplete/js/*`, já mapeado na PD2).

Essa execução concentrada bloqueia o main thread do navegador por um período mensurável e
real: **confirmado tambem no desktop** (RTTs de `page.evaluate()` de 5.9s, 2.4s e 2.3s nos
primeiros ~13s apos o clique, antes de estabilizar) — ou seja, **não é um bug exclusivo do
Chromium headless nem do viewport mobile**: é um problema real de performance no bootstrap de
"intenção de busca" do tema, que bloqueia o main thread em qualquer contexto.

O que **difere entre desktop e mobile** é a tolerância do Chromium/CDP a esse bloqueio:
- **Desktop** (clique de mouse, sem touch): Chromium tolera o main thread ocupado por vários
  segundos e o `page.evaluate()` eventualmente retorna com sucesso — degradação de
  performance real, mas sem crash.
- **Mobile** (`tap()`/toque, `hasTouch`/`isMobile`): o dispatch de eventos de toque via CDP
  (`Input.dispatchTouchEvent` — usado tanto pelo `tap()` quanto pelo `keyboard.type()` em
  contexto de touch) parece ter uma tolerância bem menor a um main thread ocupado, e a sessão
  CDP/renderer é encerrada ("Target page, context or browser has been closed") em vez de
  aguardar/recuperar — confirmado que isso só ocorre quando um comando CDP é enviado durante a
  janela de bloqueio (espera pura de 26s sem nenhuma chamada CDP NÃO crasha).

**Classificação (múltiplas categorias, não é uma causa única):**
- ✅ **Bug real de produto/performance**: SIM — o bootstrap de "intenção de busca" executa
  um volume de JS síncrono grande demais em um único burst, bloqueando o main thread por
  segundos mensuráveis mesmo no desktop.
- ✅ **Limitação/comportamento do Chromium headless em touch**: SIM — a mesma lentidão que o
  desktop tolera graciosamente resulta em encerramento da sessão CDP quando o alvo usa
  dispatch de touch, especificamente neste ambiente headless.
- ❌ **Bug do harness Playwright**: NÃO — reproduzido de forma consistente com causa
  identificada; não é falso-positivo/negativo do teste.
- ⚠️ **Ambiente da VPS**: possível fator agravante (CPU compartilhada pode alongar o tempo de
  bloqueio), mas não é a causa raiz — o padrão de bloqueio do main thread é real e
  reproduzível independente da carga momentânea da máquina.

### Correção aplicada
Nenhuma nesta fase (escopo da PD5 é diagnóstico, não correção). A causa raiz agora está
identificada com precisão suficiente para uma correção futura dedicada (candidata a **PD6**):
reduzir/escalonar o número de scripts carregados sincronamente no bootstrap de "intenção de
busca"/interação (`awa-home-bootstrap-defer.js` e o grupo de 14 scripts identificado), ou
adiar ainda mais scripts não críticos para depois do primeiro paint útil do autocomplete.

### Evidência
- Scripts de diagnóstico temporários em `tests/e2e/tmp/` (git-ignored, não commitados):
  `pd5-experiments.mjs` (bateria completa com 10 experimentos parametrizados),
  `pd5-diff-before-after-tap.mjs`, `pd5-responsiveness-poll.mjs`,
  `pd5-tap-then-pure-wait.mjs`, `pd5-desktop-responsiveness.mjs`,
  `pd5-block-mirasvit-init-only.mjs`, `pd5-block-compat-only.mjs`, `pd5-block-a11y-fixed.mjs`.
- `test-results/product-design-qa/pd5-experiments.json` — resultado estruturado de cada
  experimento.
- Logs brutos das 12 execuções (RTTs e timestamps) documentados na tabela acima.

### Atualizacao — matriz formal PD5 (31 experimentos, grupos A-G)

Uma segunda rodada, mais formal e exaustiva (matriz de hipoteses H1-H10, 31 experimentos nos
grupos A/B/C/D/F/G, JSON incremental, browser novo por experimento), foi executada na mesma
branch. Resultado consolidado — ver detalhes completos em
`docs/product-design/PD5_MOBILE_SEARCH_HEADLESS_CRASH_INVESTIGATION.md`:

- **Taxa de crash quantificada**: 4 em 19 execucoes Chromium mobile+touch com JS ativo (~21%)
  — confirma **nao-determinismo** (a mesma configuracao ora crasha, ora so fica lenta).
- **WebKit (3 execucoes, mesma sequencia exata)**: **0 crashes**. Evidencia mais decisiva desta
  fase — o crash e especifico do **Chromium**, nao do site, nao do harness em geral, nao da VPS.
- **Sem `hasTouch`/`isMobile`** (viewport estreito, mas sem emulacao de toque): **0 crashes**
  nas execucoes desta matriz — reforca que o vetor e o dispatch de eventos de toque via CDP,
  nao apenas a largura do viewport.
- **Bloquear JS inteiro, todo `AWA_Custom/*.js`, ou o grupo completo dos 14 scripts**: **0
  crashes em 3/3** execucoes limpas — confirma de forma reprodutivel que o volume agregado de
  JS do bootstrap de intencao de busca e o fator desencadeante real.
- **Bloquear arquivos individuais** (mirasvit-init, header-a11y-performance, etc.): resultado
  MISTO entre "lento" e "crash" ao longo das duas rodadas — nenhum arquivo isolado e
  determinístico.
- Grupos C (AJAX), G (terceiros/service worker/cache) e a maioria das flags de GPU do Grupo B:
  **rejeitados como causa raiz isolada** — nenhum eliminou o crash de forma confiavel.

**Classificacao final (taxonomia Caso 1-5 solicitada nesta rodada): Caso 2 — bug/limitacao do
Chromium headless confirmado**, com fator agravante real de produto (o bootstrap de JS que cria
a janela de bloqueio do main thread que torna a corrida de tempo do Chromium provavel de
acontecer). Nao e Caso 1 puro (nenhum arquivo unico e a causa determinística), nao e Caso 3
puro (nao e um bug generico do harness Playwright — o WebKit funciona perfeitamente), nao e
Caso 4 puro (o mesmo padrao de lentidao do main thread tambem ocorre no desktop).

Workaround recomendado: validar autocomplete mobile via WebKit em vez de Chromium nos specs que
precisam de digitacao real; ou usar `fill()` em vez de `pressSequentially`/`keyboard.type()`
quando o Chromium for exigido (nao crashou nos testes, embora precise de mais repeticoes para
confirmacao estatistica). Job `pd5-mobile-search-crash-investigation` adicionado a
`.github/workflows/product-design-qa.yml` (somente `workflow_dispatch`, scaffold pronto mas
ainda nao executavel em CI ate o script ser promovido de `tests/e2e/tmp/` para um local
versionado — limitacao registrada explicitamente no proprio workflow).

### Próximo passo recomendado
Fase dedicada de correção (candidata a **PD6 — Search Intent Bootstrap Performance Fix**):
1. Auditar `awa-home-bootstrap-defer.js` e reduzir o número de scripts que disparam no mesmo
   evento de intenção de busca (separar "intenção de busca" de "intenção de interação geral").
2. Medir o tempo de bloqueio do main thread antes/depois com Chrome DevTools Performance
   trace (não apenas RTT de CDP) para quantificar o ganho real.
3. Re-rodar esta mesma bateria de 12 experimentos da PD5 após a correção para confirmar que o
   `page.evaluate()` no mobile responde em <1s após o toque, sem qualquer encerramento de
   sessão.

### Critério de aceite
- [x] Causa raiz identificada e confirmada com evidência direta (não assumida) — bootstrap de
      intenção de busca bloqueia o main thread por segundos, em qualquer viewport
- [x] Uma variável por experimento, resultado de cada um registrado (12 experimentos)
- [x] Distinguido: bug real de produto (performance) + comportamento do Chromium headless em
      touch; descartado harness de teste e ambiente da VPS como causa raiz isolada
- [ ] Correção aplicada — pendente, requer fase dedicada (PD6)
- [ ] Execução em GitHub Actions com artifact (pendente — não fechar como CLOSED sem isso)

---

## PD6 — Search Intent Bootstrap Performance Fix (correcao aplicada)

Status: TESTED_LOCAL (correcao real aplicada e testada localmente; nao elimina 100% do
crash — ver classificacao Caso 2 da PD5)
Prioridade: high
Pagina: Home (`/`)
Componente: Bootstrap de intencao de busca (`awa-custom-js-loader.phtml`)
Branch: `fix/pd6-search-intent-bootstrap-performance`

Documentacao completa (mecanismo real, correcao, deploy, medicao antes/depois):
`docs/product-design/PD6_SEARCH_INTENT_BOOTSTRAP_PERFORMANCE_FIX.md`.

### Resumo

Investigacao encontrou que o arquivo originalmente suspeito (`awa-home-bootstrap-defer.js`,
ja com uma correcao pre-existente nao commitada) esta **inativo em producao** — o plugin
responsavel por injeta-lo (`DeferHomeScriptsPlugin`) depende de um padrao de bundle merge do
Magento (`dev/js/merge_files`) que esta desabilitado neste ambiente, entao a injecao nunca
dispara. O mecanismo REAL identificado foi `awa-custom-js-loader.phtml` — o orquestrador
central de scripts comportamentais, onde 2 scripts escopados para busca
(`awa-header-minicart-ui-v2.js`, `awa-header-a11y-performance.min.js`) mais 3 blocos
genericos da Home (`awaCustomCompatBootstrap`, `awa-toast`/`awa-messages-interceptor`,
`awa-scroll-reveal`) todos escutam os mesmos eventos (`pointerdown`/`touchstart`/`keydown`)
no `document` e executam `appendScript()`/`require()` **sincronamente no mesmo tick** do
evento que os disparou.

### Correcao

Adiado apenas o trabalho pesado (`appendScript()`/`require()`) via `requestIdleCallback`
(fallback `setTimeout(fn, 0)`) em todos os 4 pontos afetados, mantendo `done`/`cleanup`
imediatos (nenhuma mudanca em QUANDO o script "vai" carregar, so QUANDO o parser/executor
roda). Nenhum script removido, nenhum comportamento desabilitado.

### Resultado medido

| Momento | Amostras | Crashes | Taxa |
|---|---|---|---|
| PD5 baseline | 19 | 4 | ~21% |
| PD6 pos-correcao (2 rodadas de 10) | 20 | 2 | 10% |

Reducao real de ~21% para ~10% (redução relativa de ~50%), mas **nao elimina o crash** —
consistente com a classificacao Caso 2 da PD5 (limitacao do Chromium headless em touch sob
main thread ocupado; reduzir volume de JS reduz a probabilidade da corrida de tempo, nao
elimina a fragilidade do proprio Chromium).

### Validacao funcional

Autocomplete desktop (dropdown Mirasvit abre), minicart (painel abre), menu vertical (abre) —
todos confirmados funcionando via Playwright real em producao apos o deploy. Zero console
errors, zero page errors, sem novas entradas em exception.log/system.log.

### Arquivos alterados

- `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-custom-js-loader.phtml`
  (correcao principal)
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-home-bootstrap-defer.js` + `.min.js`
  (mantido, confirmado inativo)
- `app/code/GrupoAwamotos/Theme/Plugin/Response/DeferHomeScriptsPlugin.php` (version bump,
  mantido)

### Criterio de aceite

- [x] Causa raiz do mecanismo REAL identificada (nao o arquivo originalmente assumido)
- [x] Correcao aplicada na fonte canonica (app/design, app/code)
- [x] Autocomplete Mirasvit continua funcionando (nao desabilitado)
- [x] Comportamento desktop preservado (validado via smoke test)
- [x] Taxa de crash reduzida e medida (nao apenas assumida)
- [ ] Taxa de crash NAO chegou a 0% — meta declarada da PD6 nao totalmente atingida
- [ ] Execucao em GitHub Actions com artifact (pendente — nao fechar como CLOSED sem isso)

---

## Pendências registradas (não bloqueiam PD0)

1. **Breakpoints sem projeto Playwright exato:** `430x932` e `360x740` não têm projeto dedicado em `playwright.config.ts` hoje (mais próximos: `mobile-390` e `mobile-375`). Recomenda-se avaliar a criação de 2 novos projetos em fase futura (fora do escopo do PD0, que só documenta a régua).
2. **Discovery global do Playwright:** `npx playwright test --list` na raiz do repo retorna `0 tests` devido a erros de outros specs do suite atual (não relacionados a este PD0) — ver `docs/visual-qa/header-core-interactions-p0-report.md`. Execução direta por arquivo (`specs/product-design-qa.spec.ts`) funciona normalmente.
3. **Captura de evidência intermitente:** os screenshots `menu-vertical-aberto` e `autocomplete-aberto` não foram gerados no smoke desta sessão, apesar dos triggers estarem visíveis. Investigar em fase de correção (pode indicar necessidade de aguardar mais tempo ou tratar exceções silenciosas no próprio spec).
4. **PLP/PDP/B2B/Cart/Footer:** ainda não auditados nesta sessão (apenas a rota `home` foi executada como smoke mínimo, por instrução explícita de não rodar a matriz completa ainda). Rodar as demais rotas é o próximo passo natural, mantendo `--workers=1`.

## Rastreabilidade

- Régua de tokens/componentes: [`docs/product-design/AWA_PRODUCT_DESIGN_SYSTEM.md`](./AWA_PRODUCT_DESIGN_SYSTEM.md)
- Checklist: [`docs/product-design/PRODUCT_DESIGN_QA_CHECKLIST.md`](./PRODUCT_DESIGN_QA_CHECKLIST.md)
- Spec Playwright: [`tests/e2e/specs/product-design-qa.spec.ts`](../../tests/e2e/specs/product-design-qa.spec.ts)
- Evidências: `tests/e2e/test-results/product-design-qa/`


---

## PD6B — Mobile Search QA Stabilization & CI Policy

Status: IN_PROGRESS (implementado em branch de teste, pendente rodada completa em CI com artifact)
Prioridade: P0 (confiabilidade de validação e governança de merge)

### O que foi implementado

- Novo spec leve e dedicado: `tests/e2e/specs/pd6b-mobile-search-ci.spec.ts`
  - escopo: apenas Home + abertura do autocomplete Mirasvit em mobile;
  - evidência: screenshots + JSON dedicado;
  - assert objetivo de abertura (`opened: true`).
- Política CI formalizada (modo transicional na rodada atual):
  - `mobile-390` (WebKit) como **stabilization non-gating**;
  - `mobile-390-chromium` como **observabilidade não bloqueante**.

### Racional técnico

A investigação anterior mostrou que `mobile-390` já executava WebKit (não Chromium), e que parte relevante das falhas observadas era timeout/instabilidade de harness em cenário pesado. O PD6B separa validação funcional estável (gating) de telemetria do engine mais frágil (non-gating), preservando qualidade sem travar PR por ruído de infraestrutura/headless.

### Critério de aceite PD6B

- [x] Política documentada em `PD6B_MOBILE_SEARCH_QA_POLICY.md`
- [x] Spec mobile dedicado implementado
- [x] Execução Chromium marcada como observabilidade não bloqueante
- [ ] Rodada em GitHub Actions com artifacts desta fase
- [ ] Reclassificação dos itens legados (`PD2-002`, `PD5-001`, `PD6-001`) após evidência de CI


### Ajuste PD6B (2026-07-10, rodada atual)

- Spec dedicado `pd6b-mobile-search-ci.spec.ts` criado e validado em desktop (`1 passed`).
- Execuções mobile locais (`mobile-390` WebKit e `mobile-390-chromium`) encerraram com `Killed` nesta VPS.
- Política CI ajustada para modo transicional: jobs mobile executam com artifact e `continue-on-error` até estabilidade comprovada.
- Critério de promoção para gating mobile: 2 rodadas consecutivas estáveis em CI sem `Killed`/`interrupted`.


---

## PD7 — Auditoria fina da Home (2026-07-10)

Status: REPRODUCED (novos achados + reconfirmações com evidência técnica)
Escopo: Home (`/`) em desktop e validações complementares mobile
Evidências usadas:
- Playwright spec oficial (`product-design-qa.spec.ts`, projeto `desktop-1440`)
- Inspeção runtime em navegador (DOM/ARIA/targets/autocomplete)
- Varredura de links principais da Home via fetch

### Reconferência dos itens já abertos

- **PD-BUG-002 (radius da busca)**: permanece **reproduzido**.
  Evidência fresca do `[PD0-DIAG]`: `radius.input.borderRadius = "0px"` na Home (`desktop-1440`).
- **PD-BUG-004 (autocomplete)**: fluxo principal segue funcional no desktop.
  Evidência fresca: painel Mirasvit abriu com resultados (`opened:true`, contagem visível e lista de produtos para `bagageiro`).

### Novos bugs encontrados na Home

## PD-BUG-007 — CTA “Ver todos os mais vendidos” leva para página vazia

Status: REPRODUCED
Prioridade: P1
Página: Home (`/`) -> link da seção “Mais Vendidos”
Componente: CTA “Ver todos os mais vendidos”

### Problema
O CTA da Home aponta para `https://awamotos.com/ofertas.html`, porém a página de destino retorna estado vazio: **“Nenhum produto encontrado”**.

### Evidência
- Link da Home confirmado na inspeção da árvore de acessibilidade.
- Conteúdo de `ofertas.html` via fetch: heading “Ofertas” + bloco “Nenhum produto encontrado”.

### Impacto
Usuário clica em um CTA de alto valor comercial e cai em página sem itens, reduzindo conversão e confiança no bloco “Mais Vendidos”.

### Correção recomendada
- Corrigir destino do CTA para uma página realmente populada de best-sellers, **ou**
- Garantir indexação/população da rota `ofertas.html` antes de expor o CTA.

### Critério de aceite
- [ ] CTA “Ver todos os mais vendidos” abre página com produtos listados
- [ ] Sem estado vazio para tráfego padrão (guest)

---

## PD-BUG-008 — Ícones de categorias sem atributo `alt` na Home

Status: REPRODUCED
Prioridade: P2
Página: Home (`/`)
Componente: Carrossel “Compre por categoria”

### Problema
A varredura de imagens da Home encontrou **13 imagens sem `alt`**.
Dessas, **7 são ícones visíveis de categoria** (bauletos, guidões, retrovisores etc.) dentro de links navegáveis.

### Evidência
Inspeção runtime: imagens em `.../images/category-carousel/*.png` sem atributo `alt`.

### Impacto
Não conformidade de acessibilidade (WCAG/H37) e pior experiência para leitores de tela.

### Correção recomendada
- Para ícones informativos: definir `alt` descritivo por categoria.
- Para ícones puramente decorativos: manter no link textual e usar `alt=""` + `aria-hidden="true"` quando aplicável.

### Critério de aceite
- [ ] 0 imagens sem atributo `alt` no bloco de categorias
- [ ] Leitura de links de categoria permanece clara para tecnologias assistivas

---

## PD-BUG-009 — Alvos interativos abaixo de 44px no mobile em cards de produto

Status: REPRODUCED
Prioridade: P1
Página: Home (`/`)
Componente: Links de título nos cards (carrosséis de vitrine)
Viewport: mobile `390x844`

### Problema
Na inspeção mobile, vários links de título nos cards renderizam com **altura ~35px** (abaixo da régua recomendada de 44px para touch).

### Evidência
Inspeção runtime mobile: `smallTargetCount: 18`; amostras dos links de produto com `height: 35`.

### Impacto
Aumenta erro de toque e fricção de navegação em dispositivos móveis.

### Correção recomendada
- Ajustar line-height/padding/área clicável dos links de título para mínimo de 44px em mobile.

### Critério de aceite
- [ ] Links de cards na Home com target >= 44px em mobile
- [ ] Sem regressão visual de grid/carrossel

---

## PD-BUG-010 — Warning de preload não utilizado no slider da Home

Status: REPRODUCED
Prioridade: P3
Página: Home (`/`)
Componente: Hero/slider (`slidebanner`)

### Problema
Console registra aviso recorrente de preload não utilizado rapidamente após load:
`slider_guidao_cb_300.jpg-1920w.webp was preloaded ... but not used within a few seconds`

### Evidência
Evento de console capturado durante inspeção runtime da Home.

### Impacto
Potencial desperdício de banda e degradação de performance/percepção (preload ineficiente).

### Correção recomendada
Revisar estratégia de preload do hero (ordem, `as`, prioridade real do slide inicial e timing de consumo).

### Critério de aceite
- [ ] Warning deixa de aparecer no console em carregamento normal da Home
- [ ] LCP/hero não piora após ajuste

### Nota técnica desta rodada
- Execução oficial `design QA — home` (`desktop-1440`) passou com `httpStatus:200`, `brokenImages:[]`, `badAssets:[]`, `consoleErrorsCount:0`, `networkErrorsCount:0`.
- O objetivo desta seção é registrar **bugs de UX/acessibilidade/performance fina** que não necessariamente quebram o smoke test funcional.
