<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Authentication;

use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer as B2bCustomerResource;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\AccountManagement;
use Psr\Log\LoggerInterface;

/**
 * Permite login com CNPJ no campo de e-mail.
 *
 * Quando o usuário digita CNPJ (com ou sem máscara) no campo de login,
 * este plugin resolve o CNPJ para o e-mail do cliente B2B associado
 * antes de passar para a autenticação padrão do Magento.
 */
class CnpjLoginPlugin
{
    public function __construct(
        private readonly B2bCustomerResource $b2bCustomerResource,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param AccountManagement $subject
     * @param string            $username  E-mail ou CNPJ digitado pelo usuário
     * @param string            $password
     * @return array Modified arguments if CNPJ detected, original otherwise
     */
    public function beforeAuthenticate(
        AccountManagement $subject,
        string $username,
        string $password
    ): array {
        $cnpjDigits = preg_replace('/[^0-9]/', '', $username);

        if (strlen($cnpjDigits) !== 14) {
            return [$username, $password];
        }

        try {
            $b2bData = $this->b2bCustomerResource->getByCnpj($cnpjDigits);
            if (empty($b2bData) || empty($b2bData['email'])) {
                return [$username, $password];
            }

            $this->logger->debug(sprintf(
                '[B2B Login] CNPJ %s resolvido para e-mail: %s',
                $cnpjDigits,
                $b2bData['email']
            ));

            return [$b2bData['email'], $password];
        } catch (\Throwable $e) {
            $this->logger->error('[B2B Login] Erro ao resolver CNPJ para e-mail: ' . $e->getMessage());
            return [$username, $password];
        }
    }
}
