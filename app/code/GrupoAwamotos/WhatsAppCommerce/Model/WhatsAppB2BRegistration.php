<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Model;

use GrupoAwamotos\B2B\Api\CustomerApprovalInterface;
use GrupoAwamotos\B2B\Helper\CnpjValidator;
use GrupoAwamotos\B2B\Model\Notification\WhatsAppService as B2BWhatsAppService;
use GrupoAwamotos\B2B\Model\ResourceModel\Company\CollectionFactory as CompanyCollectionFactory;
use GrupoAwamotos\WhatsAppCommerce\Api\B2BRegistrationInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class WhatsAppB2BRegistration implements B2BRegistrationInterface
{
    private const CONFIG_PATH_DEFAULT_B2B_GROUP = 'grupoawamotos_b2b/customer_groups/pending_group';

    public function __construct(
        private readonly CnpjValidator $cnpjValidator,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly CompanyCollectionFactory $companyCollectionFactory,
        private readonly CustomerApprovalInterface $customerApproval,
        private readonly B2BWhatsAppService $b2bWhatsAppService,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger,
        private readonly ResourceConnection $resource,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function validateCnpj(string $cnpj): array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (!$this->cnpjValidator->validateLocal($cnpj)) {
            return [
                'valid' => false,
                'message' => 'CNPJ invalido. Verifique os digitos e tente novamente.',
            ];
        }

        $apiResult = $this->cnpjValidator->validateApi($cnpj);

        if ($apiResult === null) {
            return [
                'valid' => false,
                'message' => 'CNPJ nao encontrado na Receita Federal.',
            ];
        }

        if (isset($apiResult['valid']) && !$apiResult['valid']) {
            return [
                'valid' => false,
                'message' => $apiResult['message'] ?? 'CNPJ com situacao irregular.',
            ];
        }

        // Check if CNPJ already registered
        $existingCompany = $this->companyCollectionFactory->create()
            ->addFieldToFilter('cnpj', $this->formatCnpj($cnpj))
            ->getFirstItem();

        $alreadyRegistered = $existingCompany->getId() !== null;

        return [
            'valid' => true,
            'already_registered' => $alreadyRegistered,
            'razao_social' => $apiResult['razao_social'] ?? '',
            'nome_fantasia' => $apiResult['nome_fantasia'] ?? '',
            'situacao' => $apiResult['situacao'] ?? '',
            'atividade_principal' => $apiResult['atividade_principal'] ?? '',
            'municipio' => $apiResult['municipio'] ?? '',
            'uf' => $apiResult['uf'] ?? '',
            'cep' => $apiResult['cep'] ?? '',
            'telefone' => $apiResult['telefone'] ?? '',
            'email' => $apiResult['email'] ?? '',
        ];
    }

    /**
     * @inheritDoc
     */
    public function register(
        string $cnpj,
        string $phone,
        string $contactName,
        ?string $email = null,
        ?string $segment = null
    ): array {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (!$this->cnpjValidator->validateLocal($cnpj)) {
            return ['success' => false, 'message' => 'CNPJ invalido.'];
        }

        // Check if phone already has a customer account
        $existingCustomer = $this->findCustomerByPhone($phone);
        if ($existingCustomer !== null) {
            $status = $this->customerApproval->getApprovalStatus((int) $existingCustomer->getId());
            return [
                'success' => false,
                'message' => 'Ja existe um cadastro para este telefone.',
                'status' => $status ?? 'unknown',
                'customer_id' => (int) $existingCustomer->getId(),
            ];
        }

        // Check if CNPJ already has a company
        $existingCompany = $this->companyCollectionFactory->create()
            ->addFieldToFilter('cnpj', $this->formatCnpj($cnpj))
            ->getFirstItem();

        if ($existingCompany->getId()) {
            return [
                'success' => false,
                'message' => 'Este CNPJ ja esta cadastrado. Se e um novo usuario da empresa, entre em contato com nosso suporte.',
            ];
        }

        // Lookup CNPJ data
        $cnpjData = $this->cnpjValidator->validateApi($cnpj);
        $razaoSocial = $cnpjData['razao_social'] ?? $contactName;

        try {
            if (empty($email)) {
                $cleanPhone = preg_replace('/\D/', '', $phone);
                $email = "whatsapp_{$cleanPhone}@awamotos.com.br";
            }

            $customer = $this->customerFactory->create();
            $customer->setFirstname($contactName);
            $customer->setLastname('(B2B WhatsApp)');
            $customer->setEmail($email);
            $customer->setStoreId((int) $this->storeManager->getStore()->getId());
            $customer->setWebsiteId((int) $this->storeManager->getStore()->getWebsiteId());

            $pendingGroupId = (int) $this->scopeConfig->getValue(
                self::CONFIG_PATH_DEFAULT_B2B_GROUP,
                ScopeInterface::SCOPE_STORE
            );
            if ($pendingGroupId > 0) {
                $customer->setGroupId($pendingGroupId);
            }

            $customer->setCustomAttribute('person_type', 'PJ');
            $customer->setCustomAttribute('cnpj', $this->formatCnpj($cnpj));
            $customer->setCustomAttribute('telephone', $phone);

            if ($segment) {
                $customer->setCustomAttribute('b2b_segment', $segment);
            }

            $savedCustomer = $this->customerRepository->save($customer);
            $customerId = (int) $savedCustomer->getId();

            $this->customerApproval->setCustomerPending($customerId);

            $this->b2bWhatsAppService->notifyNewB2BRegistration([
                'customer_name' => $contactName,
                'cnpj' => $this->formatCnpj($cnpj),
                'razao_social' => $razaoSocial,
                'phone' => $phone,
                'email' => $email,
                'segment' => $segment ?? 'nao informado',
            ]);

            $this->logger->info('B2B WhatsApp registration created', [
                'customer_id' => $customerId,
                'cnpj' => $this->maskCnpj($cnpj),
                'phone' => $this->maskPhone($phone),
            ]);

            return [
                'success' => true,
                'message' => 'Cadastro B2B recebido! Nossa equipe vai analisar e entrar em contato em ate 24h.',
                'customer_id' => $customerId,
                'status' => 'pending',
                'razao_social' => $razaoSocial,
            ];
        } catch (\Exception $e) {
            $this->logger->error('B2B WhatsApp registration error', [
                'cnpj' => $this->maskCnpj($cnpj),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao processar cadastro. Tente novamente ou entre em contato com nossa equipe.',
            ];
        }
    }

    /**
     * @inheritDoc
     */
    public function checkStatus(string $identifier): array
    {
        $identifier = preg_replace('/\D/', '', $identifier);

        $customer = $this->findCustomerByPhone($identifier);

        if ($customer === null && strlen($identifier) === 14) {
            $customer = $this->findCustomerByCnpj($identifier);
        }

        if ($customer === null) {
            return [
                'found' => false,
                'status' => 'not_found',
                'message' => 'Nenhum cadastro B2B encontrado para este numero/CNPJ.',
            ];
        }

        $customerId = (int) $customer->getId();
        $status = $this->customerApproval->getApprovalStatus($customerId) ?? 'unknown';

        $messages = [
            'pending' => 'Seu cadastro esta em analise. Nossa equipe retornara em ate 24h.',
            'approved' => 'Seu cadastro B2B esta aprovado! Voce ja pode acessar precos de atacado.',
            'rejected' => 'Seu cadastro foi recusado. Entre em contato para mais informacoes.',
            'suspended' => 'Seu cadastro esta temporariamente suspenso.',
        ];

        return [
            'found' => true,
            'status' => $status,
            'message' => $messages[$status] ?? 'Status desconhecido.',
            'customer_name' => $customer->getFirstname() . ' ' . $customer->getLastname(),
            'customer_group' => $customer->getGroupId(),
        ];
    }

    private function findCustomerByPhone(string $phone): ?\Magento\Customer\Api\Data\CustomerInterface
    {
        $cleanPhone = preg_replace('/\D/', '', $phone);
        if (strlen($cleanPhone) >= 13 && str_starts_with($cleanPhone, '55')) {
            $cleanPhone = substr($cleanPhone, 2);
        }
        $lastDigits = substr($cleanPhone, -8);
        if (strlen($lastDigits) < 8) {
            return null;
        }

        $connection = $this->resource->getConnection();

        // Try address telephone first (most reliable)
        $sql = $connection->select()
            ->from(['a' => $this->resource->getTableName('customer_address_entity')], ['parent_id'])
            ->where(
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(a.telephone, '(', ''), ')', ''), '-', ''), ' ', ''), '+', '') LIKE ?",
                '%' . $lastDigits
            )
            ->limit(1);

        $customerId = (int) $connection->fetchOne($sql);

        // Fallback: try b2b_phone EAV attribute
        if (!$customerId) {
            $collection = $this->customerCollectionFactory->create();
            $collection->addAttributeToFilter('b2b_phone', ['like' => "%{$lastDigits}%"]);
            $collection->setPageSize(1);
            $customerModel = $collection->getFirstItem();
            $customerId = (int) $customerModel->getId();
        }

        if (!$customerId) {
            return null;
        }

        try {
            return $this->customerRepository->getById($customerId);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function findCustomerByCnpj(string $cnpj): ?\Magento\Customer\Api\Data\CustomerInterface
    {
        $formatted = $this->formatCnpj($cnpj);
        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToFilter('cnpj', $formatted);
        $collection->setPageSize(1);

        $customerModel = $collection->getFirstItem();
        if (!$customerModel->getId()) {
            return null;
        }

        try {
            return $this->customerRepository->getById((int) $customerModel->getId());
        } catch (\Exception $e) {
            return null;
        }
    }

    private function formatCnpj(string $cnpj): string
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }
        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($cnpj, 0, 2),
            substr($cnpj, 2, 3),
            substr($cnpj, 5, 3),
            substr($cnpj, 8, 4),
            substr($cnpj, 12, 2)
        );
    }

    private function maskCnpj(string $cnpj): string
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        return substr($cnpj, 0, 4) . '****' . substr($cnpj, -2);
    }

    private function maskPhone(string $phone): string
    {
        $clean = preg_replace('/\D/', '', $phone);
        if (strlen($clean) < 6) {
            return '***';
        }
        return substr($clean, 0, 4) . '****' . substr($clean, -2);
    }
}
