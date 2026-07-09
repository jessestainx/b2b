<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use GrupoAwamotos\B2B\Service\PrivateLabelDetector;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Ao salvar um produto (criação ou edição via admin, import CSV, API),
 * chama o PrivateLabelDetector para verificar se o SKU corresponde a
 * algum alias de cliente e proteger automaticamente o produto.
 */
class PrivateLabelProductSave implements ObserverInterface
{
    public function __construct(
        private readonly PrivateLabelDetector $detector,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Product $product */
        $product = $observer->getEvent()->getProduct();
        if (!$product) {
            return;
        }

        $productId = (int) $product->getId();
        $sku       = (string) $product->getSku();

        if ($productId <= 0 || $sku === '') {
            return;
        }

        try {
            $this->detector->detectAndRegister($productId, $sku);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                '[PrivateLabel] Falha ao processar produto %d (SKU: %s): %s',
                $productId,
                $sku,
                $e->getMessage()
            ));
        }
    }
}
