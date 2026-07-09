# AWA Motos — Product Design System (Fase PD0 — Foundation)

Última atualização: 2026-07-09
Responsável: agent
Escopo desta fase: **régua de design**, não correção. Nenhum código funcional da loja foi alterado para produzir este documento.

> Fora do escopo desta fase: editar tema (`app/design/frontend/AWA_Custom/ayo_home5_child`), CSS/LESS/JS, templates PHTML, `pub/static`, `vendor`. Este documento apenas descreve o padrão-alvo e mapeia onde o estado atual diverge dele.

## Contexto de plataforma (Magento 2 / Adobe Commerce)

O tema é a camada responsável por aparência consistente (templates, layout XML, estilos, imagens). Correções visuais devem ser feitas na camada correta:

- **Layout XML** (`view/frontend/layout/*.xml`) — estrutura de containers/blocks, inclusão/remoção de blocos.
- **Blocks** (`Block/*.php`) — lógica de apresentação.
- **Templates** (`*.phtml`) — marcação HTML.
- **Web assets do tema** (`web/css/source/*.less`, `web/js/*.js`) — estilo e comportamento.

Nunca editar `Rokanthemes/*` (tema pai) diretamente — sempre via override no tema filho `AWA_Custom/ayo_home5_child`. Nunca tratar `pub/static` ou `var/view_preprocessed` como fonte — são artefatos de build, não devem ser editados como "correção".

## Princípios visuais

1. **Premium** — hierarquia visual clara, sem excesso de bordas/sombras concorrentes.
2. **Clean** — baixa poluição visual; cada tela tem no máximo uma ação primária óbvia.
3. **Profissional** — tipografia consistente, alinhamento em grid, espaçamento previsível.
4. **B2B-first** — CTAs e mensagens adequadas ao contexto de negócio (preço sob aprovação, cotação, CNPJ), sem infantilizar a UI.
5. **Baixa poluição visual** — remover badges/selos/decorações que não carregam informação de decisão.
6. **Ações claras** — botão primário sempre único e visualmente dominante por bloco; ações secundárias com menor peso visual.
7. **Nunca "tema remendado"** — todo componente novo deve reutilizar os tokens abaixo; zero cor/raio/espaçamento hardcoded fora da escala.

## Tokens obrigatórios

### Radius

```less
@awa-radius-xs: 4px;
@awa-radius-sm: 6px;
@awa-radius-md: 8px;
@awa-radius-lg: 12px;
@awa-radius-pill: 999px;
```

Regra de uso:

- **8px (`@awa-radius-md`)** é o radius padrão para **botões, cards, inputs, dropdowns e modais**.
- **4px (`@awa-radius-xs`)** apenas para **badges**.
- **12px (`@awa-radius-lg`)** apenas para **containers grandes** (seções, blocos de página).
- **999px (`@awa-radius-pill`)** apenas para **chips/pills reais** (filtros ativos, tags removíveis).

### Spacing (grid de 4px/8px)

```less
@awa-space-1: 4px;
@awa-space-2: 8px;
@awa-space-3: 12px;
@awa-space-4: 16px;
@awa-space-5: 24px;
@awa-space-6: 32px;
@awa-space-7: 48px;
@awa-space-8: 64px;
```

### Divergência detectada entre esta régua e o token file real

O arquivo `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source/_awa-variables.less` (fonte real, lido em modo somente-leitura para este documento) hoje define:

| Token | Régua PD0 (este doc) | Valor real hoje em `_awa-variables.less` |
|---|---|---|
| `@awa-radius-sm` | `6px` | `8px` |
| `@awa-radius-md` | `8px` | `12px` |
| `@awa-radius-lg` | `12px` | `16px` |
| `@awa-radius-full`/`pill` | `999px` | `9999px` |
| `@awa-space-5` | `24px` | `20px` |
| `@awa-space-6` | `32px` | `24px` |
| `@awa-space-7` | `48px` | `32px` |
| `@awa-space-8` | `64px` | `40px` (existe ainda `@space-10: 64px`) |

