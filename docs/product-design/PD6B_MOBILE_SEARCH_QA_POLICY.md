# PD6B — Mobile Search QA Policy (CI)

Última atualização: 2026-07-10
Branch de referência: `test/pd6b-mobile-search-ci-stabilization`

## Objetivo

Padronizar a validação de busca mobile no CI sem alterar código funcional da loja, separando:

- caminho estável que pode bloquear PR (gating);
- caminho de observabilidade que coleta sinal sem bloquear PR.

## Política definida

### 1) WebKit mobile (transicional, não bloqueante)

- Projeto: `mobile-390` (WebKit via `devices['iPhone 14']`)
- Spec: `tests/e2e/specs/pd6b-mobile-search-ci.spec.ts`
- Critério: autocomplete Mirasvit deve abrir após digitação de `bagageiro`.

Motivo: este caminho mostrou comportamento consistente para validar UX real de busca mobile no runner oficial.

### 2) Observabilidade (não bloqueante)

- Projeto: `mobile-390-chromium`
- Spec: `tests/e2e/specs/pd6b-mobile-search-ci.spec.ts`
- Execução: `continue-on-error: true` no workflow.

Motivo: manter visibilidade da fragilidade histórica do Chromium headless em touch (PD5/PD6) sem interromper fluxo de PR quando o problema for específico do engine em cenário de carga.

## Evidências coletadas

- Screenshots anexados automaticamente em `tests/e2e/test-results/product-design-qa/`
- JSON de evidência por execução: `pd6b-mobile-search-evidence.json`
- Artifact de CI: `product-design-qa-evidence`

## Regras de decisão

- Falha em qualquer projeto mobile nesta fase transicional => registrar como observabilidade, anexar artifact e abrir investigação (sem bloquear PR automaticamente).
- Promoção para gating só após 2 execuções consecutivas estáveis em CI para `mobile-390` (sem `Killed`/`interrupted`).
- Falha apenas no Chromium (`mobile-390-chromium`) segue como sinal de engine/headless enquanto a promoção não ocorrer.

## Fora de escopo desta fase

- Qualquer alteração em tema, JS de loja, CSS, layout XML, templates ou fluxo de negócio.
- Encerrar `PD2-002`, `PD5-001`, `PD6-001` como `CLOSED` sem rodada de CI com artifacts.


## Resultado local desta rodada

- `desktop-1440` com `pd6b-mobile-search-ci.spec.ts`: **passou** (autocomplete abriu).
- `mobile-390` e `mobile-390-chromium`: encerrados com `Killed` nesta VPS durante a fase de estabilização.
- Decisão: manter execução mobile em CI com artifacts e sem bloquear merge até estabilizar runner.
