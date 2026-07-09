<?php

declare(strict_types=1);

namespace Meta\BusinessExtension\Helper;

use JsonException;
use Psr\Log\LoggerInterface;

/**
 * Adapter for Meta Graph API specialized endpoints
 */
class GraphAPIAdapter
{
    private const DEFAULT_ACTION_SOURCE = 'website';
    private const ALLOWED_ACTION_SOURCES = [
        'website',
        'app',
        'phone_call',
        'chat',
        'physical_store',
        'system_generated',
        'business_messaging',
        'email',
        'other',
    ];
    private const MINIMUM_USER_SIGNAL_KEYS = ['em', 'ph', 'external_id', 'fbp', 'fbc'];
    private const ALLOWED_BATCH_METHODS = ['CREATE', 'UPDATE', 'DELETE'];
    private const DEFAULT_CATALOG_ITEM_TYPE = 'PRODUCT_ITEM';
    private const MAX_EVENTS_PER_REQUEST = 100;
    private const MAX_CATALOG_ITEMS_PER_REQUEST = 500;
    private const CATALOG_CHUNK_DELAY_MS = 2000;

    public function __construct(
        private readonly FBEHelper $fbeHelper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Send conversion events via the Conversions API
     *
     * @param string $pixelId Meta Pixel ID
     * @param array<int, array<string, mixed>> $events Array of event data
     * @param int|null $storeId Store ID
     * @return array<string, mixed>
     */
    public function sendEvents(string $pixelId, array $events, ?int $storeId = null): array
    {
        $normalizedPixelId = $this->normalizeIdentifier($pixelId);
        if ($normalizedPixelId === null) {
            return ['error' => 'Invalid pixel ID', 'http_status' => 0];
        }

        $preparedEvents = $this->prepareEvents($events);
        if ($preparedEvents === []) {
            $this->logger->debug('[Meta CAPI] No valid events to send', [
                'pixel_id' => $normalizedPixelId,
                'store_id' => $storeId
            ]);

            return [
                'success' => true,
                'skipped' => true,
                'skip_reason' => 'no_valid_events',
                'http_status' => 204
            ];
        }

        if (count($preparedEvents) <= self::MAX_EVENTS_PER_REQUEST) {
            return $this->sendEventsChunk($normalizedPixelId, $preparedEvents, $storeId);
        }

        $chunks = array_chunk($preparedEvents, self::MAX_EVENTS_PER_REQUEST);
        $results = [];

        foreach ($chunks as $index => $chunk) {
            $results[] = $this->sendEventsChunk(
                $normalizedPixelId,
                $chunk,
                $storeId,
                $index + 1,
                count($chunks)
            );
        }

        return $this->aggregateChunkResults(
            $results,
            'events',
            [
                'pixel_id' => $normalizedPixelId,
                'store_id' => $storeId,
                'event_count' => count($preparedEvents)
            ]
        );
    }

    /**
     * Send catalog batch updates
     *
     * @param string $catalogId Meta Catalog ID
     * @param array<int, array<string, mixed>> $items Array of product items
     * @param int|null $storeId Store ID
     * @return array<string, mixed>
     */
    public function sendCatalogBatch(string $catalogId, array $items, ?int $storeId = null): array
    {
        $normalizedCatalogId = $this->normalizeIdentifier($catalogId);
        if ($normalizedCatalogId === null) {
            return ['error' => 'Invalid catalog ID', 'http_status' => 0];
        }

        $requests = $this->prepareCatalogRequests($items);
        if ($requests === []) {
            $this->logger->warning('[Meta Catalog] No valid catalog items to send', [
                'catalog_id' => $normalizedCatalogId,
                'store_id' => $storeId
            ]);

            return ['error' => 'No valid catalog items to send', 'http_status' => 0];
        }

        if (count($requests) <= self::MAX_CATALOG_ITEMS_PER_REQUEST) {
            return $this->sendCatalogBatchChunk($normalizedCatalogId, $requests, $storeId);
        }

        $chunks = array_chunk($requests, self::MAX_CATALOG_ITEMS_PER_REQUEST);
        $results = [];

        foreach ($chunks as $index => $chunk) {
            // Delay entre chunks para evitar rate limiting da Meta API
            if ($index > 0) {
                usleep(self::CATALOG_CHUNK_DELAY_MS * 1000);
            }

            $results[] = $this->sendCatalogBatchChunk(
                $normalizedCatalogId,
                $chunk,
                $storeId,
                $index + 1,
                count($chunks)
            );
        }

        return $this->aggregateChunkResults(
            $results,
            'catalog batch',
            [
                'catalog_id' => $normalizedCatalogId,
                'store_id' => $storeId,
                'item_count' => count($requests)
            ]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private function sendEventsChunk(
        string $pixelId,
        array $events,
        ?int $storeId = null,
        ?int $chunkIndex = null,
        ?int $chunkTotal = null
    ): array {
        $logContext = [
            'pixel_id' => $pixelId,
            'store_id' => $storeId,
            'event_count' => count($events)
        ];
        if ($chunkIndex !== null && $chunkTotal !== null) {
            $logContext['chunk_index'] = $chunkIndex;
            $logContext['chunk_total'] = $chunkTotal;
        }

        $encodedEvents = $this->encodeJson($events, 'events', $logContext);
        if ($encodedEvents === null) {
            return ['error' => 'Failed to encode events payload', 'http_status' => 0];
        }

        $this->logger->debug('[Meta CAPI] Sending events', $logContext);

        return $this->fbeHelper->apiPost($pixelId . '/events', ['data' => $encodedEvents], $storeId);
    }

    /**
     * @param array<int, array<string, mixed>> $requests
     * @return array<string, mixed>
     */
    private function sendCatalogBatchChunk(
        string $catalogId,
        array $requests,
        ?int $storeId = null,
        ?int $chunkIndex = null,
        ?int $chunkTotal = null
    ): array {
        $logContext = [
            'catalog_id' => $catalogId,
            'store_id' => $storeId,
            'item_count' => count($requests)
        ];
        if ($chunkIndex !== null && $chunkTotal !== null) {
            $logContext['chunk_index'] = $chunkIndex;
            $logContext['chunk_total'] = $chunkTotal;
        }

        $encodedRequests = $this->encodeJson($requests, 'catalog batch requests', $logContext);
        if ($encodedRequests === null) {
            return ['error' => 'Failed to encode catalog batch payload', 'http_status' => 0];
        }

        $itemType = self::DEFAULT_CATALOG_ITEM_TYPE;
        $firstRequest = $requests[0] ?? null;
        if (is_array($firstRequest) && isset($firstRequest['item_type'])) {
            $normalizedItemType = strtoupper(trim((string) $firstRequest['item_type']));
            if ($normalizedItemType !== '') {
                $itemType = $normalizedItemType;
            }
        }

        $this->logger->info('[Meta Catalog] Sending batch', $logContext);

        return $this->fbeHelper->apiPost(
            $catalogId . '/items_batch',
            [
                'item_type' => $itemType,
                'requests' => $encodedRequests
            ],
            $storeId
        );
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<int, array<string, mixed>>
     */
    private function prepareEvents(array $events): array
    {
        $prepared = [];
        $skipped = 0;
        $skippedMissingSignals = 0;

        foreach ($events as $event) {
            if (!is_array($event)) {
                $skipped++;
                continue;
            }

            $event = $this->sanitizeJsonArray($event);
            $eventName = isset($event['event_name']) ? trim((string) $event['event_name']) : '';
            if ($eventName === '') {
                $skipped++;
                continue;
            }

            $event['event_name'] = $eventName;
            $event['event_time'] = max(1, (int) ($event['event_time'] ?? time()));
            $actionSource = trim((string) ($event['action_source'] ?? self::DEFAULT_ACTION_SOURCE));
            if ($actionSource === '') {
                $actionSource = self::DEFAULT_ACTION_SOURCE;
            }
            if (!in_array($actionSource, self::ALLOWED_ACTION_SOURCES, true)) {
                $actionSource = self::DEFAULT_ACTION_SOURCE;
            }
            $event['action_source'] = $actionSource;

            $userData = $event['user_data'] ?? null;
            if (!is_array($userData)) {
                $skipped++;
                $skippedMissingSignals++;
                continue;
            }
            $userData = $this->sanitizeJsonArray($userData);
            if (!$this->hasMinimumUserSignals($userData)) {
                $skipped++;
                $skippedMissingSignals++;
                continue;
            }
            $event['user_data'] = $userData;

            if (isset($event['event_id'])) {
                $eventId = trim((string) $event['event_id']);
                if ($eventId === '') {
                    unset($event['event_id']);
                } else {
                    $event['event_id'] = $eventId;
                }
            }

            if (isset($event['custom_data']) && (!is_array($event['custom_data']) || $event['custom_data'] === [])) {
                unset($event['custom_data']);
            }

            $prepared[] = $event;
        }

        if ($skipped > 0) {
            $this->logger->debug('[Meta CAPI] Skipped invalid event payloads', [
                'skipped_count' => $skipped,
                'skipped_missing_user_signals' => $skippedMissingSignals
            ]);
        }

        return $prepared;
    }

    /**
     * @param array<string, mixed> $userData
     */
    private function hasMinimumUserSignals(array $userData): bool
    {
        foreach (self::MINIMUM_USER_SIGNAL_KEYS as $key) {
            if (!empty($userData[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function prepareCatalogRequests(array $items): array
    {
        $requests = [];
        $skipped = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                $skipped++;
                continue;
            }

            $method = strtoupper(trim((string) ($item['method'] ?? 'UPDATE')));
            if (!in_array($method, self::ALLOWED_BATCH_METHODS, true)) {
                $method = 'UPDATE';
            }

            $data = $item['data'] ?? $item;
            if (!is_array($data) || $data === []) {
                $skipped++;
                continue;
            }

            $data = $this->sanitizeJsonArray($data);
            $itemId = isset($data['id']) ? $this->normalizeIdentifier((string) $data['id']) : null;
            if ($itemId === null) {
                $skipped++;
                continue;
            }
            // ID já promovido para retailer_id no request; removê-lo de data[] evita
            // o erro "(#100) Unexpected key 'id'" que a API de categories e catalog rejeita.
            unset($data['id']);
            $itemType = strtoupper(trim((string) ($data['item_type'] ?? self::DEFAULT_CATALOG_ITEM_TYPE)));
            if ($itemType === '') {
                $itemType = self::DEFAULT_CATALOG_ITEM_TYPE;
            }
            unset($data['item_type']);

            $requests[] = [
                'method' => $method,
                'item_type' => $itemType,
                'retailer_id' => $itemId,
                'data' => $data
            ];
        }

        if ($skipped > 0) {
            $this->logger->warning('[Meta Catalog] Skipped invalid catalog batch items', [
                'skipped_count' => $skipped
            ]);
        }

        return $requests;
    }

    /**
     * @param array<int, array<string, mixed>> $payload
     * @param array<string, mixed> $logContext
     */
    private function encodeJson(array $payload, string $contextLabel, array $logContext): ?string
    {
        try {
            return json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $e) {
            $this->logger->error(sprintf('[Meta API] Failed to encode %s', $contextLabel), $logContext + [
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @param array<string, mixed> $logContext
     * @return array<string, mixed>
     */
    private function aggregateChunkResults(array $results, string $contextLabel, array $logContext): array
    {
        $failedChunks = [];
        $lastHttpStatus = 0;

        foreach ($results as $index => $result) {
            $httpStatus = (int) ($result['http_status'] ?? 0);
            if ($httpStatus > 0) {
                $lastHttpStatus = $httpStatus;
            }

            if (isset($result['error'])) {
                $failedChunks[] = [
                    'chunk' => $index + 1,
                    'http_status' => $httpStatus,
                    'error' => $result['error']
                ];
            }
        }

        $summary = [
            'success' => $failedChunks === [],
            'http_status' => $lastHttpStatus,
            'chunk_count' => count($results),
            'successful_chunks' => count($results) - count($failedChunks),
            'failed_chunks' => count($failedChunks)
        ];

        if ($failedChunks === []) {
            $this->logger->info(sprintf('[Meta API] %s chunked request completed', ucfirst($contextLabel)), $logContext + $summary);

            return $summary;
        }

        $summary['error'] = sprintf('One or more %s chunks failed', $contextLabel);
        $summary['chunk_errors'] = $failedChunks;

        $this->logger->warning(sprintf('[Meta API] %s chunked request partially failed', ucfirst($contextLabel)), $logContext + $summary);

        return $summary;
    }

    private function normalizeIdentifier(string $value): ?string
    {
        $normalized = preg_replace('/\s+/', '', trim($value));
        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        return $normalized;
    }

    /**
     * Recursively sanitize payloads before JSON encoding.
     *
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private function sanitizeJsonArray(array $data): array
    {
        $isList = array_is_list($data);
        $result = [];

        foreach ($data as $key => $value) {
            $sanitized = $this->sanitizeJsonValue($value);
            if ($sanitized === null) {
                continue;
            }

            if ($isList) {
                $result[] = $sanitized;
                continue;
            }

            if (!is_string($key)) {
                continue;
            }

            $normalizedKey = trim($key);
            if ($normalizedKey === '') {
                continue;
            }

            $result[$normalizedKey] = $sanitized;
        }

        return $result;
    }

    private function sanitizeJsonValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_array($value)) {
            $sanitized = $this->sanitizeJsonArray($value);

            return $sanitized === [] ? null : $sanitized;
        }

        return null;
    }
}
