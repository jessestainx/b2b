<?php

/**
 * Related Products Block
 *
 * Sugere produtos da mesma categoria ignorando relações manuais.
 * Preços só são exibidos para clientes B2B aprovados (mesma regra do PDP principal).
 */

declare(strict_types=1);

namespace GrupoAwamotos\RelatedProducts\Block\Product;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\Catalog\Model\Product as Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Registry;
use Magento\Framework\Url\Helper\Data as UrlHelper;

class Related extends AbstractProduct
{
    private const MAX_ITEMS = 8;

    /** Over-fetch so we can drop items without catalog images and still fill MAX_ITEMS. */
    private const CANDIDATE_ITEMS = 32;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * @var PriceVisibilityInterface
     */
    private PriceVisibilityInterface $priceVisibility;

    /**
     * @var Visibility
     */
    private Visibility $catalogProductVisibility;

    /**
     * @var CatalogConfig
     */
    private CatalogConfig $catalogConfig;

    /**
     * @var StockHelper
     */
    private StockHelper $stockHelper;

    /**
     * @var UrlHelper
     */
    private UrlHelper $urlHelper;

    /**
     * @var Collection|null
     */
    private ?Collection $collection = null;

    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        Registry $registry,
        PriceVisibilityInterface $priceVisibility,
        Visibility $catalogProductVisibility,
        CatalogConfig $catalogConfig,
        StockHelper $stockHelper,
        UrlHelper $urlHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->collectionFactory = $collectionFactory;
        $this->registry = $registry;
        $this->priceVisibility = $priceVisibility;
        $this->catalogProductVisibility = $catalogProductVisibility;
        $this->catalogConfig = $catalogConfig;
        $this->stockHelper = $stockHelper;
        $this->urlHelper = $urlHelper;
    }

    /**
     * Returns up to MAX_ITEMS products from the same categories as the current product.
     *
     * @return Collection
     */
    public function getRelatedCollection(): Collection
    {
        if ($this->collection !== null) {
            return $this->collection;
        }

        $currentProduct = $this->registry->registry('current_product');
        if (!$currentProduct) {
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('entity_id', ['null' => true]);
            $this->collection = $collection;
            return $this->collection;
        }

        $categoryIds = $currentProduct->getCategoryIds();
        if (empty($categoryIds)) {
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('entity_id', ['null' => true]);
            $this->collection = $collection;
            return $this->collection;
        }

        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect($this->catalogConfig->getProductAttributes());
        $collection->addAttributeToSelect(['image', 'small_image', 'thumbnail']);
        $collection->addMinimalPrice();
        $collection->addFinalPrice();
        $collection->addTaxPercents();
        $collection->addCategoriesFilter(['in' => $categoryIds]);
        $collection->addFieldToFilter('entity_id', ['neq' => $currentProduct->getId()]);
        $collection->setVisibility($this->catalogProductVisibility->getVisibleInCatalogIds());
        $collection->addWebsiteFilter();
        $collection->setPageSize(self::CANDIDATE_ITEMS);
        $collection->setOrder('updated_at', 'DESC');

        // Carrega status de estoque para que isSaleable() funcione corretamente em cada produto
        $this->stockHelper->addStockStatusToProducts($collection);

        $this->collection = $this->filterProductsWithCatalogImage($collection);
        return $this->collection;
    }

    /**
     * Keep only products that have a real catalog image (not Magento placeholder).
     *
     * SKUs without image/small_image/thumbnail (null or no_selection) otherwise
     * render Magento_Catalog/.../placeholder/small_image.jpg in the PDP shelf.
     */
    private function filterProductsWithCatalogImage(Collection $collection): Collection
    {
        $keepIds = [];
        foreach ($collection as $product) {
            if (!$product instanceof Product) {
                continue;
            }
            if (!$this->hasUsableCatalogImage($product)) {
                continue;
            }
            $keepIds[] = (int) $product->getId();
            if (count($keepIds) >= self::MAX_ITEMS) {
                break;
            }
        }

        if ($keepIds === []) {
            $empty = $this->collectionFactory->create();
            $empty->addFieldToFilter('entity_id', ['null' => true]);
            return $empty;
        }

        $ordered = $this->collectionFactory->create();
        $ordered->addAttributeToSelect($this->catalogConfig->getProductAttributes());
        $ordered->addAttributeToSelect(['image', 'small_image', 'thumbnail']);
        $ordered->addMinimalPrice();
        $ordered->addFinalPrice();
        $ordered->addTaxPercents();
        $ordered->addFieldToFilter('entity_id', ['in' => $keepIds]);
        $ordered->setVisibility($this->catalogProductVisibility->getVisibleInCatalogIds());
        $ordered->addWebsiteFilter();
        $ordered->getSelect()->order(
            new Expression('FIELD(e.entity_id,' . implode(',', $keepIds) . ')')
        );
        $this->stockHelper->addStockStatusToProducts($ordered);

        return $ordered;
    }

    /**
     * @param Product $product
     */
    private function hasUsableCatalogImage(Product $product): bool
    {
        foreach (['small_image', 'thumbnail', 'image'] as $attributeCode) {
            $value = trim((string) $product->getData($attributeCode));
            if ($value !== '' && $value !== 'no_selection') {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true when the current customer is allowed to view prices.
     * Delegates entirely to the B2B PriceVisibility service.
     *
     * @return bool
     */
    public function canShowPrice(): bool
    {
        return $this->priceVisibility->canViewPrices();
    }

    /**
     * Returns true when the current context allows price rendering for the given product.
     *
     * Product-level checks can be added later without changing templates.
     *
     * @param Product $product
     * @return bool
     */
    public function canViewProductPrice(Product $product): bool
    {
        return $this->priceVisibility->canViewPrices();
    }

    /**
     * Always enforce B2B price gate for this related carousel block.
     * Keeps price hidden for visitors even if template cache/plugins vary at render time.
     *
     * @param Product $product
     * @return string
     */
    public function getProductPrice(Product $product): string
    {
        if (!$this->priceVisibility->canViewPrices()) {
            return '<div class="b2b-login-to-see-price">'
                . '<span class="price-label">'
                . $this->priceVisibility->getPriceReplacementMessage()
                . '</span>'
                . '</div>';
        }

        return parent::getProductPrice($product);
    }

    /**
     * Returns the message to display in place of the price for non-approved customers.
     *
     * @return string
     */
    public function getPriceReplacementMessage(): string
    {
        return $this->priceVisibility->getPriceReplacementMessage();
    }

    /**
     * Returns POST params for the add-to-cart form.
     *
     * @param Product $product
     * @return array<string, mixed>
     */
    public function getAddToCartPostParams(Product $product): array
    {
        $url = $this->getAddToCartUrl($product, ['_escape' => false]);
        return [
            'action' => $url,
            'data' => [
                'product' => (int) $product->getEntityId(),
                ActionInterface::PARAM_NAME_URL_ENCODED => $this->urlHelper->getEncodedUrl($url),
            ],
        ];
    }
}
