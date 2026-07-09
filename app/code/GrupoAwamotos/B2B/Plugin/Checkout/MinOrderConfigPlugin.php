<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use GrupoAwamotos\B2B\Helper\Config;
use Magento\Checkout\Model\DefaultConfigProvider;

class MinOrderConfigPlugin
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function afterGetConfig(DefaultConfigProvider $subject, array $result): array
    {
        unset($subject);

        if (!$this->config->isEnabled() || !$this->config->isMinQtyEnabled()) {
            return $result;
        }

        $minAmount = $this->config->getMinOrderAmount();

        if ($minAmount <= 0) {
            return $result;
        }

        $result['b2bMinOrder'] = [
            'enabled' => true,
            'minAmount' => $minAmount,
            'message' => $this->config->getMinOrderMessage(),
        ];

        return $result;
    }
}
