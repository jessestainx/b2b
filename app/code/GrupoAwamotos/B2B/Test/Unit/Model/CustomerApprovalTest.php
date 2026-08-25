<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Model;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use GrupoAwamotos\B2B\Model\CustomerApproval;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\Manager as EventManager;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\B2B\Model\CustomerApproval
 */
class CustomerApprovalTest extends TestCase
{
    private CustomerApproval $approval;
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private Config&MockObject $config;
    private ResourceConnection&MockObject $resourceConnection;
    private TransportBuilder&MockObject $transportBuilder;
    private StoreManagerInterface&MockObject $storeManager;
    private DateTime&MockObject $dateTime;
    private LoggerInterface&MockObject $logger;
    private EventManager&MockObject $eventManager;
    private CacheInterface&MockObject $cache;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->transportBuilder = $this->createMock(TransportBuilder::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->eventManager = $this->createMock(EventManager::class);
        $this->cache = $this->createMock(CacheInterface::class);

        // Default: mock ResourceConnection to prevent errors in logAction and in the
        // best-effort internal helpers (syncLegacyB2bCustomerStatus,
        // recalibratePendingAlertCounter), which build a fluent Select query.
        $connection = $this->createMock(AdapterInterface::class);
        $this->resourceConnection->method('getConnection')->willReturn($connection);
        $this->resourceConnection->method('getTableName')->willReturnArgument(0);

        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $select->method('joinInner')->willReturnSelf();

        $connection->method('select')->willReturn($select);
        $connection->method('getTableName')->willReturnArgument(0);
        // No legacy row found by default: syncLegacyB2bCustomerStatus() returns early.
        $connection->method('fetchRow')->willReturn(false);
        $connection->method('fetchOne')->willReturn(0);
        $connection->method('insertOnDuplicate')->willReturn(1);

        $this->approval = new CustomerApproval(
            $this->customerRepository,
            $this->config,
            $this->resourceConnection,
            $this->transportBuilder,
            $this->storeManager,
            $this->dateTime,
            $this->logger,
            $this->eventManager,
            $this->cache
        );
    }

    /**
     * Helper: create a customer mock with optional b2b_approval_status attribute
     */
    private function createCustomerMock(?string $approvalStatus = null, int $groupId = 1): CustomerInterface&MockObject
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getGroupId')->willReturn($groupId);
        $customer->method('getFirstname')->willReturn('João');
        $customer->method('getLastname')->willReturn('Silva');
        $customer->method('getEmail')->willReturn('joao@empresa.com.br');
        $customer->method('getStoreId')->willReturn(1);

        if ($approvalStatus !== null) {
            $attr = $this->createMock(AttributeInterface::class);
            $attr->method('getValue')->willReturn($approvalStatus);
            $customer->method('getCustomAttribute')->willReturnCallback(function (string $code) use ($attr) {
                return $code === 'b2b_approval_status' ? $attr : null;
            });
        } else {
            $customer->method('getCustomAttribute')->willReturn(null);
        }

        $customer->method('setCustomAttribute')->willReturnSelf();
        $customer->method('setGroupId')->willReturnSelf();

