<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Order;

use GrupoAwamotos\B2B\Model\Order\CustomerOrderErpData;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order;

class ErpOperations extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Registry $registry,
        private readonly CustomerSession $customerSession,
        private readonly CustomerOrderErpData $customerOrderErpData,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCurrentOrder(): ?Order
    {
        $order = $this->registry->registry('current_order');

        return $order instanceof Order ? $order : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOperationsData(): array
    {
        $order = $this->getCurrentOrder();
        if (!$order || !$this->customerSession->isLoggedIn()) {
            return [
                'has_erp_mapping' => false,
                'invoice' => null,
                'tracking' => [],
                'timeline' => [],
            ];
        }

        if ((int) $order->getCustomerId() !== (int) $this->customerSession->getCustomerId()) {
            return [
                'has_erp_mapping' => false,
                'invoice' => null,
                'tracking' => [],
                'timeline' => [],
            ];
        }

        return $this->customerOrderErpData->getOperationsViewModel((int) $order->getId(), $order);
    }

    public function getDownloadUrl(string $type): string
    {
        $order = $this->getCurrentOrder();

        return $this->getUrl('b2b/order/downloadInvoice', [
            'order_id' => $order ? (int) $order->getId() : 0,
            'type' => $type,
        ]);
    }

    public function shouldDisplay(): bool
    {
        $data = $this->getOperationsData();

        return !empty($data['has_erp_mapping'])
            || !empty($data['invoice'])
            || !empty($data['tracking']['code'])
            || !empty($data['tracking']['estimated_delivery']);
    }
}
