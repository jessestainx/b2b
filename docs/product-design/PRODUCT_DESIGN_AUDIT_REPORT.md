# Product Design Audit Report — AWA Motos (Fase PD0)

Última atualização: 2026-07-09
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
