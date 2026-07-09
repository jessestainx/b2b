<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model\Admin;

use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory as RoleCollectionFactory;
use Magento\Framework\App\ResourceConnection;

/**
 * Vincula usuários admin de atendentes B2B ao papel AWA Comercial Vendedora.
 */
class CommercialRoleAssignment
{
    public const ROLE_SELLER = 'AWA Comercial Vendedora';
    public const ROLE_SUPERVISOR = 'AWA Comercial Supervisora';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly RoleCollectionFactory $roleCollectionFactory
    ) {
    }

    /**
     * @return array{assigned: int, skipped: int, missing_role: bool, details: list<array<string, mixed>>}
     */
    public function assignSellerRoleToActiveAttendants(): array
    {
        $sellerRoleId = $this->resolveGroupRoleId(self::ROLE_SELLER);
        if ($sellerRoleId === null) {
            return [
                'assigned' => 0,
                'skipped' => 0,
                'missing_role' => true,
                'details' => [],
            ];
        }

        $connection = $this->resourceConnection->getConnection();
        $attendantTable = $this->resourceConnection->getTableName('grupoawamotos_b2b_attendants');
        $roleTable = $this->resourceConnection->getTableName('authorization_role');
        $userTable = $this->resourceConnection->getTableName('admin_user');

        $attendants = $connection->fetchAll(
            $connection->select()
                ->from(['a' => $attendantTable], ['attendant_id', 'name', 'admin_user_id'])
                ->join(['u' => $userTable], 'u.user_id = a.admin_user_id', ['username', 'is_active'])
                ->where('a.is_active = ?', 1)
                ->where('a.admin_user_id IS NOT NULL')
                ->where('u.is_active = ?', 1)
                ->order('a.name ASC')
        );

        $assigned = 0;
        $skipped = 0;
        $details = [];

        foreach ($attendants as $attendant) {
            $userId = (int) $attendant['admin_user_id'];
            $userRoleId = (int) $connection->fetchOne(
                $connection->select()
                    ->from($roleTable, ['role_id'])
                    ->where('user_id = ?', $userId)
                    ->where('role_type = ?', 'U')
                    ->limit(1)
            );

            if ($userRoleId <= 0) {
                $skipped++;
                $details[] = [
                    'username' => $attendant['username'],
                    'name' => $attendant['name'],
                    'action' => 'skipped_no_user_role',
                ];
                continue;
            }

            $currentParentId = (int) $connection->fetchOne(
                $connection->select()
                    ->from($roleTable, ['parent_id'])
                    ->where('role_id = ?', $userRoleId)
                    ->limit(1)
            );

            if ($currentParentId === $sellerRoleId) {
                $skipped++;
                $details[] = [
                    'username' => $attendant['username'],
                    'name' => $attendant['name'],
                    'action' => 'already_assigned',
                ];
                continue;
            }

            $connection->update(
                $roleTable,
                [
                    'parent_id' => $sellerRoleId,
                    'tree_level' => 2,
                ],
                ['role_id = ?' => $userRoleId]
            );

            $assigned++;
            $details[] = [
                'username' => $attendant['username'],
                'name' => $attendant['name'],
                'action' => 'assigned',
                'from_role_id' => $currentParentId,
            ];
        }

        return [
            'assigned' => $assigned,
            'skipped' => $skipped,
            'missing_role' => false,
            'details' => $details,
        ];
    }

    /**
     * @return array{assigned: int, skipped: int, missing_role: bool}
     */
    public function assignSupervisorRoleByUsername(string $username): array
    {
        $supervisorRoleId = $this->resolveGroupRoleId(self::ROLE_SUPERVISOR);
        if ($supervisorRoleId === null) {
            return ['assigned' => 0, 'skipped' => 0, 'missing_role' => true];
        }

        $connection = $this->resourceConnection->getConnection();
        $userTable = $this->resourceConnection->getTableName('admin_user');
        $roleTable = $this->resourceConnection->getTableName('authorization_role');

        $userId = (int) $connection->fetchOne(
            $connection->select()
                ->from($userTable, ['user_id'])
                ->where('username = ?', $username)
                ->where('is_active = ?', 1)
                ->limit(1)
        );

        if ($userId <= 0) {
            return ['assigned' => 0, 'skipped' => 1, 'missing_role' => false];
        }

        $userRoleId = (int) $connection->fetchOne(
            $connection->select()
                ->from($roleTable, ['role_id'])
                ->where('user_id = ?', $userId)
                ->where('role_type = ?', 'U')
                ->limit(1)
        );

        if ($userRoleId <= 0) {
            return ['assigned' => 0, 'skipped' => 1, 'missing_role' => false];
        }

        $connection->update(
            $roleTable,
            [
                'parent_id' => $supervisorRoleId,
                'tree_level' => 2,
            ],
            ['role_id = ?' => $userRoleId]
        );

        return ['assigned' => 1, 'skipped' => 0, 'missing_role' => false];
    }

    private function resolveGroupRoleId(string $roleName): ?int
    {
        $collection = $this->roleCollectionFactory->create();
        $collection->addFieldToFilter('role_name', $roleName);
        $collection->addFieldToFilter('role_type', 'G');
        $collection->setPageSize(1);
        $role = $collection->getFirstItem();

        return $role->getId() ? (int) $role->getId() : null;
    }
}
