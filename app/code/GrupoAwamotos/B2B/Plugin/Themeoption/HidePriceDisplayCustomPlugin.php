<?php

/**
 * Plugin to hide price helper output for non-logged users
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Themeoption;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Rokanthemes\Themeoption\Helper\Data as ThemeoptionDataHelper;

class HidePriceDisplayCustomPlugin
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
     * After getPriceDisplayCustom, replace price with login message for guests.
     */
    public function afterGetPriceDisplayCustom(ThemeoptionDataHelper $subject, string $result): string
    {
        try {
            if (strpos($result, 'b2b-login-to-see-price') !== false) {
                return $result;
            }

            if (!$this->priceVisibility->canViewPrices()) {
                return '<div class="b2b-login-to-see-price price-box">'
                    . '<span class="price-label">'
                    . $this->priceVisibility->getPriceReplacementMessage()
                    . '</span>'
                    . '</div>';
            }

            return $result;
        } catch (\Throwable $exception) {
            $this->logger->error('[B2B HidePriceDisplayCustomPlugin] Falha ao aplicar regra de exibição.', [
                'exception' => $exception->getMessage(),
            ]);

            return $result;
        }
    }
}
