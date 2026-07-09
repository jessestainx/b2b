<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Framework\Model\AbstractModel;

class CotacaoItem extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\CotacaoItem::class);
    }

    public function getItemId(): ?int
    {
        $v = $this->getData('item_id');
        return $v !== null ? (int) $v : null;
    }

    public function getCotacaoId(): int
    {
        return (int) $this->getData('cotacao_id');
    }

    public function getProductId(): int
    {
        return (int) $this->getData('product_id');
    }

    public function getSku(): string
    {
        return (string) $this->getData('sku');
    }

    public function getName(): string
    {
        return (string) $this->getData('name');
    }

    public function getQty(): float
    {
        return (float) $this->getData('qty');
    }

    public function getPriceCatalog(): float
    {
        return (float) $this->getData('price_catalog');
    }

    public function getPriceQuoted(): ?float
    {
        $v = $this->getData('price_quoted');
        return $v !== null ? (float) $v : null;
    }
}
