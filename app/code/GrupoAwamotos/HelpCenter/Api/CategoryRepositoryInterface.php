<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Api;

use GrupoAwamotos\HelpCenter\Api\Data\CategoryInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;

interface CategoryRepositoryInterface
{
    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): CategoryInterface;

    public function save(CategoryInterface $category): CategoryInterface;

    /**
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(CategoryInterface $category): bool;

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $id): bool;

    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * @return CategoryInterface[]
     */
    public function getByAudience(string $audience): array;
}
