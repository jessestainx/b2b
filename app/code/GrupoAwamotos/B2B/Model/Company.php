<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Framework\Model\AbstractModel;

class Company extends AbstractModel
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_BUYER = 'buyer';

    protected function _construct()
    {
        $this->_init(\GrupoAwamotos\B2B\Model\ResourceModel\Company::class);
    }

    public static function getRoles(): array
    {
        return [
            self::ROLE_ADMIN => __('Administrador'),
            self::ROLE_MANAGER => __('Gerente'),
            self::ROLE_BUYER => __('Comprador'),
        ];
    }
}
