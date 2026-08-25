# Manual das vendedoras — cadastro B2B

## Como encontrar novos cadastros

Admin Magento → **Grupo Awamotos → B2B → Clientes Pendentes**  
(`grupoawamotos_b2b/customer/pending`)

Cockpit comercial (se a função tiver ACL): **AWA Comercial**.

Filtrar por status, data e buscar razão social / CNPJ / e-mail.

## Como iniciar análise

1. Abrir o cliente na fila.
2. Conferir CNPJ, Receita (quando houver), CNAE, IE, endereço e contato.
3. Ver histórico em `grupoawamotos_b2b_customer_approval_log`.
4. Ver se já existe atendente atribuído.

SLA: cadastros parados > 48h disparam alerta interno (cron de pendentes).

## Como conferir a empresa

- CNPJ e situação consultada (atributos `b2b_receita_*`, `b2b_cnae_*`).
- Duplicidade: o cadastro recusa CNPJ/e-mail já existentes no storefront.
- Origem: `b2b_origin_host`, `b2b_registration_landing`, UTMs quando preenchidos.

## Como solicitar informações

Ação **Request Review** chama `requestDataReview` (corrigido nesta branch).  
Status persistido: `data_review` (revisão / needs information).

Informe a mensagem objetiva (documento faltante, IE, telefone).

## Como aprovar

Ação aprovar → status `approved`, grupo B2B (CNAE direto/adjacente ou padrão), e-mail de aprovação se configurado, evento ERP prospect.

Aprovação de **crédito/prazo** é tela/menu à parte (Credito B2B). Não misturar.

## Como rejeitar

Ação rejeitar com motivo. Cliente deixa de ver preço e de comprar.

## Como bloquear

Equivalente operacional: **suspender** (`suspended`). Reabrir = aprovar de novo com permissão de aprovação.

## Histórico

Log de aprovação: ação, status anterior/novo, admin, comentário, data.

## Notificações

E-mail de cadastro/aprovação/rejeição. WhatsApp só com consentimento.  
Se a notificação falhar, o cadastro **não** deve ser refeito; reenviar pelo log/admin.

## Falha de integração Sectra

Menu **Fila Sectra ERP**. Não marcar pedido como importado no SQL. Não reimportar sem conferir `increment_id` e log.

## Quando chamar supervisora

- Duplicidade de CNPJ com cliente já aprovado.
- Reabrir suspenso.
- Pedido preso em `import_failed` ou `awaiting_customer_validation`.
- Qualquer dúvida fiscal/NF-e.

## O que nunca fazer

- Aprovar para “só ver preço” sem análise.
- Alterar preço/estoque em massa.
- Criar segundo pedido para “testar” o ERP.
- Colar senha, XML ou DANFE no ticket.
- Limpar cache de produção como primeiro passo.
- Usar SQL para mudar status de pedido ou cliente.
