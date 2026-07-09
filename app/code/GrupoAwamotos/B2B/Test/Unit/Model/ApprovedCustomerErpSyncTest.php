<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Model;

use GrupoAwamotos\B2B\Model\ApprovedCustomerErpSync;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ErpCustomerSyncStatus;
use GrupoAwamotos\B2B\Model\CustomerCnpjResolver;
use GrupoAwamotos\B2B\Model\ErpIntegration;
use GrupoAwamotos\B2B\Model\Sectra\ProspectPipeline;
use GrupoAwamotos\ERPIntegration\Helper\Data as ErpHelper;
use GrupoAwamotos\ERPIntegration\Model\B2BClientRegistration;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Api\AttributeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ApprovedCustomerErpSyncTest extends TestCase
{
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private ErpIntegration&MockObject $erpIntegration;
    private CustomerCnpjResolver&MockObject $cnpjResolver;
    private ProspectPipeline&MockObject $prospectPipeline;
    private ApprovedCustomerErpSync $service;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->erpIntegration = $this->createMock(ErpIntegration::class);
        $this->cnpjResolver = $this->createMock(CustomerCnpjResolver::class);
        $this->prospectPipeline = $this->createMock(ProspectPipeline::class);

        $scopeConfig = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(true);

        $this->service = new ApprovedCustomerErpSync(
            $this->customerRepository,
            $this->erpIntegration,
            $this->cnpjResolver,
            $this->createMock(B2BClientRegistration::class),
            $scopeConfig,
            $this->createMock(ErpHelper::class),
            $this->createMock(SyncLogResource::class),
            $this->prospectPipeline,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testApprovedCustomerWithoutErpGetsPullOrderStatus(): void
    {
        $customer = $this->createApprovedCustomer(8905);
        $this->customerRepository->method('getById')->willReturn($customer);
        // Note: persistence for the "pull order" pipeline path now belongs to
        // ProspectPipeline (mocked below), not to ApprovedCustomerErpSync itself.
        $this->customerRepository->expects($this->never())->method('save');

        $this->cnpjResolver->method('resolveWithSource')->willReturn([
            'digits' => '66437059000150',
            'source' => 'b2b_cnpj',
        ]);
        $this->cnpjResolver->method('isValidCnpj')->willReturn(true);
        $this->erpIntegration->method('getErpCodeForCustomer')->willReturn(null);
        $this->erpIntegration->method('findErpCustomerByCnpj')->willReturn(null);
        $this->prospectPipeline->method('processApprovedCustomer')->with(8905)->willReturn([
            'success' => true,
            'erp_customer_sync_status' => ErpCustomerSyncStatus::NOT_APPLICABLE_PULL_ORDER,
            'message' => 'Aguardando pedido para integração via pull Sectra.',
        ]);

        $result = $this->service->syncApprovedCustomer(8905);

        $this->assertTrue($result['success']);
        $this->assertSame(ApprovedCustomerErpSync::ACTION_NOT_APPLICABLE_PULL_ORDER, $result['action']);
        $this->assertSame(ErpCustomerSyncStatus::NOT_APPLICABLE_PULL_ORDER, $result['erp_customer_sync_status']);
        $this->assertFalse($result['last_sync_at_updated']);
        $this->assertSame('Aguardando pedido para integração via pull Sectra.', $result['message']);
    }

    private function createApprovedCustomer(int $id): CustomerInterface&MockObject
    {
        $status = $this->createMock(AttributeInterface::class);
        $status->method('getValue')->willReturn(ApprovalStatus::STATUS_APPROVED);

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn($id);
        $customer->method('getGroupId')->willReturn(6);
        $customer->method('getCustomAttribute')->willReturnCallback(
            static fn (string $code) => $code === 'b2b_approval_status' ? $status : null
        );

        return $customer;
    }
}
