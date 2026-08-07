<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use GrupoAwamotos\B2B\Model\CheckoutAccessValidator;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartItemInterface;

class BlockCartItemSavePlugin
{
    public function __construct(
        private readonly Config $config,
        private readonly B2BHelper $b2bHelper,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CheckoutAccessValidator $checkoutAccessValidator
    ) {
    }

    /**
     * @throws CouldNotSaveException
     */
    public function beforeSave(object $subject, CartItemInterface $cartItem): array
    {
        if (!$this->config->isEnabled()) {
            return [$cartItem];
        }

        $quoteId = (int) $cartItem->getQuoteId();
        if ($quoteId <= 0) {
            return [$cartItem];
        }

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->cartRepository->getActive($quoteId);
        if (!$this->b2bHelper->isB2BGroup((int) $quote->getCustomerGroupId())) {
            return [$cartItem];
        }

        $customerId = (int) $quote->getCustomerId();
        $customerState = $this->checkoutAccessValidator->resolveCustomerState($customerId);

        if ($customerState === CheckoutAccessValidator::STATE_APPROVED) {
            return [$cartItem];
        }

        if ($customerState === CheckoutAccessValidator::STATE_PENDING_ERP) {
            throw new CouldNotSaveException(
                __($this->config->getPendingErpMessage() ?: 'Sua tabela de preços está sendo definida. Consulte o departamento de vendas.')
            );
        }

        throw new CouldNotSaveException(
            __('Sua conta precisa ser aprovada antes de adicionar produtos ao carrinho.')
        );
    }
}
