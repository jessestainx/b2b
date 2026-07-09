<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Setup\Patch\Data;

use GrupoAwamotos\StoreSetup\Setup\CmsBlockData;
use Magento\Cms\Model\Block;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Garante o CMS Block home_product_promo_banners em ambientes que já executaram AyoHomepageCmsBlocks.
 * Idempotente: sempre atualiza conteúdo para a versão mais recente.
 */
class HomeProductPromoBannersBlock implements DataPatchInterface
{
    private const BLOCK_IDENTIFIER = 'home_product_promo_banners';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly Block $blockModel,
        private readonly LoggerInterface $logger
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        try {
            $block = clone $this->blockModel;
            $block->setStoreId(0);
            $block->load(self::BLOCK_IDENTIFIER, 'identifier');
            $action = $block->getId() ? 'atualizado' : 'criado';

            $block->addData([
                'title'      => 'Homepage — Banners Promocionais de Produto',
                'identifier' => self::BLOCK_IDENTIFIER,
                'content'    => CmsBlockData::homeProductPromoBannersContent(),
                'is_active'  => 1,
            ]);
            $block->setStores([0]);
            $block->save();

            $this->logger->info(
                sprintf('[HomeProductPromoBannersBlock] Bloco "%s" %s.', self::BLOCK_IDENTIFIER, $action)
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('[HomeProductPromoBannersBlock] Erro: %s', $e->getMessage())
            );
        }

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [
            AyoHomepageCmsBlocks::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
