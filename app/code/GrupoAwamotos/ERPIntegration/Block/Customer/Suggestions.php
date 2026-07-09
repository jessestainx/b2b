<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Block\Customer;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session as CustomerSession;
use GrupoAwamotos\ERPIntegration\Model\PurchaseHistory;
use GrupoAwamotos\ERPIntegration\Model\ProductSuggestion;
use GrupoAwamotos\ERPIntegration\Model\Cart\SuggestedCart;
use GrupoAwamotos\ERPIntegration\Model\Rfm\Calculator as RfmCalculator;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use GrupoAwamotos\ERPIntegration\Model\CustomerPriceProvider;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;

/**
 * Customer Product Suggestions Block
 *
 * Enhanced with Suggested Cart and RFM data
 */
class Suggestions extends Template
{
    protected $_template = 'GrupoAwamotos_ERPIntegration::customer/suggestions.phtml';

    private CustomerSession $customerSession;
    private PurchaseHistory $purchaseHistory;
    private ProductSuggestion $productSuggestion;
    private SuggestedCart $suggestedCart;
    private RfmCalculator $rfmCalculator;
    private SyncLogResource $syncLogResource;
    private CustomerPriceProvider $customerPriceProvider;
    private Helper $helper;
    private ?int $erpCustomerCode = null;
    private bool $erpCodeResolved = false;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        PurchaseHistory $purchaseHistory,
        ProductSuggestion $productSuggestion,
        SuggestedCart $suggestedCart,
        RfmCalculator $rfmCalculator,
        SyncLogResource $syncLogResource,
        CustomerPriceProvider $customerPriceProvider,
        Helper $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->purchaseHistory = $purchaseHistory;
        $this->productSuggestion = $productSuggestion;
        $this->suggestedCart = $suggestedCart;
        $this->rfmCalculator = $rfmCalculator;
        $this->syncLogResource = $syncLogResource;
        $this->customerPriceProvider = $customerPriceProvider;
        $this->helper = $helper;
    }

    /**
     * Cache TTL: 30 minutes. ERP data (suggestions, history) changes infrequently.
     * Prevents repeated slow SQL Server queries on every dashboard page load.
     */
    public function getCacheLifetime(): int
    {
        return 1800;
    }

    /**
     * Cache key is scoped per customer so no cross-customer data leaks.
     */
    public function getCacheKeyInfo(): array
    {
        return [
            'ERP_CUSTOMER_SUGGESTIONS',
            (string) ($this->customerSession->getCustomerId() ?? '0'),
        ];
    }

    /**
     * Check if suggestions are enabled
     */
    public function isEnabled(): bool
    {
        return $this->helper->isEnabled() && $this->helper->isSuggestionsEnabled();
    }

    /**
     * Check if customer is logged in
     */
    public function isLoggedIn(): bool
    {
        // Re-sync session storage: storage._data may be out of sync with $_SESSION
        // because some early code broke the PHP reference between them.
        // Calling start() forces storage->init($_SESSION) with current session data.
        $this->customerSession->start();
        return $this->customerSession->isLoggedIn();
    }

    /**
     * Get ERP customer code for logged in customer
     */
    public function getErpCustomerCode(): ?int
    {
        if ($this->erpCodeResolved) {
            return $this->erpCustomerCode;
        }
        $this->erpCodeResolved = true;

        if (!$this->isLoggedIn()) {
            return null;
        }

        // Use getCustomerId() directly — more reliable than getCustomer()->getId()
        // because getCustomer() uses the deprecated CustomerModel which may not
        // be loaded when CustomerSession is accessed via Proxy
        $customerId = (int) $this->customerSession->getCustomerId();
        if (!$customerId) {
            return null;
        }

        // 1. Try entity map (most reliable — set by ERP sync)
        $erpCode = $this->syncLogResource->getErpCodeByMagentoId('customer', $customerId);
        if ($erpCode !== null) {
            $this->erpCustomerCode = (int) $erpCode;
            return $this->erpCustomerCode;
        }

        // 2. Fallback: resolve via CNPJ (load customer data only when needed)
        $customer = $this->customerSession->getCustomer();
        $cnpj = $customer->getData('b2b_cnpj');
        if (empty($cnpj)) {
            $cnpj = $customer->getTaxvat();
        }

        if (!empty($cnpj)) {
            $this->erpCustomerCode = $this->purchaseHistory->getCustomerCodeByCnpj($cnpj);
        }

        return $this->erpCustomerCode;
    }

    /**
     * Get customer info from ERP
     */
    public function getErpCustomerInfo(): ?array
    {
        $customerCode = $this->getErpCustomerCode();

        if (!$customerCode) {
            return null;
        }

        return $this->purchaseHistory->getCustomerInfo($customerCode);
    }

    /**
     * Get purchase summary
     */
    public function getPurchaseSummary(): array
    {
        $customerCode = $this->getErpCustomerCode();

        if (!$customerCode) {
            return [];
        }

        return $this->purchaseHistory->getCustomerSummary($customerCode);
    }

    /**
     * Get most purchased products
     */
    public function getMostPurchasedProducts(int $limit = 10): array
    {
        $customerCode = $this->getErpCustomerCode();

        if (!$customerCode) {
            return [];
        }

        return $this->purchaseHistory->getMostPurchasedProducts($customerCode, $limit);
    }

    /**
     * Get product suggestions
     */
    public function getSuggestions(int $limit = 10): array
    {
        $customerCode = $this->getErpCustomerCode();

        if (!$customerCode) {
            return [];
        }

        return $this->productSuggestion->getSuggestions($customerCode, $limit);
    }

    /**
     * Get reorder suggestions
     */
    public function getReorderSuggestions(int $limit = 10): array
    {
        $customerCode = $this->getErpCustomerCode();

        if (!$customerCode) {
            return [];
        }

        return $this->productSuggestion->getReorderSuggestions($customerCode, $limit);
    }

    /**
     * Get trending products
     */
    public function getTrendingProducts(int $limit = 10): array
    {
        return $this->productSuggestion->getTrendingProducts($limit);
    }

    /**
     * Get last orders
     */
    public function getLastOrders(int $limit = 5): array
    {
        $customerCode = $this->getErpCustomerCode();

        if (!$customerCode) {
            return [];
        }

        return $this->purchaseHistory->getLastOrders($customerCode, $limit);
    }

    /**
     * Get monthly purchase trend for charts
     */
    public function getMonthlyTrend(int $months = 12): array
    {
        $customerCode = $this->getErpCustomerCode();
        if (!$customerCode) {
            return [];
        }
        return $this->purchaseHistory->getMonthlyTrend($customerCode, $months);
    }

    /**
     * Get purchase frequency analysis
     */
    public function getPurchaseFrequency(): array
    {
        $customerCode = $this->getErpCustomerCode();
        if (!$customerCode) {
            return [];
        }
        return $this->purchaseHistory->getPurchaseFrequency($customerCode);
    }

    /**
     * Get filtered purchase history
     */
    public function getFilteredHistory(array $filters = []): array
    {
        $customerCode = $this->getErpCustomerCode();
        if (!$customerCode) {
            return ['items' => [], 'total_count' => 0];
        }
        return $this->purchaseHistory->getFilteredHistory($customerCode, $filters);
    }

    /**
     * Format price
     */
    public function formatPrice(float $price): string
    {
        return 'R$ ' . number_format($price, 2, ',', '.');
    }

    /**
     * Format ERP date to Brazilian format
     */
    public function formatErpDate(?string $date): string
    {
        if (empty($date)) {
            return '-';
        }

        try {
            $datetime = new \DateTime($date);
            return $datetime->format('d/m/Y');
        } catch (\Exception $e) {
            return substr($date, 0, 10);
        }
    }

    /**
     * Get AJAX URL for suggestions
     */
    public function getAjaxUrl(): string
    {
        return $this->getUrl('erpintegration/customer/suggestions');
    }

    /**
     * Get product URL
     */
    public function getProductUrl(array $product): string
    {
        if (!empty($product['magento']['product_url'])) {
            return $product['magento']['product_url'];
        }

        if (!empty($product['magento']['url_key'])) {
            return $this->getBaseUrl() . $product['magento']['url_key'] . '.html';
        }

        // Search by SKU
        return $this->getUrl('catalogsearch/result', ['q' => $product['codigo_material']]);
    }

    /**
     * Check if product is available in store
     */
    public function isProductAvailable(array $product): bool
    {
        return !empty($product['available_in_store']) &&
               !empty($product['magento']['in_stock']);
    }

    /**
     * Get status label
     */
    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            'F' => 'Faturado',
            'E' => 'Entregue',
            'V' => 'Em Separação',
            'S' => 'Saiu p/ Entrega',
            'W' => 'Aguardando',
            'A' => 'Aberto',
            'P' => 'Pendente',
            'L' => 'Liberado',
            'B' => 'Bloqueado',
            'C' => 'Cancelado',
            'X' => 'Excluído',
            default => $status,
        };
    }

    /**
     * Get status CSS class
     */
    public function getStatusClass(string $status): string
    {
        return match ($status) {
            'F', 'E' => 'status-success',
            'V', 'S', 'L' => 'status-info',
            'A', 'W' => 'status-warning',
            'P', 'B' => 'status-warning',
            'C', 'X' => 'status-error',
            default => '',
        };
    }

    /**
     * Get complete suggested cart for customer
     */
    public function getSuggestedCart(): array
    {
        $customerCode = $this->getErpCustomerCode();

        if (!$customerCode) {
            return [];
        }

        return $this->suggestedCart->buildSuggestedCart($customerCode);
    }

    /**
     * Get customer RFM data
     */
    public function getCustomerRfm(): ?array
    {
        $customerCode = $this->getErpCustomerCode();

        if (!$customerCode) {
            return null;
        }

        return $this->rfmCalculator->getCustomerRfm($customerCode);
    }

    /**
     * Get add to cart URL
     */
    public function getAddToCartUrl(): string
    {
        return $this->getUrl('checkout/cart/add');
    }

    /**
     * Get add all to cart URL (custom endpoint)
     */
    public function getAddAllToCartUrl(): string
    {
        return $this->getUrl('erpintegration/cart/addSuggested');
    }

    /**
     * Get section icon
     */
    public function getSectionIcon(string $type): string
    {
        return match ($type) {
            'reorder' => '🔄',
            'cross_sell' => '🔗',
            'similar_customers' => '👥',
            'dormant' => '⏰',
            default => '💡',
        };
    }

    /**
     * Get reorder status label
     */
    public function getReorderStatusLabel(string $status): string
    {
        return match ($status) {
            'overdue' => 'Hora de Repor!',
            'due_soon' => 'Em Breve',
            'on_track' => 'OK',
            default => '',
        };
    }

    /**
     * Get reorder status class
     */
    public function getReorderStatusClass(string $status): string
    {
        return match ($status) {
            'overdue' => 'reorder-overdue',
            'due_soon' => 'reorder-soon',
            'on_track' => 'reorder-ok',
            default => '',
        };
    }

    /**
     * Format quantity
     */
    public function formatQuantity(int $qty): string
    {
        return number_format($qty, 0, '', '.');
    }

    /**
     * Check if suggested cart feature is enabled
     */
    public function isSuggestedCartEnabled(): bool
    {
        return $this->helper->isEnabled() && $this->helper->isSuggestionsEnabled();
    }

    /**
     * Check if current page is the dedicated Suggested Cart page (ERP module)
     */
    public function isSuggestedCartPage(): bool
    {
        $request = $this->getRequest();
        return $request->getModuleName() === 'erpintegration'
            && $request->getControllerName() === 'customer'
            && $request->getActionName() === 'suggestedcart';
    }

    /**
     * Get customer-specific price for a SKU
     *
     * Returns null if customer has the default (NACIONAL) list or no ERP code
     */
    public function getCustomerPrice(string $sku): ?float
    {
        $customerCode = $this->getErpCustomerCode();
        if (!$customerCode) {
            return null;
        }

        return $this->customerPriceProvider->getCustomerPrice($customerCode, $sku);
    }

    /**
     * Preload prices for multiple SKUs in a single ERP batch query.
     * Call this before a foreach loop that uses getCustomerPrice().
     * Subsequent calls to getCustomerPrice() will hit the in-memory cache.
     *
     * @param array $skus List of SKU strings
     */
    public function preloadCustomerPrices(array $skus): void
    {
        $customerCode = $this->getErpCustomerCode();
        if (!$customerCode || empty($skus)) {
            return;
        }
        // Populates the in-memory priceCache inside customerPriceProvider,
        // so subsequent getCustomerPrice() calls skip the ERP round-trip.
        $this->customerPriceProvider->getCustomerPrices($customerCode, $skus);
    }

    /**
     * Get customer's price list name (e.g. "010 - DEMA")
     */
    public function getCustomerPriceListName(): ?string
    {
        $customerCode = $this->getErpCustomerCode();
        if (!$customerCode) {
            return null;
        }

        return $this->customerPriceProvider->getCustomerPriceListName($customerCode);
    }

    /**
     * Check if customer has a specific (non-default) price list
     */
    public function hasCustomPriceList(): bool
    {
        $customerCode = $this->getErpCustomerCode();
        if (!$customerCode) {
            return false;
        }

        $listCode = $this->customerPriceProvider->getCustomerPriceListCode($customerCode);
        $defaultList = $this->helper->getDefaultPriceList();

        return $listCode !== null && $listCode !== $defaultList;
    }

    /**
     * Get Opportunity Classifier AJAX URL
     */
    public function getOpportunityClassifierUrl(): string
    {
        return $this->getUrl('erpintegration/customer/opportunityClassifier');
    }
}