Isso **não é um erro deste documento** — a régua PD0 usa exatamente a escala já registrada como meta em `docs/visual-qa/VISUAL_FIX_PLAN.md` ("Padronização visual — referência rápida"). A tabela acima existe para deixar explícito, com evidência, que:

1. Ajustar `_awa-variables.less` para bater com esta régua é trabalho de uma fase **PD1+** (correção), não desta fase (PD0).
2. Nenhum valor foi alterado no arquivo real nesta fase.
3. Todo QA visual automatizado (spec `product-design-qa.spec.ts`) deve validar o **radius computado (`getComputedStyle`) próximo de 8px em componentes padrão**, tolerando a régua-alvo, e reportar divergência como achado, não como falha bloqueante nesta fase.

## Componentes obrigatórios

Cada componente documenta: anatomia, spacing, radius, hover, focus, active, disabled, desktop, mobile, acessibilidade e teste Playwright necessário (mapeado para `tests/e2e/specs/product-design-qa.spec.ts`).

---

### 1. Button

- **Anatomia:** label + (ícone opcional) + área clicável.
- **Spacing:** padding vertical `@awa-space-2` (8px) a `@awa-space-3` (12px); padding horizontal `@awa-space-4` (16px)+.
- **Radius:** `@awa-radius-md` (8px).
- **Hover:** leve escurecimento/tint de 8-10%, sem mudança de tamanho.
- **Focus:** anel de foco visível (`outline`/`box-shadow`), nunca `outline: none` sem substituto.
- **Active:** leve compressão de sombra/opacidade, feedback tátil.
- **Disabled:** opacidade reduzida (~50%), `cursor: not-allowed`, sem hover.
- **Desktop:** altura mínima 40px.
- **Mobile:** altura mínima 44px (alvo de toque).
- **Acessibilidade:** contraste texto/fundo >= 4.5:1; `aria-disabled` quando desabilitado; foco visível via teclado.
- **Teste Playwright:** altura mínima por breakpoint; `border-radius` computado; contraste básico do CTA principal; foco visível ao navegar por `Tab`.

### 2. Input

- **Anatomia:** label + campo + (ícone/ação opcional) + mensagem de ajuda/erro.
- **Spacing:** padding `@awa-space-2`–`@awa-space-3`; gap label->campo `@awa-space-1`.
- **Radius:** `@awa-radius-md` (8px).
- **Hover:** borda levemente mais escura.
- **Focus:** borda de destaque + anel de foco; nunca só mudança de cor de borda sem contraste.
- **Active:** idêntico a focus enquanto digitando.
- **Disabled:** fundo neutro, texto acinzentado, sem cursor de edição.
- **Desktop:** altura mínima 40px.
- **Mobile:** altura mínima 44px.
- **Acessibilidade:** `label` associado via `for`/`id` (nunca só `placeholder`); erro anunciado via `aria-describedby`.
- **Teste Playwright:** presença de `<label>` visível; altura mínima; foco visível; radius computado.

### 3. Search box

- **Anatomia:** ícone de busca + input + botão (opcional) + autocomplete.
- **Spacing:** altura confortável, largura generosa (busca é ação primária de navegação).
- **Radius:** `@awa-radius-md` (8px) ou `@awa-radius-pill` se for o padrão de marca adotado — manter consistente em todo o site.
- **Hover:** realce sutil de borda.
- **Focus:** anel de foco + abertura de autocomplete quando aplicável.
- **Active:** mantém estado de foco durante digitação.
- **Disabled:** não aplicável (sempre habilitado, exceto manutenção).
- **Desktop:** campo grande, visível permanentemente no header.
- **Mobile:** campo acessível em 1 toque a partir do header.
- **Acessibilidade:** input com `type="search"`/label acessível; resultados do autocomplete navegáveis por teclado.
- **Teste Playwright:** campo visível em todas as rotas; largura mínima; autocomplete abre ou é registrado como bug; endpoint responde.

