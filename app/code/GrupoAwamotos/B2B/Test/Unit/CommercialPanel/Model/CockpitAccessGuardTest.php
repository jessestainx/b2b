<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\CommercialPanel\Model;

use GrupoAwamotos\B2B\CommercialPanel\Model\CockpitAccessGuard;
use Magento\Framework\App\Request\Http as HttpRequest;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\B2B\CommercialPanel\Model\CockpitAccessGuard
 */
class CockpitAccessGuardTest extends TestCase
{
    private CockpitAccessGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new CockpitAccessGuard();
    }

    public function testAllowsCommercialDashboardRoute(): void
    {
        $request = $this->createRequest('awa_commercial', 'commercialdashboard', 'index');
        $this->assertTrue($this->guard->isRequestAllowed($request));
    }

    public function testAllowsMuiRenderRoute(): void
    {
        $request = $this->createRequest('mui', 'index', 'render');
        $this->assertTrue($this->guard->isRequestAllowed($request));
    }

    public function testAllowsAdminDeniedPage(): void
    {
        $request = $this->createRequest('admin', 'denied', 'index');
        $this->assertTrue($this->guard->isRequestAllowed($request));
    }

    public function testDeniesTechnicalCatalogRoute(): void
    {
        $request = $this->createRequest('catalog', 'product', 'index');
        $this->assertFalse($this->guard->isRequestAllowed($request));
    }

    public function testDeniesLegacyAttendantDashboardRoute(): void
    {
        $request = $this->createRequest('grupoawamotos_b2b', 'attendant', 'dashboard');
        $this->assertFalse($this->guard->isRequestAllowed($request));
    }

    public function testDeniesLegacyPlatformRoute(): void
    {
        $request = $this->createRequest('awa_b2b', 'dashboard', 'index');
        $this->assertFalse($this->guard->isRequestAllowed($request));
    }

    public function testDeniesErpIntegrationRoute(): void
    {
        $request = $this->createRequest('admin', 'system_config', 'edit');
        $this->assertFalse($this->guard->isRequestAllowed($request));
    }

    private function createRequest(string $routeName, string $controller, string $action): HttpRequest
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getRouteName')->willReturn($routeName);
        $request->method('getControllerName')->willReturn($controller);
        $request->method('getActionName')->willReturn($action);

        return $request;
    }
}
