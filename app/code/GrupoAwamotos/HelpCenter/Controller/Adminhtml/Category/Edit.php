<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Controller\Adminhtml\Category;

use GrupoAwamotos\HelpCenter\Api\CategoryRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_HelpCenter::helpcenter_manage';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly CategoryRepositoryInterface $repository,
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $id = (int) $this->getRequest()->getParam('category_id');
        if ($id) {
            try {
                $category = $this->repository->getById($id);
                $title = __('Editar Categoria: %1', $category->getName());
            } catch (\Magento\Framework\Exception\NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('Categoria não encontrada.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }
        } else {
            $title = __('Nova Categoria');
        }

        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('GrupoAwamotos_HelpCenter::helpcenter_categories');
        $page->getConfig()->getTitle()->prepend($title);
        return $page;
    }
}
