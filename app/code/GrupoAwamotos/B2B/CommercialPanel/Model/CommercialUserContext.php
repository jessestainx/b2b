<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model;

use GrupoAwamotos\B2B\CommercialPanel\Model\Admin\CommercialRoleAssignment;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\ResourceConnection;

/**
 * Resolve acesso comercial usando ACL da sessão admin, com fallback pelo papel atribuído.
 */
class CommercialUserContext
{
    private const ACL_COMMERCIAL_DASHBOARD = 'GrupoAwamotos_B2B::commercial_dashboard';
    private const ACL_COCKPIT_ONLY = 'GrupoAwamotos_B2B::commercial_cockpit_only';
    private const ACL_TECHNICAL_B2B = 'GrupoAwamotos_B2B::b2b';

    private ?int $resolvedForUserId = null;
    private ?bool $hasCommercialRole = null;

    public function __construct(
        private readonly AdminSession $adminSession,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function hasCommercialDashboardAccess(): bool
    {
        return $this->adminSession->isAllowed(self::ACL_COMMERCIAL_DASHBOARD)
            || $this->hasCommercialRole();
    }

    public function isCockpitOnlyUser(): bool
    {
        return $this->adminSession->isAllowed(self::ACL_COCKPIT_ONLY)
            && !$this->adminSession->isAllowed(self::ACL_TECHNICAL_B2B);
    }

    public function hasCommercialRole(): bool
    {
        $userId = (int) ($this->adminSession->getUser()?->getId() ?? 0);
        if ($userId <= 0) {
            $this->resetRoleCache();

            return false;
        }

        if ($this->resolvedForUserId === $userId && $this->hasCommercialRole !== null) {
            return $this->hasCommercialRole;
        }

        $this->resolvedForUserId = $userId;
        $this->hasCommercialRole = $this->resolveCommercialRoleFromDatabase($userId);

        return $this->hasCommercialRole;
    }

    private function resolveCommercialRoleFromDatabase(int $userId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $roleTable = $this->resourceConnection->getTableName('authorization_role');

        $roleName = (string) $connection->fetchOne(
            $connection->select()
                ->from(['user_role' => $roleTable], [])
                ->join(
                    ['group_role' => $roleTable],
                    'group_role.role_id = user_role.parent_id',
                    ['role_name']
                )
                ->where('user_role.user_id = ?', $userId)
                ->where('user_role.role_type = ?', 'U')
                ->where('group_role.role_type = ?', 'G')
                ->limit(1)
        );

        return in_array($roleName, [
            CommercialRoleAssignment::ROLE_SELLER,
            CommercialRoleAssignment::ROLE_SUPERVISOR,
        ], true);
    }

    private function resetRoleCache(): void
    {
        $this->resolvedForUserId = null;
        $this->hasCommercialRole = null;
    }
}
