<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Framework\Model\AbstractModel;

class B2bCustomer extends AbstractModel
{
    public const STATUS_PENDING  = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    protected function _construct(): void
    {
        $this->_init(ResourceModel\B2bCustomer::class);
    }

    public function getB2bCustomerId(): ?int
    {
        $v = $this->getData('b2b_customer_id');
        return $v !== null ? (int) $v : null;
    }

    public function getCustomerId(): ?int
    {
        $v = $this->getData('customer_id');
        return $v !== null ? (int) $v : null;
    }

    public function getCnpj(): string
    {
        return (string) $this->getData('cnpj');
    }

    public function getRazaoSocial(): string
    {
        return (string) $this->getData('razao_social');
    }

    public function getNomeFantasia(): string
    {
        return (string) ($this->getData('nome_fantasia') ?? '');
    }

    public function getInscricaoEstadual(): string
    {
        return (string) ($this->getData('inscricao_estadual') ?? '');
    }

    public function getPhone(): string
    {
        return (string) $this->getData('phone');
    }

    public function getEmail(): string
    {
        return (string) $this->getData('email');
    }

    public function getContactName(): string
    {
        return (string) $this->getData('contact_name');
    }

    public function getStatus(): int
    {
        return (int) $this->getData('status');
    }

    public function isApproved(): bool
    {
        return $this->getStatus() === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->getStatus() === self::STATUS_PENDING;
    }

    public function getStatusLabel(): string
    {
        return match ($this->getStatus()) {
            self::STATUS_APPROVED => 'Aprovado',
            self::STATUS_REJECTED => 'Rejeitado',
            default               => 'Pendente',
        };
    }

    public function getCodErp(): string
    {
        return (string) ($this->getData('cod_erp') ?? '');
    }

    public function setCodErp(string $codErp): self
    {
        return $this->setData('cod_erp', $codErp);
    }

    public function getListaPrecoErp(): string
    {
        return (string) ($this->getData('lista_preco_erp') ?? '');
    }

    public function setListaPrecoErp(string $lista): self
    {
        return $this->setData('lista_preco_erp', $lista);
    }
}