        return $customer;
    }

    // ====================================================================
    // setCustomerPending
    // ====================================================================

    public function testSetCustomerPendingSetsStatusAndReturnsTrue(): void
    {
        $customer = $this->createCustomerMock();

        $customer->expects($this->once())
            ->method('setCustomAttribute')
            ->with('b2b_approval_status', ApprovalStatus::STATUS_PENDING);

        $this->customerRepository->method('getById')->with(42)->willReturn($customer);
        $this->customerRepository->expects($this->once())->method('save')->with($customer);

        $this->assertTrue($this->approval->setCustomerPending(42));
    }

    public function testSetCustomerPendingReturnsFalseOnException(): void
    {
        $this->customerRepository->method('getById')
            ->willThrowException(new \Exception('Customer not found'));

        $this->logger->expects($this->once())->method('error');

        $this->assertFalse($this->approval->setCustomerPending(999));
    }

    // ====================================================================
    // approveCustomer
    // ====================================================================

    public function testApproveCustomerSetsApprovedStatusAndReturnsTrue(): void
    {
        $customer = $this->createCustomerMock(ApprovalStatus::STATUS_PENDING);

        $this->customerRepository->method('getById')->with(42)->willReturn($customer);
        $this->customerRepository->expects($this->once())->method('save');

        $this->config->method('getDefaultB2BGroupId')->willReturn(4);
        $this->config->method('sendApprovalEmail')->willReturn(false);

        $this->dateTime->method('gmtDate')->willReturn('2026-02-20 10:00:00');

        $this->eventManager->expects($this->once())
            ->method('dispatch')
            ->with('grupoawamotos_b2b_customer_approved', $this->isType('array'));

        $this->assertTrue($this->approval->approveCustomer(42, 1, 'Aprovado'));
    }

    public function testApproveCustomerAssignsB2BGroupWhenInGeneral(): void
    {
        $customer = $this->createCustomerMock(ApprovalStatus::STATUS_PENDING, 1);

        $customer->expects($this->atLeastOnce())
            ->method('setGroupId')
            ->with(4);

        $this->customerRepository->method('getById')->willReturn($customer);
        $this->customerRepository->method('save');

        $this->config->method('getDefaultB2BGroupId')->willReturn(4);
        $this->config->method('sendApprovalEmail')->willReturn(false);
        $this->dateTime->method('gmtDate')->willReturn('2026-02-20 10:00:00');

        $this->approval->approveCustomer(42, 1);
    }

    public function testApproveCustomerDoesNotChangeGroupIfNotGeneral(): void
    {
        $customer = $this->createCustomerMock(ApprovalStatus::STATUS_PENDING, 5);

        // setGroupId should never be called with 4 since customer already in group 5
        $customer->expects($this->never())->method('setGroupId');

        $this->customerRepository->method('getById')->willReturn($customer);
        $this->customerRepository->method('save');

        $this->config->method('getDefaultB2BGroupId')->willReturn(4);
        $this->config->method('sendApprovalEmail')->willReturn(false);
        $this->dateTime->method('gmtDate')->willReturn('2026-02-20 10:00:00');

        $this->approval->approveCustomer(42);
    }

    public function testApproveCustomerSendsEmailWhenConfigured(): void
    {
        $customer = $this->createCustomerMock(ApprovalStatus::STATUS_PENDING);

        $this->customerRepository->method('getById')->willReturn($customer);
        $this->customerRepository->method('save');

        $this->config->method('getDefaultB2BGroupId')->willReturn(0);
        $this->config->method('sendApprovalEmail')->willReturn(true);
        $this->dateTime->method('gmtDate')->willReturn('2026-02-20 10:00:00');

        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getName')->willReturn('AWA Motos');
        $store->method('getBaseUrl')->willReturn('https://awamotos.com.br/');
        $this->storeManager->method('getStore')->willReturn($store);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('sendMessage');

        $this->transportBuilder->method('setTemplateIdentifier')->willReturnSelf();
        $this->transportBuilder->method('setTemplateOptions')->willReturnSelf();
        $this->transportBuilder->method('setTemplateVars')->willReturnSelf();
        $this->transportBuilder->method('setFromByScope')->willReturnSelf();
        $this->transportBuilder->method('addTo')->willReturnSelf();
        $this->transportBuilder->method('getTransport')->willReturn($transport);

        $this->assertTrue($this->approval->approveCustomer(42));
    }

    public function testApproveCustomerReturnsFalseOnException(): void
    {
        $this->customerRepository->method('getById')
            ->willThrowException(new \Exception('DB error'));

        $this->assertFalse($this->approval->approveCustomer(999));
    }

    // ====================================================================
    // rejectCustomer
    // ====================================================================

    public function testRejectCustomerSetsRejectedStatusAndSendsEmail(): void
    {
        $customer = $this->createCustomerMock(ApprovalStatus::STATUS_PENDING);

        $this->customerRepository->method('getById')->willReturn($customer);
        $this->customerRepository->expects($this->once())->method('save');

        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getName')->willReturn('AWA Motos');
        $store->method('getBaseUrl')->willReturn('https://awamotos.com.br/');
        $this->storeManager->method('getStore')->willReturn($store);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('sendMessage');

        $this->transportBuilder->method('setTemplateIdentifier')->willReturnSelf();
        $this->transportBuilder->method('setTemplateOptions')->willReturnSelf();
        $this->transportBuilder->method('setTemplateVars')->willReturnSelf();
        $this->transportBuilder->method('setFromByScope')->willReturnSelf();
        $this->transportBuilder->method('addTo')->willReturnSelf();
        $this->transportBuilder->method('getTransport')->willReturn($transport);

        $this->assertTrue($this->approval->rejectCustomer(42, 1, 'Documentação irregular'));
    }

    public function testRejectCustomerReturnsFalseOnException(): void
    {
        $this->customerRepository->method('getById')
            ->willThrowException(new \Exception('Error'));

        $this->assertFalse($this->approval->rejectCustomer(999, 1, 'Motivo'));
    }

    // ====================================================================
    // suspendCustomer
    // ====================================================================

    public function testSuspendCustomerSetsSuspendedStatus(): void
    {
        $customer = $this->createCustomerMock(ApprovalStatus::STATUS_APPROVED);

        $this->customerRepository->method('getById')->willReturn($customer);
        $this->customerRepository->expects($this->once())->method('save');

        $this->assertTrue($this->approval->suspendCustomer(42, 1, 'Inadimplência'));
    }

    public function testSuspendCustomerReturnsFalseOnException(): void
    {
        $this->customerRepository->method('getById')
            ->willThrowException(new \Exception('Error'));

            $this->assertFalse($this->approval->suspendCustomer(999));
    }

    public function testRequestDataReviewSetsDataReviewStatus(): void
    {
        $customer = $this->createCustomerMock(ApprovalStatus::STATUS_PENDING);

        $this->customerRepository->method('getById')->willReturn($customer);
        $this->customerRepository->expects($this->once())->method('save');
        $this->eventManager->expects($this->once())
            ->method('dispatch')
            ->with('grupoawamotos_b2b_customer_data_review_requested', $this->arrayHasKey('customer_id'));

        $this->assertTrue($this->approval->requestDataReview(42, 1, 'Envie o contrato social'));
    }

    public function testRequestDataReviewReturnsFalseOnException(): void
    {
        $this->customerRepository->method('getById')
            ->willThrowException(new \Exception('Error'));

        $this->assertFalse($this->approval->requestDataReview(999, 1, 'x'));
    }

    // ====================================================================
    // getApprovalStatus
    // ====================================================================

    public function testGetApprovalStatusReturnsStatus(): void
    {
        $customer = $this->createCustomerMock(ApprovalStatus::STATUS_APPROVED);
        $this->customerRepository->method('getById')->with(42)->willReturn($customer);

        $this->assertSame(ApprovalStatus::STATUS_APPROVED, $this->approval->getApprovalStatus(42));
    }

    public function testGetApprovalStatusReturnsNullWhenNoAttribute(): void
    {
        $customer = $this->createCustomerMock(null);
        $this->customerRepository->method('getById')->with(42)->willReturn($customer);

        $this->assertNull($this->approval->getApprovalStatus(42));
    }

    public function testGetApprovalStatusReturnsNullOnException(): void
    {
        $this->customerRepository->method('getById')
            ->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException());

        $this->assertNull($this->approval->getApprovalStatus(999));
    }

    // ====================================================================
    // isApproved
    // ====================================================================

    public function testIsApprovedReturnsTrueWhenApproved(): void
    {
        $customer = $this->createCustomerMock(ApprovalStatus::STATUS_APPROVED);
        $this->customerRepository->method('getById')->willReturn($customer);

        $this->assertTrue($this->approval->isApproved(42));
    }

    public function testIsApprovedReturnsFalseWhenNoStatus(): void
    {
        // Fail-closed: customers without an explicit approval status must not
        // be treated as approved B2B buyers (see CustomerApproval::isApproved()).
        $customer = $this->createCustomerMock(null);
        $this->customerRepository->method('getById')->willReturn($customer);

        $this->assertFalse($this->approval->isApproved(42));
    }

    public function testIsApprovedReturnsFalseWhenPending(): void
    {
        $customer = $this->createCustomerMock(ApprovalStatus::STATUS_PENDING);
        $this->customerRepository->method('getById')->willReturn($customer);

        $this->assertFalse($this->approval->isApproved(42));
    }

    public function testIsApprovedReturnsFalseWhenRejected(): void
    {
        $customer = $this->createCustomerMock(ApprovalStatus::STATUS_REJECTED);
        $this->customerRepository->method('getById')->willReturn($customer);

        $this->assertFalse($this->approval->isApproved(42));
    }

    public function testIsApprovedReturnsFalseWhenSuspended(): void
    {
        $customer = $this->createCustomerMock(ApprovalStatus::STATUS_SUSPENDED);
        $this->customerRepository->method('getById')->willReturn($customer);

        $this->assertFalse($this->approval->isApproved(42));
    }

    // ====================================================================
    // notifyAdminNewCustomer
    // ====================================================================

    public function testNotifyAdminReturnsFalseWhenNoAdminEmail(): void
    {
        $customer = $this->createCustomerMock();
        $this->customerRepository->method('getById')->willReturn($customer);
        $this->config->method('getAdminEmail')->willReturn('');

        $this->assertFalse($this->approval->notifyAdminNewCustomer(42));
    }

    public function testNotifyAdminSendsEmailSuccessfully(): void
    {
        $customer = $this->createCustomerMock();
        $this->customerRepository->method('getById')->willReturn($customer);
        $this->config->method('getAdminEmail')->willReturn('awamotos@awamotos.com.br');

        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getName')->willReturn('AWA Motos');
        $store->method('getBaseUrl')->willReturn('https://awamotos.com.br/');
        $this->storeManager->method('getStore')->willReturn($store);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('sendMessage');

        $this->transportBuilder->method('setTemplateIdentifier')->willReturnSelf();
        $this->transportBuilder->method('setTemplateOptions')->willReturnSelf();
        $this->transportBuilder->method('setTemplateVars')->willReturnSelf();
        $this->transportBuilder->method('setFromByScope')->willReturnSelf();
        $this->transportBuilder->method('addTo')->willReturnSelf();
        $this->transportBuilder->method('getTransport')->willReturn($transport);

        $this->assertTrue($this->approval->notifyAdminNewCustomer(42));
    }

    public function testNotifyAdminReturnsFalseOnException(): void
    {
        $this->customerRepository->method('getById')
            ->willThrowException(new \Exception('Error'));

        $this->assertFalse($this->approval->notifyAdminNewCustomer(42));
    }

    // ====================================================================
    // sendApprovalEmail
    // ====================================================================

    public function testSendApprovalEmailReturnsTrue(): void
    {
        $customer = $this->createCustomerMock();
        $this->customerRepository->method('getById')->willReturn($customer);

        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getName')->willReturn('AWA Motos');
        $store->method('getBaseUrl')->willReturn('https://awamotos.com.br/');
        $this->storeManager->method('getStore')->willReturn($store);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('sendMessage');

        $this->transportBuilder->method('setTemplateIdentifier')->willReturnSelf();
        $this->transportBuilder->method('setTemplateOptions')->willReturnSelf();
        $this->transportBuilder->method('setTemplateVars')->willReturnSelf();
        $this->transportBuilder->method('setFromByScope')->willReturnSelf();
        $this->transportBuilder->method('addTo')->willReturnSelf();
        $this->transportBuilder->method('getTransport')->willReturn($transport);

        $this->assertTrue($this->approval->sendApprovalEmail(42));
    }

    public function testSendApprovalEmailReturnsFalseOnException(): void
    {
        $this->customerRepository->method('getById')
            ->willThrowException(new \Exception('Error'));

        $this->assertFalse($this->approval->sendApprovalEmail(42));
    }

    // ====================================================================
    // sendRejectionEmail
    // ====================================================================

    public function testSendRejectionEmailReturnsTrueWithReason(): void
    {
        $customer = $this->createCustomerMock();
        $this->customerRepository->method('getById')->willReturn($customer);

        $store = $this->createMock(\Magento\Store\Model\Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getName')->willReturn('AWA Motos');
        $store->method('getBaseUrl')->willReturn('https://awamotos.com.br/');
        $this->storeManager->method('getStore')->willReturn($store);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('sendMessage');

        $this->transportBuilder->method('setTemplateIdentifier')->willReturnSelf();
        $this->transportBuilder->method('setTemplateOptions')->willReturnSelf();
        $this->transportBuilder->method('setTemplateVars')->willReturnSelf();
        $this->transportBuilder->method('setFromByScope')->willReturnSelf();
        $this->transportBuilder->method('addTo')->willReturnSelf();
        $this->transportBuilder->method('getTransport')->willReturn($transport);

        $this->assertTrue($this->approval->sendRejectionEmail(42, 'Documentação incompleta'));
    }

    public function testSendRejectionEmailReturnsFalseOnException(): void
    {
        $this->customerRepository->method('getById')
            ->willThrowException(new \Exception('Error'));

        $this->assertFalse($this->approval->sendRejectionEmail(42));
    }
}
