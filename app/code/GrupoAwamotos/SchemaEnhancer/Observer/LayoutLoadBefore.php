<?php

/**
 * Observer - injeta bloco schema-enhancer antes do body end do PDP.
 */

declare(strict_types=1);

namespace GrupoAwamotos\SchemaEnhancer\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class LayoutLoadBefore implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        $layout = $observer->getLayout();
        $fullActionName = (string) $observer->getData('full_action_name');

        // Detecta PDP
        if ($fullActionName !== 'catalog_product_view') {
            return;
        }

        $layout->getUpdate()->addHandle('awa_schemaenhancer_pdp');
    }
}
