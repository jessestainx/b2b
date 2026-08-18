<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Controller\Adminhtml\Copilot;

use GrupoAwamotos\AiAssistant\Api\AssistantOrchestratorInterface;
use GrupoAwamotos\AiAssistant\Helper\Config;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * POST /grupoawamotos_aiassistant/copilot/chat
 *
 * Endpoint AJAX exclusivo do Admin. Protegido por ACL.
 */
class Chat extends Action
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_AiAssistant::admin_copilot';

    public function __construct(
        Context $context,
        private readonly JsonFactory                    $jsonFactory,
        private readonly Json                           $jsonSerializer,
        private readonly Config                         $config,
        private readonly AssistantOrchestratorInterface $orchestrator,
        private readonly LoggerInterface                $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Json
    {
        $result = $this->jsonFactory->create();

        if (!$this->config->isAdminCopilotEnabled()) {
            return $result->setData(['reply' => null, 'error' => 'Copiloto desabilitado.']);
        }

        $rawBody = (string) $this->getRequest()->getContent();
        try {
            $body = $this->jsonSerializer->unserialize($rawBody);
        } catch (\Exception $e) {
            return $result->setData(['reply' => null, 'error' => 'Requisição inválida.']);
        }

        $userMessage = trim((string) ($body['message'] ?? ''));
        if ($userMessage === '' || mb_strlen($userMessage) > 2000) {
            return $result->setData(['reply' => null, 'error' => 'Mensagem inválida.']);
        }

        $history   = (array) ($body['history'] ?? []);
        $adminUser = $this->_auth->getUser();
        $sessionId = hash('sha256', (string) $adminUser->getId() . '_admin_copilot');

        $context = [
            'admin_user_id' => (int) $adminUser->getId(),
            'is_admin'      => true,
        ];

        try {
            ['reply' => $reply, 'history' => $newHistory] = $this->orchestrator->handle(
                channel: AssistantOrchestratorInterface::CHANNEL_ADMIN,
                sessionId: $sessionId,
                userMessage: $userMessage,
                history: $history,
                context: $context
            );

            return $result->setData(['reply' => $reply, 'history' => $newHistory, 'error' => null]);
        } catch (\Exception $e) {
            $this->logger->error('[AiAssistant/Admin] Chat error: ' . $e->getMessage());
            return $result->setData(['reply' => null, 'error' => 'Erro interno do copiloto.']);
        }
    }
}
