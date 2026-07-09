<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\ErpCodeResolver;
use GrupoAwamotos\ERPIntegration\Model\CustomerPriceProvider;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Http\Context as HttpContext;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Adds the customer's ERP price list code to the HTTP context.
 *
 * This makes Full Page Cache (FPC) vary by price list, ensuring that
 * customers with different ERP price lists see their own prices.
 *
 * Since there are only ~13 active price lists, this creates a manageable
 * number of cache variants (vs N customers which would be unscalable).
 */
class CustomerPriceListContextPlugin
{
    private const CONTEXT_PRICE_LIST = 'erp_price_list';

    private CustomerSession $customerSession;
    private Config $config;
    private SyncLogResource $syncLogResource;
    private CustomerPriceProvider $customerPriceProvider;
    private CustomerRepositoryInterface $customerRepository;
    private LoggerInterface $logger;
    private ?ErpCodeResolver $erpCodeResolver;

    public function __construct(
        CustomerSession $customerSession,
        Config $config,
        SyncLogResource $syncLogResource,
        CustomerPriceProvider $customerPriceProvider,
        CustomerRepositoryInterface $customerRepository,
        ?LoggerInterface $logger = null,
        ?ErpCodeResolver $erpCodeResolver = null
    ) {
        $this->customerSession = $customerSession;
        $this->config = $config;
        $this->syncLogResource = $syncLogResource;
        $this->customerPriceProvider = $customerPriceProvider;
        $this->customerRepository = $customerRepository;
        $this->logger = $logger ?? new NullLogger();
        $this->erpCodeResolver = $erpCodeResolver;
    }

    /**
     * Add ERP price list code to HTTP context for FPC cache variation.
     *
     * CRITICAL: Always set a context value for logged-in B2B customers,
     * even without ERP code. This ensures FPC creates separate cache
     * entries for guests vs logged-in users, preventing the guest
     * "Entrar para Comprar" buttons from appearing for logged-in users.
     */
    public function beforeGetVaryString(HttpContext $subject): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        // Guard: avoid session start for visitors without a session cookie.
        // CustomerSession::isLoggedIn() triggers session_start() which creates a PHP session
        // for every anonymous request, preventing PhpCookieDisabler from stripping PHPSESSID
        // on FPC HIT responses, and adds Redis session overhead on every request.
        if (($_COOKIE[session_name()] ?? null) === null) {
            return;
        }

        if (!$this->customerSession->isLoggedIn()) {
            return;
        }

        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            $erpCode = $this->resolveErpCode($customerId);

            if ($erpCode === null) {
                // Logged in but no ERP code: still differentiate from guests
                $subject->setValue(self::CONTEXT_PRICE_LIST, 'logged_in', '0');
                return;
            }

            $contextToken = $this->customerPriceProvider->getContextTokenForCustomer($erpCode);

            $subject->setValue(
                self::CONTEXT_PRICE_LIST,
                (string) ($contextToken ?? 'default:1'),
                '0' // default for non-logged-in users
            );
        } catch (\Exception $e) {
            // Still mark as logged-in to differentiate from guest cache
            $subject->setValue(self::CONTEXT_PRICE_LIST, 'logged_in', '0');
        }
    }

    /**
     * Resolve ERP code: erp_code attribute (primary) → entity_map (fallback)
     */
    private function resolveErpCode(int $customerId): ?int
    {
        try {
            $customer = $this->customerRepository->getById($customerId);

            if ($this->erpCodeResolver !== null) {
                return $this->erpCodeResolver->resolveForCustomerId($customerId, $customer);
            }

            // Primary: erp_code attribute (definitive, single value)
            $attr = $customer->getCustomAttribute('erp_code');
            $erpCode = ($attr && $attr->getValue()) ? $attr->getValue() : null;

            // Fallback: entity_map table
            if ($erpCode === null) {
                $erpCode = $this->syncLogResource->getErpCodeByMagentoId('customer', $customerId);
            }

            return ($erpCode !== null && is_numeric($erpCode)) ? (int) $erpCode : null;
        } catch (\Exception $e) {
            $this->logger->warning('[B2B] Failed to resolve ERP code for HTTP context variation', [
                'customer_id' => $customerId,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
