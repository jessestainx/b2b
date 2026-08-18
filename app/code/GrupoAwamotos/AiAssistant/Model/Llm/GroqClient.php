<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model\Llm;

use GrupoAwamotos\AiAssistant\Api\Data\LlmResponseInterface;
use GrupoAwamotos\AiAssistant\Api\LlmClientInterface;
use GrupoAwamotos\AiAssistant\Exception\LlmException;
use GrupoAwamotos\AiAssistant\Helper\Config;
use GrupoAwamotos\AiAssistant\Model\Data\LlmResponse;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Phrase;
use Psr\Log\LoggerInterface;

/**
 * Groq API client — OpenAI-compatible endpoint with function/tool calling.
 *
 * Uses the same Groq+Llama stack already proven in the WhatsAppCommerce module
 * (MetaDescriptionGenerator), reusing Magento's own Curl HTTP client.
 */
class GroqClient implements LlmClientInterface
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(
        private readonly Curl            $curl,
        private readonly Config          $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function chat(array $messages, array $tools = []): LlmResponseInterface
    {
        $apiKey = $this->config->getGroqApiKey();
        if ($apiKey === '') {
            throw new LlmException(new Phrase('Groq API key not configured in Stores › Configuration › AWA Motos › Assistente de IA.'));
        }

        $payload = [
            'model'       => $this->config->getModel(),
            'messages'    => $messages,
            'temperature' => 0.6,
            'max_tokens'  => 1024,
        ];

        if (!empty($tools)) {
            $payload['tools']       = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new LlmException(new Phrase('Failed to encode LLM request payload.'));
        }

        try {
            $this->curl->setHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ]);
            $this->curl->setTimeout($this->config->getTimeoutSeconds());
            $this->curl->post(self::API_URL, $body);

            $status   = $this->curl->getStatus();
            $rawBody  = (string) $this->curl->getBody();
            $response = json_decode($rawBody, true);

            if ($status !== 200 || !is_array($response)) {
                $this->logger->error('[AiAssistant] Groq API error', [
                    'status'   => $status,
                    'response' => substr($rawBody, 0, 500),
                ]);
                throw new LlmException(new Phrase('Groq API returned status %1.', [$status]));
            }
        } catch (LlmException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('[AiAssistant] Groq HTTP error: ' . $e->getMessage());
            throw new LlmException(new Phrase('LLM HTTP error: %1', [$e->getMessage()]));
        }

        return $this->parseResponse($response);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function parseResponse(array $response): LlmResponseInterface
    {
        $choice     = $response['choices'][0] ?? [];
        $message    = $choice['message'] ?? [];
        $content    = isset($message['content']) ? (string) $message['content'] : null;
        $toolCalls  = [];

        if (!empty($message['tool_calls']) && is_array($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $tc) {
                $rawArgs = $tc['function']['arguments'] ?? '{}';
                $args    = is_string($rawArgs)
                    ? (json_decode($rawArgs, true) ?? [])
                    : (array) $rawArgs;

                $toolCalls[] = [
                    'id'        => (string) ($tc['id'] ?? ''),
                    'name'      => (string) ($tc['function']['name'] ?? ''),
                    'arguments' => $args,
                ];
            }
        }

        $usage       = $response['usage'] ?? [];
        $totalTokens = (int) ($usage['total_tokens'] ?? 0);
        $model       = (string) ($response['model'] ?? $this->config->getModel());

        return new LlmResponse($content, $toolCalls, $model, $totalTokens);
    }
}
