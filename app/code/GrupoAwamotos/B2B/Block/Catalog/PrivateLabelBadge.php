<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Catalog;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Badge exibido na PDP quando o cliente logado é o dono exclusivo do produto.
 * Mostra a marca/label OEM configurada para o produto.
 * Se o produto não for exclusivo, o bloco não renderiza nada (retorna "").
 */
class PrivateLabelBadge extends Template
{
    protected $_template = 'GrupoAwamotos_B2B::catalog/private-label-badge.phtml';

    private const TABLE = 'grupoawamotos_b2b_exclusive_product';

    private ?array $exclusiveData = null;
    private bool $loaded = false;

    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly ResourceConnection $resourceConnection,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Retorna os dados de exclusividade se o produto atual pertencer ao cliente logado.
     * Retorna null caso contrário.
     *
     * @return array{label:string|null,customer_id:int}|null
     */
    public function getExclusiveData(): ?array
    {
        if ($this->loaded) {
            return $this->exclusiveData;
        }

        $this->loaded = true;
        $product      = $this->registry->registry('current_product');

        if (!$product) {
            return null;
        }

        $productId  = (int) $product->getId();
        $customerId = (int) $this->customerSession->getId();

        if ($productId <= 0 || $customerId <= 0) {
            return null;
        }

        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName(self::TABLE);

        $row = $connection->fetchRow(
            $connection->select()
                ->from($table, ['customer_id', 'label'])
                ->where('product_id = ?', $productId)
                ->where('customer_id = ?', $customerId)
        );

        $this->exclusiveData = $row ?: null;

        return $this->exclusiveData;
    }

    public function getLabel(): string
    {
        return (string) ($this->getExclusiveData()['label'] ?? '');
    }

    /** Não renderiza nada se o produto não for exclusivo deste cliente. */
    protected function _toHtml(): string
    {
        if ($this->getExclusiveData() === null) {
            return '';
        }

        return parent::_toHtml();
    }
}
