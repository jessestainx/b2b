<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Setup\Patch\Data;

use GrupoAwamotos\StoreSetup\Model\HomepagesliderBackfiller;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class BackfillHomepagesliderSlides implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly HomepagesliderBackfiller $homepagesliderBackfiller
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();
        $this->homepagesliderBackfiller->backfill($this->moduleDataSetup->getConnection());
        $this->moduleDataSetup->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [
            AyoSeedContentV2::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
