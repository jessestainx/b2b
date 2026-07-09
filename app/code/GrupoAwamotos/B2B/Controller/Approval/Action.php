<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Approval;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use GrupoAwamotos\B2B\Model\OrderApprovalService;
use GrupoAwamotos\B2B\Model\CompanyService;
use GrupoAwamotos\B2B\Model\Company;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class Action implements HttpPostActionInterface
{
    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private FormKeyValidator $formKeyValidator;
    private Session $customerSession;
    private OrderApprovalService $approvalService;
    private CompanyService $companyService;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        FormKeyValidator $formKeyValidator,
        Session $customerSession,
        OrderApprovalService $approvalService,
        CompanyService $companyService,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->customerSession = $customerSession;
        $this->approvalService = $approvalService;
        $this->companyService = $companyService;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setData(['success' => false, 'message' => __('Formulário inválido. Tente novamente.')]);
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['success' => false, 'message' => __('Login necessário.')]);
        }

        $approvalId = (int) $this->request->getParam('approval_id');
        $action = $this->request->getParam('action');
        $comment = $this->request->getParam('comment', '');
        $customerId = (int) $this->customerSession->getCustomerId();

        $role = $this->companyService->getUserRole($customerId);
        if (!$role || $role === Company::ROLE_BUYER) {
            return $result->setData(['success' => false, 'message' => __('Sem permissão para aprovar pedidos.')]);
        }

        try {
            if ($action === 'approve') {
                $this->approvalService->approve($approvalId, $customerId, $comment);
                return $result->setData(['success' => true, 'message' => __('Pedido aprovado com sucesso.')]);
            }

            if ($action === 'reject') {
                $reason = $this->request->getParam('reason', $comment);
                $this->approvalService->reject($approvalId, $customerId, $reason);
                return $result->setData(['success' => true, 'message' => __('Pedido rejeitado.')]);
            }

            return $result->setData(['success' => false, 'message' => __('Ação inválida.')]);
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->logger->error('[B2B Approval] Action failed', ['exception' => $e]);
            return $result->setData(['success' => false, 'message' => __('Erro ao processar aprovação.')]);
        }
    }
}
