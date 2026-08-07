<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Finance;

use GrupoAwamotos\B2B\Model\Finance\ItfBarcodeSvg;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class PrintBoleto extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly ItfBarcodeSvg $itfBarcodeSvg,
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

    /**
     * SVG do codigo de barras (server-side) — nao depende de RequireJS no iframe.
     */
    public function getBarcodeSvgHtml(): string
    {
        $boleto = $this->getBoleto();
        if ($boleto === null || empty($boleto['barcode'])) {
            return '';
        }

        return $this->itfBarcodeSvg->render((string) $boleto['barcode']);
    }

    /**
     * Iframe do modal (?embed=1) — sem toolbar interna.
     */
    public function isEmbedMode(): bool
    {
        return $this->getRequest()->getParam('embed') === '1';
    }
}
