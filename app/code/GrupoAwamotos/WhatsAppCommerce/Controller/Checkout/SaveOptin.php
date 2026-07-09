<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Controller\Checkout;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

/**
 * AJAX endpoint to save whatsapp_optin customer attribute from checkout.
 */
class SaveOptin implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly CustomerSession $customerSession,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['success' => false, 'message' => 'Not logged in']);
        }

        $optin = (int) $this->request->getParam('optin', 0);
        $optinValue = $optin === 1 ? '1' : '0';

        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            $customer = $this->customerRepository->getById($customerId);
            $customer->setCustomAttribute('whatsapp_optin', $optinValue);
            $this->customerRepository->save($customer);

            $this->logger->info('WhatsApp opt-in saved via checkout', [
                'customer_id' => $customerId,
                'optin' => $optinValue,
            ]);

            return $result->setData(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to save WhatsApp opt-in', [
                'error' => $e->getMessage(),
            ]);

            return $result->setData(['success' => false, 'message' => 'Error saving preference']);
        }
    }
}
