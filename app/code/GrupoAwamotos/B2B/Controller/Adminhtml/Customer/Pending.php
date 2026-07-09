<?php

/**
 * Admin Customer Pending List Controller
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Pending extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::customer_approval';

    /**
     * @var PageFactory
     */
    private $pageFactory;

    public function __construct(
        Context $context,
        PageFactory $pageFactory
    ) {
        $this->pageFactory = $pageFactory;
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('GrupoAwamotos_B2B::customer_approval');
        $page->getConfig()->getTitle()->prepend(__('Clientes B2B Pendentes de Aprovação'));

        return $page;
    }
}
