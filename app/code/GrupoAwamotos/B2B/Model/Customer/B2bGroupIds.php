<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Customer;

use GrupoAwamotos\B2B\Service\CustomerGroupManager;
use Magento\Framework\App\ResourceConnection;

/**
 * Single source of truth for Magento customer groups that participate in
 * B2B commercial / Sectra import pipelines.
 *
 * Pricing/profile groups (Atacado, VIP, Revendedor) and the named status
 * group "B2B Aprovado" are eligible. "B2B Pendente" is intentionally excluded.
 */
final class B2bGroupIds
{
    /**
     * @var list<string>
     */
    public const ELIGIBLE_GROUP_CODES = [
        'B2B Atacado',
        'B2B VIP',
        'B2B Revendedor',
        CustomerGroupManager::GROUP_NAME_APPROVED,
    ];

    /**
     * @return list<int>
     */
    public static function resolve(ResourceConnection $resource): array
    {
        $connection = $resource->getConnection();
        $table = $resource->getTableName('customer_group');
        $ids = $connection->fetchCol(
            $connection->select()
                ->from($table, ['customer_group_id'])
                ->where('customer_group_code IN (?)', self::ELIGIBLE_GROUP_CODES)
        );

        $ids = array_values(array_unique(array_map('intval', $ids)));

        // Safety net if groups were renamed before create-patch ran.
        return $ids !== [] ? $ids : [4, 5, 6];
    }

    public static function contains(ResourceConnection $resource, int $groupId): bool
    {
        return in_array($groupId, self::resolve($resource), true);
    }

    /**
     * Comma-separated IDs for raw SQL IN (...) clauses (values already int-cast).
     */
    public static function toSqlInList(ResourceConnection $resource): string
    {
        return implode(',', self::resolve($resource));
    }
}
