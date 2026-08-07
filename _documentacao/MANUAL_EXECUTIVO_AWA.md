# Manual Executivo — Magento AWA Motos

**Versão:** 1.0
**Data:** 2026-08-03
**Ambiente:** produção · https://awamotos.com/ · Magento 2.4.8-p3
**Público:** gestão / diretoria

---

## 1. Em uma frase

A loja B2B está no ar com aprovação por CNPJ, painel das vendedoras, ERP Sectra, recuperação de carrinho e e-mail transacional via Hostinger. WhatsApp (Z-API) e templates visuais ainda têm pendências de fechamento.

---

## 2. O que já está pronto

| Área | Status | Nota |
|------|--------|------|
| Loja Magento produção | OK | Modo production, base `awamotos.com` |
| B2B (CNPJ, aprovação, cotações) | OK | Módulo próprio GrupoAwamotos_B2B |
| Painel vendedoras | OK | 8 vendedoras + 1 supervisora |
| ERP Sectra | OK | Integração ativa |
| Carrinho abandonado | OK | 3 ondas de e-mail + cupons |
| E-mail transacional | OK | SMTP Hostinger `sac@awamotos.com` |
| Branding e-mail | OK | Logo AWA + header/footer no tema |
| DNS e-mail (`awamotos.com`) | OK | SPF Hostinger + DKIM + DMARC (p=none) |
| PIX offline | OK | `banktransfer` ativo (instruções PIX) |
| Manual vendedoras | OK | `_documentacao/MANUAL_OPERACIONAL_VENDEDORAS_AWA.md` |

---

## 3. O que ainda falta (prioridade)

| Prioridade | Item | Impacto |
|------------|------|---------|
| Por último (pedido) | Ajustar Client-Token Z-API (hoje há token Agentic Mail por engano) | WhatsApp confiável |
| Alta | Branding global dos templates de e-mail (logo/header/footer) | Imagem profissional |
| Alta | Unificar WhatsApp em Z-API e limpar fila pendente | Mensagens duplicadas/falhas |
| Alta | 2FA no admin Magento | Segurança |
| Média | Gateway PIX/cartão (hoje só “A Combinar”) | Self-checkout |
| Média | DMARC mais rígido (`p=quarantine`/`reject`) | Anti-spoofing |
| Baixa | Manual TI completo de todos os módulos | Operação sustentável |

---

## 4. Decisões de negócio já registradas

- **WhatsApp canônico:** Z-API (ajuste fino de token deixado por último).
- **E-mail canônico:** Hostinger SMTP · caixa `sac@awamotos.com`.
- **Agentic Mail / MCP Hostinger:** útil para agentes no Cursor; **não** substitui o SMTP do Magento.
- **Supervisora:** login `supervisora` ativa.

---

## 5. Riscos que a gestão precisa conhecer

1. **Senha compartilhada** entre vendedoras — temporária; trocar por senhas individuais.
2. **Pagamento online** ausente — checkout depende de “A Combinar”.
3. **Worktree sujo em produção** — risco de drift; exige disciplina de deploy.
4. **2FA admin desligado** — risco de acesso indevido ao painel.

---

## 6. KPIs sugeridos (semanal)

- Clientes B2B pendentes há > 48h
- Taxa de recuperação de carrinho abandonado
- Pedidos sem eco no ERP
- E-mails de pedido com bounce/spam (amostra)
- Mensagens WhatsApp falhas na fila

---

## 7. Documentos relacionados

- Operacional vendedoras: `MANUAL_OPERACIONAL_VENDEDORAS_AWA.md`
- E-mail/SMTP TI: `MANUAL_TI_EMAIL_SMTP_HOSTINGER.md`
- Mapa B2B/Sectra: `MAPA_OPERACIONAL_B2B_MAGENTO_SECTRA_2026-07-16.md`

---

## 8. Histórico

| Data | Alteração |
|------|-----------|
| 2026-08-03 | Criação v1.0 após auditoria de gaps e troca SMTP Hostinger |
