# Product Design QA Checklist — AWA Motos (Fase PD0)

Última atualização: 2026-07-09
Uso: checklist manual/objetivo para classificar se um componente está "premium" ou precisa de correção. Complementa (não substitui) o spec Playwright `tests/e2e/specs/product-design-qa.spec.ts`.

> Esta fase é apenas de avaliação. Nenhum item aqui deve ser "corrigido" ainda — apenas marcado como OK / REPRODUCED, com evidência.

## Como usar

Para cada item: marcar `[ ]` pendente ou `[x]` OK, e se falhar, abrir uma entrada em `docs/product-design/PRODUCT_DESIGN_AUDIT_REPORT.md` com o formato `PD-BUG-XXX`.

---

## Header

- [ ] Logo visível
- [ ] Busca visível
- [ ] Conta visível
- [ ] Minicart visível
- [ ] Menu vertical abre e fecha
- [ ] Sem backdrop indevido no desktop
- [ ] Sem elemento crítico com bounding box 0x0
- [ ] Sem overflow horizontal
- [ ] Foco visível ao navegar por teclado
- [ ] Área clicável adequada (>=40px desktop, >=44px mobile)

## Busca

- [ ] Campo grande o suficiente (visualmente dominante no header)
- [ ] Autocomplete abre ao digitar
- [ ] Endpoint de sugestão responde sem erro 4xx/5xx
- [ ] `Enter` navega para resultados
- [ ] Página de resultado carrega sem erro
- [ ] Estado vazio (sem resultados) é claro e objetivo

> A busca deve ficar visível e simples, disponível em todas as páginas — usuários recorrem a ela quando não conseguem navegar pelo site (Nielsen Norman Group).

## Formulários B2B

- [ ] Labels visíveis (nunca substituídos por placeholder)
- [ ] Placeholder usado apenas como exemplo, não como label
- [ ] Erros inline, associados ao campo (`aria-describedby`)
- [ ] Foco claro ao navegar entre campos
- [ ] Máscara de CNPJ aplicada corretamente
- [ ] Máscara de telefone aplicada corretamente
- [ ] Mensagens em português, sem termos técnicos crus

> Placeholders que desaparecem ao digitar aumentam carga de memória, dificultam a correção de erros e podem prejudicar acessibilidade (Nielsen Norman Group).

## Menu vertical (Departamentos)

- [ ] Abre ao clicar no trigger
- [ ] Fecha com `ESC`
- [ ] Fecha com clique fora
- [ ] Permanece dentro da viewport em todos os breakpoints
- [ ] Não fica atrás de outro elemento (z-index/stacking)
- [ ] `aria-expanded` sincronizado com estado visual

## Minicart

- [ ] Abre ao clicar no trigger
- [ ] Painel dentro da viewport
- [ ] Sem backdrop escuro indevido no desktop
- [ ] Backdrop intencional em mobile (se drawer/modal)
- [ ] Fecha com `ESC`
- [ ] Fecha com clique fora
- [ ] Estado vazio claro (mensagem + CTA)

## Login / Conta / B2B

- [ ] Links de login/cadastro visíveis para guest
- [ ] Login redireciona corretamente
- [ ] Painel de status B2B abre após login
- [ ] Painel fecha com `ESC`
- [ ] Links esperados pós-login presentes (Minha Conta, Pedidos, Sair)

## Home

- [ ] Nenhum carrossel/banner quebrado
- [ ] Nenhuma imagem 404
- [ ] Hierarquia visual clara entre seções
- [ ] Sem overflow horizontal em nenhum breakpoint

## PLP / Categoria

- [ ] Toolbar visível (filtro, view mode, ordenação)
- [ ] Nenhum filtro vazio renderizado
- [ ] Product card com imagem, título e CTA
- [ ] Paginação visível e funcional
- [ ] Grid sem overflow horizontal em mobile

## PDP

- [ ] Imagem principal visível e sem 404
- [ ] Preço ou mensagem B2B visível conforme estado de login
- [ ] CTA principal único e visualmente dominante
- [ ] Informações técnicas/fitment organizadas sem poluição visual

## Carrinho

- [ ] Itens listados com imagem, título, quantidade e subtotal
- [ ] CTA de checkout visível e único
- [ ] Estado vazio claro

## Checkout (auditoria visual apenas, sem alterar fluxo de pagamento)

- [ ] Etapas do checkout visualmente claras
- [ ] Nenhum erro de console durante navegação read-only
- [ ] Nenhum CSS/JS 404/403
- [ ] Nenhuma alteração de fluxo de pagamento realizada durante o teste

## Footer

- [ ] Nenhum bloco quebrado/vazio
- [ ] Ordem: Newsletter -> Benefícios -> Institucional -> Suporte -> Atendimento -> Redes sociais -> Pagamento/segurança -> Legal
- [ ] Sem overflow horizontal em mobile
- [ ] Contraste de texto sobre fundo escuro adequado

---

## Acessibilidade (transversal a todas as páginas)

- [ ] Contraste mínimo **4.5:1** para texto normal
- [ ] Contraste mínimo **3:1** para texto grande
- [ ] Foco visível em todos os elementos interativos
- [ ] Alvo de toque mínimo adequado (botões pequenos, paginação, ícones de carrossel, minicart, fechar modal)
- [ ] `ESC` fecha dropdown/modal
- [ ] Clique fora fecha overlays não-modais
- [ ] `aria-expanded`, `aria-controls`, `aria-pressed` presentes quando aplicável

> WCAG 2.2 exige contraste mínimo de 4.5:1 para texto normal e 3:1 para texto grande, além de critérios de tamanho mínimo de alvo e aparência de foco. Botões pequenos, paginação, ícones de carrossel, minicart e fechamento de modal entram nesta verificação.

---

## Critério de "premium" (resumo executivo)

Um componente só é considerado **premium/aprovado** quando:

1. Passa em 100% dos itens de checklist da sua seção.
2. Não gera bug `P0` ou `P1` no relatório de auditoria.
3. Tem teste Playwright correspondente em `product-design-qa.spec.ts` (ou está documentado como exceção justificada).
4. Não introduz regressão em nenhuma outra seção (ver `docs/visual-qa/VISUAL_FIX_PLAN.md`).

## Rastreabilidade

- Régua de tokens/componentes: [`docs/product-design/AWA_PRODUCT_DESIGN_SYSTEM.md`](./AWA_PRODUCT_DESIGN_SYSTEM.md)
- Spec Playwright: [`tests/e2e/specs/product-design-qa.spec.ts`](../../tests/e2e/specs/product-design-qa.spec.ts)
- Relatório de auditoria: [`docs/product-design/PRODUCT_DESIGN_AUDIT_REPORT.md`](./PRODUCT_DESIGN_AUDIT_REPORT.md)
