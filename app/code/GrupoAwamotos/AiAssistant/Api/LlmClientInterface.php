<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Api;

use GrupoAwamotos\AiAssistant\Api\Data\LlmResponseInterface;

interface LlmClientInterface
{
    /**
     * Send a messages array to the LLM, optionally with tool definitions.
     *
     * @param array<int, array{role: string, content: string|array<mixed>}> $messages
     * @param array<int, array{type: string, function: array<string, mixed>}> $tools
     * @throws \GrupoAwamotos\AiAssistant\Exception\LlmException on API error
     */
    public function chat(array $messages, array $tools = []): LlmResponseInterface;
}
