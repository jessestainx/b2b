<?php

/**
 * B2B Order Approval Model
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Framework\Model\AbstractModel;

class OrderApproval extends AbstractModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const LEVEL_BUYER = 1;      // Comprador
    public const LEVEL_MANAGER = 2;    // Gerente
    public const LEVEL_FINANCE = 3;    // Financeiro
    public const LEVEL_DIRECTOR = 4;   // Diretor

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(\GrupoAwamotos\B2B\Model\ResourceModel\OrderApproval::class);
    }

    /**
     * Get all approval statuses
     *
     * @return array
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => __('Aguardando Aprovação'),
            self::STATUS_APPROVED => __('Aprovado'),
            self::STATUS_REJECTED => __('Rejeitado'),
            self::STATUS_CANCELLED => __('Cancelado')
        ];
    }

    /**
     * Get all approval levels
     *
     * @return array
     */
    public static function getLevels(): array
    {
        return [
            self::LEVEL_BUYER => __('Comprador'),
            self::LEVEL_MANAGER => __('Gerente'),
            self::LEVEL_FINANCE => __('Financeiro'),
            self::LEVEL_DIRECTOR => __('Diretor')
        ];
    }

    /**
     * Get next approval level
     *
     * @param int $currentLevel
     * @return int|null
     */
    public function getNextLevel(int $currentLevel): ?int
    {
        $levels = array_keys(self::getLevels());
        $currentIndex = array_search($currentLevel, $levels);

        if ($currentIndex !== false && isset($levels[$currentIndex + 1])) {
            return $levels[$currentIndex + 1];
        }

        return null;
    }

    /**
     * Check if order is fully approved
     *
     * @return bool
     */
    public function isFullyApproved(): bool
    {
        return $this->getData('status') === self::STATUS_APPROVED
            && $this->getData('current_level') >= $this->getData('required_level');
    }

    /**
     * Check if customer can approve at this level
     *
     * @param int $customerLevel
     * @return bool
     */
    public function canApprove(int $customerLevel): bool
    {
        if ($this->getData('status') !== self::STATUS_PENDING) {
            return false;
        }

        return $customerLevel >= $this->getData('current_level');
    }
}
