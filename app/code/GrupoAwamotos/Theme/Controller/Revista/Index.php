<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Controller\Revista;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $resultPageFactory
    ) {
    }

    public function execute(): ResultInterface
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Catálogo em Revista — AWA 2026'));
        $resultPage->getConfig()->setDescription(
            (string) __('Folheie o catálogo digital AWA Motos 2026 com efeito de revista.')
        );

        return $resultPage;
    }
}
