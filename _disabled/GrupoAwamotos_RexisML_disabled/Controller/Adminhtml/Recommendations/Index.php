<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Controller\Adminhtml\Recommendations;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;

class Index extends Action implements HttpGetActionInterface
{
    const ADMIN_RESOURCE = 'GrupoAwamotos_RexisML::recommendations';

    private PageFactory $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GrupoAwamotos_RexisML::recommendations');
        $resultPage->getConfig()->getTitle()->prepend(__('REXIS ML - Recomendacoes'));
        return $resultPage;
    }
}
