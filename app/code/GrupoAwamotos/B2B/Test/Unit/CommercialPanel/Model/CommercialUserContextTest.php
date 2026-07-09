<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\CommercialPanel\Model;

use GrupoAwamotos\B2B\CommercialPanel\Model\CommercialUserContext;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\ResourceConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\B2B\CommercialPanel\Model\CommercialUserContext
 */
class CommercialUserContextTest extends TestCase
{
    private AdminSession&MockObject $adminSession;
    private ResourceConnection&MockObject $resourceConnection;

    protected function setUp(): void
    {
        $this->adminSession = $this->createMock(AdminSession::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
    }

    public function testCockpitOnlyUserRequiresCockpitFlagWithoutTechnicalB2b(): void
    {
        $this->adminSession->method('isAllowed')->willReturnCallback(
            static fn (string $resource): bool => in_array($resource, [
                'GrupoAwamotos_B2B::commercial_cockpit_only',
                'GrupoAwamotos_B2B::commercial_dashboard',
            ], true)
        );

        $context = $this->createContext();

        $this->assertTrue($context->isCockpitOnlyUser());
    }

    public function testUserWithTechnicalB2bIsNotCockpitOnly(): void
    {
        $this->adminSession->method('isAllowed')->willReturnCallback(
            static fn (string $resource): bool => in_array($resource, [
                'GrupoAwamotos_B2B::commercial_cockpit_only',
                'GrupoAwamotos_B2B::b2b',
            ], true)
        );

        $context = $this->createContext();

        $this->assertFalse($context->isCockpitOnlyUser());
    }

    private function createContext(): CommercialUserContext
    {
        return new CommercialUserContext($this->adminSession, $this->resourceConnection);
    }
}