> A busca deve permanecer visível, simples e disponível em todas as páginas — usuários recorrem à busca quando a navegação falha (Nielsen Norman Group).

### 4. Autocomplete

- **Anatomia:** lista de sugestões (texto e/ou imagem) ancorada ao input.
- **Spacing:** item de lista com padding `@awa-space-2`–`@awa-space-3`.
- **Radius:** `@awa-radius-md` no container do dropdown.
- **Hover:** destaque de fundo no item.
- **Focus:** navegação por seta do teclado com destaque visível.
- **Active:** item selecionado com destaque mais forte.
- **Disabled:** não aplicável.
- **Desktop:** dropdown ancorado abaixo do input, sem cobrir o próprio input.
- **Mobile:** pode ocupar mais largura/altura, mas sem cobrir 100% da tela sem meio de fechar.
- **Acessibilidade:** `role="listbox"`/`option`, navegação por teclado, `aria-activedescendant`.
- **Teste Playwright:** abre ao digitar; endpoint sem erro 4xx/5xx; fecha com `Escape`/clique fora.

### 5. Product card

- **Anatomia:** imagem (1:1) + título (até 2 linhas) + SKU/status de estoque + preço ou mensagem B2B + CTA.
- **Spacing:** padding interno `@awa-space-3`–`@awa-space-4`; gap entre elementos `@awa-space-2`.
- **Radius:** `@awa-radius-md` (8px), uma única borda por card.
- **Hover:** elevação leve (sombra) e/ou leve zoom da imagem, sem mudança de layout.
- **Focus:** CTA e link da imagem navegáveis e visíveis por teclado.
- **Active:** feedback visual do CTA ao clicar.
- **Disabled:** não aplicável ao card; CTA pode ficar desabilitado se produto indisponível.
- **Desktop:** grid de 4–5 colunas com filtro lateral.
- **Mobile:** 1–2 colunas, sem overflow horizontal.
- **Acessibilidade:** imagem com `alt`; CTA com texto claro (não apenas ícone).
- **Teste Playwright:** presença de imagem, título e CTA; sem bounding box 0x0; radius computado.

### 6. Category card

- **Anatomia:** imagem/ícone + nome da categoria + (contagem opcional).
- **Spacing:** padding `@awa-space-3`.
- **Radius:** `@awa-radius-md`.
- **Hover:** leve destaque/elevação.
- **Focus:** anel de foco visível no link.
- **Active:** feedback ao clique.
- **Disabled:** não aplicável.
- **Desktop:** carrossel ou grid com itens de tamanho uniforme.
- **Mobile:** scroll horizontal ou grid compacto sem overflow da página.
- **Acessibilidade:** link com texto/`aria-label` descritivo.
- **Teste Playwright:** sem overflow horizontal do body; imagem carregada (sem 404).

### 7. Header

- **Anatomia:** logo + busca + conta/B2B + minicart + menu principal (desktop) / toggle (mobile).
- **Spacing:** altura consistente entre breakpoints, sem "pulo" de layout (CLS).
- **Radius:** elementos internos (botões/inputs) seguem `@awa-radius-md`.
- **Hover:** links/ícones com destaque sutil.
- **Focus:** todos os elementos interativos navegáveis por `Tab` em ordem lógica.
- **Active:** estado de item de menu ativo visualmente diferenciado.
- **Disabled:** não aplicável.
- **Desktop:** logo, busca, conta e minicart visíveis simultaneamente; menu horizontal visível.
- **Mobile:** toggle de menu visível; busca acessível em 1 toque.
- **Acessibilidade:** `aria-expanded`/`aria-controls` no toggle; foco visível.
- **Teste Playwright:** logo/busca/conta/minicart visíveis; nenhum elemento crítico 0x0; sem overflow horizontal. (Reutiliza `header-core-interactions-p0.spec.ts` como base funcional; este spec adiciona a lente de design.)

