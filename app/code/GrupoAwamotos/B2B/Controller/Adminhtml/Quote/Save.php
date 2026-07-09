<?php

/**
 * Controller para salvar resposta da cotação
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Quote;

use GrupoAwamotos\B2B\Api\QuoteRequestRepositoryInterface;
use GrupoAwamotos\B2B\Api\Data\QuoteRequestInterface;
use GrupoAwamotos\B2B\Helper\Config;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::quotes';

    /**
     * @var QuoteRequestRepositoryInterface
     */
    private $quoteRequestRepository;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var EventManagerInterface
     */
    private $eventManager;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Context $context,
        QuoteRequestRepositoryInterface $quoteRequestRepository,
        Config $config,
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        EventManagerInterface $eventManager,
        ResourceConnection $resourceConnection,
        DateTime $dateTime,
        LoggerInterface $logger
    ) {
        $this->quoteRequestRepository = $quoteRequestRepository;
        $this->config = $config;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->eventManager = $eventManager;
        $this->resourceConnection = $resourceConnection;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Execute action - save quote response
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();

        $requestId = (int) $this->getRequest()->getParam('request_id');
        $quotedTotal = (float) $this->getRequest()->getParam('quoted_total');
        $adminNotes = $this->getRequest()->getParam('admin_notes');
        $action = $this->getRequest()->getParam('action'); // 'approve' ou 'reject'
        $expiryDays = (int) $this->getRequest()->getParam('expiry_days', 7);

        if (!$requestId) {
            $this->messageManager->addErrorMessage(__('ID da cotação não informado.'));
            return $redirect->setPath('*/*/');
        }

        try {
            $quoteRequest = $this->quoteRequestRepository->getById($requestId);
            $previousStatus = $quoteRequest->getStatus();
            $previousQuotedTotal = $quoteRequest->getQuotedTotal();
            $storeId = (int) $this->storeManager->getStore()->getId();
            $adminUser = $this->_auth->getUser();
            $adminUserId = $adminUser ? (int) $adminUser->getId() : null;

            if ($action === 'reject') {
                // Rejeitar cotação
                $quoteRequest->setStatus(QuoteRequestInterface::STATUS_REJECTED);
                $quoteRequest->setAdminNotes($adminNotes);

                $this->quoteRequestRepository->save($quoteRequest);

                $this->logAudit(
                    $requestId,
                    'rejected',
                    $previousStatus,
                    QuoteRequestInterface::STATUS_REJECTED,
                    $previousQuotedTotal,
                    $previousQuotedTotal,
                    $adminUserId,
                    $adminNotes
                );

                $this->eventManager->dispatch('grupoawamotos_b2b_quote_merchant_rejected', [
                    'quote_request' => $quoteRequest,
                    'previous_status' => $previousStatus,
                    'lifecycle_event' => 'merchant_rejected',
                    'store_id' => $storeId,
                    'admin_user_id' => $adminUserId,
                ]);

                // Enviar email de rejeição
                $this->sendRejectionEmail($quoteRequest, $adminNotes);

                $this->messageManager->addSuccessMessage(
                    __('Cotação #%1 foi rejeitada.', $requestId)
                );
            } else {
                // Aprovar/Responder cotação
                if ($quotedTotal <= 0) {
                    $this->messageManager->addErrorMessage(__('Informe um valor válido para a cotação.'));
                    return $redirect->setPath('*/*/respond', ['id' => $requestId]);
                }

                $quoteRequest->setStatus(QuoteRequestInterface::STATUS_QUOTED);
                $quoteRequest->setQuotedTotal($quotedTotal);
                $quoteRequest->setAdminNotes($adminNotes);

                // Definir validade
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
                $quoteRequest->setExpiresAt($expiresAt);

                $this->quoteRequestRepository->save($quoteRequest);

                $this->logAudit(
                    $requestId,
                    'quoted',
                    $previousStatus,
                    QuoteRequestInterface::STATUS_QUOTED,
                    $previousQuotedTotal,
                    $quotedTotal,
                    $adminUserId,
                    $adminNotes
                );

                $this->eventManager->dispatch('grupoawamotos_b2b_quote_responded', [
                    'quote_request' => $quoteRequest,
                    'previous_status' => $previousStatus,
                    'lifecycle_event' => 'quoted',
                    'store_id' => $storeId,
                    'quoted_total' => $quotedTotal,
                    'admin_user_id' => $adminUserId,
                ]);

                // Enviar email com orçamento
                $this->sendQuoteEmail($quoteRequest);

                $this->messageManager->addSuccessMessage(
                    __(
                        'Cotação #%1 respondida com sucesso! Valor: R$ %2',
                        $requestId,
                        number_format($quotedTotal, 2, ',', '.')
                    )
                );
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Erro ao processar cotação: %1', $e->getMessage())
            );
            return $redirect->setPath('*/*/respond', ['id' => $requestId]);
        }

        return $redirect->setPath('*/*/');
    }

    /**
     * Persist an audit trail entry when an admin approves/rejects a quote and sets its total.
     *
     * @param int $requestId
     * @param string $action
     * @param string|null $oldStatus
     * @param string $newStatus
     * @param float|null $oldQuotedTotal
     * @param float|null $newQuotedTotal
     * @param int|null $adminUserId
     * @param string|null $comment
     * @return void
     */
    private function logAudit(
        int $requestId,
        string $action,
        ?string $oldStatus,
        string $newStatus,
        ?float $oldQuotedTotal,
        ?float $newQuotedTotal,
        ?int $adminUserId,
        ?string $comment
    ): void {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('grupoawamotos_b2b_quote_audit_log');

            $connection->insert($tableName, [
                'request_id' => $requestId,
                'action' => $action,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'old_quoted_total' => $oldQuotedTotal,
                'new_quoted_total' => $newQuotedTotal,
                'admin_user_id' => $adminUserId,
                'comment' => $comment,
                'created_at' => $this->dateTime->gmtDate(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('B2B Quote audit log error: ' . $e->getMessage());
        }
    }

    /**
     * Enviar email com orçamento aprovado
     *
     * @param QuoteRequestInterface $quoteRequest
     * @return void
     */
    private function sendQuoteEmail(QuoteRequestInterface $quoteRequest): void
    {
        try {
            $store = $this->storeManager->getStore();

            $transport = $this->transportBuilder
                ->setTemplateIdentifier('grupoawamotos_b2b_quote_response')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $store->getId(),
                ])
                ->setTemplateVars([
                    'quote' => $quoteRequest,
                    'customer_name' => $quoteRequest->getCustomerName(),
                    'quote_id' => $quoteRequest->getRequestId(),
                    'quoted_total' => 'R$ ' . number_format($quoteRequest->getQuotedTotal(), 2, ',', '.'),
                    'expires_at' => date('d/m/Y', strtotime($quoteRequest->getExpiresAt())),
                    'admin_notes' => $quoteRequest->getAdminNotes(),
                    'store_name' => $store->getName(),
                ])
                ->setFromByScope('general')
                ->addTo($quoteRequest->getCustomerEmail(), $quoteRequest->getCustomerName())
                ->getTransport();

            $transport->sendMessage();
        } catch (\Exception $e) {
            // Log mas não interrompe o fluxo
            $this->messageManager->addWarningMessage(
                __('Cotação salva, mas houve erro ao enviar e-mail: %1', $e->getMessage())
            );
        }
    }

    /**
     * Enviar email de rejeição
     *
     * @param QuoteRequestInterface $quoteRequest
     * @param string|null $reason
     * @return void
     */
    private function sendRejectionEmail(QuoteRequestInterface $quoteRequest, ?string $reason): void
    {
        try {
            $store = $this->storeManager->getStore();

            $transport = $this->transportBuilder
                ->setTemplateIdentifier('grupoawamotos_b2b_quote_rejected')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $store->getId(),
                ])
                ->setTemplateVars([
                    'quote' => $quoteRequest,
                    'customer_name' => $quoteRequest->getCustomerName(),
                    'quote_id' => $quoteRequest->getRequestId(),
                    'reason' => $reason ?: __('Não foi possível atender sua solicitação no momento.'),
                    'store_name' => $store->getName(),
                ])
                ->setFromByScope('general')
                ->addTo($quoteRequest->getCustomerEmail(), $quoteRequest->getCustomerName())
                ->getTransport();

            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->messageManager->addWarningMessage(
                __('Cotação rejeitada, mas houve erro ao enviar e-mail.')
            );
        }
    }
}
