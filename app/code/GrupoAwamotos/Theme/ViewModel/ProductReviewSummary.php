<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Catalog\Block\Product\View as ProductViewBlock;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Review\Block\Product\ReviewRenderer;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ProductReviewSummary implements ArgumentInterface
{
    public function __construct(
        private readonly ProductStructuredData $productStructuredData,
        private readonly LayoutInterface $layout,
        private readonly RequestInterface $request,
        private readonly ReviewFactory $reviewFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Escaper $escaper
    ) {
    }

    public function getSummaryHtml(): string
    {
        return $this->getSummaryHtmlForProduct($this->resolveProduct());
    }

    public function getSummaryHtmlForProduct(?Product $product): string
    {
        $reviewsActive = $this->scopeConfig->isSetFlag('catalog/review/active', ScopeInterface::SCOPE_STORE);
        if (!$reviewsActive) {
            return '';
        }

        if (!$product instanceof Product || !$product->getId()) {
            return '';
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        $this->reviewFactory->create()->getEntitySummary($product, $storeId);

        /** @var ProductViewBlock $viewBlock */
        $viewBlock = $this->layout->createBlock(ProductViewBlock::class);
        $viewBlock->setProduct($product);

        $html = $viewBlock->getReviewsSummaryHtml($product, ReviewRenderer::SHORT_VIEW, false);
        $html = is_string($html) ? trim($html) : '';

        if ($html === '' || str_contains($html, 'short empty')) {
            $html = $this->buildNativeSummaryHtml($product);
        }

        return $html;
    }

    private function buildNativeSummaryHtml(Product $product): string
    {
        $reviewsCount = $this->extractReviewsCount($product);
        $ratingPercent = (int) round($this->extractRatingPercent($product));

        if ($reviewsCount <= 0 || $ratingPercent <= 0) {
            return '';
        }

        $reviewLabel = $reviewsCount === 1 ? __('Review') : __('Reviews');
        $url = $this->escaper->escapeUrl($product->getProductUrl() . '#reviews');

        return '<div class="product-reviews-summary short">'
            . '<div class="rating-summary">'
            . '<div class="rating-result" title="' . $ratingPercent . '%">'
            . '<span style="width:' . $ratingPercent . '%"><span>' . $ratingPercent . '%</span></span>'
            . '</div>'
            . '</div>'
            . '<div class="reviews-actions">'
            . '<a class="action view" href="' . $url . '">'
            . (int) $reviewsCount
            . '&nbsp;<span>' . $this->escaper->escapeHtml((string) $reviewLabel) . '</span>'
            . '</a>'
            . '</div>'
            . '</div>';
    }

    private function resolveProduct(): ?Product
    {
        $product = $this->productStructuredData->getCurrentProduct();
        if ($product instanceof Product && $product->getId()) {
            return $product;
        }

        $productInfo = $this->layout->getBlock('product.info');
        if ($productInfo && method_exists($productInfo, 'getProduct')) {
            $fromBlock = $productInfo->getProduct();
            if ($fromBlock instanceof Product && $fromBlock->getId()) {
                return $fromBlock;
            }
        }

        return null;
    }

    private function extractRatingPercent(Product $product): float
    {
        $ratingSummary = $product->getData('rating_summary');

        if (is_numeric($ratingSummary)) {
            return (float) $ratingSummary;
        }

        if ($ratingSummary instanceof \Magento\Framework\DataObject) {
            $summary = $ratingSummary->getData('rating_summary');

            return is_numeric($summary) ? (float) $summary : 0.0;
        }

        return 0.0;
    }

    private function extractReviewsCount(Product $product): int
    {
        $reviewsCount = $product->getData('reviews_count');

        if (is_numeric($reviewsCount)) {
            return (int) $reviewsCount;
        }

        $ratingSummary = $product->getData('rating_summary');
        if ($ratingSummary instanceof \Magento\Framework\DataObject) {
            $count = $ratingSummary->getData('reviews_count');

            return is_numeric($count) ? (int) $count : 0;
        }

        return 0;
    }
}
