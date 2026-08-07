<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Model\Quote;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\ErpCodeResolver;
use GrupoAwamotos\B2B\Model\Quote\ErpAuthorizedPriceApplier;
use GrupoAwamotos\ERPIntegration\Helper\Data as ErpHelper;
use GrupoAwamotos\ERPIntegration\Model\CustomerPriceProvider;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\B2B\Model\Quote\ErpAuthorizedPriceApplier
 */
class ErpAuthorizedPriceApplierTest extends TestCase
{
    private Config&MockObject $config;
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private ErpCodeResolver&MockObject $erpCodeResolver;
    private CustomerPriceProvider&MockObject $customerPriceProvider;
    private ErpHelper&MockObject $erpHelper;
    private LoggerInterface&MockObject $logger;
    private ErpAuthorizedPriceApplier $applier;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->erpCodeResolver = $this->createMock(ErpCodeResolver::class);
        $this->customerPriceProvider = $this->createMock(CustomerPriceProvider::class);
        $this->erpHelper = $this->createMock(ErpHelper::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->applier = new ErpAuthorizedPriceApplier(
            $this->config,
            $this->customerRepository,
            $this->erpCodeResolver,
            $this->customerPriceProvider,
            $this->erpHelper,
            $this->logger
        );
    }

    public function testSkipsGuestQuote(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $quote = $this->createPartialMock(Quote::class, ['getId']);
        $quote->setData('customer_id', null);

        $item = $this->createPartialMock(Item::class, ['getParentItem', 'setCustomPrice']);
        $item->method('getParentItem')->willReturn(null);
        $item->expects($this->never())->method('setCustomPrice');

        $this->applier->applyToItem($quote, $item, true);
        $this->addToAssertionCount(1);
    }

    public function testSkipsDefaultNationalList(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->erpHelper->method('getDefaultPriceList')->willReturn(24);
        $this->stubApprovedCustomer(151, 536, 24);

        $quote = $this->createPartialMock(Quote::class, ['getId']);
        $quote->setData('customer_id', 151);

        $item = $this->createPartialMock(Item::class, ['getParentItem', 'setCustomPrice']);
        $item->method('getParentItem')->willReturn(null);
        $item->expects($this->never())->method('setCustomPrice');

        $this->applier->applyToItem($quote, $item, true);
        $this->addToAssertionCount(1);
    }

    public function testStampsCustomPriceForCustomerList(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->erpHelper->method('getDefaultPriceList')->willReturn(24);
        $this->stubApprovedCustomer(151, 536, 6);
        $this->customerPriceProvider->method('getCustomerPrice')->with(536, '502')->willReturn(17.80);

        $product = new class extends Product {
            public bool $superModeSet = false;
            public function __construct()
            {
            }
            public function setIsSuperMode($value)
            {
                $this->superModeSet = (bool) $value;
                return $this;
            }
        };

        $quote = $this->createPartialMock(Quote::class, ['getId']);
        $quote->setData('customer_id', 151);

        $item = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getParentItem',
                'getSku',
                'getProduct',
                'setCustomPrice',
                'getOptionByCode',
                'addOption',
            ])
            ->addMethods([
                'getProductId',
                'setOriginalCustomPrice',
                'hasOriginalCustomPrice',
            ])
            ->getMock();
        $item->method('getParentItem')->willReturn(null);
        $item->method('getSku')->willReturn('502');
        $item->method('getProduct')->willReturn($product);
        $item->method('getProductId')->willReturn(28);
        $item->method('getOptionByCode')->willReturn(null);
        $item->method('hasOriginalCustomPrice')->willReturn(false);
        $item->expects($this->once())->method('setCustomPrice')->with(17.80);
        $item->expects($this->once())->method('setOriginalCustomPrice')->with(17.80);
        $item->expects($this->exactly(2))->method('addOption');

        $this->applier->applyToItem($quote, $item, true);
        $this->assertTrue($product->superModeSet);
    }

    public function testFailClosedWhenAuthorizedPriceMissing(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->erpHelper->method('getDefaultPriceList')->willReturn(24);
        $this->stubApprovedCustomer(151, 536, 6);
        $this->customerPriceProvider->method('getCustomerPrice')->willReturn(null);

        $quote = $this->createPartialMock(Quote::class, ['getId']);
        $quote->setData('customer_id', 151);

        $item = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getParentItem', 'getSku'])
            ->addMethods(['hasOriginalCustomPrice'])
            ->getMock();
        $item->method('getParentItem')->willReturn(null);
        $item->method('getSku')->willReturn('502');
        $item->method('hasOriginalCustomPrice')->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->applier->applyToItem($quote, $item, true);
    }

    private function stubApprovedCustomer(int $customerId, int $erpCode, int $listCode): void
    {
        $approval = $this->createMock(AttributeInterface::class);
        $approval->method('getValue')->willReturn('approved');

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getCustomAttribute')->with('b2b_approval_status')->willReturn($approval);

        $this->customerRepository->method('getById')->with($customerId)->willReturn($customer);
        $this->erpCodeResolver->method('resolveForCustomerId')->willReturn($erpCode);
        $this->customerPriceProvider->method('getCustomerPriceListCode')->with($erpCode)->willReturn($listCode);
    }
}
