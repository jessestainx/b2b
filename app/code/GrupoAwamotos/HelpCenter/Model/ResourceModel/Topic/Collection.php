<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Model\ResourceModel\Topic;

use GrupoAwamotos\HelpCenter\Model\Topic;
use GrupoAwamotos\HelpCenter\Model\ResourceModel\Topic as TopicResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Topic::class, TopicResource::class);
    }

    public function addActiveFilter(): self
    {
        return $this->addFieldToFilter('status', 1);
    }

    /**
     * @param string[] $audiences
     */
    public function addAudienceFilter(array $audiences): self
    {
        $allAudiences = array_values(array_unique(array_merge(['all'], $audiences)));
        return $this->addFieldToFilter('audience', ['in' => $allAudiences]);
    }

    public function addCategoryFilter(int $categoryId): self
    {
        return $this->addFieldToFilter('category_id', $categoryId);
    }

    public function addSortOrder(): self
    {
        return $this->setOrder('sort_order', 'ASC');
    }

    /**
     * Full-text + LIKE keyword search.
     */
    public function addSearchFilter(string $query): self
    {
        $escaped = $this->getConnection()->quote('%' . $query . '%');
        $this->getSelect()->where(
            "MATCH(title, keywords, summary) AGAINST (? IN BOOLEAN MODE)"
            . " OR title LIKE {$escaped}"
            . " OR keywords LIKE {$escaped}",
            $query
        );
        return $this;
    }
}
