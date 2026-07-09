<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\App\CacheInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Product Suggestion Model
 *
 * Generates product suggestions based on customer purchase history
 */
class ProductSuggestion
{
    private const CACHE_PREFIX = 'erp_suggestions_';
    private const CACHE_TTL = 1800; // 30 minutes

    private ConnectionInterface $connection;
    private PurchaseHistory $purchaseHistory;
    private Helper $helper;
    private CacheInterface $cache;
    private ProductRepositoryInterface $productRepository;
    private ImageHelper $imageHelper;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private StoreManagerInterface $storeManager;
    private LoggerInterface $logger;

    public function __construct(
        ConnectionInterface $connection,
        PurchaseHistory $purchaseHistory,
        Helper $helper,
        CacheInterface $cache,
        ProductRepositoryInterface $productRepository,
        ImageHelper $imageHelper,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->purchaseHistory = $purchaseHistory;
        $this->helper = $helper;
        $this->cache = $cache;
        $this->productRepository = $productRepository;
        $this->imageHelper = $imageHelper;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Get product suggestions for a customer
     *
     * Algorithm OTIMIZADO:
     * 1. Pega os TOP 10 produtos mais comprados pelo cliente
     * 2. Encontra TOP 50 clientes similares (que compraram os mesmos produtos)
     * 3. Busca produtos que esses clientes compraram que este cliente não comprou
     * 4. Ranqueia por número de clientes que compraram
     */
    public function getSuggestions(int $customerCode, int $limit = 10): array
    {
        if (!$this->helper->isSuggestionsEnabled()) {
            return [];
        }

        $cacheKey = self::CACHE_PREFIX . $customerCode;
        $cached = $this->cache->load($cacheKey);

        if ($cached) {
            return json_decode($cached, true);
        }

        try {
            // OTIMIZAÇÃO: Query única simplificada com limites em cada etapa
            $suggestions = $this->connection->query("
                WITH TopProducts AS (
                    -- TOP 10 produtos mais comprados pelo cliente
                    SELECT TOP 10 i.MATERIAL
                    FROM VE_PEDIDOITENS i
                    INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                    WHERE p.CLIENTE = ?
                    AND p.STATUS NOT IN ('C', 'X')
                    GROUP BY i.MATERIAL
                    ORDER BY SUM(i.QTDE) DESC
                ),
                SimilarCustomers AS (
                    -- TOP 50 clientes que compraram os mesmos produtos
                    SELECT TOP 50 p.CLIENTE
                    FROM VE_PEDIDO p
                    INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                    WHERE i.MATERIAL IN (SELECT MATERIAL FROM TopProducts)
                    AND p.CLIENTE <> ?
                    AND p.STATUS NOT IN ('C', 'X')
                    GROUP BY p.CLIENTE
                    ORDER BY COUNT(DISTINCT i.MATERIAL) DESC
                ),
                CustomerProducts AS (
                    -- Produtos que o cliente já comprou
                    SELECT DISTINCT i.MATERIAL
                    FROM VE_PEDIDOITENS i
                    INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                    WHERE p.CLIENTE = ?
                    AND p.STATUS NOT IN ('C', 'X')
                )
                SELECT TOP " . (int)$limit . "
                    i2.MATERIAL as codigo_material,
                    MAX(i2.DESCRICAO) as descricao,
                    COUNT(DISTINCT p2.CLIENTE) as clientes_compraram,
                    SUM(i2.QTDE) as quantidade_total,
                    AVG(i2.VLRUNITARIO) as preco_medio
                FROM VE_PEDIDOITENS i2
                INNER JOIN VE_PEDIDO p2 ON i2.PEDIDO = p2.CODIGO
                WHERE p2.CLIENTE IN (SELECT CLIENTE FROM SimilarCustomers)
                AND i2.MATERIAL NOT IN (SELECT MATERIAL FROM CustomerProducts)
                AND p2.STATUS NOT IN ('C', 'X')
                AND p2.DTPEDIDO >= DATEADD(year, -2, GETDATE())
                GROUP BY i2.MATERIAL
                ORDER BY COUNT(DISTINCT p2.CLIENTE) DESC
            ", [$customerCode, $customerCode, $customerCode]);

            // Enrich with Magento product data
            $enrichedSuggestions = $this->enrichWithMagentoData($suggestions);

            $this->cache->save(json_encode($enrichedSuggestions), $cacheKey, [], self::CACHE_TTL);

            return $enrichedSuggestions;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error getting suggestions: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get reorder suggestions (products customer bought before that might need reordering)
     */
    public function getReorderSuggestions(int $customerCode, int $limit = 10): array
    {
        // PERF-2026-07-04: self-join com subquery (prev) para calcular intervalo
        // médio entre compras por item — pesado no ERP remoto. Chamado direto no
        // render síncrono do dashboard B2B (dashboard.phtml) para todo cliente com
        // código ERP. Sem cache, cada carregamento do dashboard pagava essa query
        // inteira de novo. Mesmo padrão de cache de getSuggestions()/getTrendingProducts()
        // nesta mesma classe.
        $cacheKey = self::CACHE_PREFIX . 'reorder_' . $customerCode . '_' . $limit;
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return json_decode($cached, true) ?: [];
        }

        try {
            // Get products with their purchase frequency
            $products = $this->connection->query("
                SELECT TOP " . (int)$limit . "
                    i.MATERIAL as codigo_material,
                    i.DESCRICAO as descricao,
                    COUNT(DISTINCT i.PEDIDO) as vezes_comprado,
                    SUM(i.QTDE) as quantidade_total,
                    AVG(i.QTDE) as quantidade_media_pedido,
                    MAX(p.DTPEDIDO) as ultima_compra,
                    DATEDIFF(day, MAX(p.DTPEDIDO), GETDATE()) as dias_desde_ultima,
                    AVG(DATEDIFF(day, prev.DTPEDIDO, p.DTPEDIDO)) as media_dias_entre_compras
                FROM VE_PEDIDOITENS i
                INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                LEFT JOIN (
                    SELECT i2.MATERIAL, p2.DTPEDIDO, p2.CLIENTE
                    FROM VE_PEDIDOITENS i2
                    INNER JOIN VE_PEDIDO p2 ON i2.PEDIDO = p2.CODIGO
                    WHERE p2.STATUS NOT IN ('C', 'X')
                ) prev ON prev.MATERIAL = i.MATERIAL
                    AND prev.CLIENTE = p.CLIENTE
                    AND prev.DTPEDIDO < p.DTPEDIDO
                WHERE p.CLIENTE = ?
                AND p.STATUS NOT IN ('C', 'X')
                GROUP BY i.MATERIAL, i.DESCRICAO
                HAVING COUNT(DISTINCT i.PEDIDO) >= 2
                AND DATEDIFF(day, MAX(p.DTPEDIDO), GETDATE()) >=
                    COALESCE(AVG(DATEDIFF(day, prev.DTPEDIDO, p.DTPEDIDO)), 30)
                ORDER BY DATEDIFF(day, MAX(p.DTPEDIDO), GETDATE()) DESC
            ", [$customerCode]);

            $enriched = $this->enrichWithMagentoData($products);
            $this->cache->save(json_encode($enriched), $cacheKey, [], self::CACHE_TTL);

            return $enriched;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error getting reorder suggestions: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get trending products (most sold in last 30 days)
     */
    public function getTrendingProducts(int $limit = 10): array
    {
        $cacheKey = self::CACHE_PREFIX . 'trending';
        $cached = $this->cache->load($cacheKey);

        if ($cached) {
            return json_decode($cached, true);
        }

        try {
            $products = $this->connection->query("
                SELECT TOP " . (int)$limit . "
                    i.MATERIAL as codigo_material,
                    i.DESCRICAO as descricao,
                    COUNT(DISTINCT p.CLIENTE) as clientes_compraram,
                    COUNT(DISTINCT i.PEDIDO) as total_pedidos,
                    SUM(i.QTDE) as quantidade_total,
                    AVG(i.VLRUNITARIO) as preco_medio
                FROM VE_PEDIDOITENS i
                INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                WHERE p.STATUS NOT IN ('C', 'X')
                AND p.DTPEDIDO >= DATEADD(day, -30, GETDATE())
                GROUP BY i.MATERIAL, i.DESCRICAO
                ORDER BY SUM(i.QTDE) DESC
            ");

            $enriched = $this->enrichWithMagentoData($products);

            $this->cache->save(json_encode($enriched), $cacheKey, [], self::CACHE_TTL);

            return $enriched;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error getting trending products: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get complementary products (frequently bought together)
     */
    public function getComplementaryProducts(string $materialCode, int $limit = 5): array
    {
        $cacheKey = self::CACHE_PREFIX . 'complementary_' . hash('xxh128', $materialCode);
        $cached = $this->cache->load($cacheKey);

        if ($cached) {
            return json_decode($cached, true);
        }

        try {
            $products = $this->connection->query("
                SELECT TOP " . (int)$limit . "
                    i2.MATERIAL as codigo_material,
                    i2.DESCRICAO as descricao,
                    COUNT(DISTINCT i2.PEDIDO) as vezes_comprado_junto
                FROM VE_PEDIDOITENS i2
                WHERE i2.PEDIDO IN (
                    SELECT i.PEDIDO
                    FROM VE_PEDIDOITENS i
                    INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                    WHERE i.MATERIAL = ?
                    AND p.STATUS NOT IN ('C', 'X')
                )
                AND i2.MATERIAL <> ?
                GROUP BY i2.MATERIAL, i2.DESCRICAO
                ORDER BY COUNT(DISTINCT i2.PEDIDO) DESC
            ", [$materialCode, $materialCode]);

            $enriched = $this->enrichWithMagentoData($products);

            $this->cache->save(json_encode($enriched), $cacheKey, [], self::CACHE_TTL);

            return $enriched;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error getting complementary products: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Enrich ERP data with Magento product information
     *
     * Handles:
     * - Full image URLs (not just relative paths)
     * - SKU variant matching (e.g. "1119 RS" → tries "1119" as base SKU)
     * - Resized thumbnail via Magento image helper
     */
    public function enrichWithMagentoData(array $erpProducts): array
    {
        if (empty($erpProducts)) {
            return [];
        }

        // Collect all SKUs + base SKU variants for broader matching
        $skus = array_column($erpProducts, 'codigo_material');
        $baseSkus = [];
        foreach ($skus as $sku) {
            $base = $this->getBaseSku($sku);
            if ($base !== $sku) {
                $baseSkus[$base] = $sku; // base → original mapping
            }
        }

        $allSkusToSearch = array_unique(array_merge($skus, array_keys($baseSkus)));

        try {
            $magentoProductsBySku = $this->loadMagentoProducts($allSkusToSearch);
        } catch (\Exception $e) {
            $this->logger->warning('[ERP] Could not enrich with Magento data: ' . $e->getMessage());
            $magentoProductsBySku = [];
        }

        // Merge ERP and Magento data
        $enriched = [];
        foreach ($erpProducts as $product) {
            $sku = $product['codigo_material'];
            $enrichedProduct = $product;

            // Try exact SKU first, then base SKU
            $magentoData = $magentoProductsBySku[$sku] ?? null;
            if (!$magentoData) {
                $base = $this->getBaseSku($sku);
                $magentoData = $magentoProductsBySku[$base] ?? null;
            }

            if ($magentoData) {
                $enrichedProduct['magento'] = $magentoData;
                $enrichedProduct['available_in_store'] = true;
            } else {
                $enrichedProduct['magento'] = null;
                $enrichedProduct['available_in_store'] = false;
            }

            $enriched[] = $enrichedProduct;
        }

        return $enriched;
    }

    /**
     * Load Magento products by SKU and build enrichment array with full image URLs
     *
     * Uses direct product loading (not getList) to include disabled products
     * that exist in the catalog but aren't active in the storefront.
     */
    private function loadMagentoProducts(array $skus): array
    {
        $result = [];
        $mediaUrl = $this->storeManager->getStore()
            ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $catalogMediaUrl = $mediaUrl . 'catalog/product';

        foreach ($skus as $sku) {
            try {
                $product = $this->productRepository->get($sku);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                continue;
            }

            $imageRelative = $product->getImage();
            $thumbnailRelative = $product->getThumbnail() ?: $product->getSmallImage() ?: $imageRelative;

            // Build full image URL
            $imageUrl = null;
            if ($imageRelative && $imageRelative !== 'no_selection') {
                $imageUrl = $catalogMediaUrl . $imageRelative;
            }

            // Build resized thumbnail URL via image helper (240x240)
            $thumbnailUrl = null;
            if ($thumbnailRelative && $thumbnailRelative !== 'no_selection') {
                try {
                    $thumbnailUrl = $this->imageHelper
                        ->init($product, 'category_page_grid')
                        ->setImageFile($thumbnailRelative)
                        ->resize(240, 240)
                        ->getUrl();
                } catch (\Exception $e) {
                    $thumbnailUrl = $catalogMediaUrl . $thumbnailRelative;
                }
            }

            $isEnabled = (int)$product->getStatus() === \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED;

            $result[$product->getSku()] = [
                'entity_id' => $product->getId(),
                'name' => $product->getName(),
                'url_key' => $product->getUrlKey(),
                'product_url' => $product->getProductUrl(),
                'price' => $product->getPrice(),
                'final_price' => $product->getFinalPrice(),
                'image' => $thumbnailUrl ?: $imageUrl,
                'image_full' => $imageUrl,
                'status' => $product->getStatus(),
                'in_stock' => $isEnabled && $product->isAvailable(),
            ];
        }

        return $result;
    }

    /**
     * Extract base SKU from variant SKU
     *
     * ERP SKUs may have color/variant suffixes:
     * "1119 RS" → "1119", "0091 AZ" → "0091", "1125NAO" → "1125"
     */
    private function getBaseSku(string $sku): string
    {
        $sku = trim($sku);

        // If contains space, take part before first space
        if (str_contains($sku, ' ')) {
            return trim(explode(' ', $sku)[0]);
        }

        // If ends with 2-3 letter alpha suffix (not all alpha), strip it
        if (preg_match('/^(\d{3,})[A-Z]{2,3}$/i', $sku, $m)) {
            return $m[1];
        }

        return $sku;
    }

    /**
     * Clear suggestion cache for a customer
     */
    public function clearCache(int $customerCode): void
    {
        $this->cache->remove(self::CACHE_PREFIX . $customerCode);
        foreach ([5, 6, 10, 12, 20] as $limit) {
            $this->cache->remove(self::CACHE_PREFIX . 'reorder_' . $customerCode . '_' . $limit);
        }
    }

    /**
     * Clear all suggestion caches
     */
    public function clearAllCache(): void
    {
        // This would require a more sophisticated cache implementation
        // For now, trending products cache will expire naturally
        $this->cache->remove(self::CACHE_PREFIX . 'trending');
    }
}
