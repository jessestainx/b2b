<?php

declare(strict_types=1);

namespace GrupoAwamotos\BrazilCustomer\Plugin\Customer;

use Magento\Customer\Controller\Account\EditPost;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use GrupoAwamotos\BrazilCustomer\Model\Validator\Cpf as CpfValidator;
use GrupoAwamotos\BrazilCustomer\Model\Validator\Cnpj as CnpjValidator;
use Psr\Log\LoggerInterface;

/**
 * Persists Brazilian custom attributes when customer edits their account
 */
class SaveBrazilFieldsOnEditPlugin
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
    private RequestInterface $request;
    private LoggerInterface $logger;
    private CpfValidator $cpfValidator;
    private CnpjValidator $cnpjValidator;
    private MessageManager $messageManager;

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        CustomerSession $customerSession,
        RequestInterface $request,
        LoggerInterface $logger,
        CpfValidator $cpfValidator,
        CnpjValidator $cnpjValidator,
        MessageManager $messageManager
    ) {
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->request = $request;
        $this->logger = $logger;
        $this->cpfValidator = $cpfValidator;
        $this->cnpjValidator = $cnpjValidator;
        $this->messageManager = $messageManager;
    }

    /**
     * After account edit, persist Brazil fields.
     *
     * Runs after EditPost::execute() has already redirected, so an invalid
     * CPF/CNPJ cannot throw here without breaking that response — instead we
     * skip persisting the invalid document and surface a warning message.
     */
    public function afterExecute(EditPost $subject, $result)
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $result;
        }

        try {
            $customerId = $this->customerSession->getCustomerId();
            $customer = $this->customerRepository->getById($customerId);
            $updated = false;
            $personType = (string) $this->request->getParam('person_type', 'pf');

            foreach (self::BRAZIL_ATTRIBUTES as $attributeCode) {
                $value = $this->request->getParam($attributeCode);
                if ($value === null) {
                    continue;
                }

                if (!$this->isDocumentValid($attributeCode, $personType, (string) $value)) {
                    $this->logger->warning('[BrazilCustomer] Invalid document rejected on account edit, not persisted', [
                        'customer_id' => $customerId,
                        'attribute' => $attributeCode,
                    ]);
                    $this->messageManager->addWarningMessage(
                        $attributeCode === 'cnpj'
                            ? __('CNPJ inválido informado — o campo não foi atualizado.')
                            : __('CPF inválido informado — o campo não foi atualizado.')
                    );
                    continue;
                }

                $customer->setCustomAttribute($attributeCode, $value);
                $updated = true;
            }

            if ($updated) {
                $this->updateTaxvat($customer, $personType);
                $this->customerRepository->save($customer);
            }
        } catch (\Exception $e) {
            $this->logger->error('[BrazilCustomer] Error saving account edit fields: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Sync CPF/CNPJ to taxvat field for ERP compatibility.
     * Reads from the custom attributes already set on $customer, which only
     * happens after passing document validation above.
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
        if ($value === '') {
            return true;
        }

        if ($attributeCode === 'cpf' && $personType !== 'pj') {
            return $this->cpfValidator->validate($value);
        }

        if ($attributeCode === 'cnpj' && $personType === 'pj') {
            return $this->cnpjValidator->validate($value);
        }

        return true;
    }
}
