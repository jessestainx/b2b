<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_HelpCenter::helpcenter_manage';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\View\Result\Page
    {
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('GrupoAwamotos_HelpCenter::helpcenter_categories');
        $page->getConfig()->getTitle()->prepend(__('Central de Ajuda — Categorias'));
        return $page;
    }
}
