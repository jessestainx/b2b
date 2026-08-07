# Manual Operacional — Vendedoras AWA Motos

**Versão:** 1.1
**Data:** 2026-08-03
**Ambiente:** Magento 2.4.8-p3 · https://awamotos.com/
**Público:** vendedoras e supervisora comercial

> Senhas e tokens **não** ficam neste arquivo. Solicite ao administrador se precisar de reset.
> E-mails automáticos da loja saem de **sac@awamotos.com** (Hostinger). Ver também `MANUAL_EXECUTIVO_AWA.md`.

---

## 1. Acesso

| Item | Valor |
|------|--------|
| URL do admin | https://awamotos.com/admin |
| Papel vendedora | AWA Comercial Vendedora |
| Papel supervisora | AWA Comercial Supervisora |
| Login supervisora | `supervisora` |

### Contas de vendedoras

| Login | Nome | E-mail de contato |
|-------|------|-------------------|
| `atendente1` | Ana Carolina | suporte1.vendas@awamotos.com.br |
| `atendente2` | Adrielly | suporte2.vendas@awamotos.com.br |
| `atendente3` | Lívia | suporte3.vendas@awamotos.com.br |
| `atendente4` | Nathalia | suporte4.vendas@awamotos.com.br |
| `atendente5` | Claudia | suporte5.vendas@awamotos.com.br |
| `atendente6` | Maria Carolina | suporte8.vendas@awamotos.com.br |
| `atendente7` | Adriana Morganna | suporte9.vendas@awamotos.com.br |
| `atendente11` | Tamiris | suporte11.vendas@awamotos.com.br |
| `supervisora` | Ingrid Soares | comercial@awamotos.com.br |

**Regras de segurança**
- Não compartilhe a senha por WhatsApp em grupo aberto.
- Troque a senha no primeiro acesso se o sistema solicitar.
- Em caso de bloqueio, avise a TI (não peça reset em voz alta no escritório).

---

## 2. O que cada perfil vê

### Vendedora
Menu principal: **AWA Comercial (Cockpit)**
- Dashboard Comercial
- Minha Carteira
- Clientes Pendentes B2B
- Ficha 360 do Cliente
- Tarefas Comerciais
- Carrinhos Abandonados
- Sugestões de Recompra
- Clientes Parados
- Metas e Ranking
- Relatórios da própria operação

### Supervisora
Além do cockpit, costuma ter visão ampliada de carteiras, metas e relatórios.
Use para acompanhar o time, redistribuir pendências e auditar tratamentos.

---

## 3. Rotina diária (checklist)

Faça nesta ordem, no início do expediente:

1. **Dashboard Comercial** — ver volume do dia e alertas.
2. **Clientes Pendentes** — aprovar, rejeitar ou solicitar documento.
3. **Minha Carteira** — priorizar quem precisa de contato hoje.
4. **Carrinhos Abandonados** — tratar ou agendar retorno.
5. **Tarefas** — concluir ou remarcar.
6. **Clientes Parados / Recompra** — follow-up de reativação.
7. **Metas / Ranking** — saber se está no ritmo.

Ao final do dia: nenhuma pendência crítica sem dono e sem próximo passo registrado.

---

## 4. Fluxos essenciais

### 4.1 Aprovar cadastro B2B (CNPJ)

1. Abra **Clientes Pendentes**.
2. Confira CNPJ, razão social e dados de contato.
3. Se o cliente já existe no ERP/Sectra, a aprovação tende a vincular automaticamente.
4. Aprove ou rejeite com motivo claro.
5. O cliente recebe e-mail; a equipe pode receber WhatsApp (Z-API).

**Mensagem padrão ao cliente (se ligar/WhatsApp):**
“Recebemos seu cadastro. Assim que a análise for concluída, você recebe liberação por e-mail.”

### 4.2 Cotação (RFQ)

1. Abra a cotação pendente.
2. Confira itens, quantidades e validade.
3. Responda com preço/prazo ou peça ajuste.
4. Registre o contato na ficha 360.

### 4.3 Carrinho abandonado

1. Abra **Carrinhos Abandonados**.
2. Veja valor, itens e se já houve e-mail automático (ondas 1h / 24h / 72h).
3. Marque como tratado após contato.
4. Ofereça ajuda para fechar o pedido (não prometa desconto fora da política).

### 4.4 Cliente parado / recompra

1. Abra a lista de inativos ou sugestões de recompra.
2. Contate com foco em reposição (peças que o cliente já compra).
3. Registre o resultado do contato.

### 4.5 Pedido e status

Pedidos B2B entram no fluxo Magento ↔ ERP Sectra.
Se o status não atualizar em tempo razoável, registre número do pedido e acione TI/ERP — não altere status “no chute”.

---

## 5. Canais de comunicação

| Canal | Uso |
|-------|-----|
| E-mail Magento | Pedido, fatura, aprovação, cotação |
| WhatsApp (Z-API) | Alertas de equipe e alguns disparos automáticos |
| Telefone | Negociação e urgência |

**Provedor WhatsApp canônico:** Z-API.
Não use ferramentas paralelas sem alinhamento com a TI.

---

## 6. O que a vendedora NÃO deve fazer

- Alterar configuração de SMTP, cache, indexadores ou módulos.
- Pedir senha de administrador geral.
- Aprovar CNPJ sem conferir dados mínimos.
- Prometer preço/prazo que o ERP não confirma.
- Enviar planilha de clientes para e-mail pessoal.
- Desligar notificações ou crons.

---

## 7. Quando escalar para TI

Escalare se:
- Login bloqueado ou tela em branco no admin
- Cliente aprovado sem ver preço
- Pedido sem eco no ERP
- WhatsApp da equipe parou de notificar
- Relatório comercial sem dados

Informe: login usado, horário, URL, número do pedido/cliente e print do erro.

---

## 8. Glossário rápido

| Termo | Significado |
|-------|-------------|
| Carteira | Clientes sob responsabilidade da vendedora |
| Ficha 360 | Visão completa do cliente (pedidos, crédito, contatos) |
| Abandonado | Carrinho com itens sem checkout |
| Sectra / ERP | Sistema industrial integrado ao Magento |
| Cotação / RFQ | Pedido de orçamento B2B |
| Opt-in WhatsApp | Cliente autorizou mensagem |

---

## 9. Referências técnicas (TI)

- Mapa operacional: `_documentacao/MAPA_OPERACIONAL_B2B_MAGENTO_SECTRA_2026-07-16.md`
- Gap atendente / homologação: `_documentacao/ETAPA_1_5_HOMOLOGACAO_GAP_ATENDENTE_2026-07-22.md`
- Guia de teste B2B: `docs/GUIA_TESTE_MANUAL_B2B.md`
- Templates de e-mail: `docs/EMAIL_TEMPLATES_GUIA.md` e `docs/email-template-style-guide.md`
- Correções WhatsAppCommerce: `app/code/GrupoAwamotos/WhatsAppCommerce/AUDITORIA_CORRECAO_FASES.md`

---

## 10. Histórico

| Data | Alteração |
|------|-----------|
| 2026-08-03 | Criação do manual v1.0; login `ingrid` → `supervisora` ativada; senhas comerciais padronizadas pelo administrador |
