<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Model;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use GrupoAwamotos\B2B\Model\PriceVisibility;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\B2B\Model\PriceVisibility
 */
class PriceVisibilityTest extends TestCase
{
    private Config&MockObject $config;
    private CustomerSession&MockObject $customerSession;
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private HttpContext&MockObject $httpContext;
    private UrlInterface&MockObject $urlBuilder;
    private SyncLogResource&MockObject $syncLogResource;
    private array $httpContextValues = [];

    /**
     * Create a fresh PriceVisibility instance (avoids internal cache between tests)
     */
    private function createService(): PriceVisibility
    {
        return new PriceVisibility(
            $this->config,
            $this->customerSession,
            $this->customerRepository,
            $this->httpContext,
            $this->urlBuilder,
            $this->syncLogResource
        );
    }

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->httpContext = $this->createMock(HttpContext::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->syncLogResource = $this->createMock(SyncLogResource::class);
        $this->httpContextValues = [
            CustomerContext::CONTEXT_AUTH => false,
            'customer_id' => 0,
            'b2b_approval_status' => 'guest',
            'erp_price_list' => '0',
        ];

        $this->httpContext->method('getValue')
            ->willReturnCallback(function (string $key) {
                return $this->httpContextValues[$key] ?? null;
            });
    }

    /**
     * Helper: mock logged-in customer with given approval status and optional ERP code
     *
     * Also mirrors the "logged in" state into the HttpContext values, since
     * PriceVisibility::isLoggedInContext() checks HttpContext first (for FPC
     * cache-safety) and only falls back to CustomerSession when the context
     * auth key is absent (null) AND a session cookie is present — neither of
     * which is true by default in a CLI unit test run. Without this, every
     * "logged in" scenario mocked only via CustomerSession would silently be
     * evaluated as a guest.
     */
    private function mockLoggedInCustomer(?string $approvalStatus = null, ?string $erpCode = null): void
    {
        $this->httpContextValues[CustomerContext::CONTEXT_AUTH] = true;
        $this->httpContextValues['customer_id'] = 42;

        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->customerSession->method('getCustomerId')->willReturn(42);

        $customer = $this->createMock(CustomerInterface::class);

        $customer->method('getCustomAttribute')->willReturnCallback(
            function (string $code) use ($approvalStatus, $erpCode): ?AttributeInterface {
                if ($code === 'b2b_approval_status' && $approvalStatus !== null) {
                    $attr = $this->createMock(AttributeInterface::class);
                    $attr->method('getValue')->willReturn($approvalStatus);
                    return $attr;
                }
                if ($code === 'erp_code' && $erpCode !== null) {
                    $attr = $this->createMock(AttributeInterface::class);
                    $attr->method('getValue')->willReturn($erpCode);
                    return $attr;
                }
                return null;
            }
        );

        $this->customerRepository->method('getById')->with(42)->willReturn($customer);
    }

    private function mockHttpContextLoggedIn(
        int $customerId = 42,
        ?string $approvalStatus = ApprovalStatus::STATUS_APPROVED,
        string $erpPriceList = 'default'
    ): void {
        $this->httpContextValues[CustomerContext::CONTEXT_AUTH] = true;
        $this->httpContextValues['customer_id'] = $customerId;
        $this->httpContextValues['b2b_approval_status'] = $approvalStatus ?? 'guest';
        $this->httpContextValues['erp_price_list'] = $erpPriceList;
    }

    // ====================================================================
    // canViewPrices — módulo desabilitado
    // ====================================================================

