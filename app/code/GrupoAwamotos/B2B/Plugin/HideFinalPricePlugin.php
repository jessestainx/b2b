<?php

/**
 * Plugin to hide final price box for non-logged users
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use Magento\Catalog\Pricing\Render\FinalPriceBox;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class HideFinalPricePlugin
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
     * Around toHtml - replace price with message if not allowed
     *
     * @param FinalPriceBox $subject
     * @param callable $proceed
     * @return string
     */
    public function aroundToHtml(FinalPriceBox $subject, callable $proceed)
    {
        try {
            if (!$this->priceVisibility->canViewPrices()) {
                $replacementMessage = $this->priceVisibility->getPriceReplacementMessage();

                if ($this->isHomeCompactPriceMessage($replacementMessage)) {
                    return '<div class="b2b-login-to-see-price price-box">'
                        . $replacementMessage
                        . '</div>';
                }

                return '<div class="b2b-login-to-see-price price-box">'
                    . '<span class="price-label">'
                    . $replacementMessage
                    . '</span></div>';
            }

            return $proceed();
        } catch (\Throwable $exception) {
            $this->logger->error('[B2B HideFinalPricePlugin] Falha ao aplicar regra de preço final.', [
                'exception' => $exception->getMessage(),
            ]);

            // Fail-open para não bloquear preço em caso de erro de sessão/contexto.
            return $proceed();
        }
    }

    private function isHomeCompactPriceMessage(string $message): bool
    {
        return str_contains($message, 'class="b2b-login-link"')
            && str_contains($message, 'aria-describedby="awa-home-pricing-notice"');
    }
}
