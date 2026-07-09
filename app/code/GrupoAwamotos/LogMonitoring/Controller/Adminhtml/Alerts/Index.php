<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Controller\Adminhtml\Alerts;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_LogMonitoring::alerts';

    private PageFactory $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GrupoAwamotos_LogMonitoring::alerts');
        $resultPage->getConfig()->getTitle()->prepend(__('Log Monitoring Alerts'));

        return $resultPage;
    }
}
