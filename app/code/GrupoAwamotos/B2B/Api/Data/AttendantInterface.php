<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Api\Data;

interface AttendantInterface
{
    public const ATTENDANT_ID   = 'attendant_id';
    public const NAME           = 'name';
    public const EMAIL          = 'email';
    public const PHONE          = 'phone';
    public const WHATSAPP       = 'whatsapp';
    public const DEPARTMENT     = 'department';
    public const MAX_CUSTOMERS  = 'max_customers';
    public const ADMIN_USER_ID  = 'admin_user_id';
    public const ERP_SELLER_CODE = 'erp_seller_code';
    public const IS_ACTIVE      = 'is_active';
    public const CREATED_AT     = 'created_at';

    public function getAttendantId(): ?int;
    public function setAttendantId(int $id): self;
    public function getName(): ?string;
    public function setName(string $name): self;
    public function getEmail(): ?string;
    public function setEmail(string $email): self;
    public function getIsActive(): bool;
    public function setIsActive(bool $flag): self;
}
