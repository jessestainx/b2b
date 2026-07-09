<?php

declare(strict_types=1);

namespace Meta\BusinessExtension\Helper;

use JsonException;
use Magento\Framework\HTTP\Client\Curl;
use Meta\BusinessExtension\Api\SystemConfigInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Helper for Meta Graph API interactions
 */
class FBEHelper
{
    private const GRAPH_API_BASE_URL = 'https://graph.facebook.com';
    private const REQUEST_TIMEOUT_SECONDS = 15;
    private const CONNECT_TIMEOUT_SECONDS = 5;
    private const DNS_CACHE_TIMEOUT_SECONDS = 300;
    private const MAX_RETRIES = 2;
    private const MAX_RETRY_DELAY_MS = 1500;
    private const RETRYABLE_HTTP_CODES = [429, 500, 502, 503, 504];
    private const DEFAULT_ERROR_MESSAGE = 'Meta Graph API request failed';
    private const META_CATALOG_RATE_LIMIT_CODE = 80014;
    private const META_PERMISSION_DENIED_CODE = 10;
    private const CATALOG_RATE_LIMIT_COOLDOWN_MS = 60000;
    private const COOLDOWN_SKIP_LOG_INTERVAL_MS = 5000;
    private const RETRYABLE_NETWORK_ERROR_MARKERS = [
        'resolving timed out',
        'could not resolve host',
        'name or service not known',
        'temporary failure in name resolution',
        'connection timed out',
        'operation timed out',
        'failed to connect',
        'connection refused',
        'network is unreachable',
        'ssl connect error',
    ];

    /** @var array<string, int> */
    private static array $endpointCooldownUntilMs = [];

    /** @var array<string, int> */
    private static array $endpointCooldownSkipLogUntilMs = [];

