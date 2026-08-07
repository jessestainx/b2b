# Awa_CatalogFix

Search Landing UX fix for Mirasvit SearchLanding silent 302 redirects.

## Coupling (B3)

**This module is intentionally coupled to `Mirasvit_SearchLanding` and `Mirasvit_Misspell`.**

- Frontend DI declares plugins on:
  - `Mirasvit\SearchLanding\Observer\OnCatalogSearch` (landing 302)
  - `Mirasvit\Misspell\Observer\OnCatalogSearchObserver` (spell/fallback 302, e.g. capacete→cavalete)
- If either Mirasvit module is disabled or removed, `setup:di:compile` / Object Manager
  will fail until you disable this module:

```bash
php bin/magento module:disable Awa_CatalogFix
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```

`Block\SearchHint` tolerates a missing `PageInterface` via `interface_exists()` (banner simply
does not render). The plugin class still type-hints Mirasvit and remains a hard dependency
at compile time.

## Behaviour

1. Exact search terms mapped to a Mirasvit landing (e.g. `q=capacete` → `/capacetes/`) still 302.
2. On the landing page, a hint banner explains the redirect and offers **Ver resultados exatos**.
3. Link goes to `/catalogsearch/result/?q=…&exact=1` — plugin skips the 302 when `exact=1`.

## Layout handles

| File | Purpose |
|------|---------|
| `search_landing_page_view.xml` | Primary — Magento fullActionName |
| `search_landing_page.xml` | Mirasvit explicit handle |
| `default.xml` | Ultra-fallback; output gated by `shouldRender()` |

## Enable (DEV)

```bash
php bin/magento module:enable Awa_CatalogFix
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
vendor/bin/phpunit app/code/Awa/CatalogFix/Test/Unit/
```
