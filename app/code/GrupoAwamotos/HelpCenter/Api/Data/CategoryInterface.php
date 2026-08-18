<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Api\Data;

interface CategoryInterface
{
    public const ENTITY_ID  = 'category_id';
    public const NAME       = 'name';
    public const DESCRIPTION = 'description';
    public const SORT_ORDER = 'sort_order';
    public const AUDIENCE   = 'audience';
    public const STATUS     = 'status';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const AUDIENCE_ALL        = 'all';
    public const AUDIENCE_CUSTOMER   = 'customer';
    public const AUDIENCE_B2B        = 'b2b';
    public const AUDIENCE_SELLER     = 'seller';
    public const AUDIENCE_SUPERVISOR = 'supervisor';
    public const AUDIENCE_ADMIN      = 'admin';

    public function getCategoryId(): ?int;
    public function getName(): string;
    public function getDescription(): ?string;
    public function getSortOrder(): int;
    public function getAudience(): string;
    public function getStatus(): int;
    public function getCreatedAt(): ?string;
    public function getUpdatedAt(): ?string;

    public function setCategoryId(int $id): self;
    public function setName(string $name): self;
    public function setDescription(?string $description): self;
    public function setSortOrder(int $order): self;
    public function setAudience(string $audience): self;
    public function setStatus(int $status): self;
}
