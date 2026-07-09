<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Framework\Model\AbstractModel;

class Subscription extends AbstractModel
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELLED = 'cancelled';

    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_BIWEEKLY = 'biweekly';
    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_QUARTERLY = 'quarterly';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(\GrupoAwamotos\B2B\Model\ResourceModel\Subscription::class);
    }

    /**
     * Get statuses
     *
     * @return array
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => __('Ativo'),
            self::STATUS_PAUSED => __('Pausado'),
            self::STATUS_CANCELLED => __('Cancelado')
        ];
    }

    /**
     * Get frequencies
     *
     * @return array
     */
    public static function getFrequencies(): array
    {
        return [
            self::FREQUENCY_WEEKLY => __('Semanal'),
            self::FREQUENCY_BIWEEKLY => __('Quinzenal'),
            self::FREQUENCY_MONTHLY => __('Mensal'),
            self::FREQUENCY_QUARTERLY => __('Trimestral')
        ];
    }

    /**
     * Get items as array
     *
     * @return array
     */
    public function getItems(): array
    {
        $items = $this->getData('items_serialized');
        if (!$items) {
            return [];
        }
        return json_decode($items, true) ?: [];
    }

    /**
     * Set items as array
     *
     * @param array $items
     * @return $this
     */
    public function setItems(array $items): self
    {
        return $this->setData('items_serialized', json_encode($items));
    }
}
