<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $page = $this->resultPageFactory->create();
        $page->getConfig()->getTitle()->set(__('Central de Ajuda — AWA Motos'));
        $page->getConfig()->setDescription(
            'Tire suas dúvidas sobre pedidos, peças, compatibilidade, B2B e muito mais na Central de Ajuda da AWA Motos.'
        );
        return $page;
    }
}
