<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Observer;

use GrupoAwamotos\CatalogFix\Model\OfertasCategorySync;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class SyncOfertasOnProductSave implements ObserverInterface
{
    private const WATCHED_ATTRIBUTES = [
        'special_price',
        'special_from_date',
        'special_to_date',
        'status',
    ];

    public function __construct(
        private readonly OfertasCategorySync $ofertasCategorySync
    ) {
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();
        if (!$product instanceof Product || !$this->hasRelevantChanges($product)) {
            return;
        }

        $this->ofertasCategorySync->sync();
    }

    private function hasRelevantChanges(Product $product): bool
    {
        foreach (self::WATCHED_ATTRIBUTES as $attributeCode) {
            if ($product->dataHasChangedFor($attributeCode)) {
                return true;
            }
        }

        return false;
    }
}
