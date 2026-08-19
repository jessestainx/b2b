<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account;

use Magento\Framework\View\Element\Template;
use Magento\Customer\Model\Session;
use GrupoAwamotos\B2B\Model\OrderApprovalService;
use GrupoAwamotos\B2B\Model\OrderApproval;
use GrupoAwamotos\B2B\Model\CompanyService;
use GrupoAwamotos\B2B\Model\Company;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class Approval extends Template
{
    private Session $customerSession;
    private OrderApprovalService $approvalService;
    private CompanyService $companyService;
    private OrderRepositoryInterface $orderRepository;
    private PriceCurrencyInterface $priceCurrency;
    private TimezoneInterface $timezone;

    public function __construct(
        Template\Context $context,
        Session $customerSession,
        OrderApprovalService $approvalService,
        CompanyService $companyService,
        OrderRepositoryInterface $orderRepository,
        PriceCurrencyInterface $priceCurrency,
        array $data = [],
        ?TimezoneInterface $timezone = null
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->approvalService = $approvalService;
        $this->companyService = $companyService;
        $this->orderRepository = $orderRepository;
        $this->priceCurrency = $priceCurrency;
        // Optional after $data: compiled Interceptor omits TimezoneInterface.
        $this->timezone = $timezone ?? $context->getLocaleDate();
    }

    public function getPendingApprovals()
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        $role = $this->companyService->getUserRole($customerId);
        $level = $this->roleToLevel($role);

        return $this->approvalService->getPendingApprovals($level, null);
    }

    public function getMyApprovals()
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        $collection = $this->approvalService->getPendingApprovals(
            OrderApproval::LEVEL_DIRECTOR
        );
        $collection->addFieldToFilter('customer_id', ['eq' => $customerId]);
        return $collection;
    }

    public function canApprove(): bool
    {
        $role = $this->companyService->getUserRole(
            (int) $this->customerSession->getCustomerId()
        );
        return $role && $role !== Company::ROLE_BUYER;
    }

    public function getOrderIncrementId(int $orderId): string
    {
        try {
            return $this->orderRepository->get($orderId)->getIncrementId();
        } catch (\Exception $e) {
            return '#' . $orderId;
        }
    }

    public function formatPrice(float $price): string
    {
        return $this->priceCurrency->format($price, false);
    }

    public function getStatusLabel(string $status): string
    {
        $statuses = OrderApproval::getStatuses();
        return isset($statuses[$status]) ? (string) $statuses[$status] : $status;
    }

    public function getLevelLabel(int $level): string
    {
        $levels = OrderApproval::getLevels();
        return isset($levels[$level]) ? (string) $levels[$level] : (string) $level;
    }

    public function getActionUrl(): string
    {
        return $this->getUrl('b2b/approval/action');
    }


    public function formatDateTime(?string $dateTime): string
    {
        if ($dateTime === null || $dateTime === '') {
            return '-';
        }

        try {
            return $this->timezone->date(new \DateTime($dateTime))->format('d/m/Y H:i');
        } catch (\Exception) {
            return substr($dateTime, 0, 16);
        }
    }

    private function roleToLevel(?string $role): int
    {
        $map = [
            Company::ROLE_BUYER => OrderApproval::LEVEL_BUYER,
            Company::ROLE_MANAGER => OrderApproval::LEVEL_MANAGER,
            Company::ROLE_ADMIN => OrderApproval::LEVEL_DIRECTOR,
        ];
        return $map[$role] ?? OrderApproval::LEVEL_BUYER;
    }
}
