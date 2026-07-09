# Distill Super Ofertas — opt12b (2026-06-01)

## Contexto
- Seção HTML desativada em `top-home.phtml` (`$hotDealHtml` vazio, bloco `<?php if ($hotDealHtml !== ''): ?>`)
- CSS morto no `awa-home-gate-polish-bundle.css` (~14KB, 49 referências)

## Removido do polish bundle
- FIX 25 countdown high-contrast (global `.super-deal-countdown` — módulo Rokan ainda tem estilos em `awa-shelf-carousel.css` se reativado)
- Blocos Phase 25/27/§29 exclusivos `.awa-carousel-section--super-offers`
- Owl mobile rules só para super-offers
- Regra §29 gradiente urgência
- Regra `~ .awa-carousel-section` eyebrow (dependia de super-offers no DOM)
- RODADA 24 inteira (era trust-and-offers + super-offers; trust-and-offers também ausente do HTML atual)

## Preservado
- Regras `.productTabContent` (widgets tab)
- Hero Swiper bullets, Owl nav geral, SKU B2B (Phase 27 tail)
- `.hot-deal-tab-slider` border-radius global (§18.6 — outras páginas)

## Resultado
- Fonte: 840318 → 826438 bytes (−13880, ~1.6%)
- Minificado: 598538 bytes (era ~606094)
- Referências `super-offers` no polish: **0**

## Reativar Super Ofertas no futuro
1. Popular `$hotDealHtml` em `top-home.phtml`
2. Restaurar blocos do git history ou `awa-home-gate-postaudit-bundle.css` (legado)
3. Re-deploy polish min
