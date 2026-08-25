# Revisão de performance

## Impacto das mudanças

- Plugins GraphQL: um `canViewPrices()` extra por resolver de preço. Já era chamado no HTML; custo baixo, cache interno no serviço.
- Plugin JSON-LD: mesmo serviço; só unsets de chaves.
- Cadastro: hash SHA-256 e atributos EAV já existentes; sem query N+1 nova deliberada.
- Coach: `MutationObserver` só em `/b2b/register`; delay padrão 6s mantido.
- Playwright global-setup: validação de env, sem Chrome extra.

## Observado em produção (somente leitura)

- Indexers idle.
- Consumers ERP e WhatsApp listados.
- Home TTFB curl ~31 ms (FPC/Varnish).
- Categoria/PDP ~0,4 s.

## Não feito

- `cache:flush` / purge Varnish
- `static-content:deploy`
- Mudança de Redis/OPcache

GO para merge da branch **do ponto de vista de performance**. NO-GO para implantar sem janela oficial.
