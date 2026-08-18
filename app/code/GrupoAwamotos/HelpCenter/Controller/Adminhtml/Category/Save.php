<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Controller\Adminhtml\Category;

use GrupoAwamotos\HelpCenter\Api\CategoryRepositoryInterface;
use GrupoAwamotos\HelpCenter\Model\CategoryFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_HelpCenter::helpcenter_manage';

    public function __construct(
        Context $context,
        private readonly CategoryRepositoryInterface $repository,
        private readonly CategoryFactory $factory,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (empty($data)) {
            return $redirect->setPath('*/*/index');
        }

        try {
            $id = (int) ($data['category_id'] ?? 0);
            $model = $id ? $this->repository->getById($id) : $this->factory->create();
            $model->setName((string) $this->sanitizePlainText((string) ($data['name'] ?? '')));
            $model->setDescription($this->sanitizePlainText($data['description'] ?? null));
            $model->setSortOrder((int) ($data['sort_order'] ?? 0));
            $model->setAudience((string) ($data['audience'] ?? 'all'));
            $model->setStatus((int) ($data['status'] ?? 1));

            $this->repository->save($model);
            $this->messageManager->addSuccessMessage(__('Categoria salva com sucesso.'));

            if ($this->getRequest()->getParam('back') === 'edit') {
                return $redirect->setPath('*/*/edit', ['category_id' => $model->getCategoryId()]);
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $back = $this->getRequest()->getParam('category_id')
                ? ['category_id' => $this->getRequest()->getParam('category_id')]
                : [];
            return $redirect->setPath('*/*/edit', $back);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Erro ao salvar categoria.'));
        }

        return $redirect->setPath('*/*/index');
    }

    private function sanitizePlainText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = trim(strip_tags((string) $value));
        return $clean !== '' ? $clean : null;
    }
}
