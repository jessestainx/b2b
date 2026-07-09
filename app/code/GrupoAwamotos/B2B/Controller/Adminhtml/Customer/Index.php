<?php

/**
 * Admin All B2B Customers Controller
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::customer_approval';

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GrupoAwamotos_B2B::customer_all');
        $resultPage->getConfig()->getTitle()->prepend(__('Todos os Clientes B2B'));

        return $resultPage;
    }
}
