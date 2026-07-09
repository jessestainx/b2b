<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Finance;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Per-cliente rate limiter para a rota de impressao de boleto (Fase 5 do plano
 * BOLETOS_NFE_IMPLEMENTACAO.md). Mesmo padrao (sliding window via cache) usado em
 * GrupoAwamotos\B2B\Model\Cep\RequestRateLimiter, com namespace/limites proprios.
 *
 * Fail-open por design: um erro no backend de cache nunca deve bloquear a impressao
 * de um boleto legitimo (nao e uma protecao contra abuso critico, apenas contra
 * scraping/automacao acidental).
 */
class RequestRateLimiter
{
    private const CACHE_KEY_PREFIX = 'grupoawamotos_b2b_finance_print_rate_limit_';
    private const CACHE_TAG = 'GRUPOAWAMOTOS_B2B_FINANCE_PRINT_RATE_LIMIT';

    private const DEFAULT_MAX_REQUESTS = 20;
    private const DEFAULT_WINDOW_SECONDS = 60;

    private const CONFIG_PATH_RATE_LIMIT_MAX_REQUESTS = 'grupoawamotos_b2b/finance/print_rate_limit_max_requests';
    private const CONFIG_PATH_RATE_LIMIT_WINDOW_SECONDS = 'grupoawamotos_b2b/finance/print_rate_limit_window_seconds';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array{allowed: bool, retry_after: int, remaining: int, limit: int, window: int}
     */
    public function consume(string $identifier): array
    {
        $maxRequests = $this->getMaxRequests();
        $windowSeconds = $this->getWindowSeconds();

        try {
            $cacheId = $this->buildCacheId($identifier);
            $now = time();
            $state = $this->loadState($cacheId);

            if ($state === null || $state['expires_at'] <= $now) {
                $state = ['count' => 0, 'expires_at' => $now + $windowSeconds];
            }

            $state['count']++;
            $retryAfter = max(1, $state['expires_at'] - $now);
            $this->persistState($cacheId, $state, $retryAfter);

            $allowed = $state['count'] <= $maxRequests;

            return [
                'allowed' => $allowed,
                'retry_after' => $allowed ? 0 : $retryAfter,
                'remaining' => $allowed ? max(0, $maxRequests - $state['count']) : 0,
                'limit' => $maxRequests,
                'window' => $windowSeconds,
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning(
                sprintf('[B2B][Finance] Rate limiter bypassed due to error: %s', $exception->getMessage())
            );

            return [
                'allowed' => true,
                'retry_after' => 0,
                'remaining' => $maxRequests,
                'limit' => $maxRequests,
                'window' => $windowSeconds,
            ];
        }
    }

    /**
     * @return array{count: int, expires_at: int}|null
     */
    private function loadState(string $cacheId): ?array
    {
        $cached = $this->cache->load($cacheId);
        if (!$cached) {
            return null;
        }

        try {
            $state = $this->json->unserialize($cached);
        } catch (\InvalidArgumentException) {
            return null;
        }

        if (!is_array($state) || !isset($state['count'], $state['expires_at'])) {
            return null;
        }

        return ['count' => (int) $state['count'], 'expires_at' => (int) $state['expires_at']];
    }

    private function persistState(string $cacheId, array $state, int $ttl): void
    {
        $this->cache->save($this->json->serialize($state), $cacheId, [self::CACHE_TAG], $ttl);
    }

    private function buildCacheId(string $identifier): string
    {
        $normalized = trim($identifier) !== '' ? trim($identifier) : 'anonymous';
        return self::CACHE_KEY_PREFIX . sha1($normalized);
    }

    private function getMaxRequests(): int
    {
        return $this->readPositiveIntConfig(self::CONFIG_PATH_RATE_LIMIT_MAX_REQUESTS, self::DEFAULT_MAX_REQUESTS, 10000);
    }

    private function getWindowSeconds(): int
    {
        return $this->readPositiveIntConfig(self::CONFIG_PATH_RATE_LIMIT_WINDOW_SECONDS, self::DEFAULT_WINDOW_SECONDS, 86400);
    }

    private function readPositiveIntConfig(string $path, int $default, int $max): int
    {
        $raw = $this->scopeConfig->getValue($path);
        $value = is_numeric($raw) ? (int) $raw : $default;

        if ($value <= 0) {
            return $default;
        }

        return min($value, $max);
    }
}
