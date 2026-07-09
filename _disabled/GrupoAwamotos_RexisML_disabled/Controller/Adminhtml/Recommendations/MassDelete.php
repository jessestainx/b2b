<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Controller\Adminhtml\Recommendations;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;

class MassDelete extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'GrupoAwamotos_RexisML::recommendations';

    private Filter $filter;
    private CollectionFactory $collectionFactory;
    private ResourceConnection $resource;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        ResourceConnection $resource
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->resource = $resource;
    }

    public function execute()
    {
        try {
            $collection = $this->filter->getCollection(
                $this->collectionFactory->getReport('rexisml_recommendations_listing_data_source')
            );

            $ids = [];
            foreach ($collection as $item) {
                $ids[] = (int)$item->getId();
            }

            if (!empty($ids)) {
                $connection = $this->resource->getConnection();
                $table = $this->resource->getTableName('rexis_dataset_recomendacao');
                $deleted = $connection->delete($table, ['id IN (?)' => $ids]);
                $this->messageManager->addSuccessMessage(
                    __('%1 recomendação(ões) excluída(s) com sucesso.', $deleted)
                );
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Erro ao excluir: %1', $e->getMessage())
            );
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
