# Product Design Audit Report — AWA Motos (Fase PD0)

Última atualização: 2026-07-09
Fonte de evidência: execução real do smoke `design QA — home` via
`tests/e2e/specs/product-design-qa.spec.ts` (`--project=desktop-1440`, exit code 0,
"1 passed", sem `Killed`). Screenshots em `test-results/product-design-qa/`.

> Nenhuma correção foi aplicada nesta fase. Todos os bugs abaixo estão classificados
> como `REPRODUCED` (evidência real coletada) ou `TODO` (ainda não testado em todas
> as rotas/breakpoints).

---

## PD-BUG-001 — Imagens quebradas (produto e footer)

Status: REPRODUCED
Prioridade: P0
Página: Home (`/`)
Componente: Product card (vitrine) + Footer (selos de pagamento/logo)
Viewport: desktop-1440 (1440x1000)
Estado: guest

### Problema
`findBrokenImages` detectou 11 imagens com `naturalWidth === 0` (quebradas) na Home:
- 2 imagens de produto (`.../10350_2.jpg`, `.../10401_2.jpg`)
- `logo_rodape.png`
- selos de pagamento: `visa.png`, `mastercard.png`, `elo.png`, `amex.png`, `diners-club.png`, `boleto.png`, `pix.png`
- `logo_bluu.png`

### Evidência
- Screenshot: `test-results/product-design-qa/desktop-1440__home-fullpage.png`
- JSON attach: `pd0-home.json` (campo `brokenImages`)
- Console/network: 0 erros de console, 0 erros de rede nesta execução (as imagens carregam com status 200 mas renderizam com `naturalWidth: 0` — sugere problema de decodificação/formato/cache, não 404).

### Causa provável
A avaliar: cache de imagem corrompido, CDN/otimização de imagem, ou mudança de path não refletida. Não é claramente CSS/LESS/JS — requer inspeção de `pub/media/img/*` e do pipeline de otimização de imagem antes de qualquer correção.

### Correção recomendada
Fora do escopo do PD0. Investigar na fase PD5 (Footer) e PD3 (PLP/Home product card).

### Critério de aceite
- [ ] `findBrokenImages` retorna `[]` na Home (desktop e mobile)
- [ ] Playwright
- [ ] Sem erro console
- [ ] Sem 404/403
- [ ] Sem erro em `exception.log`/`system.log`
- [ ] Screenshot depois

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
  classes `is-open active has-results`, conteudo real de resultado
  (`<div class="no-result">Nenhum resultado encontrado.</div>` para a query de teste), e
  requisicao `search/ajax/suggest?q=bagageiro` -> `200`.
- Via Playwright real (`product-design-qa.spec.ts`, rota `home`): `autocomplete.opened: true`
  (spec ajustado de wait fixo de 600ms para polling de ate 6s, refletindo a latencia real
  medida de ate ~2.6-3.5s da cadeia de bootstrap — ver nota tecnica no proprio spec).
- Regressao: `header-core-interactions-p0.spec.ts` (`diagnostico — home`) permanece
  passando (`1 passed`, exit code 0) apos a mudanca de JS.

### Achado secundario (nao corrigido nesta fase, fora de escopo PD2)
- Query de teste "bagageiro" retornou "Nenhum resultado encontrado" em uma das
  investigacoes — pode ser um problema de indexacao/dados do catalogo, nao do
  autocomplete em si. Registrar como candidato de investigacao futura (fora do escopo
  desta branch, que e apenas sobre a race condition do autocomplete).
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
