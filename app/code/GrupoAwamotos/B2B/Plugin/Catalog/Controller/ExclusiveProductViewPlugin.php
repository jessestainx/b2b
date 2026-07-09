<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Catalog\Controller;

use Magento\Catalog\Controller\Product\View as ProductViewController;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\Page;

/**
 * Bloqueia o acesso direto à PDP de produtos OEM/exclusivos para qualquer
 * cliente diferente do dono cadastrado em grupoawamotos_b2b_exclusive_product.
 *
 * Também marca a resposta como no-store para impedir que o FPC/Varnish
 * sirva a página exclusiva para outros visitantes via cache compartilhado.
 */
class ExclusiveProductViewPlugin
{
    private const TABLE = 'grupoawamotos_b2b_exclusive_product';

    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly ResourceConnection $resourceConnection,
        private readonly ForwardFactory $resultForwardFactory
    ) {
    }

    public function aroundExecute(
        ProductViewController $subject,
        \Closure $proceed
    ): mixed {
        $productId = (int) $subject->getRequest()->getParam('id');

        $exclusiveRow = $productId > 0 ? $this->getExclusiveRow($productId) : false;

        if ($exclusiveRow !== false) {
            $customerId = (int) $this->customerSession->getId();

            if ((int) $exclusiveRow['customer_id'] !== $customerId) {
                /** @var Forward $resultForward */
                $resultForward = $this->resultForwardFactory->create();
                return $resultForward->forward('noroute');
            }

            // Owner is viewing: prevent FPC/Varnish from caching this private page.
            $result = $proceed();
            if ($result instanceof Page) {
                $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', true);
                $result->setHeader('Pragma', 'no-cache', true);
            }
            return $result;
        }

        return $proceed();
    }

    private function getExclusiveRow(int $productId): array|false
    {
        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName(self::TABLE);

        return $connection->fetchRow(
            $connection->select()
                ->from($table, ['customer_id'])
                ->where('product_id = ?', $productId)
        );
    }
}
