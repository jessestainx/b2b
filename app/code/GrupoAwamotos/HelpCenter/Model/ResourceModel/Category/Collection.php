<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Model\ResourceModel\Category;

use GrupoAwamotos\HelpCenter\Model\Category;
use GrupoAwamotos\HelpCenter\Model\ResourceModel\Category as CategoryResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Category::class, CategoryResource::class);
    }

    public function addActiveFilter(): self
    {
        return $this->addFieldToFilter('status', 1);
    }

    public function addAudienceFilter(string $audience): self
    {
        return $this->addFieldToFilter('audience', ['in' => ['all', $audience]]);
    }

    public function addSortOrder(): self
    {
        return $this->setOrder('sort_order', 'ASC');
    }
}