### 8. Menu vertical (Departamentos)

- **Anatomia:** trigger + lista de categorias (dropdown/drawer).
- **Spacing:** item de lista com padding `@awa-space-2`–`@awa-space-3`.
- **Radius:** `@awa-radius-md` no container.
- **Hover:** destaque de fundo no item.
- **Focus:** navegação por teclado com destaque.
- **Active:** `aria-expanded="true"` sincronizado com estado visual.
- **Disabled:** não aplicável.
- **Desktop:** dropdown sem cobrir header nem sair da viewport.
- **Mobile:** drawer/painel dedicado, sem sobrepor conteúdo de forma confusa.
- **Acessibilidade:** `ESC` fecha; clique fora fecha; `aria-expanded`/`aria-controls`.
- **Teste Playwright:** abre/fecha; permanece dentro da viewport; `ESC`/clique fora fecham.

### 9. Minicart

- **Anatomia:** trigger (ícone/contador) + painel (itens, subtotal, CTA checkout) + estado vazio.
- **Spacing:** item de carrinho com padding `@awa-space-3`.
- **Radius:** `@awa-radius-md` no painel.
- **Hover:** destaque em itens/ações (remover, editar quantidade).
- **Focus:** CTA de checkout navegável e visível.
- **Active:** feedback ao adicionar/remover item.
- **Disabled:** CTA de checkout desabilitado se carrinho vazio (ou oculto, mas nunca ambíguo).
- **Desktop:** dropdown ancorado ao header, **sem backdrop escuro indevido**.
- **Mobile:** pode usar drawer/modal com backdrop **intencional**.
- **Acessibilidade:** `role="dialog"` ou equivalente em mobile; `ESC`/clique fora fecham.
- **Teste Playwright:** dentro da viewport; sem backdrop indevido no desktop; fecha com `ESC`/clique fora; estado vazio claro.

### 10. B2B status panel

- **Anatomia:** trigger (nome/status do cliente) + painel (links de conta, pedidos, sair).
- **Spacing:** item de painel com padding `@awa-space-2`–`@awa-space-3`.
- **Radius:** `@awa-radius-md`.
- **Hover:** destaque de item.
- **Focus:** navegação por teclado nos links do painel.
- **Active:** `aria-expanded` sincronizado.
- **Disabled:** não aplicável.
- **Desktop:** painel ancorado ao trigger, sem sair da viewport.
- **Mobile:** painel adaptado (drawer ou dropdown compacto).
- **Acessibilidade:** `aria-controls`/`aria-expanded`; `ESC` fecha.
- **Teste Playwright:** abre após login B2B; fecha com `ESC`; links esperados presentes.

### 11. Dropdown (genérico)

- **Anatomia:** trigger + lista/painel flutuante.
- **Spacing:** `@awa-space-2`–`@awa-space-3` interno.
- **Radius:** `@awa-radius-md`.
- **Hover/Focus/Active:** mesmo padrão de item de lista dos componentes acima.
- **Disabled:** trigger desabilitado quando não aplicável ao contexto.
- **Desktop:** nunca com backdrop full-screen (isso é padrão de modal, não de dropdown).
- **Mobile:** pode virar drawer.
- **Acessibilidade:** `aria-haspopup`, `aria-expanded`.
- **Teste Playwright:** sem backdrop indevido no desktop; dentro da viewport.

### 12. Modal / Drawer

- **Anatomia:** overlay/backdrop + painel + botão de fechar + conteúdo.
- **Spacing:** padding interno `@awa-space-4`–`@awa-space-5`.
- **Radius:** `@awa-radius-md` (modal) — nunca 0 (cantos retos "remendados").
- **Hover/Focus:** botão de fechar sempre focável e visível.
- **Active:** foco preso dentro do modal (focus trap) enquanto aberto.
- **Disabled:** ações internas desabilitadas conforme contexto (ex.: submit em loading).
- **Desktop:** centralizado, com backdrop.
- **Mobile:** pode ocupar tela cheia (drawer), com backdrop intencional.
- **Acessibilidade:** `role="dialog"`, `aria-modal="true"`, `ESC` fecha, foco retorna ao trigger ao fechar.
- **Teste Playwright:** backdrop presente quando é modal real; `ESC` fecha; foco não escapa do modal.

