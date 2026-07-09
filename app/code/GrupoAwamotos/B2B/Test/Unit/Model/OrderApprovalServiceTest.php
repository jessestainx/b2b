<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Model;

use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use GrupoAwamotos\B2B\Model\CompanyService;
use GrupoAwamotos\B2B\Model\OrderApproval;
use GrupoAwamotos\B2B\Model\OrderApprovalFactory;
use GrupoAwamotos\B2B\Model\OrderApprovalService;
use GrupoAwamotos\B2B\Model\ResourceModel\OrderApproval as OrderApprovalResource;
use GrupoAwamotos\B2B\Model\ResourceModel\OrderApproval\CollectionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\OrderApproval\Collection;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\B2B\Model\OrderApprovalService
 */
class OrderApprovalServiceTest extends TestCase
{
    private OrderApprovalService $service;
    private OrderRepositoryInterface&MockObject $orderRepository;
    private CustomerSession&MockObject $customerSession;
    private OrderApprovalFactory&MockObject $approvalFactory;
    private OrderApprovalResource&MockObject $approvalResource;
    private CollectionFactory&MockObject $collectionFactory;
    private B2BHelper&MockObject $b2bHelper;
    private LoggerInterface&MockObject $logger;
    private CompanyService&MockObject $companyService;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->approvalFactory = $this->createMock(OrderApprovalFactory::class);
        $this->approvalResource = $this->createMock(OrderApprovalResource::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->b2bHelper = $this->createMock(B2BHelper::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->companyService = $this->createMock(CompanyService::class);

        // Default: requester and approver belong to the same company, so the
        // cross-company guard (assertApproverBelongsToCompany) does not
        // interfere with tests that aren't specifically exercising it.
        $this->companyService->method('getCompanyIdsForCustomer')->willReturn([1]);

        $this->service = new OrderApprovalService(
            $this->orderRepository,
            $this->customerSession,
            $this->approvalFactory,
            $this->approvalResource,
            $this->collectionFactory,
            $this->b2bHelper,
            $this->logger,
            $this->companyService
        );
    }

    // ====================================================================
    // determineRequiredLevel — lógica pura baseada em thresholds
    // ====================================================================

    public function testDetermineRequiredLevelDirector(): void
    {
        $this->b2bHelper->method('getThresholdDirector')->willReturn(50000.0);
        $this->b2bHelper->method('getThresholdFinance')->willReturn(10000.0);
        $this->b2bHelper->method('getThresholdManager')->willReturn(2000.0);

        $this->assertSame(OrderApproval::LEVEL_DIRECTOR, $this->service->determineRequiredLevel(50000.0));
    }

    public function testDetermineRequiredLevelDirectorAboveThreshold(): void
    {
        $this->b2bHelper->method('getThresholdDirector')->willReturn(50000.0);
        $this->b2bHelper->method('getThresholdFinance')->willReturn(10000.0);
        $this->b2bHelper->method('getThresholdManager')->willReturn(2000.0);

        $this->assertSame(OrderApproval::LEVEL_DIRECTOR, $this->service->determineRequiredLevel(100000.0));
    }

    public function testDetermineRequiredLevelFinance(): void
    {
        $this->b2bHelper->method('getThresholdDirector')->willReturn(50000.0);
        $this->b2bHelper->method('getThresholdFinance')->willReturn(10000.0);
        $this->b2bHelper->method('getThresholdManager')->willReturn(2000.0);

        $this->assertSame(OrderApproval::LEVEL_FINANCE, $this->service->determineRequiredLevel(10000.0));
    }

    public function testDetermineRequiredLevelFinanceBelowDirector(): void
    {
        $this->b2bHelper->method('getThresholdDirector')->willReturn(50000.0);
        $this->b2bHelper->method('getThresholdFinance')->willReturn(10000.0);
        $this->b2bHelper->method('getThresholdManager')->willReturn(2000.0);

        $this->assertSame(OrderApproval::LEVEL_FINANCE, $this->service->determineRequiredLevel(49999.99));
    }

    public function testDetermineRequiredLevelManager(): void
    {
        $this->b2bHelper->method('getThresholdDirector')->willReturn(50000.0);
        $this->b2bHelper->method('getThresholdFinance')->willReturn(10000.0);
        $this->b2bHelper->method('getThresholdManager')->willReturn(2000.0);

        $this->assertSame(OrderApproval::LEVEL_MANAGER, $this->service->determineRequiredLevel(2000.0));
    }

    public function testDetermineRequiredLevelManagerBelowFinance(): void
    {
        $this->b2bHelper->method('getThresholdDirector')->willReturn(50000.0);
        $this->b2bHelper->method('getThresholdFinance')->willReturn(10000.0);
        $this->b2bHelper->method('getThresholdManager')->willReturn(2000.0);

        $this->assertSame(OrderApproval::LEVEL_MANAGER, $this->service->determineRequiredLevel(9999.99));
    }

    public function testDetermineRequiredLevelBuyer(): void
    {
        $this->b2bHelper->method('getThresholdDirector')->willReturn(50000.0);
        $this->b2bHelper->method('getThresholdFinance')->willReturn(10000.0);
        $this->b2bHelper->method('getThresholdManager')->willReturn(2000.0);

        $this->assertSame(OrderApproval::LEVEL_BUYER, $this->service->determineRequiredLevel(1999.99));
    }

    public function testDetermineRequiredLevelBuyerZero(): void
    {
        $this->b2bHelper->method('getThresholdDirector')->willReturn(50000.0);
        $this->b2bHelper->method('getThresholdFinance')->willReturn(10000.0);
        $this->b2bHelper->method('getThresholdManager')->willReturn(2000.0);

        $this->assertSame(OrderApproval::LEVEL_BUYER, $this->service->determineRequiredLevel(0.0));
    }

    public function testDetermineRequiredLevelWhenDirectorThresholdDisabled(): void
    {
        $this->b2bHelper->method('getThresholdDirector')->willReturn(0.0);
        $this->b2bHelper->method('getThresholdFinance')->willReturn(10000.0);
        $this->b2bHelper->method('getThresholdManager')->willReturn(2000.0);

        // Even 100k should not trigger director if threshold is 0
        $this->assertSame(OrderApproval::LEVEL_FINANCE, $this->service->determineRequiredLevel(100000.0));
    }

    public function testDetermineRequiredLevelWhenAllThresholdsDisabled(): void
    {
        $this->b2bHelper->method('getThresholdDirector')->willReturn(0.0);
        $this->b2bHelper->method('getThresholdFinance')->willReturn(0.0);
        $this->b2bHelper->method('getThresholdManager')->willReturn(0.0);

        $this->assertSame(OrderApproval::LEVEL_BUYER, $this->service->determineRequiredLevel(100000.0));
    }

    // ====================================================================
    // createApprovalRequest
    // ====================================================================

    public function testCreateApprovalRequestCreatesAndHoldsOrder(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getCustomerId')->willReturn(42);
        $order->method('getGrandTotal')->willReturn(15000.0);
        $order->expects($this->once())->method('hold');
        $order->method('addCommentToStatusHistory')->willReturnSelf();

        $this->orderRepository->method('get')->with(1001)->willReturn($order);
        $this->orderRepository->expects($this->once())->method('save')->with($order);

        $approval = $this->getMockBuilder(OrderApproval::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setData'])
            ->getMock();
        $approval->expects($this->once())->method('setData')->with($this->callback(function (array $data) {
            return $data['order_id'] === 1001
                && $data['customer_id'] === 42
                && $data['status'] === OrderApproval::STATUS_PENDING
                && $data['current_level'] === OrderApproval::LEVEL_BUYER
                && $data['required_level'] === OrderApproval::LEVEL_FINANCE;
        }))->willReturnSelf();

        $this->approvalFactory->method('create')->willReturn($approval);
        $this->approvalResource->expects($this->once())->method('save')->with($approval);

        $result = $this->service->createApprovalRequest(1001, OrderApproval::LEVEL_FINANCE);
        $this->assertSame($approval, $result);
    }

    // ====================================================================
    // approve
    // ====================================================================

    public function testApproveThrowsWhenNotFound(): void
    {
        $approval = $this->getMockBuilder(OrderApproval::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $approval->method('getId')->willReturn(null);

        $this->approvalFactory->method('create')->willReturn($approval);

        $this->expectException(LocalizedException::class);
        $this->service->approve(999, 1);
    }

    public function testApproveThrowsWhenAlreadyProcessed(): void
    {
        $approval = $this->getMockBuilder(OrderApproval::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getData'])
            ->getMock();
        $approval->method('getId')->willReturn(1);
        $approval->method('getData')->willReturnCallback(function (string $key) {
            return match ($key) {
                'status' => OrderApproval::STATUS_APPROVED,
                default => null,
            };
        });

        $this->approvalFactory->method('create')->willReturn($approval);

        $this->expectException(LocalizedException::class);
        $this->service->approve(1, 10);
    }

    public function testApproveThrowsAuthorizationExceptionWhenApproverBelongsToDifferentCompany(): void
    {
        $approval = $this->getMockBuilder(OrderApproval::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getData'])
            ->getMock();
        $approval->method('getId')->willReturn(1);
        $approval->method('getData')->willReturnCallback(function (string $key) {
            return match ($key) {
                'status' => OrderApproval::STATUS_PENDING,
                'customer_id' => 42,
                default => null,
            };
        });

        $this->approvalFactory->method('create')->willReturn($approval);

        // Requester (customer 42) and approver (customer 99) belong to different companies,
        // with no overlap — even accounting for multi-empresa (multiple companies per customer).
        $this->companyService = $this->createMock(CompanyService::class);
        $this->companyService->method('getCompanyIdsForCustomer')->willReturnMap([
            [42, [1]],
            [99, [2]],
        ]);

        $this->service = new OrderApprovalService(
            $this->orderRepository,
            $this->customerSession,
            $this->approvalFactory,
            $this->approvalResource,
            $this->collectionFactory,
            $this->b2bHelper,
            $this->logger,
            $this->companyService
        );

        $this->expectException(AuthorizationException::class);
        $this->service->approve(1, 99);
    }

    public function testApproveAdvancesToNextLevelWhenNotFullyApproved(): void
    {
        $approval = $this->getMockBuilder(OrderApproval::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getData', 'setData', 'getNextLevel'])
            ->getMock();

        $approval->method('getId')->willReturn(1);
        $approval->method('getData')->willReturnCallback(function (?string $key = null) {
            return match ($key) {
                'status' => OrderApproval::STATUS_PENDING,
                'current_level' => OrderApproval::LEVEL_BUYER,
                'required_level' => OrderApproval::LEVEL_FINANCE,
                'approval_history' => '[]',
                'order_id' => 1001,
                default => null,
            };
        });
        $approval->method('getNextLevel')
            ->with(OrderApproval::LEVEL_BUYER)
            ->willReturn(OrderApproval::LEVEL_MANAGER);

        $approval->method('setData')->willReturnSelf();

        $this->approvalFactory->method('create')->willReturn($approval);
        $this->approvalResource->expects($this->atLeastOnce())->method('save');

        $result = $this->service->approve(1, 10, 'Aprovado pelo comprador');
        $this->assertTrue($result);
    }

    public function testApproveFullyApprovesAndReleasesOrder(): void
    {
        $approval = $this->getMockBuilder(OrderApproval::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getData', 'setData', 'getNextLevel'])
            ->getMock();

        $approval->method('getId')->willReturn(1);
        $approval->method('getData')->willReturnCallback(function (?string $key = null) {
            return match ($key) {
                'status' => OrderApproval::STATUS_PENDING,
                'current_level' => OrderApproval::LEVEL_FINANCE,
                'required_level' => OrderApproval::LEVEL_FINANCE,
                'approval_history' => '[]',
                'order_id' => 1001,
                default => null,
            };
        });
        // getNextLevel(3) = 4 (DIRECTOR), which is > required (3) → fully approved
        $approval->method('getNextLevel')
            ->with(OrderApproval::LEVEL_FINANCE)
            ->willReturn(OrderApproval::LEVEL_DIRECTOR);

        $approval->method('setData')->willReturnSelf();

        $this->approvalFactory->method('create')->willReturn($approval);
        $this->approvalResource->expects($this->atLeastOnce())->method('save');

        // Order should be released from hold
        $order = $this->createMock(Order::class);
        $order->expects($this->once())->method('unhold');
        $order->method('addCommentToStatusHistory')->willReturnSelf();
        $this->orderRepository->method('get')->with(1001)->willReturn($order);

        $result = $this->service->approve(1, 10);
        $this->assertTrue($result);
    }

    public function testApproveFullyApprovesWhenNoNextLevel(): void
    {
        $approval = $this->getMockBuilder(OrderApproval::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getData', 'setData', 'getNextLevel'])
            ->getMock();

        $approval->method('getId')->willReturn(1);
        $approval->method('getData')->willReturnCallback(function (?string $key = null) {
            return match ($key) {
                'status' => OrderApproval::STATUS_PENDING,
                'current_level' => OrderApproval::LEVEL_DIRECTOR,
                'required_level' => OrderApproval::LEVEL_DIRECTOR,
                'approval_history' => '[]',
                'order_id' => 1001,
                default => null,
            };
        });
        $approval->method('getNextLevel')
            ->with(OrderApproval::LEVEL_DIRECTOR)
            ->willReturn(null); // No next level

        $approval->method('setData')->willReturnSelf();

        $this->approvalFactory->method('create')->willReturn($approval);

        $order = $this->createMock(Order::class);
        $order->method('unhold')->willReturnSelf();
        $order->method('addCommentToStatusHistory')->willReturnSelf();
        $this->orderRepository->method('get')->with(1001)->willReturn($order);

        $result = $this->service->approve(1, 10);
        $this->assertTrue($result);
    }

    // ====================================================================
    // reject
    // ====================================================================

    public function testRejectThrowsWhenNotFound(): void
    {
        $approval = $this->getMockBuilder(OrderApproval::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $approval->method('getId')->willReturn(null);

        $this->approvalFactory->method('create')->willReturn($approval);

        $this->expectException(LocalizedException::class);
        $this->service->reject(999, 1, 'Pedido não autorizado');
    }

    public function testRejectSetsStatusAndCancelsOrder(): void
    {
        $approval = $this->getMockBuilder(OrderApproval::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getData', 'setData'])
            ->getMock();

        $approval->method('getId')->willReturn(1);
        $approval->method('getData')->willReturnCallback(function (?string $key = null) {
            return match ($key) {
                'current_level' => OrderApproval::LEVEL_MANAGER,
                'approval_history' => '[]',
                'order_id' => 1001,
                default => null,
            };
        });
        $approval->method('setData')->willReturnSelf();

        $this->approvalFactory->method('create')->willReturn($approval);
        $this->approvalResource->expects($this->once())->method('save');

        $order = $this->createMock(Order::class);
        $order->method('canCancel')->willReturn(true);
        $order->expects($this->once())->method('cancel')->willReturnSelf();
        $order->method('addCommentToStatusHistory')->willReturnSelf();
        $this->orderRepository->method('get')->with(1001)->willReturn($order);

        $result = $this->service->reject(1, 10, 'Valor não autorizado');
        $this->assertTrue($result);
    }

    // ====================================================================
    // getByOrderId
    // ====================================================================

    public function testGetByOrderIdReturnsApproval(): void
    {
        $approval = $this->getMockBuilder(OrderApproval::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $approval->method('getId')->willReturn(1);

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($approval);
        $this->collectionFactory->method('create')->willReturn($collection);

        $result = $this->service->getByOrderId(1001);
        $this->assertSame($approval, $result);
    }

    public function testGetByOrderIdReturnsNullWhenNotFound(): void
    {
        $approval = $this->getMockBuilder(OrderApproval::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $approval->method('getId')->willReturn(null);

        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($approval);
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->assertNull($this->service->getByOrderId(9999));
    }

    // ====================================================================
    // getPendingApprovals
    // ====================================================================

    public function testGetPendingApprovalsReturnsFilteredCollection(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('filterPending')->willReturnSelf();
        $collection->expects($this->once())->method('filterByLevel')
            ->with(OrderApproval::LEVEL_MANAGER)->willReturnSelf();
        $collection->expects($this->never())->method('filterByCustomer');

        $this->collectionFactory->method('create')->willReturn($collection);

        $result = $this->service->getPendingApprovals(OrderApproval::LEVEL_MANAGER);
        $this->assertSame($collection, $result);
    }

    public function testGetPendingApprovalsWithCustomerFilter(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('filterPending')->willReturnSelf();
        $collection->method('filterByLevel')->willReturnSelf();
        $collection->expects($this->once())->method('filterByCustomer')->with(42)->willReturnSelf();

        $this->collectionFactory->method('create')->willReturn($collection);

        $this->service->getPendingApprovals(OrderApproval::LEVEL_MANAGER, 42);
    }
}
