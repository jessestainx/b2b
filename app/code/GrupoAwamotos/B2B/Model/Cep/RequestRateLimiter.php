<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Cep;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Per-IP rate limiter for the public /b2b/ajax/ceplookup endpoint.
 *
 * Without this, the endpoint is an unauthenticated proxy to ViaCEP that
 * anyone can hammer directly (no CNPJ-style validation gate exists for CEP
 * before the outbound call), unlike GrupoAwamotos\B2B\Model\Cnpj\RequestRateLimiter
 * which already protects /b2b/ajax/cnpjlookup. Mirrors that class's cache-based
 * sliding-window approach but with its own cache namespace/thresholds so CEP
 * traffic never competes with (or inherits) CNPJ-specific admin config.
 */
class RequestRateLimiter
{
    private const CACHE_KEY_PREFIX = 'grupoawamotos_b2b_cep_rate_limit_';
    private const CACHE_TAG = 'GRUPOAWAMOTOS_B2B_CEP_RATE_LIMIT';
    private const MAX_REQUESTS = 30;
    private const WINDOW_SECONDS = 60;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array{allowed: bool, retry_after: int, remaining: int, limit: int, window: int}
     */
    public function consume(string $identifier): array
    {
        try {
            $cacheId = $this->buildCacheId($identifier);
            $now = time();
            $state = $this->loadState($cacheId);

            if ($state === null || $state['expires_at'] <= $now) {
                $state = ['count' => 0, 'expires_at' => $now + self::WINDOW_SECONDS];
            }

            $state['count']++;
            $retryAfter = max(1, $state['expires_at'] - $now);
            $this->persistState($cacheId, $state, $retryAfter);

            $allowed = $state['count'] <= self::MAX_REQUESTS;

            return [
                'allowed' => $allowed,
                'retry_after' => $allowed ? 0 : $retryAfter,
                'remaining' => $allowed ? max(0, self::MAX_REQUESTS - $state['count']) : 0,
                'limit' => self::MAX_REQUESTS,
                'window' => self::WINDOW_SECONDS,
            ];
        } catch (\Throwable $exception) {
            // Fail open: a broken cache backend must never block checkout address lookups.
            $this->logger->warning(
                sprintf('[B2B][CEP] Rate limiter bypassed due to error: %s', $exception->getMessage())
            );

            return [
                'allowed' => true,
                'retry_after' => 0,
                'remaining' => self::MAX_REQUESTS,
                'limit' => self::MAX_REQUESTS,
                'window' => self::WINDOW_SECONDS,
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
        } catch (\InvalidArgumentException $exception) {
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
}
