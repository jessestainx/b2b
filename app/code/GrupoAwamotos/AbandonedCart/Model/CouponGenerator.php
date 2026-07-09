<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Model;

use GrupoAwamotos\AbandonedCart\Api\CouponGeneratorInterface;
use Magento\SalesRule\Model\RuleFactory;
use Magento\SalesRule\Model\CouponFactory;
use Magento\SalesRule\Model\ResourceModel\Rule as RuleResource;
use Magento\SalesRule\Model\ResourceModel\Coupon as CouponResource;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as CustomerGroupCollectionFactory;
use Psr\Log\LoggerInterface;

class CouponGenerator implements CouponGeneratorInterface
{
    private RuleFactory $ruleFactory;
    private CouponFactory $couponFactory;
    private RuleResource $ruleResource;
    private CouponResource $couponResource;
    private StoreManagerInterface $storeManager;
    private CustomerGroupCollectionFactory $customerGroupCollectionFactory;
    private LoggerInterface $logger;

    public function __construct(
        RuleFactory $ruleFactory,
        CouponFactory $couponFactory,
        RuleResource $ruleResource,
        CouponResource $couponResource,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        CustomerGroupCollectionFactory $customerGroupCollectionFactory
    ) {
        $this->ruleFactory = $ruleFactory;
        $this->couponFactory = $couponFactory;
        $this->ruleResource = $ruleResource;
        $this->couponResource = $couponResource;
        $this->storeManager = $storeManager;
        $this->customerGroupCollectionFactory = $customerGroupCollectionFactory;
        $this->logger = $logger;
    }

    public function generate(
        float $discount,
        string $type,
        int $quoteId,
        ?string $customerEmail = null
    ): string {
        try {
            // Gerar código único
            $code = $this->generateUniqueCode($quoteId);

            // Criar regra de desconto
            $rule = $this->ruleFactory->create();

            $websiteIds = [];
            foreach ($this->storeManager->getWebsites() as $website) {
                $websiteIds[] = $website->getId();
            }

            $rule->setName('Carrinho Abandonado - ' . $code)
                ->setDescription('Cupom automático para recuperação de carrinho abandonado')
                ->setIsActive(1)
                ->setWebsiteIds($websiteIds)
                ->setCustomerGroupIds($this->getValidCustomerGroupIds())
                ->setCouponType(\Magento\SalesRule\Model\Rule::COUPON_TYPE_SPECIFIC)
                ->setCouponCode($code)
                ->setUsesPerCoupon(1)
                ->setUsesPerCustomer(1)
                ->setFromDate(date('Y-m-d'))
                ->setToDate(date('Y-m-d', strtotime('+7 days')))
                ->setSortOrder(1)
                ->setStopRulesProcessing(0);

            // Configurar tipo de desconto
            if ($type === 'percent') {
                $rule->setSimpleAction(\Magento\SalesRule\Model\Rule::BY_PERCENT_ACTION)
                    ->setDiscountAmount($discount);
            } else {
                $rule->setSimpleAction(\Magento\SalesRule\Model\Rule::CART_FIXED_ACTION)
                    ->setDiscountAmount($discount);
            }

            $rule->setDiscountQty(0)
                ->setDiscountStep(0)
                ->setSimpleFreeShipping(0)
                ->setApplyToShipping(0);

            // Salvar regra
            $this->ruleResource->save($rule);

            $this->logger->info(sprintf(
                '[AbandonedCart] Coupon created: code=%s, discount=%s%s, quote_id=%d',
                $code,
                $discount,
                $type === 'percent' ? '%' : ' BRL',
                $quoteId
            ));

            return $code;
        } catch (\Exception $e) {
            $this->logger->error('[AbandonedCart] Coupon generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getCouponByCode(string $code): ?array
    {
        try {
            $coupon = $this->couponFactory->create();
            $this->couponResource->load($coupon, $code, 'code');

            if (!$coupon->getId()) {
                return null;
            }

            return [
                'coupon_id' => $coupon->getId(),
                'rule_id' => $coupon->getRuleId(),
                'code' => $coupon->getCode(),
                'usage_limit' => $coupon->getUsageLimit(),
                'times_used' => $coupon->getTimesUsed(),
                'expiration_date' => $coupon->getExpirationDate(),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    public function invalidateCoupon(string $code): bool
    {
        try {
            $coupon = $this->couponFactory->create();
            $this->couponResource->load($coupon, $code, 'code');

            if ($coupon->getId()) {
                $rule = $this->ruleFactory->create();
                $this->ruleResource->load($rule, $coupon->getRuleId());

                if ($rule->getId()) {
                    $rule->setIsActive(0);
                    $this->ruleResource->save($rule);
                }

                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error('[AbandonedCart] Coupon invalidation failed: ' . $e->getMessage());
            return false;
        }
    }

    private function generateUniqueCode(int $quoteId): string
    {
        $prefix = 'VOLTA';
        $random = strtoupper(substr(hash('sha256', uniqid('', true)), 0, 6));
        return $prefix . $random . $quoteId;
    }

    private function getValidCustomerGroupIds(): array
    {
        $groupIds = array_values(array_filter(array_map(
            static fn($group): int => (int) $group->getId(),
            $this->customerGroupCollectionFactory->create()->getItems()
        ), static fn(int $groupId): bool => $groupId >= 0));

        if ($groupIds === []) {
            throw new \RuntimeException('Nenhum grupo de cliente válido encontrado para criar regra de cupom.');
        }

        return $groupIds;
    }
}
