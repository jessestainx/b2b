<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Model;

use GrupoAwamotos\HelpCenter\Api\CategoryRepositoryInterface;
use GrupoAwamotos\HelpCenter\Api\Data\CategoryInterface;
use GrupoAwamotos\HelpCenter\Model\ResourceModel\Category as CategoryResource;
use GrupoAwamotos\HelpCenter\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private readonly CategoryResource $resource,
        private readonly CategoryFactory $factory,
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory,
    ) {
    }

    public function getById(int $id): CategoryInterface
    {
        $model = $this->factory->create();
        $this->resource->load($model, $id);
        if (!$model->getCategoryId()) {
            throw new NoSuchEntityException(__('Categoria %1 não encontrada.', $id));
        }
        return $model;
    }

    public function save(CategoryInterface $category): CategoryInterface
    {
        try {
            $this->resource->save($category);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()), $e);
        }
        return $category;
    }

    public function delete(CategoryInterface $category): bool
    {
        try {
            $this->resource->delete($category);
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

    public function getByAudience(string $audience): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addActiveFilter();
        $collection->addAudienceFilter($audience);
        $collection->addSortOrder();
        return $collection->getItems();
    }
}
