<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Api\Data;

interface AlertInterface
{
    public const ENTITY_ID = 'entity_id';
    public const ALERT_TYPE = 'alert_type';
    public const SEVERITY = 'severity';
    public const TITLE = 'title';
    public const MESSAGE = 'message';
    public const CONTEXT_DATA = 'context_data';
    public const SOURCE = 'source';
    public const STATUS = 'status';
    public const OCCURRENCES = 'occurrences';
    public const FIRST_OCCURRENCE = 'first_occurrence';
    public const LAST_OCCURRENCE = 'last_occurrence';
    public const ACKNOWLEDGED_AT = 'acknowledged_at';
    public const ACKNOWLEDGED_BY = 'acknowledged_by';
    public const RESOLVED_AT = 'resolved_at';
    public const RESOLVED_BY = 'resolved_by';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    // Severity Levels
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    // Status
    public const STATUS_OPEN = 'open';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_RESOLVED = 'resolved';

    public function getEntityId(): ?int;
    public function setEntityId(mixed $entityId): self;

    public function getAlertType(): ?string;
    public function setAlertType(string $alertType): self;

    public function getSeverity(): ?string;
    public function setSeverity(string $severity): self;

    public function getTitle(): ?string;
    public function setTitle(string $title): self;

    public function getMessage(): ?string;
    public function setMessage(?string $message): self;

    public function getContextData(): ?array;
    public function setContextData(?array $contextData): self;

    public function getSource(): ?string;
    public function setSource(string $source): self;

    public function getStatus(): ?string;
    public function setStatus(string $status): self;

    public function getOccurrences(): int;
    public function setOccurrences(int $occurrences): self;

    public function getFirstOccurrence(): ?string;
    public function setFirstOccurrence(string $firstOccurrence): self;

    public function getLastOccurrence(): ?string;
    public function setLastOccurrence(string $lastOccurrence): self;

    public function getAcknowledgedAt(): ?string;
    public function setAcknowledgedAt(?string $acknowledgedAt): self;

    public function getAcknowledgedBy(): ?string;
    public function setAcknowledgedBy(?string $acknowledgedBy): self;

    public function getResolvedAt(): ?string;
    public function setResolvedAt(?string $resolvedAt): self;

    public function getResolvedBy(): ?string;
    public function setResolvedBy(?string $resolvedBy): self;

    public function getCreatedAt(): ?string;
    public function setCreatedAt(string $createdAt): self;

    public function getUpdatedAt(): ?string;
    public function setUpdatedAt(string $updatedAt): self;
}
