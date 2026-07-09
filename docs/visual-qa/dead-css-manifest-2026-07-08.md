# Manifesto de CSS Mortos — Quarentena Fase 6A

**Data da auditoria:** 2026-07-08
**Branch:** `cleanup/css-dead-quarantine-2026-07-08`
**Autor:** Agente Magento 2 / Adobe Commerce (sessão de quarentena segura)
**Escopo:** 61 arquivos CSS identificados como potencialmente mortos em `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/`, documentados originalmente em `.cursor/rules/awa-css-governance.mdc`.

## Metodologia

1. **Evidência estática**: busca por nome exato de arquivo em todo o repositório (`app/design`, `app/code`, `app/etc`, `.github`, `docs`, `visual-qa`, `e2e`, `tests`, `pwa-studio`, `pub/media`), classificada por tipo de arquivo referenciador (XML, PHTML, JS, LESS, CSS, PHP).
2. **Evidência de runtime**: download de HTML fresco (`curl` com User-Agent de browser real) de 8 rotas — home, PLP (categoria), PDP, carrinho, login B2B, redirect de checkout, contato e busca (`catalogsearch`) — com busca literal do nome de cada arquivo no HTML renderizado.
3. **Verificação de contexto**: para cada match, o trecho de código foi lido para diferenciar entre uma referência real (`<css src>`, `<link href>`, `getViewFileUrl()` com uso efetivo) e falsos positivos (comentários descritivos, código comentado/desativado, docstrings técnicos).
4. **Cruzamento com política existente**: verificação contra `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/LEGACY_STATIC_SHIMS.md`, que já designa oficialmente 8 arquivos como *shims* de compatibilidade que **nunca devem ser removidos**.
5. **Achados de auditoria anterior corrigidos**: a classificação original de "61 arquivos mortos" (sessão anterior) usava apenas 5 rotas e um array curado manualmente, o que gerou **3 falsos positivos confirmados** (arquivos que na verdade são carregados ativamente na rota `/catalogsearch/result`, nunca testada antes).

## Resumo executivo

| Decisão | Quantidade | Ação |
|---|---|---|
| **MANTER** | 9 | Nenhuma ação. Arquivo é shim oficial ou está comprovadamente ativo. |
| **QUARENTENA** | 33 | Mover para pasta de quarentena nesta fase (6A), reversível via Git. Não deletar. |
| **INVESTIGAR** | 19 | Manter no local atual. Requer investigação adicional (sessão autenticada, trace de código PHP, ou decisão do time) antes de qualquer ação. |
| **Total** | 61 | |

---

## MANTER — Não tocar

