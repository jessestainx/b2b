<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Api;

use GrupoAwamotos\HelpCenter\Api\Data\TopicInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;

interface TopicRepositoryInterface
{
    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): TopicInterface;

    public function save(TopicInterface $topic): TopicInterface;

    /**
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(TopicInterface $topic): bool;

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $id): bool;

    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * Search topics by query, filtered by audience.
     *
     * @param string[] $audiences e.g. ['all', 'customer']
     * @return TopicInterface[]
     */
    public function search(string $query, array $audiences, int $limit = 5): array;

    /**
     * Get topics matching the given admin route pattern.
     *
     * @param string[] $audiences
     * @return TopicInterface[]
     */
    public function getByRoutePattern(string $routeName, array $audiences): array;
}
