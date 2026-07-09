<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Service;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class CustomerGroupManager
{
    public const GROUP_NAME_PENDING  = 'B2B Pendente';
    public const GROUP_NAME_APPROVED = 'B2B Aprovado';

    /** Cache key prefix — detectado pelo observer CustomerGroupRefresh no frontend */
    public const CACHE_KEY_PREFIX = 'b2b_group_changed_';
    public const CACHE_LIFETIME   = 86400; // 24h

    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly GroupCollectionFactory $groupCollectionFactory,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    public function assignToPendingGroup(int $customerId): void
    {
        $this->assignToGroup($customerId, self::GROUP_NAME_PENDING);
    }

    public function assignToApprovedGroup(int $customerId): void
    {
        $this->assignToGroup($customerId, self::GROUP_NAME_APPROVED);
    }

    public function getGroupIdByName(string $groupName): ?int
    {
        /** @var GroupCollection $collection */
        $collection = $this->groupCollectionFactory->create();
        $collection->addFieldToFilter('customer_group_code', $groupName);
        $group = $collection->getFirstItem();

        if (!$group->getId()) {
            return null;
        }

        return (int) $group->getId();
    }

    public function isInApprovedGroup(int $customerId): bool
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $approvedGroupId = $this->getGroupIdByName(self::GROUP_NAME_APPROVED);
            return $approvedGroupId !== null && (int)$customer->getGroupId() === $approvedGroupId;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function assignToGroup(int $customerId, string $groupName): void
    {
        $groupId = $this->getGroupIdByName($groupName);
        if ($groupId === null) {
            throw new LocalizedException(
                __('Grupo de clientes "%1" não encontrado. Execute php bin/magento setup:upgrade.', $groupName)
            );
        }

        $customer = $this->customerRepository->getById($customerId);
        $customer->setGroupId($groupId);
        $this->customerRepository->save($customer);

        /* Sinaliza que o grupo mudou — o observer CustomerGroupRefresh atualiza a sessão
           do cliente na próxima request frontend sem precisar de logout manual. */
        $this->cache->save(
            (string) $groupId,
            self::CACHE_KEY_PREFIX . $customerId,
            [],
            self::CACHE_LIFETIME
        );

        $this->logger->info(sprintf(
            '[B2B] Customer %d assigned to group "%s" (id=%d)',
            $customerId,
            $groupName,
            $groupId
        ));
    }
}
