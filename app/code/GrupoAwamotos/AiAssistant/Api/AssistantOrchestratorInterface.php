<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Api;

interface AssistantOrchestratorInterface
{
    public const CHANNEL_STOREFRONT = 'storefront';
    public const CHANNEL_B2B        = 'b2b';
    public const CHANNEL_ADMIN      = 'admin';

    /**
     * Process a user message and return the assistant text reply.
     *
     * @param string  $channel      One of the CHANNEL_* constants.
     * @param string  $sessionId    Opaque session identifier (already hashed/anonymised).
     * @param string  $userMessage  Raw user input.
     * @param array<int, array{role: string, content: string}> $history Previous turns (trimmed).
     * @param array<string, mixed> $context  Optional data: customerId, isB2B, isAdmin, pageType, pageName.
     * @return array{reply: string, history: array<int, array{role: string, content: string}>}
     */
    public function handle(
        string $channel,
        string $sessionId,
        string $userMessage,
        array $history = [],
        array $context = []
    ): array;
}
