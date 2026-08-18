<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Api\Data;

interface TopicInterface
{
    public const ENTITY_ID     = 'topic_id';
    public const CATEGORY_ID   = 'category_id';
    public const TITLE         = 'title';
    public const SUMMARY       = 'summary';
    public const CONTENT       = 'content';
    public const KEYWORDS      = 'keywords';
    public const AUDIENCE      = 'audience';
    public const ROUTE_PATTERN = 'route_pattern';
    public const SORT_ORDER    = 'sort_order';
    public const STATUS        = 'status';
    public const TOUR_STEPS    = 'tour_steps';
    public const VIEWS         = 'views';
    public const CREATED_AT    = 'created_at';
    public const UPDATED_AT    = 'updated_at';

    public function getTopicId(): ?int;
    public function getCategoryId(): ?int;
    public function getTitle(): string;
    public function getSummary(): ?string;
    public function getContent(): ?string;
    public function getKeywords(): ?string;
    public function getAudience(): string;
    public function getRoutePattern(): ?string;
    public function getSortOrder(): int;
    public function getStatus(): int;
    public function getTourSteps(): ?string;
    public function getViews(): int;
    public function getCreatedAt(): ?string;
    public function getUpdatedAt(): ?string;

    public function setTopicId(int $id): self;
    public function setCategoryId(?int $categoryId): self;
    public function setTitle(string $title): self;
    public function setSummary(?string $summary): self;
    public function setContent(?string $content): self;
    public function setKeywords(?string $keywords): self;
    public function setAudience(string $audience): self;
    public function setRoutePattern(?string $pattern): self;
    public function setSortOrder(int $order): self;
    public function setStatus(int $status): self;
    public function setTourSteps(?string $json): self;
    public function setViews(int $views): self;
}
