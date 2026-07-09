<?php

/**
 * B2B — remove a opção de ordenação "Valor" (preço) do toolbar da PLP/busca
 * quando o visitante não pode ver preços reais.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Catalog;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use Magento\Catalog\Block\Product\ProductList\Toolbar;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Fase 7 (P2, 2026-07-03): complementa HidePriceLayeredFilterPlugin.
 *
 * Sem essa correção, o seletor "Ordenar por" continuava com a opção "Valor"
 * mesmo com o preço escondido no card ("Cadastre-se para ver o preço"),
 * permitindo ordenar produtos por um dado que o próprio visitante não
 * consegue ver — comportamento contraditório e sem utilidade real para ele.
 */
class HidePriceSortOrderPlugin
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
     * @param Toolbar $subject
     * @param array $result
     * @return array
     */
    public function afterGetAvailableOrders(Toolbar $subject, array $result): array
    {
        try {
            if ($this->priceVisibility->canViewPrices()) {
                return $result;
            }

            unset($result['price']);

            return $result;
        } catch (\Throwable $exception) {
            $this->logger->error(
                '[B2B HidePriceSortOrderPlugin] Falha ao aplicar regra de visibilidade da ordenação por preço.',
                ['exception' => $exception->getMessage()]
            );

            return $result;
        }
    }
}