    public function __construct(
        private readonly SystemConfigInterface $config,
        private readonly Curl $curl,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Make a GET request to the Meta Graph API
     *
     * @param string $endpoint API endpoint path (e.g. "/me/adaccounts")
     * @param array<string, mixed> $params Query parameters
     * @param int|null $storeId Store ID for multi-store config
     * @return array<string, mixed>
     */
    public function apiGet(string $endpoint, array $params = [], ?int $storeId = null): array
    {
        return $this->request('GET', $endpoint, $params, $storeId);
    }

    /**
     * Make a POST request to the Meta Graph API
     *
     * @param string $endpoint API endpoint path
     * @param array<string, mixed> $data POST data
     * @param int|null $storeId Store ID for multi-store config
     * @return array<string, mixed>
     */
    public function apiPost(string $endpoint, array $data = [], ?int $storeId = null): array
    {
        return $this->request('POST', $endpoint, $data, $storeId);
    }

    /**
     * @param 'GET'|'POST' $method
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $payload, ?int $storeId = null): array
    {
        $method = strtoupper(trim($method));
        if (!in_array($method, ['GET', 'POST'], true)) {
            return ['error' => 'Unsupported HTTP method', 'http_status' => 0];
        }

        $accessToken = $this->config->getAccessToken($storeId);
        if ($accessToken === null) {
            $this->logger->error('[Meta FBE] Access token not configured', [
                'endpoint' => $endpoint,
                'store_id' => $storeId
            ]);

            return ['error' => 'Access token not configured', 'http_status' => 0];
        }

        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return ['error' => 'Endpoint is required', 'http_status' => 0];
        }

        $payload = $this->sanitizePayload($payload);
        $payload['access_token'] = $accessToken;
        $attempt = 0;
        $lastResponse = ['error' => self::DEFAULT_ERROR_MESSAGE, 'http_status' => 0];
        $url = $this->buildUrl($endpoint, $storeId);
        $cooldownKey = $this->getEndpointCooldownKey($endpoint, $storeId);

        if ($cooldownKey !== null) {
            $remainingCooldownMs = $this->getCooldownRemainingMs($cooldownKey);
            if ($remainingCooldownMs > 0) {
                $this->logCooldownSkip($cooldownKey, $endpoint, $storeId, $remainingCooldownMs);

                return [
                    'error' => 'Meta catalog batch rate limited; request skipped during cooldown',
                    'http_status' => 429,
                    'error_code' => self::META_CATALOG_RATE_LIMIT_CODE,
                    'rate_limited' => true,
                    'retry_after_ms' => $remainingCooldownMs,
                    'skipped_due_to_cooldown' => true
                ];
            }
        }

        while ($attempt <= self::MAX_RETRIES) {
            $attempt++;
            $this->prepareClient();

            try {
                if ($method === 'GET') {
                    $query = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
                    $requestUrl = $query !== '' ? ($url . '?' . $query) : $url;
                    $this->curl->get($requestUrl);
                } else {
                    $this->curl->post($url, $payload);
                }

                $decoded = $this->parseResponse($endpoint, $method, $storeId);
                $httpStatus = (int) $this->curl->getStatus();
                $isRetryable = in_array($httpStatus, self::RETRYABLE_HTTP_CODES, true);
                $hasApiError = isset($decoded['error']);
                $isCatalogRateLimited = $this->isCatalogBatchRateLimited($endpoint, $decoded);

                if (!$hasApiError && $httpStatus >= 200 && $httpStatus < 300) {
                    if ($this->config->isDebugEnabled($storeId)) {
                        $this->logger->debug('[Meta FBE] API request succeeded', [
                            'method' => $method,
                            'endpoint' => $endpoint,
                            'store_id' => $storeId,
                            'http_status' => $httpStatus,
                            'attempt' => $attempt
                        ]);
                    }

                    return $decoded;
                }

                $lastResponse = $decoded;
                $lastResponse['http_status'] = $httpStatus;

                if ($isCatalogRateLimited && $cooldownKey !== null) {
                    $cooldownMs = $this->getRetryAfterDelayMs() ?? self::CATALOG_RATE_LIMIT_COOLDOWN_MS;
                    $this->setEndpointCooldown($cooldownKey, $cooldownMs);
                    $lastResponse['error_code'] = self::META_CATALOG_RATE_LIMIT_CODE;
                    $lastResponse['rate_limited'] = true;
                    $lastResponse['retry_after_ms'] = $cooldownMs;
                }

                $this->logApiFailure($method, $endpoint, $storeId, $decoded, $httpStatus, $attempt);

                if ($isCatalogRateLimited) {
                    return $lastResponse;
                }

                if (!$isRetryable || $attempt > self::MAX_RETRIES) {
                    return $lastResponse;
                }

                $this->sleepBeforeRetry($attempt, $httpStatus);
            } catch (Throwable $e) {
                $lastResponse = [
                    'error' => $e->getMessage(),
                    'http_status' => 0
                ];

                $hasRetryAttemptsRemaining = $attempt <= self::MAX_RETRIES;
                $isRetryableNetworkFailure = $this->isRetryableNetworkFailure($e);
                $logContext = [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'store_id' => $storeId,
                    'attempt' => $attempt,
                    'will_retry' => $hasRetryAttemptsRemaining && $isRetryableNetworkFailure,
                    'exception_type' => $e::class,
                    'error' => $e->getMessage()
                ];

                if ($hasRetryAttemptsRemaining && $isRetryableNetworkFailure) {
                    $this->logger->warning('[Meta FBE] API request transient failure, retrying', $logContext);
                } else {
                    $this->logger->error('[Meta FBE] API request failed', $logContext);
                }

                if (!$isRetryableNetworkFailure || !$hasRetryAttemptsRemaining) {
                    return $lastResponse;
                }

                $this->sleepBeforeRetry($attempt, 0);
            }
        }

        return $lastResponse;
    }

    /**
     * Build full Graph API URL
     */
    private function buildUrl(string $endpoint, ?int $storeId = null): string
    {
        $apiVersion = $this->config->getApiVersion($storeId);

        return rtrim(self::GRAPH_API_BASE_URL, '/') . '/' . $apiVersion . '/' . ltrim($endpoint, '/');
    }

    private function prepareClient(): void
    {
        $this->curl->setTimeout(self::REQUEST_TIMEOUT_SECONDS);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
        $this->curl->setOption(CURLOPT_DNS_CACHE_TIMEOUT, self::DNS_CACHE_TIMEOUT_SECONDS);
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            // VPS com IPv6 instável pode gerar timeout de resolução; forçar IPv4 reduz falhas transitórias.
            $this->curl->setOption(CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        $this->curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->curl->setHeaders([]);
        $this->curl->removeCookies();
        $this->curl->addHeader('Accept', 'application/json');
        $this->curl->addHeader('User-Agent', 'AWA-MetaBusinessExtension/1.0');
    }

    /**
     * @return array<string, mixed>
     */
    private function parseResponse(string $endpoint, string $method, ?int $storeId = null): array
    {
        $response = (string) $this->curl->getBody();
        $httpStatus = (int) $this->curl->getStatus();

        if ($response === '') {
            if ($httpStatus >= 200 && $httpStatus < 300) {
                return [
                    'success' => true,
                    'http_status' => $httpStatus
                ];
            }

            return [
                'error' => 'Empty response from Meta Graph API',
                'http_status' => $httpStatus
            ];
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $this->logger->error('[Meta FBE] Invalid JSON response', [
                'method' => $method,
                'endpoint' => $endpoint,
                'store_id' => $storeId,
                'http_status' => $httpStatus,
                'json_error' => json_last_error_msg(),
                'response_excerpt' => mb_substr($response, 0, 500)
            ]);

            return [
                'error' => 'Invalid JSON response',
                'http_status' => $httpStatus
            ];
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function logApiFailure(
        string $method,
        string $endpoint,
        ?int $storeId,
        array $decoded,
        int $httpStatus,
        int $attempt
    ): void {
        $error = isset($decoded['error']) && is_array($decoded['error']) ? $decoded['error'] : [];
        $errorCode = isset($error['code']) ? (int) $error['code'] : null;

        $logContext = [
            'method' => $method,
            'endpoint' => $endpoint,
            'store_id' => $storeId,
            'http_status' => $httpStatus,
            'attempt' => $attempt,
            'message' => is_string($error['message'] ?? null) ? $error['message'] : ($decoded['error'] ?? 'Unknown API error'),
            'type' => $error['type'] ?? null,
            'code' => $error['code'] ?? null,
            'error_subcode' => $error['error_subcode'] ?? null,
            'fbtrace_id' => $error['fbtrace_id'] ?? null
        ];

        if ($this->shouldDemoteApiFailure($endpoint, $errorCode)) {
            $this->logger->warning('[Meta FBE] API Warning', $logContext);
            return;
        }

        $this->logger->error('[Meta FBE] API Error', $logContext);
    }

    private function shouldDemoteApiFailure(string $endpoint, ?int $errorCode): bool
    {
        if ($errorCode !== self::META_PERMISSION_DENIED_CODE) {
            return false;
        }

        return (bool) preg_match('#^\d+/categories$#', trim($endpoint, '/'));
    }

    private function sleepBeforeRetry(int $attempt, int $httpStatus): void
    {
        $retryAfterMs = $this->getRetryAfterDelayMs();
        $delayMs = $retryAfterMs ?? ($httpStatus === 429 ? 600 : (200 * $attempt));
        $delayMs = min(self::MAX_RETRY_DELAY_MS, max(0, $delayMs));

        usleep($delayMs * 1000);
    }

    private function isRetryableNetworkFailure(Throwable $exception): bool
    {
        $message = strtolower(trim($exception->getMessage()));
        if ($message === '') {
            return false;
        }

        foreach (self::RETRYABLE_NETWORK_ERROR_MARKERS as $marker) {
            if (str_contains($message, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function getRetryAfterDelayMs(): ?int
    {
        $headers = $this->curl->getHeaders();
        if (!is_array($headers) || empty($headers['Retry-After'])) {
            return null;
        }

        $retryHeader = $headers['Retry-After'];
        if (is_array($retryHeader)) {
            $retryHeader = $retryHeader[0] ?? null;
        }

        if (!is_string($retryHeader) || trim($retryHeader) === '') {
            return null;
        }

        $retryHeader = trim($retryHeader);

        if (ctype_digit($retryHeader)) {
            return max(0, (int) $retryHeader) * 1000;
        }

        $retryTimestamp = strtotime($retryHeader);
        if ($retryTimestamp === false) {
            return null;
        }

        $seconds = max(0, $retryTimestamp - time());

        return $seconds * 1000;
    }

    private function isCatalogBatchRateLimited(string $endpoint, array $decoded): bool
    {
        if (!$this->isCatalogBatchEndpoint($endpoint)) {
            return false;
        }

        $error = $decoded['error'] ?? null;
        if (!is_array($error)) {
            return false;
        }

        return (int) ($error['code'] ?? 0) === self::META_CATALOG_RATE_LIMIT_CODE;
    }

    private function isCatalogBatchEndpoint(string $endpoint): bool
    {
        return str_ends_with(ltrim($endpoint, '/'), 'items_batch');
    }

    private function getEndpointCooldownKey(string $endpoint, ?int $storeId): ?string
    {
        if (!$this->isCatalogBatchEndpoint($endpoint)) {
            return null;
        }

        return sprintf('catalog_items_batch:%s:%s', (string) ($storeId ?? 'default'), ltrim($endpoint, '/'));
    }

    private function getCooldownRemainingMs(string $cooldownKey): int
    {
        $nowMs = (int) floor(microtime(true) * 1000);
        $untilMs = self::$endpointCooldownUntilMs[$cooldownKey] ?? 0;

        return max(0, $untilMs - $nowMs);
    }

    private function setEndpointCooldown(string $cooldownKey, int $cooldownMs): void
    {
        $cooldownMs = max(1000, $cooldownMs);
        $nowMs = (int) floor(microtime(true) * 1000);
        self::$endpointCooldownUntilMs[$cooldownKey] = $nowMs + $cooldownMs;
        unset(self::$endpointCooldownSkipLogUntilMs[$cooldownKey]);
    }

    private function logCooldownSkip(string $cooldownKey, string $endpoint, ?int $storeId, int $remainingCooldownMs): void
    {
        $nowMs = (int) floor(microtime(true) * 1000);
        $nextAllowedLogAt = self::$endpointCooldownSkipLogUntilMs[$cooldownKey] ?? 0;
        if ($nextAllowedLogAt > $nowMs) {
            return;
        }

        self::$endpointCooldownSkipLogUntilMs[$cooldownKey] = $nowMs + self::COOLDOWN_SKIP_LOG_INTERVAL_MS;

        $this->logger->warning('[Meta FBE] Skipping catalog batch request during cooldown', [
            'endpoint' => $endpoint,
            'store_id' => $storeId,
            'retry_after_ms' => $remainingCooldownMs
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (!is_string($key) || $key === '') {
                unset($payload[$key]);
                continue;
            }

            if ($value === null) {
                unset($payload[$key]);
                continue;
            }

            if (is_bool($value)) {
                $payload[$key] = $value ? '1' : '0';
                continue;
            }

            if (is_scalar($value)) {
                continue;
            }

            if (is_array($value)) {
                // Graph API form-encoded endpoints accept nested arrays inconsistently; encode early.
                try {
                    $encoded = json_encode(
                        $value,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                    $payload[$key] = $encoded;
                } catch (JsonException $e) {
                    $this->logger->warning('[Meta FBE] Dropping non-encodable payload field', [
                        'field' => $key,
                        'error' => $e->getMessage()
                    ]);
                    unset($payload[$key]);
                }
                continue;
            }

            unset($payload[$key]);
        }

        return $payload;
    }
}
