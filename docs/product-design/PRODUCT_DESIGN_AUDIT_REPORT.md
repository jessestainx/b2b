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

## PD-BUG-003 — Menu vertical (Departamentos) não abriu visivelmente após clique

Status: REPRODUCED
Prioridade: P0
Página: Home (`/`)
Componente: Menu vertical
Viewport: desktop-1440
Estado: guest

### Problema
O trigger do menu vertical está visível e com bounding box válido, mas após o clique automatizado, `verticalMenu.visible` foi registrado como `false` (lista `.togge-menu.list-category-dropdown` não ficou visível/detectável).

### Evidência
- JSON attach: `pd0-home.json` (campo `verticalMenu = {"visible":false,"bbox":null,"withinViewport":null}`)
- Screenshot de tentativa: não gerado nesta execução (arquivo `menu-vertical-aberto.png` não foi produzido — **achado adicional**: a captura de evidência para este componente falhou silenciosamente e precisa de investigação em fase de correção, não é apenas o menu que não abriu, mas a própria evidência).

### Causa provável
A avaliar em fase de correção: seletor da lista pode estar desatualizado, classe de estado "aberto" diferente da esperada, ou timing insuficiente após o clique.

### Correção recomendada
Fase PD1 (Header): confirmar seletor real do menu vertical em runtime (DevTools) antes de qualquer alteração de JS/CSS.

### Critério de aceite
- [ ] Menu vertical abre e é detectado como visível pelo spec
- [ ] Screenshot `menu-vertical-aberto` gerado com sucesso
- [ ] Playwright
- [ ] Sem erro console

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
