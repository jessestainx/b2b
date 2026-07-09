<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Setup\Patch\Data;

use GrupoAwamotos\B2B\CommercialPanel\Model\Admin\CommercialRoleAssignment;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AssignCommercialSellerRoles implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CommercialRoleAssignment $roleAssignment,
        private readonly TypeListInterface $cacheTypeList
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $this->roleAssignment->assignSellerRoleToActiveAttendants();
        $this->cacheTypeList->cleanType('config');

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [UpdateCommercialAdminRolesPhase3::class];
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
