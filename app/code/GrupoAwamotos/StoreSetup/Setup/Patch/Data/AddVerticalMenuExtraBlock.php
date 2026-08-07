<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Setup\Patch\Data;

use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\ResourceModel\Block as BlockResource;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Cria o bloco CMS `vertical-menu-extra` exibido abaixo do menu lateral de categorias.
 *
 * O bloco é injetado via Layout XML (Rokanthemes_Themeoption/layout/default.xml)
 * no child theme AWA_Custom/ayo_home5_child, após o bloco `menu.vertical`.
 *
 * Para editar o conteúdo: Admin > Content > Blocks > "Menu Lateral — Links Extras".
 */
class AddVerticalMenuExtraBlock implements DataPatchInterface
{
    private const BLOCK_IDENTIFIER = 'vertical-menu-extra';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly BlockFactory $blockFactory,
        private readonly BlockResource $blockResource,
        private readonly LoggerInterface $logger
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        try {
            $block = $this->blockFactory->create();
            $this->blockResource->load($block, self::BLOCK_IDENTIFIER, 'identifier');

            if ($block->getId()) {
                $this->logger->info('[AddVerticalMenuExtraBlock] Bloco já existe, pulando.');
            } else {
                $block->setData([
                    'title'      => 'Menu Lateral — Links Extras',
                    'identifier' => self::BLOCK_IDENTIFIER,
                    'is_active'  => 1,
                    'stores'     => [0],
                    'content'    => $this->getBlockContent(),
                ]);
                $this->blockResource->save($block);
                $this->logger->info('[AddVerticalMenuExtraBlock] Bloco "vertical-menu-extra" criado.');
            }
        } catch (\Exception $e) {
            $this->logger->error('[AddVerticalMenuExtraBlock] Erro: ' . $e->getMessage());
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

    private function getBlockContent(): string
    {
        return <<<'HTML'
<nav class="awa-vertical-extra-menu" aria-label="Links rápidos">
    <ul class="awa-vem-list">
        <li class="awa-vem-item">
            <a class="awa-vem-link" href="{{store url="about-us"}}">
                Sobre a AWA Motos
            </a>
        </li>
        <li class="awa-vem-item">
            <a class="awa-vem-link" href="{{store url="contato"}}">
                Fale Conosco
            </a>
        </li>
        <li class="awa-vem-item">
            <a class="awa-vem-link" href="{{store url="sales/guest/form"}}">
                Rastrear Pedido
            </a>
        </li>
        <li class="awa-vem-item">
            <a class="awa-vem-link" href="{{store url="customer/account"}}">
                Minha Conta
            </a>
        </li>
    </ul>
</nav>
HTML;
    }
}
