<?php

declare(strict_types=1);

namespace Meta\Conversion\Model;

use Meta\BusinessExtension\Helper\GraphAPIAdapter;
use Psr\Log\LoggerInterface;

/**
 * Dispatches Meta CAPI events without blocking the HTTP response on frontend requests.
 */
class CapiEventDispatcher
{
    private const META_LOW_MATCH_SUBCODE = 2804050;

    public function __construct(
        private readonly GraphAPIAdapter $graphApi,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    public function sendEvents(
        string $pixelId,
        array $events,
        ?int $storeId = null,
        string $context = 'event',
        bool $defer = true
    ): void {
        if ($defer && PHP_SAPI !== 'cli') {
            register_shutdown_function(function () use ($pixelId, $events, $storeId, $context): void {
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }

                $this->dispatchNow($pixelId, $events, $storeId, $context);
            });

            return;
        }

        $this->dispatchNow($pixelId, $events, $storeId, $context);
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    private function dispatchNow(string $pixelId, array $events, ?int $storeId, string $context): void
    {
        try {
            $result = $this->graphApi->sendEvents($pixelId, $events, $storeId);

            if (!empty($result['skipped'])) {
                return;
            }

            if (!isset($result['error'])) {
                return;
            }

            if ($this->shouldSuppressErrorLog($result['error'])) {
                return;
            }

            $this->logger->warning(sprintf('[Meta CAPI] %s API error', $context), [
                'store_id' => $storeId,
                'http_status' => $result['http_status'] ?? null,
                'error' => $result['error'],
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error(sprintf('[Meta CAPI] %s dispatch failed', $context), [
                'store_id' => $storeId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function shouldSuppressErrorLog(mixed $error): bool
    {
        if (is_string($error)) {
            return $error === 'No valid events to send';
        }

        if (!is_array($error)) {
            return false;
        }

        return (int) ($error['error_subcode'] ?? 0) === self::META_LOW_MATCH_SUBCODE;
    }
}
