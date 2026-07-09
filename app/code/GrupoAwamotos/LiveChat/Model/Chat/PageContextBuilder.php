<?php

declare(strict_types=1);

namespace GrupoAwamotos\LiveChat\Model\Chat;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;

class PageContextBuilder
{
    private const MAX_VALUE_LENGTH = 500;

    /**
     * @var array<int, array{name: string, value: string}>|null
     */
    private ?array $pageVariablesCache = null;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly Registry $registry,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return array<int, array{name: string, value: string}>
     */
    public function build(): array
    {
        if ($this->pageVariablesCache !== null) {
            return $this->pageVariablesCache;
        }

        $variables = [];

        $this->appendVariable($variables, 'Tipo de pagina', $this->resolvePageType());
        $this->appendVariable($variables, 'Store view', $this->storeManager->getStore()->getCode());

        $product = $this->getCurrentProduct();
        if ($product !== null) {
            $this->appendProductVariables($variables, $product);
        }

        $category = $this->getCurrentCategory();
        if ($category !== null) {
            $this->appendVariable($variables, 'Categoria atual', (string) $category->getName());
        }

        $searchQuery = trim((string) $this->request->getParam('q', ''));
        if ($searchQuery !== '') {
            $this->appendVariable($variables, 'Busca', $searchQuery);
        }

        $this->pageVariablesCache = $variables;

        return $this->pageVariablesCache;
    }

    /**
     * @param array<int, array{name: string, value: string}> $variables
     */
    private function appendProductVariables(array &$variables, ProductInterface $product): void
    {
        $this->appendVariable($variables, 'Produto', (string) $product->getName());
        $this->appendVariable($variables, 'SKU do produto', (string) $product->getSku());
        $this->appendVariable($variables, 'Marca do produto', $this->getProductBrand($product));
    }

    private function getCurrentProduct(): ?ProductInterface
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof ProductInterface ? $product : null;
    }

    private function getCurrentCategory(): ?CategoryInterface
    {
        $category = $this->registry->registry('current_category');
        return $category instanceof CategoryInterface ? $category : null;
    }

    private function getProductBrand(ProductInterface $product): ?string
    {
        $manufacturer = $product->getAttributeText('manufacturer');

        if (is_array($manufacturer)) {
            $manufacturer = implode(', ', array_filter($manufacturer));
        }

        $manufacturer = is_string($manufacturer) ? trim($manufacturer) : '';

        return $manufacturer !== '' ? $manufacturer : null;
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

        return $this->truncateValue($value);
    }

    private function truncateValue(string $value): string
    {
        if (mb_strlen($value) <= self::MAX_VALUE_LENGTH) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, self::MAX_VALUE_LENGTH - 1)) . '…';
    }

    private function resolvePageType(): string
    {
        return match ($this->request->getFullActionName()) {
            'cms_index_index' => 'Home',
            'catalog_product_view' => 'Produto',
            'catalog_category_view' => 'Categoria',
            'catalogsearch_result_index' => 'Busca',
            'checkout_cart_index' => 'Carrinho',
            'checkout_index_index' => 'Checkout',
            'customer_account_login' => 'Login',
            'customer_account_create' => 'Cadastro',
            default => 'Pagina interna',
        };
    }
}
