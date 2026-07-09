<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use GrupoAwamotos\B2B\Model\B2bCustomer;
use GrupoAwamotos\B2B\Model\B2bCustomerFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer as B2bCustomerResource;
use GrupoAwamotos\B2B\Service\CustomerGroupManager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Quando um admin move um cliente manualmente para o grupo B2B Aprovado,
 * atualiza o status B2B correspondente para 'aprovado'.
 */
class CustomerSaveAfter implements ObserverInterface
{
    public function __construct(
        private readonly B2bCustomerFactory $b2bCustomerFactory,
        private readonly B2bCustomerResource $b2bCustomerResource,
        private readonly CustomerGroupManager $groupManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var \Magento\Customer\Model\Customer $customer */
        $customer = $observer->getEvent()->getCustomer();
        if (!$customer) {
            return;
        }

        $customerId = (int) $customer->getId();
        $approvedGroupId = $this->groupManager->getGroupIdByName(CustomerGroupManager::GROUP_NAME_APPROVED);

        if ($approvedGroupId === null) {
            return;
        }

        try {
            $data = $this->b2bCustomerResource->getByCustomerId($customerId);
            if (empty($data)) {
                return;
            }

            $currentGroupId = (int) $customer->getGroupId();
            $currentStatus  = (int) $data['status'];

            if ($currentGroupId === $approvedGroupId && $currentStatus !== B2bCustomer::STATUS_APPROVED) {
                /** @var B2bCustomer $b2bCustomer */
                $b2bCustomer = $this->b2bCustomerFactory->create();
                $this->b2bCustomerResource->load($b2bCustomer, $data['b2b_customer_id']);
                $b2bCustomer->setData('status', B2bCustomer::STATUS_APPROVED);
                $b2bCustomer->setData('approved_at', date('Y-m-d H:i:s'));
                $this->b2bCustomerResource->save($b2bCustomer);

                $this->logger->info(sprintf(
                    '[B2B] Customer %d auto-aprovado via mudança de grupo.',
                    $customerId
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error('[B2B] Observer CustomerSaveAfter falhou: ' . $e->getMessage());
        }
    }
}
