<?php

/**
 * Plugin to guarantee B2B price gate on Brand widget items.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Brand;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Rokanthemes\Brand\Block\Widget\Brandlisttab;

class HideBrandWidgetProductPricePlugin
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
     * After getProductPrice - replace price with B2B message when user shouldn't see prices.
     */
    public function afterGetProductPrice(Brandlisttab $subject, string $result): string
    {
        try {
            if (strpos($result, 'b2b-login-to-see-price') !== false) {
                return $result;
            }

            if (!$this->priceVisibility->canViewPrices()) {
                return '<div class="b2b-login-to-see-price">'
                    . '<span class="price-label">'
                    . $this->priceVisibility->getPriceReplacementMessage()
                    . '</span>'
                    . '</div>';
            }

            return $result;
        } catch (\Throwable $exception) {
            $this->logger->error('[B2B HideBrandWidgetProductPricePlugin] Falha ao aplicar regra de visibilidade de preço.', [
                'exception' => $exception->getMessage(),
            ]);

            return $result;
        }
    }
}
