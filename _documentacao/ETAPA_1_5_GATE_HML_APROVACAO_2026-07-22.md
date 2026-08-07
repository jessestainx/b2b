# Etapa 1.5 — Gate de aprovação HML (bloqueante)

**Status:** AGUARDANDO DECISÃO HUMANA  
**Produção:** NO-GO (inalterada)  
**Data:** 2026-07-22

Nenhum recurso pago ou hostname HML deve ser criado até este gate estar completo.

## Campos obrigatórios

| Campo | Valor aprovado | Aprovado por | Data |
|---|---|---|---|
| Hostname HML (FQDN) | _pendente_ | | |
| IP público HML | _pendente_ | | |
| Datacenter / região | _pendente_ | | |
| Provedor (ex.: Hostinger VPS novo) | _pendente_ | | |
| SKU / plano | _pendente_ | | |
| Custo mensal (R$ / USD) | _pendente_ | | |
| Custo setup / anual | _pendente_ | | |
| Orçamento aprovado? (sim/não) | **não** | | |
| Subdomínio awamotos.com permitido? | _pendente_ (ex.: `hml.awamotos.com`) | | |
| Método de acesso (VPN / Basic Auth / allowlist IP) | _pendente_ | | |
| Política LGPD do dump (máscara PII) | _pendente_ | | |

## Dimensionamento mínimo sugerido (espelho leve)

Com base no docroot de produção (read-only):

| Recurso | Observado em prod | Sugestão HML |
|---|---|---|
| Disco código+media | ~9.7 GiB tree; media ~1.2 GiB | **≥ 40 GiB** (OS + Magento + logs + dump + folga) |
| RAM | prod 31 GiB | **≥ 8 GiB** (admin + index pontual; sem carga de loja) |
| vCPU | prod 8 | **≥ 2–4 vCPU** |
| PHP | 8.4.17 FPM | 8.4 FPM pool próprio |
| MySQL | 8.4.7 | 8.4 próprio |
| Redis | 7.0.15 | instância própria (espelhar 7.0.x ou alinhar 7.2 com decisão) |
| Search | Elasticsearch 7.17.29 | **espelhar 7.17.x** na 1ª HML (evitar drift) |

## Checklist de autorização

- [ ] Hostname/IP formalmente definidos
- [ ] Custo aprovado por responsável financeiro
- [ ] Confirmado: **não** co-localizar no VPS `awamotos.com`
- [ ] Confirmado: crypt/key do dump só em secret store (não no git)
- [ ] Confirmado: branch de código = `feature/admin-menu-unification` (ou merge HML equivalente)

**Somente após este gate:** executar `_documentacao/ETAPA_1_5_HML_RUNBOOK_PROVISIONAMENTO_2026-07-22.md`.
