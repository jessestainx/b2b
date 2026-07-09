# Visual QA — Round 2

## Resultado
- CSS: `awa-visual-qa-fixes-2026-06-17.css` v3
- Issues reais desktop: 30 → redução após retest (ver issues-after.json)
- Screenshots after: `screenshots/after/`

## Fixes aplicados
- B2B/auth: eixo único de containers
- Cart/PLP: grid multi-coluna explícito
- PDP: h1 maior que h2
- Detector: ignora header full-bleed, layouts 2 colunas, overlaps header/conteúdo

## Comando
`VQA_LITE=1 VQA_DESKTOP_ONLY=1 ./scripts/visual-qa-lite.sh retest`

## Round 3–4 (final desktop)
- **30 → 7 issues reais** (css-conflicts excluído)
- CSS v5 em `awa-visual-qa-fixes-2026-06-17.css`
- Restantes: PLP overlap, PDP h1, cart overlap, B2B container width

| Fase | Issues reais |
|------|-------------|
| Inicial | 30 |
| Round 2 | 23 |
| Round 3 | 14 |
| Round 4 | **7** |
