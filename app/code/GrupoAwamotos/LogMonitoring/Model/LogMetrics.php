<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Model;

use GrupoAwamotos\LogMonitoring\Api\Data\LogMetricsInterface;
use GrupoAwamotos\LogMonitoring\Model\ResourceModel\LogMetrics as LogMetricsResource;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Serialize\Serializer\Json;

class LogMetrics extends AbstractModel implements LogMetricsInterface
{
    private Json $serializer;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        Json $serializer,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->serializer = $serializer;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    protected function _construct(): void
    {
        $this->_init(LogMetricsResource::class);
    }

    public function getEntityId(): ?int
    {
        return $this->getData(self::ENTITY_ID) ? (int)$this->getData(self::ENTITY_ID) : null;
    }

    public function setEntityId(mixed $entityId): LogMetricsInterface
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    public function getLogType(): ?string
    {
        return $this->getData(self::LOG_TYPE);
    }

    public function setLogType(string $logType): LogMetricsInterface
    {
        return $this->setData(self::LOG_TYPE, $logType);
    }

    public function getSourceFile(): ?string
    {
        return $this->getData(self::SOURCE_FILE);
    }

    public function setSourceFile(string $sourceFile): LogMetricsInterface
    {
        return $this->setData(self::SOURCE_FILE, $sourceFile);
    }

    public function getTotalEntries(): int
    {
        return (int)$this->getData(self::TOTAL_ENTRIES);
    }

    public function setTotalEntries(int $totalEntries): LogMetricsInterface
    {
        return $this->setData(self::TOTAL_ENTRIES, $totalEntries);
    }

    public function getErrorEntries(): int
    {
        return (int)$this->getData(self::ERROR_ENTRIES);
    }

    public function setErrorEntries(int $errorEntries): LogMetricsInterface
    {
        return $this->setData(self::ERROR_ENTRIES, $errorEntries);
    }

    public function getWarningEntries(): int
    {
        return (int)$this->getData(self::WARNING_ENTRIES);
    }

    public function setWarningEntries(int $warningEntries): LogMetricsInterface
    {
        return $this->setData(self::WARNING_ENTRIES, $warningEntries);
    }

    public function getCriticalEntries(): int
    {
        return (int)$this->getData(self::CRITICAL_ENTRIES);
    }

    public function setCriticalEntries(int $criticalEntries): LogMetricsInterface
    {
        return $this->setData(self::CRITICAL_ENTRIES, $criticalEntries);
    }

    public function getFileSizeBytes(): int
    {
        return (int)$this->getData(self::FILE_SIZE_BYTES);
    }

    public function setFileSizeBytes(int $fileSizeBytes): LogMetricsInterface
    {
        return $this->setData(self::FILE_SIZE_BYTES, $fileSizeBytes);
    }

    public function getAnalysisData(): ?array
    {
        $data = $this->getData(self::ANALYSIS_DATA);
        if ($data === null) {
            return null;
        }

        if (is_string($data)) {
            try {
                return $this->serializer->unserialize($data);
            } catch (\Exception $e) {
                return null;
            }
        }

        return is_array($data) ? $data : null;
    }

    public function setAnalysisData(?array $analysisData): LogMetricsInterface
    {
        if ($analysisData === null) {
            return $this->setData(self::ANALYSIS_DATA, null);
        }

        try {
            $serialized = $this->serializer->serialize($analysisData);
            return $this->setData(self::ANALYSIS_DATA, $serialized);
        } catch (\Exception $e) {
            return $this->setData(self::ANALYSIS_DATA, null);
        }
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(string $createdAt): LogMetricsInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt(string $updatedAt): LogMetricsInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
