# Instruções de governança para o agente — Correção Visual AWA Motos

Estas instruções regem como qualquer agente (Cursor, Claude Code, Copilot, etc.) deve trabalhar nos itens de
[`VISUAL_FIX_PLAN.md`](./VISUAL_FIX_PLAN.md). Reforçadas pela regra `.cursor/rules/awa-visual-governance.mdc`,
que se aplica automaticamente quando arquivos em `docs/visual-qa/**` ou `tests/e2e/specs/*-visual-*.spec.ts`
estiverem abertos.

## Sequência segura de trabalho

1. Ler `VISUAL_FIX_PLAN.md` e `visual-fix-status.yml` (devem estar sincronizados).
2. Escolher **um item por vez**, começando pela Fase P0. Dentro de P0, priorizar itens **globais**
   (`HEADER-P0-*`, `FOOTER-P0-*`) antes dos itens específicos de página, pois uma correção global evita
   retrabalho em várias páginas.
3. Reproduzir o bug e capturar screenshot "antes" → status `REPRODUCED`.
4. Investigar causa raiz por leitura de código (nunca assumir) → registrar arquivo + linha no item.
5. Corrigir na fonte canônica (`app/code`, `app/design`, layout XML, `.phtml`, LESS fonte) → status `FIXED_SOURCE`.
6. Fazer deploy em staging conforme playbook do tema → status `DEPLOYED_STAGING`.
7. Testar localmente e/ou via CI com Playwright → status `TESTED_LOCAL`/`TESTED_CI`.
8. Se o item é global (`HEADER-*`/`FOOTER-*`), validar em mais de uma rota (Home + uma PLP no mínimo).
9. Validar em produção quando aplicável → status `VERIFIED_PROD`.
10. Preencher todas as evidências obrigatórias → só então `CLOSED`.
11. Passar para o próximo item.

**Não avançar de fase (P0 → P1 → P2) enquanto houver item P0 não `CLOSED`**, salvo decisão explícita do
humano registrada como `BLOCKED` com motivo.

## Regras não negociáveis

1. Não corrigir mais de um item por commit sempre que possível — facilita rollback e auditoria.
2. Não usar `pub/static` ou `var/view_preprocessed` como fonte de edição.
3. Não alterar `app/code/Rokanthemes/*`, `vendor/`, `node_modules/`.
4. Não criar arquivo LESS/CSS novo com data no nome — editar o arquivo já responsável pelo domínio (ver
   `.cursor/rules/awa-css-governance.mdc` para o mapa de bundles).
5. Não criar um segundo sistema de design tokens — os tokens já existem em `_tokens.less`
   (`--awa-radius-*`, `@awa-space-*`). Auditar e aplicar, não recriar.
6. Não usar `!important` sem comentário explicando o motivo.
7. Não misturar correção visual com a limpeza de CSS morto (escopo separado, ver Fase 6A / dead-css-manifest).
8. Não marcar `CLOSED` sem passar pelo checklist completo de
   [`visual-fix-definition-of-done.md`](./visual-fix-definition-of-done.md).
9. Ao encontrar um bug novo não catalogado, criar um novo item no YAML + Markdown (ID seguinte no mesmo
   prefixo/fase) em vez de resolver "de passagem" dentro de outro item.
10. Sempre atualizar `visual-fix-status.yml` **e** `VISUAL_FIX_PLAN.md` juntos — nunca só um dos dois.
11. Um bug em Header ou Footer é **um único item global** — não recriar o mesmo bug com ID diferente por
    página onde for observado. Referenciar o ID global e anotar a página no histórico do item, se necessário.
12. Ao corrigir um item global, testar em todas as páginas que o compartilham antes de marcar `CLOSED`
    (mínimo: Home `/` e uma PLP).

## Como reportar progresso ao humano

Ao final de cada item, o agente deve responder objetivamente:

- ID do item e novo status.
- Causa raiz (arquivo + linha).
- Arquivos alterados.
- Link/caminho dos screenshots antes/depois.
- Comando Playwright usado e resultado.
- Commit e (se aplicável) PR.
- Erros novos encontrados (console/rede/logs) — ou confirmação explícita de que não houve nenhum.
- (Se item global) em quais rotas foi validado.

Se qualquer um desses pontos não puder ser respondido, o status máximo é `FIXED_SOURCE`, nunca `CLOSED`
— ver regras de transição em `visual-fix-definition-of-done.md`.
