<?php

declare(strict_types=1);

namespace GrupoAwamotos\BrazilCustomer\Plugin\Checkout;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use GrupoAwamotos\BrazilCustomer\Model\Validator\Cpf as CpfValidator;
use GrupoAwamotos\BrazilCustomer\Model\Validator\Cnpj as CnpjValidator;
use Psr\Log\LoggerInterface;

/**
 * Persists Brazilian custom attributes (CPF, CNPJ, etc.) from checkout to customer entity
 */
class SaveBrazilFieldsPlugin
{
    private const BRAZIL_ATTRIBUTES = [
        'person_type',
        'cpf',
        'rg',
        'cnpj',
        'ie',
        'company_name',
        'trade_name',
    ];

    private CustomerRepositoryInterface $customerRepository;
    private CustomerSession $customerSession;
    private LoggerInterface $logger;
    private CpfValidator $cpfValidator;
    private CnpjValidator $cnpjValidator;

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        CustomerSession $customerSession,
        LoggerInterface $logger,
        CpfValidator $cpfValidator,
        CnpjValidator $cnpjValidator
    ) {
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->logger = $logger;
        $this->cpfValidator = $cpfValidator;
        $this->cnpjValidator = $cnpjValidator;
    }

    /**
     * After saving shipping information, persist Brazil fields to customer
     */
    public function afterSaveAddressInformation(
        ShippingInformationManagement $subject,
        $result,
        $cartId,
        ShippingInformationInterface $addressInformation
    ) {
        if (!$this->customerSession->isLoggedIn()) {
            return $result;
        }

        try {
            $shippingAddress = $addressInformation->getShippingAddress();
            $customAttributes = $shippingAddress->getCustomAttributes();

            if (empty($customAttributes)) {
                return $result;
            }

            $customerId = $this->customerSession->getCustomerId();
            $customer = $this->customerRepository->getById($customerId);
            $updated = false;

            $personTypeAttr = $shippingAddress->getCustomAttribute('person_type');
            $personType = $personTypeAttr ? $personTypeAttr->getValue() : 'pf';

            foreach (self::BRAZIL_ATTRIBUTES as $attributeCode) {
                $attribute = $shippingAddress->getCustomAttribute($attributeCode);
                if (!$attribute || !$attribute->getValue()) {
                    continue;
                }

                if (!$this->isDocumentValid($attributeCode, $personType, (string) $attribute->getValue())) {
                    $this->logger->warning('[BrazilCustomer] Invalid document rejected at checkout, not persisted', [
                        'customer_id' => $customerId,
                        'attribute' => $attributeCode,
                    ]);
                    continue;
                }

                $customer->setCustomAttribute($attributeCode, $attribute->getValue());
                $updated = true;
            }

            // Also update taxvat from CPF/CNPJ for ERP integration compatibility
            if ($updated) {
                $this->updateTaxvat($customer, $personType);
                $this->customerRepository->save($customer);
            }
        } catch (\Exception $e) {
            $this->logger->error('[BrazilCustomer] Error saving checkout fields: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Sync CPF/CNPJ to taxvat field for ERP compatibility.
     *
     * Reads from the custom attributes already set on $customer (which only
     * happens after passing document validation in the loop above), so an
     * invalid CPF/CNPJ can never reach taxvat either.
     */
    private function updateTaxvat($customer, string $personType): void
    {
        $attributeCode = $personType === 'pj' ? 'cnpj' : 'cpf';
        $attribute = $customer->getCustomAttribute($attributeCode);
        if ($attribute && $attribute->getValue()) {
            $customer->setTaxvat(preg_replace('/[^0-9]/', '', (string) $attribute->getValue()));
        }
    }

    /**
     * Validates CPF/CNPJ attributes against their check digits.
     * Non-document attributes (rg, ie, company_name, etc.) pass through untouched.
     */
    private function isDocumentValid(string $attributeCode, string $personType, string $value): bool
    {
        if ($attributeCode === 'cpf' && $personType !== 'pj') {
            return $this->cpfValidator->validate($value);
        }

        if ($attributeCode === 'cnpj' && $personType === 'pj') {
            return $this->cnpjValidator->validate($value);
        }

        return true;
    }
}
