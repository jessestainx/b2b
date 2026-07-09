<?php

/**
 * B2B — remove o filtro de preço ("Valor") da navegação em camadas (PLP/busca)
 * quando o visitante não pode ver preços reais.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Catalog;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\FilterList;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Fase 7 (P2, 2026-07-03): causa raiz do "filtro de preço contraditório".
 *
 * Para guest/B2B pendente/rejeitado, o card de produto some o preço real e
 * mostra "Cadastre-se para ver o preço" (ver HidePricePlugin), mas a sidebar
 * "Comprar por" continuava oferecendo um filtro "Valor" com faixas de preço
 * reais (ex.: "R$ 10,00 - R$ 19,99") e a ordenação "Ordenar por: Valor" —
 * ambos vazam o preço indiretamente (o cliente descobre a faixa de preço de
 * cada produto ao ver em qual filtro ele aparece) e, pior, prometem um
 * recurso (filtrar/ordenar por preço) que não tem serventia nenhuma para
 * quem não pode ver o preço em si. Este plugin remove o filtro de preço da
 * navegação em camadas nesses estados, mantendo-o disponível apenas quando
 * o preço real já é exibido (canViewPrices() true).
 *
 * Detalhe de implementação: o site usa OpenSearch, então o filtro de preço
 * real em produção é "Rokanthemes\LayeredAjax\Model\Layer\Filter\Price",
 * que estende "Magento\CatalogSearch\Model\Layer\Filter\Price" — uma classe
 * IRMÃ (não filha) de "Magento\Catalog\Model\Layer\Filter\Price". Por isso a
 * checagem usa "getRequestVar() === 'price'" (contrato comum de
 * AbstractFilter, agnóstico à engine de busca/implementação) em vez de
 * "instanceof", que falharia silenciosamente com CatalogSearch/OpenSearch.
 */
class HidePriceLayeredFilterPlugin
{
    private PriceVisibilityInterface $priceVisibility;
    private LoggerInterface $logger;

    public function __construct(
        PriceVisibilityInterface $priceVisibility,
        ?LoggerInterface $logger = null
    ) {
        $this->priceVisibility = $priceVisibility;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param FilterList $subject
     * @param \Magento\Catalog\Model\Layer\Filter\AbstractFilter[] $result
     * @return \Magento\Catalog\Model\Layer\Filter\AbstractFilter[]
     */
    public function afterGetFilters(FilterList $subject, array $result, Layer $layer): array
    {
        try {
            if ($this->priceVisibility->canViewPrices()) {
                return $result;
            }

            return array_values(array_filter(
                $result,
                static function ($filter) {
                    return !($filter instanceof AbstractFilter && $filter->getRequestVar() === 'price');
                }
            ));
        } catch (\Throwable $exception) {
            $this->logger->error(
                '[B2B HidePriceLayeredFilterPlugin] Falha ao aplicar regra de visibilidade do filtro de preço.',
                ['exception' => $exception->getMessage()]
            );

            // Fail-open: em caso de erro inesperado, mantém o comportamento padrão do Magento.
            return $result;
        }
    }
}
