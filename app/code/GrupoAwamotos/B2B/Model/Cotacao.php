<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Framework\Model\AbstractModel;

class Cotacao extends AbstractModel
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_QUOTED   = 'quoted';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED  = 'expired';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\Cotacao::class);
    }

    public function getCotacaoId(): ?int
    {
        $v = $this->getData('cotacao_id');
        return $v !== null ? (int) $v : null;
    }

    public function getB2bCustomerId(): int
    {
        return (int) $this->getData('b2b_customer_id');
    }

    public function getCustomerId(): ?int
    {
        $v = $this->getData('customer_id');
        return $v !== null ? (int) $v : null;
    }

    public function getStatus(): string
    {
        return (string) ($this->getData('status') ?? self::STATUS_PENDING);
    }

    public function getNotes(): string
    {
        return (string) ($this->getData('notes') ?? '');
    }

    public function getAdminNotes(): string
    {
        return (string) ($this->getData('admin_notes') ?? '');
    }

    public function getStatusLabel(): string
    {
        return match ($this->getStatus()) {
            self::STATUS_QUOTED   => 'Cotado',
            self::STATUS_ACCEPTED => 'Aceito',
            self::STATUS_REJECTED => 'Recusado',
            self::STATUS_EXPIRED  => 'Expirado',
            default               => 'Pendente',
        };
    }

    public function isPending(): bool
    {
        return $this->getStatus() === self::STATUS_PENDING;
    }

    public function isQuoted(): bool
    {
        return $this->getStatus() === self::STATUS_QUOTED;
    }

    public function isAccepted(): bool
    {
        return $this->getStatus() === self::STATUS_ACCEPTED;
    }
}
