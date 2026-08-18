<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Controller\Adminhtml\Topic;

use GrupoAwamotos\HelpCenter\Api\TopicRepositoryInterface;
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
        private readonly TopicRepositoryInterface $repository,
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $id = (int) $this->getRequest()->getParam('topic_id');
        if ($id) {
            try {
                $topic = $this->repository->getById($id);
                $title = __('Editar Tópico: %1', $topic->getTitle());
            } catch (\Magento\Framework\Exception\NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('Tópico não encontrado.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }
        } else {
            $title = __('Novo Tópico');
        }

        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('GrupoAwamotos_HelpCenter::helpcenter_topics');
        $page->getConfig()->getTitle()->prepend($title);
        return $page;
    }
}
