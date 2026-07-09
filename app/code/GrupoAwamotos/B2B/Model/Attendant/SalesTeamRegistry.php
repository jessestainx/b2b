<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Attendant;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface as ErpConnectionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * Equipe comercial oficial (8 vendedoras suporte*.vendas) e resolução de VENDPREF/canonical.
 */
class SalesTeamRegistry
{
    private const CONFIG_CANONICAL_IDS = 'grupoawamotos_b2b/attendants/canonical_attendant_ids';
    private const CONFIG_VENDPREF_ALIASES = 'grupoawamotos_b2b/attendants/vendpref_aliases';

    /** @var array<int, array<string, mixed>>|null */
    private ?array $canonicalByErpCode = null;

    /** @var array<int, int>|null */
    private ?array $vendPrefAliases = null;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ErpConnectionInterface $erpConnection,
        private readonly AttendantManager $attendantManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return int[]
     */
    public function getCanonicalAttendantIds(): array
    {
        $raw = (string) $this->scopeConfig->getValue(self::CONFIG_CANONICAL_IDS, ScopeInterface::SCOPE_STORE);
        if ($raw === '') {
            return [1, 2, 3, 4, 5, 6, 7, 8];
        }

        $ids = array_values(array_filter(array_map('intval', array_map('trim', explode(',', $raw)))));
        return $ids !== [] ? $ids : [1, 2, 3, 4, 5, 6, 7, 8];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCanonicalAttendantsByErpCode(): array
    {
        if ($this->canonicalByErpCode !== null) {
            return $this->canonicalByErpCode;
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('grupoawamotos_b2b_attendants');
        $ids = $this->getCanonicalAttendantIds();

        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table)
                ->where('attendant_id IN (?)', $ids)
                ->where('is_active = ?', 1)
                ->where('erp_seller_code IS NOT NULL')
        );

        $this->canonicalByErpCode = [];
        foreach ($rows as $row) {
            $this->canonicalByErpCode[(int) $row['erp_seller_code']] = $row;
        }

        return $this->canonicalByErpCode;
    }

    /**
     * @return array<int, int> legacy VENDPREF => canonical erp_seller_code
     */
    public function getVendPrefAliases(): array
    {
        if ($this->vendPrefAliases !== null) {
            return $this->vendPrefAliases;
        }

        $raw = (string) $this->scopeConfig->getValue(self::CONFIG_VENDPREF_ALIASES, ScopeInterface::SCOPE_STORE);
        $this->vendPrefAliases = [];

        if ($raw === '') {
            return $this->vendPrefAliases;
        }

        foreach (explode(',', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '' || !str_contains($pair, ':')) {
                continue;
            }
            [$legacy, $canonical] = array_map('trim', explode(':', $pair, 2));
            $legacyCode = (int) $legacy;
            $canonicalCode = (int) $canonical;
            if ($legacyCode > 0 && $canonicalCode > 0) {
                $this->vendPrefAliases[$legacyCode] = $canonicalCode;
            }
        }

        return $this->vendPrefAliases;
    }

    public function normalizeVendPref(int $vendPref): int
    {
        if ($vendPref <= 0) {
            return 0;
        }

        return $this->getVendPrefAliases()[$vendPref] ?? $vendPref;
    }

    public function resolveAttendantIdFromVendPref(int $vendPref): ?int
    {
        $normalized = $this->normalizeVendPref($vendPref);
        if ($normalized <= 0) {
            return null;
        }

        $index = $this->getCanonicalAttendantsByErpCode();
        if (!isset($index[$normalized])) {
            return null;
        }

        return (int) $index[$normalized]['attendant_id'];
    }

    /**
     * Resolve a vendedora oficial para notificações WhatsApp.
     *
     * @return array<string, mixed>|null
     */
    public function resolveNotificationAttendant(int $customerId): ?array
    {
        $attendant = $this->attendantManager->getCustomerAttendant($customerId);
        if ($attendant !== null && $this->isCanonicalAttendantId((int) $attendant['attendant_id'])) {
            return $attendant;
        }

        $vendPref = $this->fetchCustomerVendPref($customerId);
        if ($vendPref === null) {
            return null;
        }

        $targetId = $this->resolveAttendantIdFromVendPref($vendPref);
        if ($targetId === null) {
            return null;
        }

        return $this->attendantManager->getAttendantById($targetId);
    }

    public function isCanonicalAttendantId(int $attendantId): bool
    {
        return in_array($attendantId, $this->getCanonicalAttendantIds(), true);
    }

    private function fetchCustomerVendPref(int $customerId): ?int
    {
        if (!$this->erpConnection->hasAvailableDriver()) {
            return null;
        }

        $connection = $this->resource->getConnection();
        $erpCodeAttrId = $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('eav_attribute'), ['attribute_id'])
                ->where('attribute_code = ?', 'erp_code')
                ->where('entity_type_id = ?', 1)
        );

        if (!$erpCodeAttrId) {
            return null;
        }

        $erpCode = $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('customer_entity_varchar'), ['value'])
                ->where('entity_id = ?', $customerId)
                ->where('attribute_id = ?', $erpCodeAttrId)
        );

        if (!$erpCode) {
            return null;
        }

        try {
            $rows = $this->erpConnection->query(
                'SELECT TOP 1 f.VENDPREF FROM dbo.FN_FORNECEDORES f WHERE f.CKCLIENTE = ? AND f.CODIGO = ?',
                ['S', (string) $erpCode]
            );
        } catch (\Throwable $e) {
            $this->logger->warning('[SalesTeamRegistry] ERP VENDPREF lookup failed: ' . $e->getMessage(), [
                'customer_id' => $customerId,
            ]);
            return null;
        }

        if ($rows === []) {
            return null;
        }

        $vendPref = (int) ($rows[0]['VENDPREF'] ?? 0);
        return $vendPref > 0 ? $vendPref : null;
    }
}
