<?php

declare(strict_types=1);

namespace GrupoAwamotos\BrazilCustomer\Plugin\Customer;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\InputException;
use GrupoAwamotos\BrazilCustomer\Model\Validator\Cpf as CpfValidator;
use GrupoAwamotos\BrazilCustomer\Model\Validator\Cnpj as CnpjValidator;
use Psr\Log\LoggerInterface;

/**
 * Persists Brazilian custom attributes on customer registration
 */
class SaveBrazilFieldsOnCreatePlugin
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

    private RequestInterface $request;
    private LoggerInterface $logger;
    private CpfValidator $cpfValidator;
    private CnpjValidator $cnpjValidator;

    public function __construct(
        RequestInterface $request,
        LoggerInterface $logger,
        CpfValidator $cpfValidator,
        CnpjValidator $cnpjValidator
    ) {
        $this->request = $request;
        $this->logger = $logger;
        $this->cpfValidator = $cpfValidator;
        $this->cnpjValidator = $cnpjValidator;
    }

    /**
     * Before creating account, set Brazil attributes from POST data
     *
     * @throws InputException when the informed CPF/CNPJ fails check-digit validation
     */
    public function beforeCreateAccount(
        AccountManagementInterface $subject,
        CustomerInterface $customer,
        $password = null,
        $redirectUrl = ''
    ): array {
        $personType = (string) $this->request->getParam('person_type', 'pf');

        $this->validateDocument($personType);

        foreach (self::BRAZIL_ATTRIBUTES as $attributeCode) {
            $value = $this->request->getParam($attributeCode);
            if ($value !== null && $value !== '') {
                $customer->setCustomAttribute($attributeCode, $value);
            }
        }

        // Sync CPF/CNPJ to taxvat for ERP compatibility
        if ($personType === 'pj') {
            $cnpj = $this->request->getParam('cnpj');
            if ($cnpj) {
                $customer->setTaxvat(preg_replace('/[^0-9]/', '', (string) $cnpj));
            }
        } else {
            $cpf = $this->request->getParam('cpf');
            if ($cpf) {
                $customer->setTaxvat(preg_replace('/[^0-9]/', '', (string) $cpf));
            }
        }

        return [$customer, $password, $redirectUrl];
    }

    /**
     * @throws InputException
     */
    private function validateDocument(string $personType): void
    {
        if ($personType === 'pj') {
            $cnpj = (string) $this->request->getParam('cnpj', '');
            if ($cnpj !== '' && !$this->cnpjValidator->validate($cnpj)) {
                throw new InputException(__('CNPJ inválido. Verifique o número informado.'));
            }
            return;
        }

        $cpf = (string) $this->request->getParam('cpf', '');
        if ($cpf !== '' && !$this->cpfValidator->validate($cpf)) {
            throw new InputException(__('CPF inválido. Verifique o número informado.'));
        }
    }
}
