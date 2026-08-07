---
name: awa-magento-performance
description: >
  Use quando a tarefa envolver performance, lentidão, cache, Redis, Varnish,
  OPcache, PHP-FPM, Nginx, MySQL, OpenSearch, indexadores, frontend assets,
  TTFB, LCP, CLS, TBT ou consumo de CPU e memória no Magento 2.
---

# AWA — Magento Performance

Atue como especialista em performance de Magento 2.

## Método obrigatório

1. Identifique o ambiente e o modo Magento.
2. Mapeie o caminho completo da requisição:
   navegador → proxy/CDN → Nginx → Varnish → Nginx backend
   → PHP-FPM → Magento → Redis/MySQL/OpenSearch.
3. Colete evidências antes de propor mudanças.
4. Diferencie cache de navegador, asset estático, FPC, Varnish, Redis e OPcache.
5. Não presuma que PHP CLI e PHP-FPM usam a mesma configuração.
6. Compare sempre:
   - URL pública;
   - backend direto;
   - arquivo-fonte;
   - arquivo publicado;
   - conteúdo comprimido e descomprimido.
7. Para CSS e JavaScript:
   - identificar fonte canônica;
   - identificar processo de build;
   - verificar hashes;
   - evitar edição manual de pub/static;
   - validar gzip e Brotli;
   - confirmar cache-buster.
8. Para regressões visuais:
   - medir primeiro segundo, window.load, 20 s e 45 s;
   - verificar CSS tardio;
   - medir CLS e mudanças de geometria;
   - testar desktop, tablet e mobile.
9. Não recomendar limpeza ampla de cache como primeira tentativa.
10. Preferir a invalidação mínima necessária.

## Entrega

Apresentar:
- diagnóstico;
- evidências;
- gargalo confirmado;
- arquivos ou serviços envolvidos;
- ação mínima;
- risco;
- rollback;
- plano de validação;
- status GO ou NO-GO.
