<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account\Cotacao;

use GrupoAwamotos\B2B\Model\Cotacao;
use GrupoAwamotos\B2B\Model\ResourceModel\CotacaoItem as CotacaoItemResource;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class View extends Template
{
    protected $_template = 'GrupoAwamotos_B2B::cotacao/view.phtml';

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly CotacaoItemResource $cotacaoItemResource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCotacao(): ?Cotacao
    {
        return $this->registry->registry('current_cotacao');
    }

    public function getItems(): array
    {
        $cotacao = $this->getCotacao();
        if (!$cotacao) {
            return [];
        }
        return $this->cotacaoItemResource->getItemsByCotacaoId((int) $cotacao->getId());
    }

    public function getRespondUrl(): string
    {
        return $this->getUrl('b2b/cotacao/respond');
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('b2b/cotacao');
    }

    public function getCreateUrl(): string
    {
        return $this->getUrl('b2b/cotacao/create');
    }
}
