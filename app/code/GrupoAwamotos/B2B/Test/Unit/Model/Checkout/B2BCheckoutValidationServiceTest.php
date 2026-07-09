<?php

/**
 * Test for B2BCheckoutValidationService
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Checkout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use GrupoAwamotos\B2B\Helper\Config as B2BConfig;
use Psr\Log\LoggerInterface;

class B2BCheckoutValidationServiceTest extends TestCase
{
    private B2BCheckoutValidationService $validationService;
    private B2BConfig $configMock;
    private LoggerInterface $loggerMock;
    private Quote $quoteMock;
    private PaymentInterface $paymentMock;

    protected function setUp(): void
    {
        $this->configMock = $this->createMock(B2BConfig::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        // Mocked against the concrete Quote model (not CartInterface) because
        // getData() is a DataObject method, not part of the API interface,
        // yet it's how the real object is always accessed in production.
        $this->quoteMock = $this->createMock(Quote::class);
        $this->paymentMock = $this->createMock(PaymentInterface::class);

        $this->validationService = new B2BCheckoutValidationService(
            $this->configMock,
            $this->loggerMock
        );
    }

    /**
     * Test delivery date validation - required and missing
     */
    public function testValidateDeliveryDateRequired(): void
    {
        $this->configMock->expects($this->any())->method('isDeliveryDateEnabled')->willReturn(true);
        $this->configMock->expects($this->any())->method('isDeliveryDateRequired')->willReturn(true);

        $this->quoteMock->expects($this->any())->method('getData')->willReturn(null);

        $this->expectException(LocalizedException::class);
        $this->validationService->validateCheckoutData($this->quoteMock, $this->paymentMock);
    }

    /**
     * Test delivery date validation - invalid format
     */
    public function testValidateDeliveryDateInvalidFormat(): void
    {
        $this->configMock->expects($this->any())->method('isDeliveryDateEnabled')->willReturn(true);
        $this->configMock->expects($this->any())->method('isDeliveryDateRequired')->willReturn(false);

        $this->quoteMock->expects($this->any())->method('getData')->with('b2b_delivery_date')
            ->willReturn('invalid-date');

        $this->expectException(LocalizedException::class);
        $this->validationService->validateCheckoutData($this->quoteMock, $this->paymentMock);
    }

    /**
     * Test delivery date validation - past date
     */
    public function testValidateDeliveryDatePastDate(): void
    {
        $this->configMock->expects($this->any())->method('isDeliveryDateEnabled')->willReturn(true);
        $this->configMock->expects($this->any())->method('isDeliveryDateRequired')->willReturn(false);

        $pastDate = (new \DateTime('yesterday'))->format('Y-m-d');
        $this->quoteMock->expects($this->any())->method('getData')->with('b2b_delivery_date')
            ->willReturn($pastDate);

        $this->expectException(LocalizedException::class);
        $this->validationService->validateCheckoutData($this->quoteMock, $this->paymentMock);
    }

    /**
     * Test order notes validation - max length exceeded
     */
    public function testValidateOrderNotesMaxLength(): void
    {
        $this->configMock->expects($this->any())->method('isDeliveryDateEnabled')->willReturn(false);
        $this->configMock->expects($this->any())->method('isOrderNotesEnabled')->willReturn(true);
        $this->configMock->expects($this->any())->method('isOrderNotesRequired')->willReturn(false);

        $longNotes = str_repeat('a', 501); // Exceeds max of 500
        $this->quoteMock->expects($this->any())->method('getData')->with('b2b_order_notes')
            ->willReturn($longNotes);

        $this->expectException(LocalizedException::class);
        $this->validationService->validateCheckoutData($this->quoteMock, $this->paymentMock);
    }

    /**
     * Test successful validation with disabled fields
     */
    public function testValidateSuccessfulWithDisabledFields(): void
    {
        $this->configMock->expects($this->any())->method('isDeliveryDateEnabled')->willReturn(false);
        $this->configMock->expects($this->any())->method('isOrderNotesEnabled')->willReturn(false);
        $this->configMock->expects($this->any())->method('isPoNumberEnabled')->willReturn(false);

        // Should not throw exception
        $this->validationService->validateCheckoutData($this->quoteMock, $this->paymentMock);
        $this->assertTrue(true);
    }

    /**
     * Test error message retrieval for delivery date
     */
    public function testGetDeliveryDateErrors(): void
    {
        $errors = $this->validationService->getDeliveryDateErrors();

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('required', $errors);
        $this->assertArrayHasKey('invalid_format', $errors);
        $this->assertArrayHasKey('past_date', $errors);
    }

    /**
     * Test error message retrieval for order notes
     */
    public function testGetOrderNotesErrors(): void
    {
        $errors = $this->validationService->getOrderNotesErrors();

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('required', $errors);
        $this->assertArrayHasKey('max_length', $errors);
    }

    /**
     * Test error message retrieval for PO number
     */
    public function testGetPoNumberErrors(): void
    {
        $errors = $this->validationService->getPoNumberErrors();

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('required', $errors);
        $this->assertArrayHasKey('max_length', $errors);
        $this->assertArrayHasKey('invalid_chars', $errors);
    }
}
