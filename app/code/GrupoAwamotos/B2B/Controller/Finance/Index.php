<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Finance;

use Magento\Customer\Controller\AbstractAccount;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * Pagina "Financeiro" do Portal B2B -- lista titulos (boletos) do cliente logado,
 * separados por situacao (Aberto / Vencido / A vencer / Pago).
 *
 * Somente leitura. Exige login (AbstractAccount).
 */
class Index extends AbstractAccount implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        return $this->resultPageFactory->create();
    }
}