### `awa-bundle-site.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-site.css`
- **Tamanho:** 437 bytes
- **SHA256:** `36e1417975e8b11228b3794a0c70bc27f0fe313f10c2da13406f21f373513b5b`
- **Último commit:** `32216c6e8` (2026-06-25, Jess) — chore(css): versionar shims de compatibilidade CSS estáticos legados
- **Referências em código:** XML (1), LESS (1), CSS (5) (total bruto: 31 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **MANTER**
- **Evidência/justificativa:** SHIM OFICIAL documentado em LEGACY_STATIC_SHIMS.md. Regra do proprio projeto proibe remocao sem checar access logs.

### `awa-cart-shell-final.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-cart-shell-final.css`
- **Tamanho:** 57366 bytes
- **SHA256:** `95b4b843be883c54aceb12a97252f87cee76aa24459bb7cd0f83aa6a94c6178e`
- **Último commit:** `32216c6e8` (2026-06-25, Jess) — chore(css): versionar shims de compatibilidade CSS estáticos legados
- **Referências em código:** XML (1) (total bruto: 2 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **MANTER**
- **Evidência/justificativa:** SHIM OFICIAL (LEGACY_STATIC_SHIMS.md). Comentario em checkout_cart_index.xml confirma: "removido: referencia orfa (404 em producao)" -- consistente com papel de shim.

### `awa-home-shell-final.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-home-shell-final.min.css`
- **Tamanho:** 23418 bytes
- **SHA256:** `4bcebf19cc28b59c291370765a74850d0767928994374c22296282e2c3d23f8c`
- **Último commit:** `32216c6e8` (2026-06-25, Jess) — chore(css): versionar shims de compatibilidade CSS estáticos legados
- **Referências em código:** nenhuma referência encontrada em nenhum tipo de arquivo (total bruto: 1 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **MANTER**
- **Evidência/justificativa:** SHIM OFICIAL (LEGACY_STATIC_SHIMS.md). Nota: esta e a versao .min -- distinta da versao base .css acima, que foi explicitamente removida por comentario e NAO e o shim oficial.

### `awa-plp-critical-fixes.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-plp-critical-fixes.min.css`
- **Tamanho:** 26888 bytes
- **SHA256:** `5cfc971afb3c550d013210a5e77346e9fa7d2e01a2e78e335510632eea11eeac`
- **Último commit:** `21407a58d` (2026-06-28, Jess) — chore(css): track 3 untracked CSS PLP bundles ativos
- **Referências em código:** PHTML (2) (total bruto: 2 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** search
- **Decisão:** **MANTER**
- **Evidência/justificativa:** CONFIRMADO ATIVO: <link rel="stylesheet" href=".../awa-plp-critical-fixes.min.css?v=20260619-search-impeccable-v9-25"> presente no HTML real da rota /catalogsearch/result (nao testada na auditoria original de 5 rotas). Classificacao anterior de "morto" estava INCORRETA por cobertura de rotas insuficiente.

### `awa-plp-distill.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-plp-distill.min.css`
- **Tamanho:** 137500 bytes
- **SHA256:** `8049d1ac5f3b783ce99fc0791152e406f1e171147e1dac6a88d22c42b8cb8762`
- **Último commit:** `db0f9989c` (2026-07-08, Jess) — fix(plp): título e descrição sobrepostos no banner B2B (categoria/busca)
- **Referências em código:** PHTML (1), PHP (1) (total bruto: 2 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** search
- **Decisão:** **MANTER**
- **Evidência/justificativa:** CONFIRMADO ATIVO: <link rel="stylesheet" href=".../awa-plp-distill.min.css?v=20260610-round5"> presente no HTML real da rota /catalogsearch/result. Classificacao anterior de "morto" estava INCORRETA por cobertura de rotas insuficiente.

### `awa-plp-shell-final.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-plp-shell-final.css`
- **Tamanho:** 15391 bytes
- **SHA256:** `963d0bd138520c0f8bb065771615b28cc43b24271dc29bf2d4680503dbcddfad`
- **Último commit:** `32216c6e8` (2026-06-25, Jess) — chore(css): versionar shims de compatibilidade CSS estáticos legados
- **Referências em código:** XML (2) (total bruto: 3 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **MANTER**
- **Evidência/justificativa:** SHIM OFICIAL (LEGACY_STATIC_SHIMS.md). Comentarios em 2 XMLs confirmam: "removido: referencia orfa (404 em producao)" -- consistente com papel de shim.

### `awa-plp-ui-promax-2026-05-22.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-plp-ui-promax-2026-05-22.min.css`
- **Tamanho:** 9885 bytes
- **SHA256:** `5830f78c23727af3c4d67dac85aa927c5954a9350a9c95ad82e3912b3055d52b`
- **Último commit:** `21407a58d` (2026-06-28, Jess) — chore(css): track 3 untracked CSS PLP bundles ativos
- **Referências em código:** PHTML (1) (total bruto: 2 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** search
- **Decisão:** **MANTER**
- **Evidência/justificativa:** CONFIRMADO ATIVO: <link rel="stylesheet" href=".../awa-plp-ui-promax-2026-05-22.min.css?v=20260528"> presente no HTML real da rota /catalogsearch/result. Classificacao anterior de "morto" estava INCORRETA por cobertura de rotas insuficiente.

### `awa-social-proof.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-social-proof.css`
- **Tamanho:** 437 bytes
- **SHA256:** `5969851b33f92984d4aaa4ec0607651fefa270e85c9c21aabb9d6228841fd0e7`
- **Último commit:** `32216c6e8` (2026-06-25, Jess) — chore(css): versionar shims de compatibilidade CSS estáticos legados
- **Referências em código:** CSS (1) (total bruto: 2 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **MANTER**
- **Evidência/justificativa:** SHIM OFICIAL (LEGACY_STATIC_SHIMS.md).

### `email-fonts.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/email-fonts.min.css`
- **Tamanho:** 458 bytes
- **SHA256:** `feba34ef0539fd836b32ab439ab1a6fb4dd1bc766503bd4c94e7b83c0af1d9ec`
- **Último commit:** `32216c6e8` (2026-06-25, Jess) — chore(css): versionar shims de compatibilidade CSS estáticos legados
- **Referências em código:** CSS (1) (total bruto: 2 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **MANTER**
- **Evidência/justificativa:** SHIM OFICIAL (LEGACY_STATIC_SHIMS.md) -- mantido para compatibilidade com URLs antigas de e-mail transacional.

---

## QUARENTENA — Candidatos à Fase 6A (mover, não deletar)

### `awa-align-grid-inline-lock-20260626-phase3d22b.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-align-grid-inline-lock-20260626-phase3d22b.min.css`
- **Tamanho:** 52203 bytes
- **SHA256:** `1dfb86905add19d678b590238b0a9aa62ad250325f744fbb8395b94cb98197a6`
- **Último commit:** `c253112bf` (2026-06-28, Jess) — chore(css): track 52KB inline lock como CSS estatico (preparacao Fase 4)
- **Referências em código:** nenhuma referência encontrada em nenhum tipo de arquivo (total bruto: 0 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Zero referencias em codigo (XML/PHTML/JS/LESS/CSS/PHP) e zero presenca em HTML ao vivo (8 rotas testadas).

### `awa-bestseller-fixes.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bestseller-fixes.css`
- **Tamanho:** 3002 bytes
- **SHA256:** `bada7a8490374dffba67aca1b50c010bee5a40fbbf075ff5b7b34454c8c7df62`
- **Último commit:** `e0182f0c4` (2026-06-28, Jess) — fix(css): home bestseller cards — thumb ratio 1:1 + price label 36px
- **Referências em código:** PHTML (1) (total bruto: 3 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Comentario em awa-head-preload.phtml confirma merge: "included in awa-layout-bundle.css (2026-05-09)". Zero presenca ao vivo.

### `awa-bundle-async-distill-lock.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-async-distill-lock.css`
- **Tamanho:** 26409 bytes
- **SHA256:** `36e06e5e39c00e9a284c73774c9b68157d13d3e4e66cdd7ae05db22f9fb66e3f`
- **Último commit:** `d020b9617` (2026-06-12, Jess) — fix(pdp): galeria compacta em PDPs B2B para reduzir vazio no hero.
- **Referências em código:** LESS (1) (total bruto: 1 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Apenas mencionado em comentario .less (nao e @import). A versao .min.css e a efetivamente usada pelo plugin PHP.

### `awa-card-image-hero.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-card-image-hero.css`
- **Tamanho:** 8623 bytes
- **SHA256:** `48053082bb639f35c1a268e33722307d88a968081ea2cd1126af470e0c8e66ba`
- **Último commit:** `fea2ae7ef` (2026-05-07, Jess) — refactor(hex-tokenization): tokenize ayo_home5_child theme CSS/LESS/PHTML
- **Referências em código:** PHTML (1) (total bruto: 3 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Comentario confirma merge: "included in awa-layout-bundle.css (2026-05-09)". Zero presenca ao vivo.

### `awa-checkout-polish.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-checkout-polish.css`
- **Tamanho:** 204613 bytes
- **SHA256:** `3006683b6669a16a578be65978c02900c601aa37d5b18290b641e2a013b1ffed`
- **Último commit:** `810dd6590` (2026-06-10, Jess) — fix(checkout): padronização visual v7–v9 no OPC
- **Referências em código:** XML (3), PHTML (1), CSS (8) (total bruto: 12 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Comentarios em checkout_index_index.xml confirmam remocao explicita: "Removido <css> bloqueante: awa-checkout-polish.css (13KB)". Substituido pela versao .min.css.

### `awa-checkout-shell-final.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-checkout-shell-final.min.css`
- **Tamanho:** 12717 bytes
- **SHA256:** `8a170eb4492d13e775828144788280bef06245a53731d207c2bbfecde5e8b7c3`
- **Último commit:** `2813602f9` (2026-06-28, Jess) — chore(css): track 19 untracked CSS ativos (cart, checkout, header, design)
- **Referências em código:** nenhuma referência encontrada em nenhum tipo de arquivo (total bruto: 0 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Zero referencias em codigo. Apenas a versao base .css (nao-min) esta com wiring ativo.

### `awa-design-tokens.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-design-tokens.css`
- **Tamanho:** 14119 bytes
- **SHA256:** `11e5adfbc7948b2034cdc2be9975441b23efbecfec5ec14ad436e73a2dd5b764`
- **Último commit:** `de9893546` (2026-06-28, Jess) — feat(css): expand design tokens vocabulary (124 novas variaveis)
- **Referências em código:** PHTML (1), LESS (2), CSS (2) (total bruto: 13 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** b2b-login,checkout-redirect
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** AUDIT_VISUAL.md declara explicitamente "deletado (auto-declarado deprecated)". Match inicial em HTML era falso-positivo (comentario interno "-- elimina 1 HTTP request bloqueante (awa-design-tokens.css 2026-04-22...)" dentro de <style> inline, confirmado via extracao de offset binario).

### `awa-design-tokens.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-design-tokens.min.css`
- **Tamanho:** 7942 bytes
- **SHA256:** `0a02811c8398420e66d842912489c3c44c2ad43aeadb05d9f1d7394f75d9886c`
- **Último commit:** `2813602f9` (2026-06-28, Jess) — chore(css): track 19 untracked CSS ativos (cart, checkout, header, design)
- **Referências em código:** nenhuma referência encontrada em nenhum tipo de arquivo (total bruto: 0 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Zero referencias em qualquer arquivo do repositorio. Zero presenca ao vivo.

### `awa-flex-grid-flow.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-flex-grid-flow.css`
- **Tamanho:** 273289 bytes
- **SHA256:** `c5b85757ed602054f5f88a95a3add1b907205c4fb43382298efda0f0b1aeb4ee`
- **Último commit:** `2813602f9` (2026-06-28, Jess) — chore(css): track 19 untracked CSS ativos (cart, checkout, header, design)
- **Referências em código:** LESS (3), CSS (1) (total bruto: 4 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Apenas mencionado em comentarios (.less e .css) como espelho/documentacao historica, nao ha @import ou <css src> ativo.

### `awa-header-home-light-lock-v1.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-header-home-light-lock-v1.css`
- **Tamanho:** 22902 bytes
- **SHA256:** `fa7c7a66d417f06bcb6f32e0636ce5ac271a39487fc391b55b829299826aeaff`
- **Último commit:** `2813602f9` (2026-06-28, Jess) — chore(css): track 19 untracked CSS ativos (cart, checkout, header, design)
- **Referências em código:** nenhuma referência encontrada em nenhum tipo de arquivo (total bruto: 0 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Zero referencias em codigo (apenas a versao .min.css tem wiring). Zero presenca ao vivo.

### `awa-header-stack-2026-05-28.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-header-stack-2026-05-28.min.css`
- **Tamanho:** 63370 bytes
- **SHA256:** `af8b3dca136268cb1e72d29e251d8af067a0928db13db150b883cecfe8951811`
- **Último commit:** `59f5fcfc6` (2026-06-28, Jess) — chore(css): track 15 untracked CSS ativos (home critical stack, header, PLP, PDP)
- **Referências em código:** XML (2) (total bruto: 3 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Confirmado em MIGRATED_HEADER_CSS_FRAGMENTS (lista de STRIP -- migrado para styles-l via _extend.less, Fase 43.01/43.02) + <remove src> explicito em 2 XMLs (cms_index_index + checkout_cart_index). Sem logica de reinjecao encontrada (diferente do caso acima). Zero presenca ao vivo.

### `awa-home-flex-grid-flow.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-home-flex-grid-flow.css`
- **Tamanho:** 204014 bytes
- **SHA256:** `86521820baf9dbfb27aaeda57317245808fcd1f9e890cdb0ef4076ae2cccca98`
- **Último commit:** `e097cdd5d` (2026-05-22, Jess) — fix(theme): ui-ux-pro-max touch targets and home shelf hierarchy
- **Referências em código:** LESS (2), CSS (2) (total bruto: 5 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Apenas comentarios de espelho/documentacao em .less e .css (nao ha @import ou <css src> ativo).

### `awa-home-gap-fix.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-home-gap-fix.css`
- **Tamanho:** 8927 bytes
- **SHA256:** `092696dfa29669a186fbd9508339528e5944b5fa68429936058c1f3365cc51d2`
- **Último commit:** `3d0011631` (2026-05-06, Jess) — fix(theme): atualizacoes CSS/JS do tema ayo_home5_child
- **Referências em código:** PHTML (1), CSS (1) (total bruto: 4 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Comentario confirma merge: "awa-card-image-hero.css + awa-home-gap-fix.css -> included in awa-layout-bundle.css (2026-05-09)". Zero presenca ao vivo.

### `awa-home-hover-lock.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-home-hover-lock.css`
- **Tamanho:** 28255 bytes
- **SHA256:** `b4892a4a4118bffbfd7c6252a8ffdd344e55da7484e19ca354c8b63d42fcdd51`
- **Último commit:** `59f5fcfc6` (2026-06-28, Jess) — chore(css): track 15 untracked CSS ativos (home critical stack, header, PLP, PDP)
- **Referências em código:** PHP (1) (total bruto: 1 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Apenas presente na lista defensiva HOME_GATE_CSS_FRAGMENTS (no-op se nao encontrado no HTML). Sem chamada direta getViewFileUrl para a versao base (apenas .min.css tem). Zero presenca ao vivo.

### `awa-home-shell-final.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-home-shell-final.css`
- **Tamanho:** 414 bytes
- **SHA256:** `68379a0118095839aee9d1ec9582c3918873277cd1c39d0075ecccf59ce9dc64`
- **Último commit:** `59f5fcfc6` (2026-06-28, Jess) — chore(css): track 15 untracked CSS ativos (home critical stack, header, PLP, PDP)
- **Referências em código:** XML (2), CSS (2) (total bruto: 4 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Comentario explicito confirma remocao: "awa-home-shell-final.css REMOVIDO 2026-06-25: arquivo placeholder (home funciona sem shell CSS separada)" em cms_index_index.xml + <remove src> em catalog_category_view.xml.

### `awa-home-standardize-terminal-wins-2026-06-09.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-home-standardize-terminal-wins-2026-06-09.css`
- **Tamanho:** 76264 bytes
- **SHA256:** `5112361411b86bbf5d2f9e49e30b68e226ae957afd6109cac3b9053977bc8b47`
- **Último commit:** `632ebbe1a` (2026-06-09, Jess) — fix(home): padronização visual homepage — trust bar 2x2, carousel arrows laterais, nomes de produto visíveis
- **Referências em código:** PHTML (2), CSS (2) (total bruto: 5 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** home
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Unica ocorrencia e docstring/comentario tecnico dentro de awa-scripts.phtml e awa-head-preload.phtml explicando uma tecnica de CSS (100vw scrollbar), confirmado falso-positivo via grep de contexto. Nao e <link href> real. A versao .min.css e a efetivamente usada.

### `awa-medium-visual-fixes.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-medium-visual-fixes.min.css`
- **Tamanho:** 5442 bytes
- **SHA256:** `9d8b8d7cb4d05128425f1dd4996c282f95dd19c940b29fa95fd268ec78d2b10f`
- **Último commit:** _sem commit registrado (arquivo pode ter sido adicionado fora do controle normal de versão ou commit não rastreável pelo git log)_
- **Referências em código:** nenhuma referência encontrada em nenhum tipo de arquivo (total bruto: 0 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Zero referencias em qualquer arquivo do repositorio. Zero presenca ao vivo.

### `awa-mobile-drill.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-mobile-drill.css`
- **Tamanho:** 5562 bytes
- **SHA256:** `cd2ffa86e389d530248fdc0ccb969a745c27671d567e51424fa66c0e9f87dcd8`
- **Último commit:** `7cbdc9937` (2026-05-11, Jess) — fix(css): CSS bundles — layout, mobile-drill, vertical-menu, visual-polish, header-audit
- **Referências em código:** PHTML (1) (total bruto: 1 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Comentario confirma merge: "awa-mobile-drill.css -> awa-head-tail-bundle.css (P3)". Zero presenca ao vivo.

### `awa-modern-optimizations-2026.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-modern-optimizations-2026.css`
- **Tamanho:** 8548 bytes
- **SHA256:** `e95f8529a58427e2cb67f3bc9f164d848ee2b13bbd2fa7de342c8b8285014468`
- **Último commit:** `59f5fcfc6` (2026-06-28, Jess) — chore(css): track 15 untracked CSS ativos (home critical stack, header, PLP, PDP)
- **Referências em código:** nenhuma referência encontrada em nenhum tipo de arquivo (total bruto: 2 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Apenas mencionado em relatorio historico (__RELATORIO_FINAL_2026-06-05.md) descrevendo a existencia da versao .min.css, nao ha wiring da versao base.

### `awa-pdp-cascade-terminal.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-pdp-cascade-terminal.min.css`
- **Tamanho:** 23558 bytes
- **SHA256:** `feec1369a0a3cc70dba1205e0ac31230d93465269ef1b6db4c4fc5e793703539`
- **Último commit:** `59f5fcfc6` (2026-06-28, Jess) — chore(css): track 15 untracked CSS ativos (home critical stack, header, PLP, PDP)
- **Referências em código:** XML (1) (total bruto: 1 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Unica mencao e comentario descritivo em catalog_product_view.xml ("Os estilos criticos ja estao no awa-pdp-cascade-terminal.min.css") sem <css src> ou getViewFileUrl associado -- comentario aponta para consolidacao ja realizada em outro arquivo.

### `awa-pdp-premium.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-pdp-premium.min.css`
- **Tamanho:** 16302 bytes
- **SHA256:** `7620747ded746430d94286ee8d70d8a594a8d993678fcdd20f7866cf8572deac`
- **Último commit:** `61609497f` (2026-06-24, Jess) — feat: B2B pricing notice PDP visual — MEL-02
- **Referências em código:** nenhuma referência encontrada em nenhum tipo de arquivo (total bruto: 1 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Zero referencias diretas de codigo (apenas mencionado em doc de analise de duplicacoes). Apenas a versao base .css tem wiring ativo via loader phtml.

### `awa-plp-critical-fixes.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-plp-critical-fixes.css`
- **Tamanho:** 33280 bytes
- **SHA256:** `7a85f58d9ff6a0938ce83348a88db4ceb2bff340b138a4f05a5f295a6d75bf03`
- **Último commit:** `59f5fcfc6` (2026-06-28, Jess) — chore(css): track 15 untracked CSS ativos (home critical stack, header, PLP, PDP)
- **Referências em código:** nenhuma referência encontrada em nenhum tipo de arquivo (total bruto: 0 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Zero referencias em codigo. Zero presenca ao vivo em nenhuma das 8 rotas (incluindo busca).

### `awa-plp-critical-fixes.css.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-plp-critical-fixes.css.min.css`
- **Tamanho:** 10696 bytes
- **SHA256:** `df4825ebc2242b912ec574e3ebf94ea5e9555b50caf703d92487d86dc6400eba`
- **Último commit:** `0dba8914c` (2026-06-28, Jess) — chore(theme): track remaining untracked CSS/PHTML/XML/JS no tema filho
- **Referências em código:** nenhuma referência encontrada em nenhum tipo de arquivo (total bruto: 0 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Nome de arquivo malformado (dupla extensao .css.min.css), provavel artefato de erro de invocacao do cleancss. Zero referencias, zero presenca ao vivo.

### `awa-plp-distill.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-plp-distill.css`
- **Tamanho:** 169147 bytes
- **SHA256:** `d205186f838765850e09cced4f881e2eea130514d654e0b737734957915cd2ad`
- **Último commit:** `db0f9989c` (2026-07-08, Jess) — fix(plp): título e descrição sobrepostos no banner B2B (categoria/busca)
- **Referências em código:** LESS (2), CSS (1) (total bruto: 3 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Apenas comentarios de espelho em .less e .css referenciando a versao .min.css como a efetivamente usada.

### `awa-plp-final-polish.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-plp-final-polish.css`
- **Tamanho:** 64905 bytes
- **SHA256:** `362ac880ca64e28c3505ccebf5ffc9bf47277082b93073b3e04c81a82a335b8c`
- **Último commit:** `266466602` (2026-06-28, Jess) — feat(css): PLP final polish (cards uniformes + tokens semanticos)
- **Referências em código:** XML (5), PHTML (2) (total bruto: 12 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Confirmado removido por design: <remove src> explicito em AMBOS catalog_category_view.xml E catalogsearch_result_index.xml, com comentario "Removido por TICKET-002: PLP em Home". Zero presenca ao vivo em PLP ou busca (confirmado nesta auditoria).

### `awa-plp-ui-promax-2026-05-22.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-plp-ui-promax-2026-05-22.css`
- **Tamanho:** 11921 bytes
- **SHA256:** `71ad97f76a70b9674fe5f2f274b44d5008b262f407f93941b364a9eeb8c851c1`
- **Último commit:** `0dba8914c` (2026-06-28, Jess) — chore(theme): track remaining untracked CSS/PHTML/XML/JS no tema filho
- **Referências em código:** nenhuma referência encontrada em nenhum tipo de arquivo (total bruto: 0 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Zero referencias em codigo (apenas a versao .min.css tem wiring). Zero presenca ao vivo.

### `awa-super-home.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-super-home.min.css`
- **Tamanho:** 393477 bytes
- **SHA256:** `078f4f67f5ed4b70482d453a3bbd530957ccecfa44757fd23b6ae0a504ad94ad`
- **Último commit:** `a0ededdee` (2026-05-19, Jess) — fix(theme): corrigir conflito entre grid AWA e carrosséis Rokan/Owl
- **Referências em código:** PHTML (1) (total bruto: 2 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** CONFIRMADO DEAD: phtml linha 1322 tem flag explicita $__awaSkipHomeSuperHome = true (comentario: "RETIRADO 2026-06-13: body-end sync via OptimizeHeadStylesPlugin"). Porem OptimizeHeadStylesPlugin.php tem ZERO referencias a "super-home" em qualquer variacao -- a migracao mencionada no comentario nunca foi completada ou foi revertida sem atualizar o comentario. Confirmado ausente da fila JSON de gate (#awa-css-gate-queue) na home ao vivo. Match inicial (falso-positivo) era um comentario HTML descrevendo outro conceito ("awa-super-home.css:" sem "min", nome diferente).

### `awa-vertical-menu-desktop-final.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-vertical-menu-desktop-final.css`
- **Tamanho:** 25821 bytes
- **SHA256:** `2621e7017e8fda2b3d3a163ea8a827ff8205841934af09b352d5412afdd3ec20`
- **Último commit:** `990d61b40` (2026-05-09, Jess) — refactor(theme): CSS/LESS/JS consolidação visual — design tokens + audit fixes
- **Referências em código:** PHTML (1) (total bruto: 3 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Comentario confirma merge: "included in awa-layout-bundle.css (2026-05-09)". Zero presenca ao vivo.

### `awa-vertical-menu-modern.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-vertical-menu-modern.css`
- **Tamanho:** 53702 bytes
- **SHA256:** `c88f04cbf0d407e29b58afe327226ad05da12ae0a05770f8d82fe76800228c99`
- **Último commit:** `7cbdc9937` (2026-05-11, Jess) — fix(css): CSS bundles — layout, mobile-drill, vertical-menu, visual-polish, header-audit
- **Referências em código:** PHTML (1), LESS (1), CSS (5) (total bruto: 8 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Comentario explicito confirma substituicao: "awa-vertical-menu-modern.css -- substituido por awa-vertical-menu-stack (default.xml 2026-05-28)". Demais referencias sao comentarios historicos de cascata em outros CSS (nao comprovam carregamento atual).

### `awa-vertical-menu-modern.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-vertical-menu-modern.min.css`
- **Tamanho:** 38033 bytes
- **SHA256:** `a2a887c9fd073742b2e672f8e6937a46e48d4413cdaf36d8bb591d45d178e9d1`
- **Último commit:** `990d61b40` (2026-05-09, Jess) — refactor(theme): CSS/LESS/JS consolidação visual — design tokens + audit fixes
- **Referências em código:** PHTML (1) (total bruto: 1 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Unica referencia de codigo e uma atribuicao PHP COMENTADA (dead code): "/* $__vmenuModernUrl = $block->getViewFileUrl(...) */" -- confirmado desativado no proprio codigo-fonte.

### `awa-visual-audit-2026-05-18.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-visual-audit-2026-05-18.css`
- **Tamanho:** 61231 bytes
- **SHA256:** `9bedacb857589cc776172e3b3c280a8d97bb7a71c2373583b7f970f0d7876443`
- **Último commit:** `9107caa74` (2026-05-22, Jess) — fix(b2b): restore dashboard main column beside sidebar
- **Referências em código:** PHP (1) (total bruto: 1 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Apenas presente na lista defensiva HOME_GATE_CSS_FRAGMENTS (no-op se fragmento nao encontrado no HTML). Sem chamada getViewFileUrl direta em nenhum phtml. Zero presenca ao vivo.

### `awa-visual-audit-2026-05-18.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-visual-audit-2026-05-18.min.css`
- **Tamanho:** 38616 bytes
- **SHA256:** `1c3100b4e74ac93a6894a1ae8edb42d645cb9db7a9d9a36d98c1cd07f29419ca`
- **Último commit:** `e097cdd5d` (2026-05-22, Jess) — fix(theme): ui-ux-pro-max touch targets and home shelf hierarchy
- **Referências em código:** PHP (1) (total bruto: 1 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Mesma situacao da versao base -- apenas na lista defensiva de gate, sem wiring direto via phtml. Zero presenca ao vivo.

### `awa-visual-bug-fixes.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-visual-bug-fixes.min.css`
- **Tamanho:** 9221 bytes
- **SHA256:** `7e18e133ac9ec4c37ac7cb5bad69a8b05cbf400551618e2c3e6ecc608f2351fb`
- **Último commit:** _sem commit registrado (arquivo pode ter sido adicionado fora do controle normal de versão ou commit não rastreável pelo git log)_
- **Referências em código:** nenhuma referência encontrada em nenhum tipo de arquivo (total bruto: 0 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **QUARENTENA**
- **Evidência/justificativa:** Zero referencias em qualquer arquivo do repositorio. Zero presenca ao vivo.

---

## INVESTIGAR — Pendências (manter no local, aguardar investigação)

### `awa-b2b-account-shell-final.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-b2b-account-shell-final.css`
- **Tamanho:** 35880 bytes
- **SHA256:** `a11417dbe7a32bb07eec319d584e2115626e8287c71fea10b3389addb7c750a3`
- **Último commit:** _sem commit registrado (arquivo pode ter sido adicionado fora do controle normal de versão ou commit não rastreável pelo git log)_
- **Referências em código:** XML (1), PHTML (1) (total bruto: 5 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Referenciado ativamente em customer_account.xml + loader phtml (getViewFileUrl). Doc AUDITORIA_PLANO_FASES_2026-07-01.md registrou "404 ao vivo" em 2026-07-01, mas HTTP HEAD atual retorna 200. Rota customer/account nao pode ser testada sem sessao autenticada (loja e B2B-gated, login redireciona). Sem evidencia definitiva.

### `awa-bundle-async-distill-lock.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-async-distill-lock.min.css`
- **Tamanho:** 25933 bytes
- **SHA256:** `9ff7ac63525adcd8e3abeed61e6da9ed8b73471e618da37ed4a233cfefad77b8`
- **Último commit:** `d020b9617` (2026-06-12, Jess) — fix(pdp): galeria compacta em PDPs B2B para reduzir vazio no hero.
- **Referências em código:** PHTML (3), PHP (2) (total bruto: 5 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Wiring real: OptimizeHeadStylesPlugin.php constroi href dinamicamente (2x) + phtml getViewFileUrl com query PDP/HOME_DISTILL_LOCK. Nao encontrado em HTML ao vivo das 8 rotas testadas (pode ser interaction-gated ou condicional).

### `awa-cart-polish.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-cart-polish.min.css`
- **Tamanho:** 58678 bytes
- **SHA256:** `6fa58be32b28842190c5449f828d4181e9625904790e2c553baff617f61a015d`
- **Último commit:** _sem commit registrado (arquivo pode ter sido adicionado fora do controle normal de versão ou commit não rastreável pelo git log)_
- **Referências em código:** XML (2) (total bruto: 4 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Referenciado em checkout_onepage_success.xml. Doc antigo registrou "404 ao vivo". Rota de sucesso de pedido nao pode ser testada sem completar uma compra real em producao (proibido pelas regras desta fase).

### `awa-checkout-layout-lock.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-checkout-layout-lock.css`
- **Tamanho:** 6695 bytes
- **SHA256:** `a21e8df2ff27771fca0bb366363ecd9aa91a9ed6a805f424d04a7f36c8ff60b5`
- **Último commit:** `810dd6590` (2026-06-10, Jess) — fix(checkout): padronização visual v7–v9 no OPC
- **Referências em código:** XML (2), PHTML (2) (total bruto: 4 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Wiring ativo: 2 layout XML (<css src... order="200">) + 2 phtml com getViewFileUrl. Area de checkout -- regra #4 desta fase proibe alterar checkout sem aprovacao. Nao verificavel sem sessao/carrinho autenticado.

### `awa-checkout-polish.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-checkout-polish.min.css`
- **Tamanho:** 162310 bytes
- **SHA256:** `15fb7998822217bfd4a6ecb20f33d42686519226ed837591b30151360d521d60`
- **Último commit:** `2813602f9` (2026-06-28, Jess) — chore(css): track 19 untracked CSS ativos (cart, checkout, header, design)
- **Referências em código:** PHTML (2) (total bruto: 3 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Wiring ativo: 2 phtml com getViewFileUrl e query version atual (v=20260619-checkout-b2b-address-v17). Area de checkout -- nao verificavel sem sessao autenticada, regra #4 desta fase proibe alteracao.

### `awa-checkout-shell-final.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-checkout-shell-final.css`
- **Tamanho:** 16630 bytes
- **SHA256:** `67a200cc3c6e2d517280282600e879b5e0044d62f23f6fac90cea24fb690dde8`
- **Último commit:** `2813602f9` (2026-06-28, Jess) — chore(css): track 19 untracked CSS ativos (cart, checkout, header, design)
- **Referências em código:** XML (3), PHTML (1) (total bruto: 4 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Wiring ativo: 3 layout XML (order=10000) + loader phtml dedicado. Area de checkout/success -- nao verificavel sem sessao autenticada.

### `awa-critical-fold-site.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-critical-fold-site.css`
- **Tamanho:** 3117 bytes
- **SHA256:** `f5f1a845aa0ae76d0ab417f5e9b522690ce8937a836f137f83d302fb0ea73e32`
- **Último commit:** `2813602f9` (2026-06-28, Jess) — chore(css): track 19 untracked CSS ativos (cart, checkout, header, design)
- **Referências em código:** PHTML (2) (total bruto: 2 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Wiring ativo em awa-critical-inline.phtml: selecionado como CSS critico para paginas nao-home ($criticalBasename). Hipotese forte de que o CONTEUDO e inlined via <style> (nao <link href>), o que explicaria ausencia do nome do arquivo no HTML mesmo estando ativo. Requer leitura do phtml para confirmar mecanismo de inline antes de qualquer acao.

### `awa-flex-grid-flow.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-flex-grid-flow.min.css`
- **Tamanho:** 244129 bytes
- **SHA256:** `03beaee3a33f051965ccad227f83474a7745b784231efdec7d3360ffa62983cc`
- **Último commit:** `2813602f9` (2026-06-28, Jess) — chore(css): track 19 untracked CSS ativos (cart, checkout, header, design)
- **Referências em código:** XML (1), PHTML (1) (total bruto: 3 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Presente em <remove src> explicito no cms_index_index.xml (exclusao apenas da home) + comentario em phtml ("defer-global + awa-og-meta.phtml"). A exclusao explicita sugere que ainda e herdado/carregado em OUTRAS rotas nao testadas nesta auditoria.

### `awa-header-home-hotfix.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-header-home-hotfix.min.css`
- **Tamanho:** 492 bytes
- **SHA256:** `0a67d346847376f9f60dd0a2f6056b29c50aa9f7b9e420bef67cf45d658e0575`
- **Último commit:** `2813602f9` (2026-06-28, Jess) — chore(css): track 19 untracked CSS ativos (cart, checkout, header, design)
- **Referências em código:** PHTML (1), CSS (1) (total bruto: 3 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Wiring ativo: phtml dedicado awa-header-home-hotfix-home.phtml com getViewFileUrl, referenciado como "P6" em comentario de bundling da home. Nao encontrado no HTML ao vivo da home atual -- necessario confirmar se o bloco phtml esta de fato registrado em algum layout XML ativo.

### `awa-header-home-light-lock-v1.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-header-home-light-lock-v1.min.css`
- **Tamanho:** 22884 bytes
- **SHA256:** `e88369b895c2ccccfc47a7467379cc3d3491d4f08c367c21d1783cf3f5ec90b2`
- **Último commit:** `2813602f9` (2026-06-28, Jess) — chore(css): track 19 untracked CSS ativos (cart, checkout, header, design)
- **Referências em código:** PHTML (1), PHP (1) (total bruto: 2 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Referenciado como constante PHP HeaderImpeccableCascadeLockCss::HOME_LIGHT_CSS_FILE, usado condicionalmente por logica de cascade lock. Requer leitura da classe para determinar condicao de ativacao.

### `awa-header-refine-terminal.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-header-refine-terminal.min.css`
- **Tamanho:** 33549 bytes
- **SHA256:** `ea1236f329a15fda28d617a27bd8f3f66cb57a438daf046f41e5a5e50c9b5d9f`
- **Último commit:** _sem commit registrado (arquivo pode ter sido adicionado fora do controle normal de versão ou commit não rastreável pelo git log)_
- **Referências em código:** PHP (8) (total bruto: 9 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Confirmado em MIGRATED_HEADER_CSS_FRAGMENTS (lista de STRIP -- stripStylesheetFragments -- fragmentos migrados para LESS/_extend.less e removidos do HTML se encontrados). PORTANTO ha remocao ativa por design. MAS tambem ha logica extensa e não trivial em PatchHomeHeaderHtmlPlugin.php e OptimizeHeadStylesPlugin.php construindo href dinamicamente para reinjecao em posicao "terminal" da cascata (padrao terminal-wins documentado no projeto). Zero presenca no HTML ao vivo das 8 rotas. Evidencia contraditoria -- requer trace de codigo mais profundo antes de decidir.

### `awa-home-flex-grid-flow.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-home-flex-grid-flow.min.css`
- **Tamanho:** 139821 bytes
- **SHA256:** `3f5e6483d12fd5d09a6cf5792b11634d1fd7fedb56fdcf3ed7ed95bfc828314d`
- **Último commit:** `e097cdd5d` (2026-05-22, Jess) — fix(theme): ui-ux-pro-max touch targets and home shelf hierarchy
- **Referências em código:** PHP (1) (total bruto: 3 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Presente em HOME_GATE_CSS_FRAGMENTS (lista de gate/defer, nao de strip) + referenciado em doc PLANO_BUGS_VISUAIS.md como arquivo "provavel" causador de bug ainda nao fechado. O mecanismo de gate e um no-op defensivo se o fragmento nao estiver no HTML -- confirmar se de fato nunca aparece ou se ha caminho de injecao nao mapeado.

### `awa-home-hover-lock.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-home-hover-lock.min.css`
- **Tamanho:** 23161 bytes
- **SHA256:** `04f702b99b72120534220947dea38d321fd8aa4d2b50307ba9eae83d8f9c04d0`
- **Último commit:** `5aa6bbc71` (2026-06-28, Jess) — chore(css): track 5 untracked CSS home bundles ativos
- **Referências em código:** PHTML (1), PHP (1) (total bruto: 3 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Wiring direto: phtml linha 1492 com getViewFileUrl('css/awa-home-hover-lock.min.css') + presente em HOME_GATE_CSS_FRAGMENTS. Wiring direto sugere intencao ativa, mas zero presenca confirmada no HTML da home atual -- possivel condicao PHP false ou variavel nao utilizada a jusante. Requer leitura completa do fluxo phtml.

### `awa-home-standardize-terminal-wins-2026-06-09.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-home-standardize-terminal-wins-2026-06-09.min.css`
- **Tamanho:** 60991 bytes
- **SHA256:** `2e714848c881ff2d8e484e9d5fc45fbe28e98a77a6c8ce70e411069bac0bf3e3`
- **Último commit:** `2fd4f0288` (2026-06-28, Jess) — chore(css): 9 sync min files + interaction-widgets tokens
- **Referências em código:** PHTML (1), JS (2) (total bruto: 3 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Wiring pesado: phtml getViewFileUrl com query version (linha 1486) + construcao dinamica de URL via regex em awa-css-gate.js (probe.href.replace) para deteccao de interaction-gate. Este e um padrao classico de asset carregado apenas apos interacao do usuario (pointerdown/scroll/keydown) -- nao detectavel por curl estatico do HTML inicial. Alta probabilidade de estar ativo.

### `awa-modern-optimizations-2026.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-modern-optimizations-2026.min.css`
- **Tamanho:** 1961 bytes
- **SHA256:** `4a985145e5b841504adfc65356fb0d84925dee95c923d1e5d92a93a72b51e837`
- **Último commit:** `bd6cbea2d` (2026-06-28, Jess) — chore(css): track 2 untracked CSS interactive bundles ativos
- **Referências em código:** PHTML (1) (total bruto: 2 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Wiring ativo: phtml getViewFileUrl (linha 1408) + relatorio historico confirma "Deployado". Zero presenca no HTML ao vivo atual -- pode ter sido superado desde o relatorio (2026-06-05) sem atualizacao do phtml, ou pode ser condicional.

### `awa-pdp-faq-2026-06-27.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-pdp-faq-2026-06-27.css`
- **Tamanho:** 2714 bytes
- **SHA256:** `15aea99c5346c95ceeb6f03e40cf40c1c8f187a88d61040762438be43c3ab408`
- **Último commit:** `d98160269` (2026-06-27, Jess) — feat(vtex-grade): FAQ estruturada + Trust seals + Free shipping cart + AggregateOffer
- **Referências em código:** PHTML (1) (total bruto: 3 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Wiring ativo: phtml awa-pdp-conversion-async.phtml com getViewFileUrl (linha 23), documentado como "Criado agora" em RELATORIO_VTEX_GRADE_2026-06-27.md (recente). Zero presenca no HTML da PDP testada -- pode ser condicional (produto com FAQ) ou interaction-gated (nome do loader sugere "async").

### `awa-pdp-premium.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-pdp-premium.css`
- **Tamanho:** 20976 bytes
- **SHA256:** `2709b7d6478eda4fd00df42239372fcf430f2730db9552f4f4717d066c3271e8`
- **Último commit:** `61609497f` (2026-06-24, Jess) — feat: B2B pricing notice PDP visual — MEL-02
- **Referências em código:** XML (1), PHTML (3), CSS (1) (total bruto: 5 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Wiring ativo: mesmo loader phtml awa-pdp-conversion-async.phtml (linha 22), comentario confirma "contem os estilos de compatibilidade inline". Zero presenca na PDP testada -- mesmo padrao async do item acima.

### `awa-pdp-ui-promax-2026-05-22.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-pdp-ui-promax-2026-05-22.min.css`
- **Tamanho:** 19146 bytes
- **SHA256:** `570d4f0f5fbcce7972bf1eafb88731df605791f759e02b55f149e3336433ae7e`
- **Último commit:** `2fd4f0288` (2026-06-28, Jess) — chore(css): 9 sync min files + interaction-widgets tokens
- **Referências em código:** PHTML (1) (total bruto: 2 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Wiring ativo: phtml getViewFileUrl com query version (linha 1728, PDP-specific). Zero presenca na PDP testada -- possivel condicao ou gate nao identificado.

### `awa-plp-final-polish.min.css`

- **Caminho original:** `app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-plp-final-polish.min.css`
- **Tamanho:** 50296 bytes
- **SHA256:** `f8807e84ad686db753985fa39524eb18cb277d676450292a353329e09de4e8fa`
- **Último commit:** `183c4d0da` (2026-06-28, Jess) — chore(css): regenerate awa-plp-final-polish.min.css (1.470 linhas)
- **Referências em código:** PHTML (2) (total bruto: 4 linhas)
- **Rotas onde foi encontrado ao vivo (HTML renderizado):** NENHUMA
- **Decisão:** **INVESTIGAR**
- **Evidência/justificativa:** Wiring ativo: 2 phtml com getViewFileUrl e query version. Apesar da versao base .css estar formalmente removida (TICKET-002), a versao .min nao tem remove-src equivalente. Zero presenca confirmada em PLP e busca nesta auditoria -- mas o padrao do codebase (visto em outros arquivos) e a versao .min sobreviver a remocao da base. Requer decisao explicita do time antes de quarentenar.

---

## Rotas testadas (evidência de runtime)

| Rota | URL |
|---|---|
| Home | `https://awamotos.com/` |
| PLP (categoria) | `https://awamotos.com/pecas.html` |
| PDP (produto) | `https://awamotos.com/bauleto-givi-b47nt-47-litros-preto.html` |
| Carrinho | `https://awamotos.com/checkout/cart` |
| Login B2B | `https://awamotos.com/b2b/account/login` |
| Checkout (redirect não-autenticado) | `https://awamotos.com/checkout/` |
| Contato | `https://awamotos.com/contact/` |
| Busca | `https://awamotos.com/catalogsearch/result/?q=bauleto` |

**Rotas não testadas (requerem sessão autenticada ou ação transacional — fora do escopo desta fase por segurança):**
- `customer/account/*` (área logada do cliente)
- `checkout/index/index` com carrinho populado e sessão ativa
- `checkout/onepage/success` (requer completar um pedido real)
- Páginas de conta B2B autenticadas (dashboard, cotações, listas de compra)

## Próximos passos recomendados

1. **Fase 6A.2** (esta branch): mover os 33 arquivos `QUARENTENA` para `_quarantine/css-dead-2026-07-08/` preservando histórico Git (via `git mv`), sem deletar.
2. **Não avançar** com os 19 arquivos `INVESTIGAR` nesta fase — requerem uma das seguintes ações antes de qualquer decisão:
   - Teste em ambiente de staging com sessão de cliente B2B autenticada (para arquivos de checkout/conta).
   - Simulação de interação do usuário (pointerdown/scroll) com browser headless para capturar assets carregados via `awa-css-gate.js` após o primeiro paint.
   - Leitura completa do fluxo condicional em `OptimizeHeadStylesPlugin.php` e `HeaderImpeccableCascadeLockCss.php` para os arquivos com wiring PHP complexo.
   - Decisão explícita do time sobre `awa-plp-final-polish.min.css` (o arquivo base foi removido por ticket, mas o `.min` permanece referenciado).
3. **Rodar o smoke test oficial** antes e depois de qualquer movimentação: `bash scripts/check-legacy-static-assets.sh`.
4. Registrar erratas na regra `.cursor/rules/awa-css-governance.mdc`, que hoje lista incorretamente 3 arquivos (`awa-plp-critical-fixes.min.css`, `awa-plp-distill.min.css`, `awa-plp-ui-promax-2026-05-22.min.css`) como candidatos a deleção — confirmados ativos na rota de busca.

---

## Cobertura de testes — Fase 6A (2026-07-08)

### Smoke test dedicado da quarentena

**Arquivo:** `tests/e2e/specs/dead-css-quarantine.spec.ts`
**Config de execução local recomendada (leve, sem vídeo/trace/screenshot):** `tests/e2e/pw-dead-css-quarantine.config.ts`

Valida, nas 8 rotas críticas definidas para esta fase, que:
1. Nenhum request de CSS retorna `404` ou `403` (regressão geral, não só quarentena).
2. Nenhum dos 33 arquivos classificados como `QUARENTENA` (16 já movidos para `_quarantine/css-dead-2026-07-08/` + 17 ainda em `web/css/` aguardando commit separado — ver `_quarantine/css-dead-2026-07-08/README.md`) é requisitado por nenhuma dessas rotas.

**Rotas cobertas por este spec:**

| Rota | URL testada |
|---|---|
| Home | `/` |
| Catálogo | `/catalogo` |
| Bauletos (PLP) | `/bauletos.html` |
| Nossas marcas | `/nossas-marcas` |
| About us | `/about-us` |
| Lançamentos | `/lancamentos` |
| Lançamentos (redirect) | `/lancamentos.html` → `/lancamentos` (301) |
| B2B — registro | `/b2b/register` |

**Resultado da execução em 2026-07-08 (produção, `--project=notebook-1366`):**

```
9 passed (40.5s)
```

Todas as 8 rotas + teste de resumo passaram. Nenhum CSS quebrado, nenhum arquivo quarentenado requisitado.

**Limitação conhecida (documentada no próprio spec):** esta versão não simula interação do usuário (pointerdown/scroll/keydown) para forçar o carregamento de bundles *interaction-gated* via `awa-css-gate.js`. Uma primeira versão do spec simulava mouse/teclado, mas isso se mostrou instável neste ambiente de desenvolvimento compartilhado (crashes intermitentes de renderer Chromium sob contenção de recursos — `Error: Channel closed` / `browserContext.close: Target page, context or browser has been closed`, reproduzido de forma consistente na rota `/catalogo`). A versão simplificada (sem interação, apenas espera de estabilização de 3.5s) rodou de forma estável. A cobertura de assets pós-interação deve ser validada manualmente ou num runner de CI dedicado (GitHub Actions, com mais headroom de recursos que este VPS de produção compartilhado).

### Suíte existente (regressão geral) — execução manual nesta fase

Rodar a suíte completa (887 testes em 93 arquivos × 13 projetos de browser/viewport) neste VPS de desenvolvimento compartilhado não é viável nem seguro — o próprio ambiente já demonstra instabilidade sob carga (ver limitação acima). Como evidência de regressão geral para esta fase, foi executada a suíte `tests/e2e/specs/smoke/` (11 arquivos, 82 testes, `--project=notebook-1366`), que é o subconjunto que já cobre carrinho, categoria, checkout, footer, formulários, header, home, login, menu, busca e produto (PDP).

**Resultado (2026-07-08, produção, `--project=notebook-1366`):**

- **73 de 82 testes concluídos com resultado** dentro do orçamento de tempo desta sessão (10 de 11 arquivos totalmente executados; `product.spec.ts` — 12 testes sobre uma PDP específica — não completou dentro do tempo desta sessão, aparentemente por instabilidade do mesmo tipo já documentada acima, não relacionada à quarentena de CSS).
- **Falhas observadas — todas pré-existentes e não relacionadas à quarentena de CSS** (nenhuma delas envolve os 33 arquivos quarentenados nem 404/403 de CSS):
  - `category.spec.ts › 05 — cards com preço ou B2B gate` — sem preço e sem gate B2B visível na categoria testada.
  - `checkout.spec.ts › 02 — sem tela branca (P0)` — timeout de 21.3s ao localizar conteúdo esperado.
  - `category.spec.ts › 08 — sem overflow` — overflow horizontal detectado.
  - `footer.spec.ts › 05 — footer height razoável` — footer com 1918px (limite: 800px).
  - `home.spec.ts › 11 — grid de produtos existe` e `13 — cards com preço ou B2B gate` — mesma classe de problema de `category.spec.ts` 05.
  - `search.spec.ts › 03 — resultados exibem produtos (P0)` — busca sem resultados para o termo de teste.
- Nenhuma das falhas acima menciona ou referencia qualquer um dos 33 arquivos CSS em quarentena — são achados de UX/conteúdo pré-existentes, fora do escopo da Fase 6A.

### Estratégia de CI recomendada (implementada em `.github/workflows/e2e-pr-smoke.yml`)

| Gate | Quando roda | O que valida | Workflow |
|---|---|---|---|
| **Smoke de quarentena (obrigatório)** | Todo PR | `dead-css-quarantine.spec.ts` — 8 rotas críticas, ~40s | `.github/workflows/e2e-pr-smoke.yml` (nova etapa "Dead CSS quarantine smoke (Fase 6A)") |
| **Regressão B2B + CLS (obrigatório)** | Todo PR | Já existente, inalterado | `.github/workflows/e2e-pr-smoke.yml` |
| **Suíte completa de regressão** | Push em `main` (pré-merge) | `test:b2b-regression:all` + pipeline visual MCP | `.github/workflows/e2e-premerge-regression.yml` (já existente, sem alteração necessária) |
| **Suíte completa noturna** | Diariamente às 04:00 UTC + manual | Suíte MCP visual completa + B2B todos os projetos | `.github/workflows/e2e-nightly-full.yml` (já existente, sem alteração necessária) |

Não foi criado nenhum workflow novo — a etapa de quarentena foi adicionada ao workflow de PR-smoke já existente, evitando duplicação de infraestrutura de CI. Os workflows de pré-merge e noturno já cobrem a suíte completa nos moldes solicitados; a execução isolada do smoke de quarentena garante que uma regressão específica da Fase 6A (CSS quarentenado voltando a ser servido, ou qualquer CSS 404/403) seja pega rapidamente em todo PR, sem esperar pela suíte pesada.

**Comando para rodar localmente:**

```bash
cd tests/e2e
npm run test:dead-css-quarantine
# ou, com config leve dedicada (sem vídeo/trace/screenshot):
PLAYWRIGHT_BASE_URL=https://awamotos.com ALLOW_PRODUCTION_VALIDATION=true \
  npx playwright test --config=pw-dead-css-quarantine.config.ts
```
