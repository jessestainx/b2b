<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Marketing;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultInterface;

class Landing implements HttpGetActionInterface
{
    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * Execute view action
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Seja um Revendedor - Grupo AWA Motos'));
        $resultPage->getConfig()->setDescription(__('Acesse preços exclusivos de atacado, crédito facilitado e entrega rápida para sua oficina ou loja de motos. Cadastre-se no B2B AWA.'));

        return $resultPage;
    }
}
