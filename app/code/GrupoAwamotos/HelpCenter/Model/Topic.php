<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Model;

use GrupoAwamotos\HelpCenter\Api\Data\TopicInterface;
use GrupoAwamotos\HelpCenter\Model\ResourceModel\Topic as TopicResource;
use Magento\Framework\Model\AbstractModel;

class Topic extends AbstractModel implements TopicInterface
{
    protected function _construct(): void
    {
        $this->_init(TopicResource::class);
    }

    public function getTopicId(): ?int
    {
        $id = $this->getData(self::ENTITY_ID);
        return $id !== null ? (int) $id : null;
    }

    public function getCategoryId(): ?int
    {
        $v = $this->getData(self::CATEGORY_ID);
        return $v !== null ? (int) $v : null;
    }

    public function getTitle(): string
    {
        return (string) $this->getData(self::TITLE);
    }

    public function getSummary(): ?string
    {
        $v = $this->getData(self::SUMMARY);
        return $v !== null ? (string) $v : null;
    }

    public function getContent(): ?string
    {
        $v = $this->getData(self::CONTENT);
        return $v !== null ? (string) $v : null;
    }

    public function getKeywords(): ?string
    {
        $v = $this->getData(self::KEYWORDS);
        return $v !== null ? (string) $v : null;
    }

    public function getAudience(): string
    {
        return (string) ($this->getData(self::AUDIENCE) ?: 'all');
    }

    public function getRoutePattern(): ?string
    {
        $v = $this->getData(self::ROUTE_PATTERN);
        return ($v !== null && $v !== '') ? (string) $v : null;
    }

    public function getSortOrder(): int
    {
        return (int) $this->getData(self::SORT_ORDER);
    }

    public function getStatus(): int
    {
        return (int) $this->getData(self::STATUS);
    }

    public function getTourSteps(): ?string
    {
        $v = $this->getData(self::TOUR_STEPS);
        return ($v !== null && $v !== '') ? (string) $v : null;
    }

    public function getViews(): int
    {
        return (int) $this->getData(self::VIEWS);
    }

    public function getCreatedAt(): ?string
    {
        $v = $this->getData(self::CREATED_AT);
        return $v !== null ? (string) $v : null;
    }

    public function getUpdatedAt(): ?string
    {
        $v = $this->getData(self::UPDATED_AT);
        return $v !== null ? (string) $v : null;
    }

    public function setTopicId(int $id): self
    {
        return $this->setData(self::ENTITY_ID, $id);
    }

    public function setCategoryId(?int $categoryId): self
    {
        return $this->setData(self::CATEGORY_ID, $categoryId);
    }

    public function setTitle(string $title): self
    {
        return $this->setData(self::TITLE, $title);
    }

    public function setSummary(?string $summary): self
    {
        return $this->setData(self::SUMMARY, $summary);
    }

    public function setContent(?string $content): self
    {
        return $this->setData(self::CONTENT, $content);
    }

    public function setKeywords(?string $keywords): self
    {
        return $this->setData(self::KEYWORDS, $keywords);
    }

    public function setAudience(string $audience): self
    {
        return $this->setData(self::AUDIENCE, $audience);
    }

    public function setRoutePattern(?string $pattern): self
    {
        return $this->setData(self::ROUTE_PATTERN, $pattern);
    }

    public function setSortOrder(int $order): self
    {
        return $this->setData(self::SORT_ORDER, $order);
    }

    public function setStatus(int $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    public function setTourSteps(?string $json): self
    {
        return $this->setData(self::TOUR_STEPS, $json);
    }

    public function setViews(int $views): self
    {
        return $this->setData(self::VIEWS, $views);
    }
}
