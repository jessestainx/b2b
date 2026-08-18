<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Model;

use GrupoAwamotos\HelpCenter\Api\Data\TopicInterface;
use GrupoAwamotos\HelpCenter\Api\TopicRepositoryInterface;
use GrupoAwamotos\HelpCenter\Model\ResourceModel\Topic as TopicResource;
use GrupoAwamotos\HelpCenter\Model\ResourceModel\Topic\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class TopicRepository implements TopicRepositoryInterface
{
    public function __construct(
        private readonly TopicResource $resource,
        private readonly TopicFactory $factory,
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory,
    ) {
    }

    public function getById(int $id): TopicInterface
    {
        $model = $this->factory->create();
        $this->resource->load($model, $id);
        if (!$model->getTopicId()) {
            throw new NoSuchEntityException(__('Tópico %1 não encontrado.', $id));
        }
        return $model;
    }

    public function save(TopicInterface $topic): TopicInterface
    {
        try {
            $this->resource->save($topic);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()), $e);
        }
        return $topic;
    }

    public function delete(TopicInterface $topic): bool
    {
        try {
            $this->resource->delete($topic);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__($e->getMessage()), $e);
        }
        return true;
    }

    public function deleteById(int $id): bool
    {
        return $this->delete($this->getById($id));
    }

    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $results = $this->searchResultsFactory->create();
        $results->setSearchCriteria($searchCriteria);
        $results->setItems($collection->getItems());
        $results->setTotalCount($collection->getSize());
        return $results;
    }

    public function search(string $query, array $audiences, int $limit = 5): array
    {
        if (trim($query) === '') {
            return [];
        }
        $collection = $this->collectionFactory->create();
        $collection->addActiveFilter();
        $collection->addAudienceFilter($audiences);
        $collection->addSearchFilter($query);
        $collection->addSortOrder();
        $collection->setPageSize($limit);
        return array_values($collection->getItems());
    }

    public function getByRoutePattern(string $routeName, array $audiences): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addActiveFilter();
        $collection->addAudienceFilter($audiences);
        $collection->addSortOrder();

        $escaped = $collection->getConnection()->quote(
            str_replace('*', '%', $routeName) . '%'
        );
        $collection->getSelect()->where(
            "route_pattern IS NULL OR route_pattern = '' OR route_pattern LIKE {$escaped}"
        );

        return array_values($collection->getItems());
    }
}