    public function testCanViewPricesReturnsTrueWhenModuleDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $service = $this->createService();
        $this->assertTrue($service->canViewPrices());
    }

    // ====================================================================
    // canViewPrices — logado
    // ====================================================================

    public function testCanViewPricesLoggedInNoStatusReturnsTrue(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->mockLoggedInCustomer(null);

        $service = $this->createService();
        $this->assertTrue($service->canViewPrices());
    }

    public function testCanViewPricesLoggedInApprovedWithErpCodeReturnsTrue(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('hidePriceForNoErp')->willReturn(true);
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_APPROVED, '12345');

        $service = $this->createService();
        $this->assertTrue($service->canViewPrices());
    }

    public function testCanViewPricesLoggedInApprovedWithoutErpCodeReturnsFalse(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('hidePriceForNoErp')->willReturn(true);
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_APPROVED, null);
        $this->syncLogResource->method('getErpCodeByMagentoId')->willReturn(null);

        $service = $this->createService();
        $this->assertFalse($service->canViewPrices());
    }

    public function testCanViewPricesLoggedInApprovedHidePriceForNoErpDisabledReturnsTrue(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('hidePriceForNoErp')->willReturn(false);
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_APPROVED, null);

        $service = $this->createService();
        $this->assertTrue($service->canViewPrices());
    }

    public function testCanViewPricesLoggedInPendingShowPriceTrue(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('showPriceForPending')->willReturn(true);
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_PENDING);

        $service = $this->createService();
        $this->assertTrue($service->canViewPrices());
    }

    public function testCanViewPricesLoggedInPendingShowPriceFalse(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('showPriceForPending')->willReturn(false);
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_PENDING);

        $service = $this->createService();
        $this->assertFalse($service->canViewPrices());
    }

    public function testCanViewPricesLoggedInRejectedReturnsFalse(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_REJECTED);

        $service = $this->createService();
        $this->assertFalse($service->canViewPrices());
    }

    public function testCanViewPricesLoggedInSuspendedReturnsFalse(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_SUSPENDED);

        $service = $this->createService();
        $this->assertFalse($service->canViewPrices());
    }

    // ====================================================================
    // canViewPrices — visitante
    // ====================================================================

    public function testCanViewPricesGuestStrictB2BReturnsFalse(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isStrictB2B')->willReturn(true);
        $this->customerSession->method('isLoggedIn')->willReturn(false);

        $service = $this->createService();
        $this->assertFalse($service->canViewPrices());
    }

    public function testCanViewPricesGuestMixedHidePricesReturnsFalse(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isStrictB2B')->willReturn(false);
        $this->config->method('hidePriceForGuests')->willReturn(true);
        $this->customerSession->method('isLoggedIn')->willReturn(false);

        $service = $this->createService();
        $this->assertFalse($service->canViewPrices());
    }

    public function testCanViewPricesGuestMixedShowPricesReturnsTrue(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isStrictB2B')->willReturn(false);
        $this->config->method('hidePriceForGuests')->willReturn(false);
        $this->customerSession->method('isLoggedIn')->willReturn(false);

        $service = $this->createService();
        $this->assertTrue($service->canViewPrices());
    }

    // ====================================================================
    // canAddToCart — módulo desabilitado
    // ====================================================================

    public function testCanAddToCartReturnsTrueWhenModuleDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $service = $this->createService();
        $this->assertTrue($service->canAddToCart());
    }

    // ====================================================================
    // canAddToCart — logado
    // ====================================================================

    public function testCanAddToCartLoggedInApprovedWithErpReturnsTrue(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('hidePriceForNoErp')->willReturn(true);
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_APPROVED, '12345');

        $service = $this->createService();
        $this->assertTrue($service->canAddToCart());
    }

    public function testCanAddToCartLoggedInApprovedWithoutErpReturnsFalse(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('hidePriceForNoErp')->willReturn(true);
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_APPROVED, null);
        $this->syncLogResource->method('getErpCodeByMagentoId')->willReturn(null);

        $service = $this->createService();
        $this->assertFalse($service->canAddToCart());
    }

    public function testCanAddToCartLoggedInPendingReturnsFalse(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_PENDING);

        $service = $this->createService();
        $this->assertFalse($service->canAddToCart());
    }

    public function testCanAddToCartLoggedInRejectedReturnsFalse(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_REJECTED);

        $service = $this->createService();
        $this->assertFalse($service->canAddToCart());
    }

    public function testCanAddToCartLoggedInNoStatusReturnsFalse(): void
    {
        // Fail-closed: canViewPrices() treats "no status" as allowed for backward
        // compatibility, but canAddToCart()/isCustomerApproved() must not — a
        // customer without an explicit approval status must not be able to
        // checkout as B2B.
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('hidePriceForNoErp')->willReturn(false);
        $this->mockLoggedInCustomer(null);

        $service = $this->createService();
        $this->assertFalse($service->canAddToCart());
    }

    // ====================================================================
    // canAddToCart — visitante
    // ====================================================================

    public function testCanAddToCartGuestStrictReturnsFalse(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isStrictB2B')->willReturn(true);
        $this->customerSession->method('isLoggedIn')->willReturn(false);

        $service = $this->createService();
        $this->assertFalse($service->canAddToCart());
    }

    public function testCanAddToCartGuestMixedHideCartReturnsFalse(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isStrictB2B')->willReturn(false);
        $this->config->method('hideAddToCartForGuests')->willReturn(true);
        $this->customerSession->method('isLoggedIn')->willReturn(false);

        $service = $this->createService();
        $this->assertFalse($service->canAddToCart());
    }

    public function testCanAddToCartGuestMixedShowCartReturnsTrue(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isStrictB2B')->willReturn(false);
        $this->config->method('hideAddToCartForGuests')->willReturn(false);
        $this->customerSession->method('isLoggedIn')->willReturn(false);

        $service = $this->createService();
        $this->assertTrue($service->canAddToCart());
    }

    // ====================================================================
    // getPriceReplacementMessage
    // ====================================================================

    public function testGetPriceReplacementMessageApprovedNoErp(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('hidePriceForNoErp')->willReturn(true);
        $this->config->method('getPendingErpMessage')->willReturn('Tabela em definição');
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_APPROVED, null);
        $this->syncLogResource->method('getErpCodeByMagentoId')->willReturn(null);

        $service = $this->createService();
        $this->assertSame('Tabela em definição', $service->getPriceReplacementMessage());
    }

    public function testGetPriceReplacementMessageApprovedNoErpDefaultMsg(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('hidePriceForNoErp')->willReturn(true);
        $this->config->method('getPendingErpMessage')->willReturn('');
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_APPROVED, null);
        $this->syncLogResource->method('getErpCodeByMagentoId')->willReturn(null);

        $service = $this->createService();
        $msg = $service->getPriceReplacementMessage();
        $this->assertStringContainsString('tabela de preços', $msg);
    }

    public function testGetPriceReplacementMessagePendingCustomMsg(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getPendingMessage')->willReturn('Aguardando aprovação do cadastro');
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_PENDING);

        $service = $this->createService();
        $this->assertSame('Aguardando aprovação do cadastro', $service->getPriceReplacementMessage());
    }

    public function testGetPriceReplacementMessagePendingDefaultMsg(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getPendingMessage')->willReturn('');
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_PENDING);

        $service = $this->createService();
        $msg = $service->getPriceReplacementMessage();
        $this->assertStringContainsString('pendente', $msg);
    }

    public function testGetPriceReplacementMessageGuestWithLoginUrl(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(false);

        $this->config->method('getLoginMessage')
            ->willReturn('<a href="{{login_url}}">Login</a> ou <a href="{{register_url}}">Cadastre-se</a>');

        $this->urlBuilder->method('getUrl')->willReturnCallback(function (string $route) {
            return 'https://awamotos.com.br/' . $route;
        });

        $service = $this->createService();
        $msg = $service->getPriceReplacementMessage();

        $this->assertStringContainsString('https://awamotos.com.br/customer/account/login', $msg);
        $this->assertStringContainsString('https://awamotos.com.br/customer/account/create', $msg);
        $this->assertStringNotContainsString('{{login_url}}', $msg);
        $this->assertStringNotContainsString('{{register_url}}', $msg);
    }

    public function testGetPriceReplacementMessageGuestDefaultMsg(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(false);
        $this->config->method('getLoginMessage')->willReturn('');

        $this->urlBuilder->method('getUrl')->willReturnCallback(function (string $route) {
            return 'https://awamotos.com.br/' . $route;
        });

        $service = $this->createService();
        $msg = $service->getPriceReplacementMessage();
        $this->assertStringContainsString('login', $msg);
    }

    // ====================================================================
    // isCustomerApproved
    // ====================================================================

    public function testIsCustomerApprovedReturnsTrueWhenApproved(): void
    {
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_APPROVED);

        $service = $this->createService();
        $this->assertTrue($service->isCustomerApproved());
    }

    public function testIsCustomerApprovedReturnsFalseWhenNoAttribute(): void
    {
        // Fail-closed: sem status explícito não deve liberar compra B2B.
        $this->mockLoggedInCustomer(null);

        $service = $this->createService();
        $this->assertFalse($service->isCustomerApproved());
    }

    public function testIsCustomerApprovedReturnsFalseWhenNotLoggedIn(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(false);

        $service = $this->createService();
        $this->assertFalse($service->isCustomerApproved());
    }

    public function testIsCustomerApprovedReturnsFalseWhenPending(): void
    {
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_PENDING);

        $service = $this->createService();
        $this->assertFalse($service->isCustomerApproved());
    }

    // ====================================================================
    // isApprovedPendingErp
    // ====================================================================

    public function testIsApprovedPendingErpReturnsTrueWhenApprovedNoErp(): void
    {
        $this->config->method('hidePriceForNoErp')->willReturn(true);
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_APPROVED, null);
        $this->syncLogResource->method('getErpCodeByMagentoId')->willReturn(null);

        $service = $this->createService();
        $this->assertTrue($service->isApprovedPendingErp());
    }

    public function testIsApprovedPendingErpReturnsFalseWhenHasErpCode(): void
    {
        $this->config->method('hidePriceForNoErp')->willReturn(true);
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_APPROVED, '555');

        $service = $this->createService();
        $this->assertFalse($service->isApprovedPendingErp());
    }

    public function testIsApprovedPendingErpReturnsFalseWhenNotLoggedIn(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(false);

        $service = $this->createService();
        $this->assertFalse($service->isApprovedPendingErp());
    }

    public function testIsApprovedPendingErpReturnsFalseWhenConfigDisabled(): void
    {
        $this->config->method('hidePriceForNoErp')->willReturn(false);
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_APPROVED, null);

        $service = $this->createService();
        $this->assertFalse($service->isApprovedPendingErp());
    }

    // ====================================================================
    // clearCache
    // ====================================================================

    public function testClearCacheResetsInternalCaches(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $service = $this->createService();

        // Call once to set cache
        $this->assertTrue($service->canViewPrices());

        // Clear cache
        $service->clearCache();

        // Should re-evaluate (still true since module is disabled)
        $this->assertTrue($service->canViewPrices());
    }

    // ====================================================================
    // canViewPrices — ERP code via SyncLogResource fallback
    // ====================================================================

    public function testCanViewPricesUsesErpCodeFromSyncLogFallback(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('hidePriceForNoErp')->willReturn(true);
        $this->mockLoggedInCustomer(ApprovalStatus::STATUS_APPROVED, null);

        // erp_code attribute is null, but syncLogResource returns a code
        $this->syncLogResource->method('getErpCodeByMagentoId')
            ->with('customer', 42)
            ->willReturn('999');

        $service = $this->createService();
        $this->assertTrue($service->canViewPrices());
    }

    public function testCanViewPricesUsesHttpContextForApprovedCustomer(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('hidePriceForNoErp')->willReturn(true);
        $this->mockHttpContextLoggedIn(42, ApprovalStatus::STATUS_APPROVED, 'default');
        $this->customerSession->method('isLoggedIn')->willReturn(false);

        $service = $this->createService();
        $this->assertTrue($service->canViewPrices());
    }

    public function testCanViewPricesBlocksApprovedCustomerWithoutErpUsingHttpContext(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('hidePriceForNoErp')->willReturn(true);
        $this->mockHttpContextLoggedIn(42, ApprovalStatus::STATUS_APPROVED, 'logged_in');
        $this->customerSession->method('isLoggedIn')->willReturn(false);

        $service = $this->createService();
        $this->assertFalse($service->canViewPrices());
    }
}
