<?php

declare(strict_types=1);

namespace GrupoAwamotos\LiveChat\Model\Chat;

use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session as CustomerSession;

class CustomerContextBuilder
{
    private const MAX_VALUE_LENGTH = 255;

    private CustomerSession $customerSession;

    private B2BHelper $b2bHelper;

    private GroupRepositoryInterface $groupRepository;

    private ApprovalStatus $approvalStatusSource;

    public function __construct(
        CustomerSession $customerSession,
        B2BHelper $b2bHelper,
        GroupRepositoryInterface $groupRepository,
        ApprovalStatus $approvalStatusSource
    ) {
        $this->customerSession = $customerSession;
        $this->b2bHelper = $b2bHelper;
        $this->groupRepository = $groupRepository;
        $this->approvalStatusSource = $approvalStatusSource;
    }

    /**
     * Build customer-specific variables for LiveChat.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public function build(): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return [];
        }

        $customer = $this->customerSession->getCustomer();
        if (!$customer instanceof Customer || !(int) $customer->getId()) {
            return [];
        }

        $variables = [];
        $groupId = (int) $customer->getGroupId();

        $this->appendVariable(
            $variables,
            'Tipo de cliente',
            $this->b2bHelper->isB2BCustomer() ? 'B2B' : 'B2C'
        );
        $this->appendVariable($variables, 'Grupo do cliente', $this->getGroupName($groupId));
        $this->appendVariable(
            $variables,
            'Status B2B',
            $this->getApprovalStatusLabel($this->getCustomerAttributeValue($customer, 'b2b_approval_status'))
        );
        $this->appendVariable($variables, 'CNPJ', $this->getCustomerAttributeValue($customer, 'b2b_cnpj'));
        $this->appendVariable(
            $variables,
            'Razao social',
            $this->getCustomerAttributeValue($customer, 'b2b_razao_social')
        );
        $this->appendVariable(
            $variables,
            'Nome fantasia',
            $this->getCustomerAttributeValue($customer, 'b2b_nome_fantasia')
        );
        $this->appendVariable($variables, 'Telefone', $this->getCustomerAttributeValue($customer, 'b2b_phone'));

        return $variables;
    }

    private function getGroupName(int $groupId): ?string
    {
        if ($groupId <= 0) {
            return null;
        }

        try {
            return (string) $this->groupRepository->getById($groupId)->getCode();
        } catch (\Throwable $exception) {
            return $this->b2bHelper->getB2BGroupName($groupId);
        }
    }

    private function getApprovalStatusLabel(?string $status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        $label = $this->approvalStatusSource->getOptionText($status);
        return $label !== false ? (string) $label : $status;
    }

    private function getCustomerAttributeValue(Customer $customer, string $attributeCode): ?string
    {
        $attribute = $customer->getCustomAttribute($attributeCode);
        if ($attribute !== null && $attribute->getValue() !== null && $attribute->getValue() !== '') {
            return (string) $attribute->getValue();
        }

        $value = $customer->getData($attributeCode);
        if (!is_scalar($value) || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param array<int, array{name: string, value: string}> $variables
     */
    private function appendVariable(array &$variables, string $name, ?string $value): void
    {
        $normalizedValue = $this->normalizeValue($value);
        if ($normalizedValue === null) {
            return;
        }

        $variables[] = [
            'name' => $name,
            'value' => $normalizedValue,
        ];
    }

    private function normalizeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strip_tags($value);
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?: '';
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) <= self::MAX_VALUE_LENGTH) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, self::MAX_VALUE_LENGTH - 1)) . '…';
    }
}
