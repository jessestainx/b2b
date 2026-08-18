<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Persists every AI conversation turn to grupoawamotos_ai_conversation_log.
 *
 * IP is hashed (SHA-256) before storage — no raw personal data is written,
 * in compliance with Brazilian LGPD.
 */
class ConversationLogger
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly LoggerInterface    $logger
    ) {
    }

    /**
     * @param string[] $toolsCalled
     */
    public function log(
        string  $sessionId,
        string  $channel,
        string  $userMessage,
        ?string $assistantResponse,
        array   $toolsCalled = [],
        string  $model = '',
        int     $tokensUsed = 0,
        string  $status = 'ok',
        ?string $errorDetail = null,
        ?int    $customerId = null,
        ?string $ipAddress = null
    ): void {
        try {
            $this->resource->getConnection()->insert(
                $this->resource->getTableName('grupoawamotos_ai_conversation_log'),
                [
                    'session_id'         => $sessionId,
                    'customer_id'        => $customerId,
                    'channel'            => $channel,
                    'user_message'       => mb_substr($userMessage, 0, 65535),
                    'assistant_response' => $assistantResponse !== null
                        ? mb_substr($assistantResponse, 0, 16777215)
                        : null,
                    'tools_called'       => !empty($toolsCalled)
                        ? mb_substr(implode(',', $toolsCalled), 0, 512)
                        : null,
                    'model_used'         => mb_substr($model, 0, 64),
                    'tokens_used'        => $tokensUsed > 0 ? $tokensUsed : null,
                    'status'             => $status,
                    'error_detail'       => $errorDetail !== null
                        ? mb_substr($errorDetail, 0, 65535)
                        : null,
                    'ip_hash'            => $ipAddress !== null ? hash('sha256', $ipAddress) : null,
                ]
            );
        } catch (\Exception $e) {
            $this->logger->warning('[AiAssistant] ConversationLogger failed: ' . $e->getMessage());
        }
    }
}
