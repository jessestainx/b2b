<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model\Data;

use GrupoAwamotos\AiAssistant\Api\Data\LlmResponseInterface;

class LlmResponse implements LlmResponseInterface
{
    /**
     * @param array<int, array{id: string, name: string, arguments: array<string, mixed>}> $toolCalls
     */
    public function __construct(
        private readonly ?string $content,
        private readonly array   $toolCalls,
        private readonly string  $model,
        private readonly int     $totalTokens
    ) {
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    public function hasToolCalls(): bool
    {
        return !empty($this->toolCalls);
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }
}
