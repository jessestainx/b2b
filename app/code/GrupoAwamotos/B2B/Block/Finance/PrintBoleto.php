<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Finance;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class PrintBoleto extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBoleto(): ?array
    {
        $boleto = $this->registry->registry('b2b_finance_boleto_imprimivel');

        return is_array($boleto) ? $boleto : null;
    }
}
