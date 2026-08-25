# Matriz de testes

## PHPUnit (executado na worktree)

```
php vendor/bin/phpunit -c app/code/GrupoAwamotos/B2B/Test/phpunit.xml
```

**Resultado: OK (263 tests, 425 assertions).**

Cobertura nova/alterada:

- `PriceVisibilityTest` — logado sem status fail-closed
- `HidePricePluginTest` / `HideFinalPricePluginTest` — fail-closed
- `HideGraphQlPricePluginsTest`
- `HideProductSchemaPricePluginTest`
- `HiddenGraphQlPriceTest`
- `CustomerApprovalTest::testRequestDataReview*`

Bootstrap: `app/code/GrupoAwamotos/B2B/Test/bootstrap.php` força autoload do worktree (o `vendor` de produção apontaria o DocumentRoot).

## Playwright

Não executado contra produção (escrita/piloto bloqueados; `PILOT_*` vazios).

Novos artefatos:

- `tests/e2e/helpers/resolve-base-url.ts` — `TARGET_ENV` obrigatório
- `tests/e2e/helpers/production-write-guard.ts`
- `tests/e2e/specs/b2b-production-write-guard.spec.ts`
- CI: `TARGET_ENV` default `production_readonly`

Para smoke visual read-only:

```
TARGET_ENV=production_readonly ALLOW_PRODUCTION_VALIDATION=true PLAYWRIGHT_BASE_URL=https://awamotos.com npx playwright test tests/e2e/specs/b2b-register.spec.ts --workers=1
```

## Smoke HTTP produção (GET)

| Rota | HTTP | Preço HTML | Observação |
|---|---|---|---|
| `/` | 200 | não | gate presente |
| `/bauletos.html` | 200 | não | 51 gates |
| `/b2b/register/` | 200 | n/a | wizard 4 etapas |
| `/graphql` products | 200 | **vazou 22.99** | G01; correção só na branch |
| REST V1/products/F340 | 401 ACL | n/a | consumidor anônimo bloqueado |

## Não executado

- MFTF
- `setup:di:compile` / `setup:upgrade` (produção proibida; staging ausente)
- Pedido sintético / piloto
- Browser MCP (perfil leve)
