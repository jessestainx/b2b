# Definition of Done — Correção Visual AWA Motos (multi-página)

Este documento define, sem ambiguidade, quando um item de
[`VISUAL_FIX_PLAN.md`](./VISUAL_FIX_PLAN.md) pode avançar de status — em especial para chegar a `CLOSED`.
Vale para todos os itens, independente do prefixo (`HEADER-*`, `FOOTER-*`, `HOME-*`, `PLP-*`) e da fase
(`P0`/`P1`/`P2`).

## Checklist de prova por item

Para cada item, antes de avançar de status, preencher:

### Prova da correção

- [ ] Bug reproduzido antes (screenshot + descrição).
- [ ] Causa raiz identificada (arquivo + linha — nunca "CSS genérico" ou "algo no tema").
- [ ] Arquivo fonte canônico corrigido (`app/code`, `app/design`, layout XML, `.phtml`, LESS fonte).
- [ ] `pub/static` e `var/view_preprocessed` **não** usados como fonte canônica (apenas destino de deploy).
- [ ] Static deploy executado conforme playbook do tema, se aplicável (`setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child`).
- [ ] Screenshot antes anexado em `evidence/<ID>/before-*.png`.
- [ ] Screenshot depois anexado em `evidence/<ID>/after-*.png`.
- [ ] Desktop testado (mínimo: `desktop-1440` ou `notebook-1366`).
- [ ] Mobile testado (mínimo: `mobile-390` ou `mobile-375`).
- [ ] Se o item é `global-header`/`global-footer`: validado em **pelo menos duas rotas diferentes**
      (ex.: Home `/` e uma PLP), já que a correção afeta todas as páginas.
- [ ] Playwright passou (local ou CI) cobrindo o critério de aceite do item.
- [ ] Sem CSS/JS retornando 404/403.
- [ ] Sem erro novo de CSP no console.
- [ ] Sem `pageerror` novo no console.
- [ ] Sem entrada nova em `exception.log`.
- [ ] Sem entrada nova em `system.log`.
- [ ] Commit vinculado (`evidence/<ID>/commit.txt`).
- [ ] PR vinculado (`evidence/<ID>/pr.txt`).
- [ ] Pendências (P2/nice-to-have descobertas durante a correção) registradas como item novo, não escondidas
      dentro do item fechado.

Isso evita a frase vaga "corrigido". **O agente precisa provar.**

## Regras de transição de status

O agente **não pode** marcar um item como `FIXED_SOURCE` se:
- a alteração existe apenas em `pub/static` ou `var/view_preprocessed`;
- a causa raiz não foi registrada com arquivo + linha;
- os arquivos fonte alterados não foram listados no item (`source_files` no YAML).

O agente **não pode** marcar um item como `TESTED_CI` se:
- não houver link ou ID do workflow/run do GitHub Actions;
- não houver artifact ou relatório Playwright anexado;
- o teste não cobrir o critério de aceite declarado no item.

O agente **não pode** marcar um item como `CLOSED` se:
- não houver screenshot depois;
- não houver validação desktop **e** mobile;
- for item global (`HEADER-*`/`FOOTER-*`) e não houver validação em mais de uma rota;
- houver erro novo no console;
- houver CSS/JS 404/403 novo;
- houver entrada nova em `exception.log` ou `system.log`;
- não houver commit/PR associado.

Se o agente não conseguir responder a todas as perguntas abaixo, o status máximo permitido é
`FIXED_SOURCE` — nunca `CLOSED`:

1. Qual é o ID do bug?
2. Qual era a causa raiz (arquivo + linha)?
3. Quais arquivos fonte foram alterados?
4. Qual screenshot antes?
5. Qual screenshot depois?
6. Qual teste Playwright valida isso?
7. Qual commit?
8. Qual PR?
9. Qual artifact/run do GitHub Actions (se aplicável)?
10. Houve erro novo em console/rede/logs Magento?
11. (Se item global) Em quais rotas foi validado?

## Estrutura de evidência por item

```
docs/visual-qa/evidence/<ID>/
├── before-desktop-1440.png
├── after-desktop-1440.png
├── after-mobile-390.png
├── playwright-report.txt
├── console-errors.txt
├── network-errors.txt
├── magento-logs.txt
├── commit.txt
└── pr.txt
```

Nem todo item precisa de todos os arquivos (ex.: itens P2 de art direction não precisam de
`playwright-report.txt` se não houver asserção automatizada), mas os campos que existirem devem ser reais,
não placeholders vazios "para constar".

## Regra específica para itens globais (Header/Footer)

Um bug em Header ou Footer **tem um único ID**, mesmo aparecendo em várias páginas. Antes de marcar
`CLOSED`, confirmar que a correção não regrediu em nenhuma página que compartilha o componente — no mínimo
Home (`/`) e uma PLP (`/bagageiros.html`). Não duplicar o mesmo bug com IDs diferentes por página.

## Critério para o humano aprovar ou rejeitar uma marcação do agente

Quando o agente disser "corrigido", exigir sempre as perguntas da seção anterior. Se qualquer resposta
faltar, rebaixar o status para `FIXED_SOURCE` e devolver o item para o agente completar a evidência.
