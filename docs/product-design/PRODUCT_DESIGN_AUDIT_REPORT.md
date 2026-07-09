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

## PD-BUG-004 — Autocomplete não abriu ao digitar

Status: REPRODUCED
Prioridade: P1
Página: Home (`/`)
Componente: Busca / Autocomplete
Viewport: desktop-1440
Estado: guest

### Problema
Após preencher o campo de busca com "bagageiro" e aguardar 600ms, nenhum seletor de autocomplete conhecido (`#search_autocomplete`, `.search-autocomplete`, `.mirasvit-searchautocomplete`, `[data-role="search-autocomplete"]`, `.mst-searchautocomplete__autocomplete`) ficou visível.

### Evidência
- JSON attach: `pd0-home.json` (campo `autocomplete = {"opened": false}`)
- Screenshot de tentativa: não gerado nesta execução (mesma observação de PD-BUG-003 — falha silenciosa na captura, investigar).

### Causa provável
A avaliar: JS de autocomplete pode exigir mais tempo, evento diferente (`keyup` vs `input`), ou o seletor real do dropdown de sugestões difere dos candidatos testados.

### Correção recomendada
Fase PD1 (Header/Busca): identificar seletor real do autocomplete em runtime antes de qualquer alteração.

### Critério de aceite
- [ ] Autocomplete detectado como aberto pelo spec após digitação
- [ ] Endpoint de sugestão validado
- [ ] Playwright
- [ ] Screenshot `autocomplete-aberto` gerado com sucesso

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
