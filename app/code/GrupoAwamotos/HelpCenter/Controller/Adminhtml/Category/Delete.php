<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Controller\Adminhtml\Category;

use GrupoAwamotos\HelpCenter\Api\CategoryRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_HelpCenter::helpcenter_manage';

    public function __construct(
        Context $context,
        private readonly CategoryRepositoryInterface $repository,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/index');
        $id = (int) $this->getRequest()->getParam('category_id');
        if (!$id) {
            $this->messageManager->addErrorMessage(__('ID inválido.'));
            return $redirect;
        }
        try {
            $this->repository->deleteById($id);
            $this->messageManager->addSuccessMessage(__('Categoria excluída.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect;
    }
}
