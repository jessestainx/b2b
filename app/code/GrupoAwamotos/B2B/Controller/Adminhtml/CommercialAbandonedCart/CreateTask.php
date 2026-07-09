<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\CommercialAbandonedCart;

use GrupoAwamotos\B2B\CommercialPanel\Api\CommercialTaskManagementInterface;
use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\AbandonedCartCommercialManagement;
use GrupoAwamotos\B2B\CommercialPanel\Model\TaskType;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

class CreateTask extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::commercial_tasks_manage';

    public function __construct(
        Context $context,
        private readonly AbandonedCartCommercialManagement $abandonedCartManagement,
        private readonly CommercialTaskManagementInterface $taskManagement,
        private readonly PortfolioScopeInterface $portfolioScope
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->resultRedirectFactory->create();

        try {
            if (!$this->_formKeyValidator->validate($this->getRequest())) {
                throw new LocalizedException(__('Formulário inválido.'));
            }

            $entityId = (int) $this->getRequest()->getPostValue('entity_id');
            $row = $this->abandonedCartManagement->loadRow($entityId);
            if ($row === null) {
                throw new LocalizedException(__('Carrinho não encontrado.'));
            }

            $customerId = (int) ($row['customer_id'] ?? 0);
            $attendantId = (int) ($row['attendant_id'] ?? 0);
            if ($customerId <= 0) {
                throw new LocalizedException(__('Carrinho sem cliente vinculado.'));
            }

            if (!$this->portfolioScope->canAccessCustomer($customerId)) {
                throw new LocalizedException(__('Carrinho fora do seu escopo comercial.'));
            }

            $user = $this->_auth->getUser();
            if ($attendantId <= 0 && $user) {
                $this->taskManagement->createManual([
                    'customer_id' => $customerId,
                    'title' => (string) __('Recuperar carrinho abandonado'),
                    'observation' => (string) __('Tarefa manual — carrinho #%1.', $entityId),
                    'priority' => 'high',
                    'due_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
                ], (int) $user->getId());
                $this->messageManager->addSuccessMessage(__('Tarefa criada com sucesso.'));
                return $redirect->setPath('awa_commercial/commercialabandonedcart/index');
            }

            if ($attendantId <= 0) {
                throw new LocalizedException(__('Carrinho sem vendedora responsável.'));
            }

            if (!in_array($attendantId, $this->portfolioScope->getVisibleAttendantIds(), true)) {
                throw new LocalizedException(__('Carrinho fora do seu escopo comercial.'));
            }

            $period = date('Y-m');
            $dedupKey = sprintf('%s:%d:%d:%s', TaskType::ABANDONED_CART, $customerId, $entityId, $period);

            $task = $this->taskManagement->createAutomatic([
                'dedup_key' => $dedupKey,
                'customer_id' => $customerId,
                'attendant_id' => $attendantId,
                'task_type' => TaskType::ABANDONED_CART,
                'priority' => 'high',
                'title' => (string) __('Recuperar carrinho abandonado'),
                'observation' => (string) __('Tarefa criada manualmente a partir do carrinho #%1.', $entityId),
                'source_entity_type' => 'abandoned_cart',
                'source_entity_id' => $entityId,
                'due_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
            ]);

            if ($task === null) {
                throw new LocalizedException(__('Já existe tarefa aberta para este carrinho.'));
            }

            $this->messageManager->addSuccessMessage(__('Tarefa criada com sucesso.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception) {
            $this->messageManager->addErrorMessage(__('Não foi possível criar a tarefa.'));
        }

        return $redirect->setPath('awa_commercial/commercialabandonedcart/index');
    }
}
