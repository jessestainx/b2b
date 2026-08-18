<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Api\Data;

interface LlmResponseInterface
{
    /**
     * Text content returned by the LLM (may be null if tool_calls present).
     */
    public function getContent(): ?string;

    /**
     * Tool calls requested by the LLM in this turn.
     *
     * @return array<int, array{id: string, name: string, arguments: array<string, mixed>}>
     */
    public function getToolCalls(): array;

    public function hasToolCalls(): bool;

    /**
     * Model identifier actually used (as reported by the API).
     */
    public function getModel(): string;

    /**
     * Total tokens consumed (prompt + completion).
     */
    public function getTotalTokens(): int;
}
