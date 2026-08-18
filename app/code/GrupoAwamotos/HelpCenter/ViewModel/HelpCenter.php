<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\ViewModel;

use GrupoAwamotos\HelpCenter\Api\CategoryRepositoryInterface;
use GrupoAwamotos\HelpCenter\Api\Data\CategoryInterface;
use GrupoAwamotos\HelpCenter\Api\Data\TopicInterface;
use GrupoAwamotos\HelpCenter\Api\TopicRepositoryInterface;
use GrupoAwamotos\HelpCenter\Model\ResourceModel\Topic\CollectionFactory as TopicCollectionFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class HelpCenter implements ArgumentInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly TopicRepositoryInterface $topicRepository,
        private readonly TopicCollectionFactory $topicCollectionFactory,
        private readonly CustomerSession $customerSession,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly RequestInterface $request,
        private readonly UrlInterface $urlBuilder,
    ) {
    }

    /**
     * @return CategoryInterface[]
     */
    public function getCategories(): array
    {
        return $this->categoryRepository->getByAudience($this->getPrimaryAudience());
    }

    /**
     * @return TopicInterface[]
     */
    public function getTopicsByCategory(int $categoryId): array
    {
        $collection = $this->topicCollectionFactory->create();
        $collection->addActiveFilter();
        $collection->addAudienceFilter($this->getAudiences());
        $collection->addCategoryFilter($categoryId);
        $collection->addSortOrder();
        return array_values($collection->getItems());
    }

    public function isLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    public function isB2B(): bool
    {
        return $this->customerSession->isLoggedIn()
            && $this->customerSession->getCustomer()->getData('b2b_approval_status') === 'approved';
    }

    public function getSearchUrl(): string
    {
        return $this->urlBuilder->getUrl('ajuda/ajax/search');
    }

    /**
     * @return string[]
     */
    private function getAudiences(): array
    {
        $base = [CategoryInterface::AUDIENCE_ALL];
        if ($this->customerSession->isLoggedIn()) {
            $base[] = CategoryInterface::AUDIENCE_CUSTOMER;
            if ($this->isB2B()) {
                $base[] = CategoryInterface::AUDIENCE_B2B;
            }
        }
        return $base;
    }

    private function getPrimaryAudience(): string
    {
        if ($this->isB2B()) {
            return CategoryInterface::AUDIENCE_B2B;
        }
        if ($this->customerSession->isLoggedIn()) {
            return CategoryInterface::AUDIENCE_CUSTOMER;
        }
        return CategoryInterface::AUDIENCE_ALL;
    }
}
