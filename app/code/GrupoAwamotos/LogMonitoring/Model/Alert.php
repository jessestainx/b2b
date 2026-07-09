<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Model;

use GrupoAwamotos\LogMonitoring\Api\Data\AlertInterface;
use GrupoAwamotos\LogMonitoring\Model\ResourceModel\Alert as AlertResource;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Serialize\Serializer\Json;

class Alert extends AbstractModel implements AlertInterface
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
        $this->_init(AlertResource::class);
    }

    public function getEntityId(): ?int
    {
        return $this->getData(self::ENTITY_ID) ? (int)$this->getData(self::ENTITY_ID) : null;
    }

    public function setEntityId(mixed $entityId): AlertInterface
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    public function getAlertType(): ?string
    {
        return $this->getData(self::ALERT_TYPE);
    }

    public function setAlertType(string $alertType): AlertInterface
    {
        return $this->setData(self::ALERT_TYPE, $alertType);
    }

    public function getSeverity(): ?string
    {
        return $this->getData(self::SEVERITY);
    }

    public function setSeverity(string $severity): AlertInterface
    {
        return $this->setData(self::SEVERITY, $severity);
    }

    public function getTitle(): ?string
    {
        return $this->getData(self::TITLE);
    }

    public function setTitle(string $title): AlertInterface
    {
        return $this->setData(self::TITLE, $title);
    }

    public function getMessage(): ?string
    {
        return $this->getData(self::MESSAGE);
    }

    public function setMessage(?string $message): AlertInterface
    {
        return $this->setData(self::MESSAGE, $message);
    }

    public function getContextData(): ?array
    {
        $data = $this->getData(self::CONTEXT_DATA);
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

    public function setContextData(?array $contextData): AlertInterface
    {
        if ($contextData === null) {
            return $this->setData(self::CONTEXT_DATA, null);
        }

        try {
            $serialized = $this->serializer->serialize($contextData);
            return $this->setData(self::CONTEXT_DATA, $serialized);
        } catch (\Exception $e) {
            return $this->setData(self::CONTEXT_DATA, null);
        }
    }

    public function getSource(): ?string
    {
        return $this->getData(self::SOURCE);
    }

    public function setSource(string $source): AlertInterface
    {
        return $this->setData(self::SOURCE, $source);
    }

    public function getStatus(): ?string
    {
        return $this->getData(self::STATUS);
    }

    public function setStatus(string $status): AlertInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getOccurrences(): int
    {
        return (int)$this->getData(self::OCCURRENCES);
    }

    public function setOccurrences(int $occurrences): AlertInterface
    {
        return $this->setData(self::OCCURRENCES, $occurrences);
    }

    public function getFirstOccurrence(): ?string
    {
        return $this->getData(self::FIRST_OCCURRENCE);
    }

    public function setFirstOccurrence(string $firstOccurrence): AlertInterface
    {
        return $this->setData(self::FIRST_OCCURRENCE, $firstOccurrence);
    }

    public function getLastOccurrence(): ?string
    {
        return $this->getData(self::LAST_OCCURRENCE);
    }

    public function setLastOccurrence(string $lastOccurrence): AlertInterface
    {
        return $this->setData(self::LAST_OCCURRENCE, $lastOccurrence);
    }

    public function getAcknowledgedAt(): ?string
    {
        return $this->getData(self::ACKNOWLEDGED_AT);
    }

    public function setAcknowledgedAt(?string $acknowledgedAt): AlertInterface
    {
        return $this->setData(self::ACKNOWLEDGED_AT, $acknowledgedAt);
    }

    public function getAcknowledgedBy(): ?string
    {
        return $this->getData(self::ACKNOWLEDGED_BY);
    }

    public function setAcknowledgedBy(?string $acknowledgedBy): AlertInterface
    {
        return $this->setData(self::ACKNOWLEDGED_BY, $acknowledgedBy);
    }

    public function getResolvedAt(): ?string
    {
        return $this->getData(self::RESOLVED_AT);
    }

    public function setResolvedAt(?string $resolvedAt): AlertInterface
    {
        return $this->setData(self::RESOLVED_AT, $resolvedAt);
    }

    public function getResolvedBy(): ?string
    {
        return $this->getData(self::RESOLVED_BY);
    }

    public function setResolvedBy(?string $resolvedBy): AlertInterface
    {
        return $this->setData(self::RESOLVED_BY, $resolvedBy);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(string $createdAt): AlertInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt(string $updatedAt): AlertInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
