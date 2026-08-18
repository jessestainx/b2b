<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model\Llm;

use GrupoAwamotos\AiAssistant\Api\Data\LlmResponseInterface;
use GrupoAwamotos\AiAssistant\Api\LlmClientInterface;
use GrupoAwamotos\AiAssistant\Exception\LlmException;
use GrupoAwamotos\AiAssistant\Model\Data\LlmResponse;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Phrase;
use Psr\Log\LoggerInterface;

/**
 * NousResearch Hermes 3 client via Together AI (OpenAI-compatible endpoint).
 *
 * Hermes 3 foi fine-tuned especificamente para function/tool calling estruturado,
 * seguindo o formato ChatML + <tool_call> XML nativo. A Together AI expõe o modelo
 * via API OpenAI-compatível, então este client segue a mesma estrutura do GroqClient.
 *
 * Modelo padrão: NousResearch/Hermes-3-Llama-3.1-70B
 * Alternativa:   NousResearch/Hermes-3-Llama-3.1-405B-FP8  (melhor qualidade, mais caro)
 *
 * Troca de provedor: basta alterar a preference em etc/di.xml de GroqClient para HermesClient.
 */
class HermesClient implements LlmClientInterface
{
    private const API_URL = 'https://api.together.xyz/v1/chat/completions';

    private const XML_API_KEY    = 'ai_assistant/hermes/api_key';
    private const XML_MODEL      = 'ai_assistant/hermes/model';
    private const XML_TIMEOUT    = 'ai_assistant/hermes/timeout_seconds';

    private const DEFAULT_MODEL  = 'NousResearch/Hermes-3-Llama-3.1-70B';
    private const DEFAULT_TIMEOUT = 30;

    public function __construct(
        private readonly Curl                 $curl,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface      $logger
    ) {
    }

    public function chat(array $messages, array $tools = []): LlmResponseInterface
    {
        $apiKey = (string) $this->scopeConfig->getValue(self::XML_API_KEY);
        if ($apiKey === '') {
            throw new LlmException(new Phrase(
                'Together AI API key não configurada. Acesse Stores → Configuration → AWA Motos → Assistente de IA → Hermes.'
            ));
        }

        $model   = (string) $this->scopeConfig->getValue(self::XML_MODEL) ?: self::DEFAULT_MODEL;
        $timeout = (int) $this->scopeConfig->getValue(self::XML_TIMEOUT) ?: self::DEFAULT_TIMEOUT;

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0.5,   // Hermes responde melhor com temperatura mais baixa para tool calling
            'max_tokens'  => 1024,
        ];

        if (!empty($tools)) {
            $payload['tools']       = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new LlmException(new Phrase('Falha ao encodar payload para Hermes.'));
        }

        try {
            $this->curl->setHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ]);
            $this->curl->setTimeout($timeout);
            $this->curl->post(self::API_URL, $body);

            $status   = $this->curl->getStatus();
            $rawBody  = (string) $this->curl->getBody();
            $response = json_decode($rawBody, true);

            if ($status !== 200 || !is_array($response)) {
                $this->logger->error('[AiAssistant/Hermes] Together AI error', [
                    'status'   => $status,
                    'response' => substr($rawBody, 0, 500),
                ]);
                throw new LlmException(new Phrase('Together AI retornou status %1.', [$status]));
            }
        } catch (LlmException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('[AiAssistant/Hermes] HTTP error: ' . $e->getMessage());
            throw new LlmException(new Phrase('Hermes HTTP error: %1', [$e->getMessage()]));
        }

        return $this->parseResponse($response);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function parseResponse(array $response): LlmResponseInterface
    {
        $choice    = $response['choices'][0] ?? [];
        $message   = $choice['message'] ?? [];
        $content   = isset($message['content']) ? (string) $message['content'] : null;
        $toolCalls = [];

        /*
         * Together AI expõe Hermes 3 com tool_calls no formato OpenAI-compat.
         * Se o servidor retornar no formato nativo XML (<tool_call>...</tool_call>),
         * parseamos o fallback abaixo.
         */
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

        // Fallback: parse formato XML nativo do Hermes (<tool_call>{"name":...}</tool_call>)
        if (empty($toolCalls) && $content !== null && str_contains($content, '<tool_call>')) {
            $toolCalls = $this->parseNativeHermesToolCalls($content);
            if (!empty($toolCalls)) {
                $content = trim(preg_replace('/<tool_call>.*?<\/tool_call>/s', '', $content));
                if ($content === '') {
                    $content = null;
                }
            }
        }

        $usage       = $response['usage'] ?? [];
        $totalTokens = (int) ($usage['total_tokens'] ?? 0);
        $model       = (string) ($response['model'] ?? $this->scopeConfig->getValue(self::XML_MODEL) ?? self::DEFAULT_MODEL);

        return new LlmResponse($content, $toolCalls, $model, $totalTokens);
    }

    /**
     * Parseia o formato nativo de tool calling do Hermes 3:
     *   <tool_call>{"name": "catalog_search", "arguments": {"query": "freio"}}</tool_call>
     *
     * @return array<int, array{id: string, name: string, arguments: array<string, mixed>}>
     */
    private function parseNativeHermesToolCalls(string $content): array
    {
        $toolCalls = [];
        $pattern   = '/<tool_call>(.*?)<\/tool_call>/s';

        if (!preg_match_all($pattern, $content, $matches)) {
            return [];
        }

        foreach ($matches[1] as $idx => $raw) {
            $data = json_decode(trim($raw), true);
            if (!is_array($data) || empty($data['name'])) {
                continue;
            }
            $toolCalls[] = [
                'id'        => 'hermes_tc_' . $idx,
                'name'      => (string) $data['name'],
                'arguments' => (array) ($data['arguments'] ?? []),
            ];
        }

        return $toolCalls;
    }
}
