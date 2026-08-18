<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Api;

interface ToolInterface
{
    /**
     * Unique name of the tool (used in function-calling schema).
     */
    public function getName(): string;

    /**
     * Human-readable description sent to the LLM in the tool schema.
     */
    public function getDescription(): string;

    /**
     * JSON Schema describing the function parameters.
     *
     * @return array<string, mixed>
     */
    public function getParametersSchema(): array;

    /**
     * Execute the tool with the given arguments.
     *
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $context  Channel context: customerId, isB2B, isAdmin
     * @return array<string, mixed>
     */
    public function execute(array $arguments, array $context = []): array;

    /**
     * Returns the channels this tool is allowed in.
     * Values: 'storefront', 'b2b', 'admin'
     *
     * @return string[]
     */
    public function getAllowedChannels(): array;
}
