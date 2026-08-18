<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\ViewModel;

use GrupoAwamotos\HelpCenter\Api\Data\CategoryInterface;
use GrupoAwamotos\HelpCenter\Api\Data\TopicInterface;
use GrupoAwamotos\HelpCenter\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use GrupoAwamotos\HelpCenter\Model\ResourceModel\Topic\CollectionFactory as TopicCollectionFactory;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class CockpitHelp implements ArgumentInterface
{
    public function __construct(
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly TopicCollectionFactory $topicCollectionFactory,
        private readonly AdminSession $adminSession,
        private readonly RequestInterface $request,
    ) {
    }

    /**
     * @return string[]
     */
    private function getAudiences(): array
    {
        return [
            CategoryInterface::AUDIENCE_ALL,
            CategoryInterface::AUDIENCE_SELLER,
            $this->isSupervisor()
                ? CategoryInterface::AUDIENCE_SUPERVISOR
                : CategoryInterface::AUDIENCE_SELLER,
        ];
    }

    public function isSupervisor(): bool
    {
        $user = $this->adminSession->getUser();
        if (!$user) {
            return false;
        }
        return (bool) $user->getData('is_commercial_supervisor');
    }

    /**
     * @return CategoryInterface[]
     */
    public function getCategories(): array
    {
        $audiences = $this->getAudiences();
        $collection = $this->categoryCollectionFactory->create();
        $collection->addActiveFilter();
        $collection->addFieldToFilter('audience', ['in' => array_values(array_unique($audiences))]);
        $collection->addSortOrder();
        return array_values($collection->getItems());
    }

    /**
     * @return TopicInterface[]
     */
    public function getTopicsByCategory(int $categoryId): array
    {
        $audiences = $this->getAudiences();
        $collection = $this->topicCollectionFactory->create();
        $collection->addActiveFilter();
        $collection->addAudienceFilter($audiences);
        $collection->addCategoryFilter($categoryId);
        $collection->addSortOrder();
        return array_values($collection->getItems());
    }

    /**
     * @return TopicInterface[]
     */
    public function getContextualTopics(string $routePattern = ''): array
    {
        if ($routePattern === '') {
            return [];
        }
        $audiences = $this->getAudiences();
        $collection = $this->topicCollectionFactory->create();
        $collection->addActiveFilter();
        $collection->addAudienceFilter($audiences);
        $escapedPattern = $collection->getConnection()->quote(
            str_replace('*', '%', $routePattern) . '%'
        );
        $collection->getSelect()->where(
            "route_pattern IS NOT NULL AND route_pattern != '' AND route_pattern LIKE {$escapedPattern}"
        );
        $collection->addSortOrder();
        return array_values($collection->getItems());
    }
}