### 13. PLP toolbar

- **Anatomia:** botão mostrar/ocultar filtros + grid/list view + ordenação + itens por página.
- **Spacing:** controles com altura 40px, gap `@awa-space-3` entre grupos.
- **Radius:** `@awa-radius-md` em inputs/selects/botões.
- **Hover/Focus/Active:** padrão de botão/input acima.
- **Disabled:** não aplicável normalmente.
- **Desktop:** filtro (esq.) -> view mode -> ordenação/qtd (dir.).
- **Mobile:** filtro vira drawer; toolbar não quebra em múltiplas linhas de forma confusa.
- **Acessibilidade:** `aria-pressed` em toggles de view mode; labels visíveis em selects.
- **Teste Playwright:** presença de controles; altura mínima; sem overflow horizontal.

### 14. Filters (Layered Navigation)

- **Anatomia:** grupo de filtro (título) + opções + (contagem opcional).
- **Spacing:** opção com padding `@awa-space-2`.
- **Radius:** `@awa-radius-xs`–`@awa-radius-sm` em chips de filtro ativo; `@awa-radius-pill` se forem chips reais removíveis.
- **Hover:** destaque de opção.
- **Focus:** navegável por teclado.
- **Active:** filtro selecionado com destaque visual claro.
- **Disabled:** filtro sem opções úteis não deve ser renderizado (nunca aparecer vazio).
- **Desktop:** sidebar.
- **Mobile:** drawer.
- **Acessibilidade:** checkboxes/links com label associado.
- **Teste Playwright:** nenhum filtro vazio renderizado; drawer mobile funcional.

### 15. Pagination

- **Anatomia:** botões numéricos + anterior/próximo + contagem de itens.
- **Spacing:** botão 36–40px com gap `@awa-space-1`–`@awa-space-2`.
- **Radius:** `@awa-radius-sm`–`@awa-radius-md`.
- **Hover:** destaque de botão.
- **Focus:** anel de foco visível.
- **Active:** página atual com destaque forte (cor + peso de fonte).
- **Disabled:** botão "anterior"/"próximo" desabilitado nos limites.
- **Desktop/Mobile:** sempre próxima ao fim do grid, nunca "solta".
- **Acessibilidade:** `aria-current="page"` no item ativo.
- **Teste Playwright:** botões com altura mínima; item ativo com contraste suficiente.

### 16. Footer

- **Anatomia:** newsletter -> benefícios -> institucional -> suporte -> atendimento -> redes sociais -> pagamento/segurança -> legal.
- **Spacing:** colunas com gap `@awa-space-6`–`@awa-space-7`; padding vertical de seção `@awa-space-7`–`@awa-space-8`.
- **Radius:** `@awa-radius-md` em cards/badges internos (ex.: selos de pagamento).
- **Hover/Focus:** links do footer com estado de hover/foco visível.
- **Active:** não aplicável fortemente.
- **Disabled:** não aplicável.
- **Desktop:** colunas alinhadas em grid; sem blocos "vermelhos" isolados por coluna.
- **Mobile:** colunas empilhadas, sem overflow horizontal.
- **Acessibilidade:** contraste de texto sobre fundo escuro >= 4.5:1.
- **Teste Playwright:** nenhum bloco quebrado/vazio; sem overflow horizontal.

### 17. Form field (B2B)

