<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Setup\Patch\Data;

use GrupoAwamotos\B2B\Service\CustomerGroupManager;
use Magento\Customer\Api\Data\GroupInterfaceFactory;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Tax\Model\TaxClass\Source\Customer as CustomerTaxClass;

class CreateB2bCustomerGroups implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly GroupInterfaceFactory $groupFactory,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly CustomerGroupManager $groupManager
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        foreach ([CustomerGroupManager::GROUP_NAME_PENDING, CustomerGroupManager::GROUP_NAME_APPROVED] as $groupName) {
            if ($this->groupManager->getGroupIdByName($groupName) === null) {
                $group = $this->groupFactory->create();
                $group->setCode($groupName);
                $group->setTaxClassId(3); // 3 = Retail Customer (padrão Magento)
                $this->groupRepository->save($group);
            }
        }

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
