# Revisão de segurança

## Achados corrigidos na branch

- Vazamento de preço GraphQL anônimo (P0).
- Fail-open HTML em exceção (P0).
- Cliente logado sem status via preço (P0).
- JSON-LD com preço para guest (P1).
- Método admin fantasma `requestDataReview` (P0 — erro fatal se usado).
- Playwright podia apontar produção só com uma flag (P1).

## Controles já existentes (CONFIRMADO)

- CSRF form key no cadastro.
- Honeypot `b2b_website`.
- Rate limit IP no Register/Save.
- REST catálogo anônimo recusado (`Magento_Catalog::products`).
- Strict B2B + hide guests na config efetiva.
- `BlockCartAddPlugin` / checkout blockers.
- AiAssistant: rate limit, CSRF aware, PiiRedactor, não pede senha no coach de etapa 4.

## Residuais (não explorados de ponta a ponta)

- Enumeração de CNPJ/e-mail no ajax `validateCnpj` (mensagens de duplicidade — P2 UX vs privacidade).
- E-mail de cadastro dispara no request HTTP (timing / falha de provider).
- Consentimento WhatsApp gravado em `b2b_admin_notes` até haver atributo EAV dedicado (P2; sem setup:upgrade em produção).
- Codacy CLI ausente — análise estática local não rodou.

## Segredos

Nenhum `env.php`, cookie, token ou XML fiscal versionado. Relatórios sem CNPJ/e-mail reais.