- **Anatomia:** label + campo + máscara (quando aplicável) + mensagem de erro inline.
- **Spacing:** gap label->campo `@awa-space-1`; gap entre campos `@awa-space-4`.
- **Radius:** `@awa-radius-md`.
- **Hover:** borda mais escura.
- **Focus:** anel de foco + preservação do valor digitado.
- **Active:** estado de digitação com foco mantido.
- **Disabled:** campo readonly/desabilitado com estilo diferenciado.
- **Desktop/Mobile:** mesma altura mínima de input (40/44px).
- **Acessibilidade:** **nunca usar `placeholder` como substituto de `label`**; erro anunciado via `aria-describedby`; mensagens em português.
- **Teste Playwright:** labels visíveis (não apenas placeholder); erro inline presente quando aplicável; máscara de CNPJ/telefone aplicada.

> Placeholders que desaparecem ao digitar aumentam carga de memória, dificultam a correção de erros e prejudicam acessibilidade (Nielsen Norman Group). Labels devem ser sempre visíveis.

### 18. Empty state

- **Anatomia:** ilustração/ícone + mensagem clara + (CTA de próxima ação).
- **Spacing:** padding generoso (`@awa-space-6`+) para não parecer "quebrado".
- **Radius:** `@awa-radius-md` se houver container/card.
- **Hover/Focus:** CTA (se houver) segue padrão de botão.
- **Active/Disabled:** conforme CTA.
- **Desktop/Mobile:** mensagem legível sem exigir scroll excessivo.
- **Acessibilidade:** texto real (não apenas imagem) descrevendo o estado.
- **Teste Playwright:** presença de mensagem clara quando lista/carrinho/minicart estão vazios.

### 19. Loading / Skeleton

- **Anatomia:** placeholder de mesma forma/tamanho do conteúdo final.
- **Spacing/Radius:** idênticos ao componente que está sendo substituído (evita "pulo" de layout).
- **Hover/Focus/Active/Disabled:** não aplicável (não interativo).
- **Desktop/Mobile:** mesma proporção do componente real.
- **Acessibilidade:** `aria-busy="true"` no container durante carregamento.
- **Teste Playwright:** ausência de CLS perceptível (dimensões do skeleton ~= dimensões finais).

### 20. Error state

- **Anatomia:** ícone/indicador + mensagem objetiva + ação de recuperação (retry/voltar).
- **Spacing:** padding `@awa-space-4`+.
- **Radius:** `@awa-radius-md` em container de erro.
- **Hover/Focus:** ação de retry segue padrão de botão.
- **Active/Disabled:** conforme ação de retry.
- **Desktop/Mobile:** mensagem legível, sem sobreposição de outros elementos.
- **Acessibilidade:** mensagem em português, anunciável por leitor de tela (`role="alert"` quando dinâmico).
- **Teste Playwright:** nenhum erro de console/rede não tratado deixando a UI em estado ambíguo.

---

## Acessibilidade — critérios transversais (WCAG 2.2)

- Contraste mínimo **4.5:1** para texto normal, **3:1** para texto grande.
- Foco sempre visível (nunca `outline: none` sem substituto equivalente).
- Alvo de toque mínimo adequado (botões pequenos, paginação, ícones de carrossel, minicart, fechar modal).
- `ESC` fecha dropdown/modal; clique fora fecha overlays não-modais.
- `aria-expanded`, `aria-controls`, `aria-pressed` aplicados de forma consistente em todos os componentes interativos acima.

## Rastreabilidade

- Checklist operacional: [`docs/product-design/PRODUCT_DESIGN_QA_CHECKLIST.md`](./PRODUCT_DESIGN_QA_CHECKLIST.md)
- Spec Playwright: [`tests/e2e/specs/product-design-qa.spec.ts`](../../tests/e2e/specs/product-design-qa.spec.ts)
- Relatório de auditoria: [`docs/product-design/PRODUCT_DESIGN_AUDIT_REPORT.md`](./PRODUCT_DESIGN_AUDIT_REPORT.md)
- Plano mestre de correção visual: [`docs/visual-qa/VISUAL_FIX_PLAN.md`](../visual-qa/VISUAL_FIX_PLAN.md)
