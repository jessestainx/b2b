<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Customer\Block\Address;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Block\Address\Edit;
use Magento\Customer\Model\Session as CustomerSession;
use Psr\Log\LoggerInterface;

/**
 * Prefills address company from B2B razao social when the address company is empty.
 */
class PrefillCompanyPlugin
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param Edit $subject
     * @param AddressInterface|null $result
     * @return AddressInterface|null
     */
    public function afterGetAddress(Edit $subject, $result)
    {
        if (!$result instanceof AddressInterface) {
            return $result;
        }

        if (trim((string) $result->getCompany()) !== '') {
            return $result;
        }

        $razao = $this->resolveRazaoSocial($subject);
        if ($razao === '') {
            return $result;
        }

        $result->setCompany($razao);

        return $result;
    }

    private function resolveRazaoSocial(Edit $subject): string
    {
        $customer = null;

        try {
            $customer = $subject->getCustomer();
        } catch (\Throwable $e) {
            $customer = null;
        }

        if (!$customer instanceof CustomerInterface) {
            $customerId = (int) $this->customerSession->getCustomerId();
            if ($customerId <= 0) {
                return '';
            }

            try {
                $customer = $this->customerRepository->getById($customerId);
            } catch (\Throwable $e) {
                $this->logger->debug(
                    'B2B address company prefill: customer load failed',
                    ['customer_id' => $customerId, 'error' => $e->getMessage()]
                );

                return '';
            }
        }

        $razaoAttr = $customer->getCustomAttribute('b2b_razao_social');
        if ($razaoAttr) {
            $razao = trim((string) $razaoAttr->getValue());
            if ($razao !== '') {
                return $razao;
            }
        }

        // Fallback: some accounts only store company name on customer names.
        $fullName = trim($customer->getFirstname() . ' ' . $customer->getLastname());
        if ($fullName !== '' && preg_match('/\b(LTDA|EIRELI|ME|EPP|S\/?A)\b/i', $fullName)) {
            return $fullName;
        }

        return '';
    }
}
