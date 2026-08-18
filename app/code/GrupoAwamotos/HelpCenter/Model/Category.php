<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Model;

use GrupoAwamotos\HelpCenter\Api\Data\CategoryInterface;
use GrupoAwamotos\HelpCenter\Model\ResourceModel\Category as CategoryResource;
use Magento\Framework\Model\AbstractModel;

class Category extends AbstractModel implements CategoryInterface
{
    protected function _construct(): void
    {
        $this->_init(CategoryResource::class);
    }

    public function getCategoryId(): ?int
    {
        $id = $this->getData(self::ENTITY_ID);
        return $id !== null ? (int) $id : null;
    }

    public function getName(): string
    {
        return (string) $this->getData(self::NAME);
    }

    public function getDescription(): ?string
    {
        $v = $this->getData(self::DESCRIPTION);
        return $v !== null ? (string) $v : null;
    }

    public function getSortOrder(): int
    {
        return (int) $this->getData(self::SORT_ORDER);
    }

    public function getAudience(): string
    {
        return (string) ($this->getData(self::AUDIENCE) ?: self::AUDIENCE_ALL);
    }

    public function getStatus(): int
    {
        return (int) $this->getData(self::STATUS);
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

    public function setCategoryId(int $id): self
    {
        return $this->setData(self::ENTITY_ID, $id);
    }

    public function setName(string $name): self
    {
        return $this->setData(self::NAME, $name);
    }

    public function setDescription(?string $description): self
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    public function setSortOrder(int $order): self
    {
        return $this->setData(self::SORT_ORDER, $order);
    }

    public function setAudience(string $audience): self
    {
        return $this->setData(self::AUDIENCE, $audience);
    }

    public function setStatus(int $status): self
    {
        return $this->setData(self::STATUS, $status);
    }
}
