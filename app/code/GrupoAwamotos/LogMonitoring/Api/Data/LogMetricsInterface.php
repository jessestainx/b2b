<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Api\Data;

interface LogMetricsInterface
{
    public const ENTITY_ID = 'entity_id';
    public const LOG_TYPE = 'log_type';
    public const SOURCE_FILE = 'source_file';
    public const TOTAL_ENTRIES = 'total_entries';
    public const ERROR_ENTRIES = 'error_entries';
    public const WARNING_ENTRIES = 'warning_entries';
    public const CRITICAL_ENTRIES = 'critical_entries';
    public const FILE_SIZE_BYTES = 'file_size_bytes';
    public const ANALYSIS_DATA = 'analysis_data';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public function getEntityId(): ?int;
    public function setEntityId(mixed $entityId): self;

    public function getLogType(): ?string;
    public function setLogType(string $logType): self;

    public function getSourceFile(): ?string;
    public function setSourceFile(string $sourceFile): self;

    public function getTotalEntries(): int;
    public function setTotalEntries(int $totalEntries): self;

    public function getErrorEntries(): int;
    public function setErrorEntries(int $errorEntries): self;

    public function getWarningEntries(): int;
    public function setWarningEntries(int $warningEntries): self;

    public function getCriticalEntries(): int;
    public function setCriticalEntries(int $criticalEntries): self;

    public function getFileSizeBytes(): int;
    public function setFileSizeBytes(int $fileSizeBytes): self;

    public function getAnalysisData(): ?array;
    public function setAnalysisData(?array $analysisData): self;

    public function getCreatedAt(): ?string;
    public function setCreatedAt(string $createdAt): self;

    public function getUpdatedAt(): ?string;
    public function setUpdatedAt(string $updatedAt): self;
}
