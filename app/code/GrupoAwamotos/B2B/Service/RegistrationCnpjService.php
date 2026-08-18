<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Service;

use GrupoAwamotos\B2B\Helper\CnpjValidator as CnpjValidatorHelper;
use GrupoAwamotos\B2B\Model\CnaeClassifier;
use GrupoAwamotos\B2B\Model\ErpIntegration;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;

/**
 * Storefront CNPJ registration lookup: duplicate, Receita, ERP and CNAE.
 */
class RegistrationCnpjService
{
    public function __construct(
        private readonly CnpjValidatorHelper $cnpjValidator,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly ErpIntegration $erpIntegration,
        private readonly CnaeClassifier $cnaeClassifier
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function lookupForRegistration(string $cnpj, bool $forceRefresh = false): array
    {
        $cnpj = $this->cnpjValidator->clean($cnpj);

        if (strlen($cnpj) !== 14) {
            return [
                'success' => false,
                'message' => 'CNPJ deve ter 14 dígitos.'
            ];
        }

        if (!$this->cnpjValidator->validateLocal($cnpj)) {
            return [
                'success' => false,
                'message' => 'CNPJ inválido. Verifique os dígitos.'
            ];
        }

        $duplicateEmail = $this->findExistingCnpjOwner($cnpj);
        if ($duplicateEmail !== null) {
            return [
                'success' => false,
                'cnpj_duplicate' => true,
                'message' => (string) __(
                    'Este CNPJ já está vinculado a uma conta existente (%1). Faça login ou use a opção "Vincular minha conta".',
                    $duplicateEmail
                )
            ];
        }

        $apiData = $this->cnpjValidator->validateApi($cnpj, $forceRefresh);
        $result = $this->cnpjValidator->toHttpResult($apiData);

        if (($result['success'] ?? false) !== true || !empty($result['api_unavailable'])) {
            return $result;
        }

        $erpData = $this->getErpData($cnpj);
        $cnaeData = $this->getCnaeData(is_array($apiData) ? $apiData : []);

        $result['erp_found'] = $erpData['found'];
        $result['erp_email'] = $erpData['email_masked'];
        $result['erp_email_full'] = $erpData['email_full'];
        $result['erp_codigo'] = $erpData['codigo'];
        $result['erp_razao'] = $erpData['razao'];
        $result['cnae_code'] = $cnaeData['code'];
        $result['cnae_profile'] = $cnaeData['profile'];
        $result['cnae_profile_label'] = $cnaeData['label'];

        return $result;
    }

    /**
     * @param array<string, mixed> $apiData
     * @return array{code: string, profile: string, label: string}
     */
    private function getCnaeData(array $apiData): array
    {
        $default = ['code' => '', 'profile' => '', 'label' => ''];

        if (!$this->cnaeClassifier->isEnabled() || !isset($apiData['data'])) {
            return $default;
        }

        $rawData = $apiData['data'];
        $cnaeCode = $this->cnaeClassifier->extractCnaeCode($rawData);

        if (empty($cnaeCode)) {
            return $default;
        }

        $profile = $this->cnaeClassifier->classify($cnaeCode);

        return [
            'code' => $cnaeCode,
            'profile' => $profile,
            'label' => $this->cnaeClassifier->getProfileLabel($profile),
        ];
    }

    /**
     * @return array{found: bool, email_masked: ?string, email_full: ?string, codigo: mixed, razao: mixed}
     */
    private function getErpData(string $cnpj): array
    {
        $default = [
            'found' => false,
            'email_masked' => null,
            'email_full' => null,
            'codigo' => null,
            'razao' => null,
        ];

        try {
            $erpCustomer = $this->erpIntegration->findErpCustomerByCnpj($cnpj);

            if (!$erpCustomer) {
                return $default;
            }

            $email = trim($erpCustomer['EMAIL'] ?? '');
            return [
                'found' => true,
                'email_masked' => $email !== '' ? $this->maskEmail($email) : null,
                'email_full' => strtolower($email),
                'codigo' => $erpCustomer['CODIGO'] ?? null,
                'razao' => $erpCustomer['RAZAO'] ?? null,
            ];
        } catch (\Exception) {
            return $default;
        }
    }

    private function maskEmail(string $email): string
    {
        $email = strtolower(trim($email));

        if ($email === '' || strpos($email, '@') === false) {
            return '';
        }

        [$local, $domain] = explode('@', $email, 2);

        $maskedLocal = substr($local, 0, 1) . str_repeat('*', max(4, strlen($local) - 1));

        $domainParts = explode('.', $domain);
        if (count($domainParts) >= 2) {
            $tld = array_pop($domainParts);
            $domainName = implode('.', $domainParts);
            $maskedDomain = substr($domainName, 0, 1) . '***.' . $tld;
        } else {
            $maskedDomain = substr($domain, 0, 1) . '***';
        }

        return $maskedLocal . '@' . $maskedDomain;
    }

    private function findExistingCnpjOwner(string $cnpjDigits): ?string
    {
        $formattedCnpj = $this->cnpjValidator->format($cnpjDigits);

        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToSelect(['email', 'b2b_cnpj']);
        $collection->addAttributeToFilter(
            [
                ['attribute' => 'b2b_cnpj', 'eq' => $formattedCnpj],
                ['attribute' => 'b2b_cnpj', 'eq' => $cnpjDigits]
            ]
        );
        $collection->setPageSize(1);

        $existing = $collection->getFirstItem();
        if (!$existing || !$existing->getId()) {
            return null;
        }

        $email = trim((string) $existing->getData('email'));
        if ($email === '' || strpos($email, '@') === false) {
            return (string) __('outro cliente');
        }

        [$local, $domain] = explode('@', $email, 2);
        if (strlen($local) <= 1) {
            return $local . '***@' . $domain;
        }

        return substr($local, 0, 1) . str_repeat('*', max(2, strlen($local) - 1)) . '@' . $domain;
    }
}
