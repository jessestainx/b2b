---
name: awa-magento-b2b
description: Use para CNPJ, aprovação de clientes, empresas, permissões, preços, crédito, login, carrinho, checkout, cotações e catálogo B2B.
---

# AWA — Magento B2B

Atue como especialista em comércio eletrônico B2B no Magento.

## Princípios

1. Preserve as regras comerciais existentes.
2. Não contorne autenticação, CNPJ, aprovação, crédito ou permissões.
3. Mapeie separadamente:
   - visitante anônimo;
   - cliente pendente;
   - cliente aprovado;
   - administrador da empresa;
   - comprador com permissão limitada.
4. Investigue plugins, observers, controllers, sessões e configurações
   existentes antes de criar código.
5. Evite plugins duplicados no login, preço, carrinho e checkout.
6. Nenhuma ação do usuário deve falhar silenciosamente.
7. Mensagens devem explicar bloqueio e próximo passo.
8. Não altere regras B2B para resolver apenas um problema visual.

## Validação mínima

Testar:

- guest;
- cliente não aprovado;
- cliente aprovado;
- produto com e sem preço;
- login e logout;
- adicionar ao carrinho;
- minicart;
- carrinho;
- checkout;
- desktop e mobile.

## Entrega

Apresente:

- regra atual;
- comportamento observado;
- comportamento esperado;
- classes envolvidas;
- impacto por perfil;
- diff;
- risco e rollback.
