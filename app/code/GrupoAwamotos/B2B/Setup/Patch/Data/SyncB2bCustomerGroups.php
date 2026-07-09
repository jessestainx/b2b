<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Setup\Patch\Data;

use GrupoAwamotos\B2B\Model\B2bCustomer;
use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer\Collection;
use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer\CollectionFactory;
use GrupoAwamotos\B2B\Service\CustomerGroupManager;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Sincroniza o grupo Magento de TODOS os clientes B2B com seu status na tabela
 * grupoawamotos_b2b_customer.
 *
 * Necessário porque clientes importados em lote foram gravados com status=Aprovado
 * diretamente no banco sem passar pelo controller de aprovação, portanto seu grupo
 * Magento nunca foi atualizado de "B2B Pendente" → "B2B Aprovado".
 */
class SyncB2bCustomerGroups implements DataPatchInterface
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CollectionFactory $collectionFactory,
        private readonly CustomerGroupManager $groupManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        $approvedGroupId = $this->groupManager->getGroupIdByName(CustomerGroupManager::GROUP_NAME_APPROVED);
        $pendingGroupId  = $this->groupManager->getGroupIdByName(CustomerGroupManager::GROUP_NAME_PENDING);

        if ($approvedGroupId === null || $pendingGroupId === null) {
            $this->logger->error('[B2B SyncGroups] Grupos "B2B Aprovado" / "B2B Pendente" não encontrados. Execute CreateB2bCustomerGroups primeiro.');
            $this->moduleDataSetup->endSetup();
            return $this;
        }

        $this->syncByStatus(B2bCustomer::STATUS_APPROVED);
        $this->syncByStatus(B2bCustomer::STATUS_PENDING);

        $this->moduleDataSetup->endSetup();
        return $this;
    }

    private function syncByStatus(int $status): void
    {
        $page = 1;
        do {
            /** @var Collection $collection */
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('status', $status)
                       ->addFieldToFilter('customer_id', ['notnull' => true])
                       ->setPageSize(self::BATCH_SIZE)
                       ->setCurPage($page);

            $items = $collection->getItems();

            foreach ($items as $b2bCustomer) {
                /** @var B2bCustomer $b2bCustomer */
                $customerId = (int) $b2bCustomer->getCustomerId();
                try {
                    if ($status === B2bCustomer::STATUS_APPROVED) {
                        $this->groupManager->assignToApprovedGroup($customerId);
                    } else {
                        $this->groupManager->assignToPendingGroup($customerId);
                    }
                } catch (\Exception $e) {
                    $this->logger->warning(sprintf(
                        '[B2B SyncGroups] Falha ao sincronizar customer_id=%d (status=%d): %s',
                        $customerId,
                        $status,
                        $e->getMessage()
                    ));
                }
            }

            $page++;
        } while (count($items) === self::BATCH_SIZE);
    }

    public static function getDependencies(): array
    {
        return [CreateB2bCustomerGroups::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
