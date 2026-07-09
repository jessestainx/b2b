<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Framework\Model\AbstractModel;

class CreditTransaction extends AbstractModel
{
    public const TYPE_CHARGE = 'charge';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_PAYMENT = 'payment';

    protected function _construct()
    {
        $this->_init(\GrupoAwamotos\B2B\Model\ResourceModel\CreditTransaction::class);
    }

    public static function getTypes(): array
    {
        return [
            self::TYPE_CHARGE => __('Débito (Pedido)'),
            self::TYPE_REFUND => __('Estorno'),
            self::TYPE_ADJUSTMENT => __('Ajuste Manual'),
            self::TYPE_PAYMENT => __('Pagamento Recebido'),
        ];
    }
}
