<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Plugin;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Cache\Type\Config as CacheType;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Webapi\Controller\Rest as RestController;
use Psr\Log\LoggerInterface;

/**
 * Rate limiter for WhatsAppCommerce REST APIs.
 * Limits to 60 requests per minute per API token.
 */
class ApiRateLimiter
{
    private const MAX_REQUESTS = 60;
    private const WINDOW_SECONDS = 60;
    private const CACHE_PREFIX = 'whatsapp_api_rl_';
    private const API_PATH_PREFIX = '/V1/awa-whatsapp/';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param RestController $subject
     * @param RequestInterface $request
     * @return array
     * @throws WebapiException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeDispatch(RestController $subject, RequestInterface $request): array
    {
        $path = $request->getPathInfo();
        if ($path === null || strpos($path, self::API_PATH_PREFIX) === false) {
            return [$request];
        }

        $authHeader = $request->getHeader('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return [$request];
        }

        $token = substr($authHeader, 7);
        $cacheKey = self::CACHE_PREFIX . hash('sha256', $token);
        $data = $this->cache->load($cacheKey);

        $now = time();
        $requests = [];

        if ($data !== false) {
            $requests = json_decode($data, true) ?: [];
            $requests = array_filter(
                $requests,
                static fn(int $ts): bool => $ts > ($now - self::WINDOW_SECONDS)
            );
        }

        if (count($requests) >= self::MAX_REQUESTS) {
            $this->logger->warning('Rate limit exceeded for WhatsApp API', [
                'path' => $path,
                'count' => count($requests),
            ]);
            throw new WebapiException(
                __('Rate limit exceeded. Maximum %1 requests per minute.', self::MAX_REQUESTS),
                0,
                WebapiException::HTTP_TOO_MANY_REQUESTS
            );
        }

        $requests[] = $now;
        $this->cache->save(
            (string) json_encode(array_values($requests)),
            $cacheKey,
            [CacheType::CACHE_TAG],
            self::WINDOW_SECONDS
        );

        return [$request];
    }
}
