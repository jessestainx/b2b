<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Controller\Chat;

use GrupoAwamotos\AiAssistant\Api\AssistantOrchestratorInterface;
use GrupoAwamotos\AiAssistant\Helper\Config;
use GrupoAwamotos\AiAssistant\Model\RateLimiter;
use GrupoAwamotos\B2B\Model\CustomerApproval;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * POST /aiassistant/chat/message
 *
 * Request JSON body:
 *   { "message": "...", "channel": "storefront"|"b2b", "history": [...] }
 *
 * Response JSON:
 *   { "reply": "...", "history": [...], "error": null }
 *
 * History is owned by the client: the JS widget maintains it in memory and
 * sends it with every request. The server never persists conversation history,
 * which keeps the approach stateless and avoids any session-storage interface
 * mismatch between Magento versions.
 */
class Message implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface                $request,
        private readonly JsonFactory                    $jsonFactory,
        private readonly Json                           $jsonSerializer,
        private readonly Config                         $config,
        private readonly AssistantOrchestratorInterface $orchestrator,
        private readonly RateLimiter                    $rateLimiter,
        private readonly CustomerSession                $customerSession,
        private readonly CustomerApproval               $customerApproval,
        private readonly ResourceConnection             $resource,
        private readonly LoggerInterface                $logger
    ) {
    }

    public function execute(): \Magento\Framework\Controller\Result\Json
    {
        $result = $this->jsonFactory->create();

        if (!$this->config->isStorefrontEnabled()) {
            return $result->setData(['reply' => null, 'history' => [], 'error' => 'Assistente de IA não disponível.']);
        }

        $ipAddress = (string) $this->request->getServer('REMOTE_ADDR', '0.0.0.0');

        if (!$this->rateLimiter->checkAndIncrement($ipAddress)) {
            return $result->setData([
                'reply'   => null,
                'history' => [],
                'error'   => 'Limite de mensagens por hora atingido. Tente novamente mais tarde ou utilize nosso chat ao vivo.',
            ]);
        }

        $rawBody = (string) $this->request->getContent();
        try {
            $body = $this->jsonSerializer->unserialize($rawBody);
        } catch (\Exception $e) {
            return $result->setData(['reply' => null, 'history' => [], 'error' => 'Requisição inválida.']);
        }

        $userMessage = trim((string) ($body['message'] ?? ''));
        if ($userMessage === '' || mb_strlen($userMessage) > 1000) {
            return $result->setData(['reply' => null, 'history' => [], 'error' => 'Mensagem inválida.']);
        }

        // History is provided by the client; validate it is a plain array.
        $history = is_array($body['history'] ?? null) ? (array) $body['history'] : [];

        $isB2BApproved = false;
        if ($this->customerSession->isLoggedIn()) {
            $customerId    = (int) $this->customerSession->getCustomerId();
            $isB2BApproved = $this->customerApproval->isApproved($customerId);
        }

        $channel = $isB2BApproved
            ? AssistantOrchestratorInterface::CHANNEL_B2B
            : AssistantOrchestratorInterface::CHANNEL_STOREFRONT;

        $context = [
            'ip_address' => $ipAddress,
            'is_b2b'     => $isB2BApproved,
        ];

        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->customerSession->getCustomer();
            $context['customer_id']    = (int) $customer->getId();
            $context['customer_phone'] = $this->getCustomerPhone((int) $customer->getId());
        }

        try {
            $handled = $this->orchestrator->handle(
                channel: $channel,
                sessionId: $this->buildSessionId($ipAddress),
                userMessage: $userMessage,
                history: $history,
                context: $context
            );

            return $result->setData([
                'reply' => $handled['reply'] ?? null,
                'history' => $handled['history'] ?? [],
                'products' => $handled['products'] ?? [],
                'error' => null,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[AiAssistant] Controller error: ' . $e->getMessage());
            return $result->setData(['reply' => null, 'history' => $history, 'error' => 'Erro interno. Tente novamente.']);
        }
    }

    private function buildSessionId(string $ipAddress): string
    {
        $sid = session_id() ?: $ipAddress;
        return hash('sha256', $sid . $ipAddress);
    }

    private function getCustomerPhone(int $customerId): string
    {
        try {
            $conn   = $this->resource->getConnection();
            $select = $conn->select()
                ->from($this->resource->getTableName('customer_address_entity'), ['telephone'])
                ->where('parent_id = ?', $customerId)
                ->where('telephone IS NOT NULL')
                ->order('entity_id DESC')
                ->limit(1);

            $phone = (string) ($conn->fetchOne($select) ?? '');
            return preg_replace('/\D/', '', $phone) ?? '';
        } catch (\Exception $e) {
            return '';
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
