<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

use GrupoAwamotos\ERPIntegration\Api\PriceSyncInterface;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\Action as ProductAction;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

/**
 * Price Sync — MT_MATERIALLISTA (ERP) → Magento
 *
 * Uses the ERP price list table (MT_MATERIALLISTA) which has 74k+ rows
 * across 25 price lists, instead of the old MT_COMPOSICAOPRECO (69 rows).
 *
 * Default list: NACIONAL (#24) — 3,341 items, largest coverage.
 * Price field: VLRVDSUG (suggested sale price).
 */
class PriceSync implements PriceSyncInterface
{
    private const BATCH_SIZE = 500;

    private ConnectionInterface $connection;
    private Helper $helper;
    private ProductRepositoryInterface $productRepository;
    private ProductAction $productAction;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private SyncLogResource $syncLogResource;
    private LoggerInterface $logger;

    public function __construct(
        ConnectionInterface $connection,
        Helper $helper,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SyncLogResource $syncLogResource,
        LoggerInterface $logger,
        ProductAction $productAction
    ) {
        $this->connection = $connection;
        $this->helper = $helper;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->syncLogResource = $syncLogResource;
        $this->logger = $logger;
        $this->productAction = $productAction;
    }

    public function getPriceBySku(string $sku): ?array
    {
        return $this->getErpPrice($sku);
    }

    /**
     * Get price data from ERP for a specific SKU using MT_MATERIALLISTA
     */
    public function getErpPrice(string $sku, ?int $priceList = null): ?array
    {
        try {
            $fatorPreco = $priceList ?? $this->helper->getDefaultPriceList();
            $filial = $this->helper->getStockFilial();

            $sql = "SELECT
                        m.MATERIAL as CODIGO,
                        m.VLRVDSUG as VLRVENDA,
                        m.VLRVDMIN,
                        m.VLRVDMAX,
                        m.VLRCUSTO,
                        m.VLRTABELA,
                        m.FATORPRECO
                    FROM MT_MATERIALLISTA m
                    WHERE m.MATERIAL = ?
                    AND m.FATORPRECO = ?
                    AND m.FILIAL = ?";

            $result = $this->connection->fetchOne($sql, [$sku, $fatorPreco, $filial]);

            // If not found with exact SKU, try base SKU
            if (!$result) {
                $baseSku = $this->getBaseSku($sku);
                if ($baseSku !== $sku) {
                    $result = $this->connection->fetchOne($sql, [$baseSku, $fatorPreco, $filial]);
                }
            }

            return $result ?: null;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Price query error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get prices from ERP with pagination using MT_MATERIALLISTA
     */
    public function getErpPrices(int $limit = 100, int $offset = 0, ?int $priceList = null): array
    {
        try {
            $fatorPreco = $priceList ?? $this->helper->getDefaultPriceList();
            $filial = $this->helper->getStockFilial();

            $sql = "SELECT
                        m.MATERIAL as CODIGO,
                        m.VLRVDSUG as VLRVENDA,
                        m.VLRVDMIN,
                        m.VLRVDMAX,
                        m.VLRCUSTO,
                        m.FATORPRECO
                    FROM MT_MATERIALLISTA m
                    WHERE m.FATORPRECO = ?
                    AND m.FILIAL = ?
                    AND m.VLRVDSUG > 0
                    ORDER BY m.MATERIAL
                    OFFSET {$offset} ROWS FETCH NEXT {$limit} ROWS ONLY";

            return $this->connection->query($sql, [$fatorPreco, $filial]) ?? [];
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Price list query error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all available price lists from ERP
     */
    public function getAvailablePriceLists(): array
    {
        try {
            return $this->connection->query("
                SELECT
                    f.CODIGO,
                    f.DESCRICAO,
                    f.CKATIVO as ATIVO,
                    (SELECT COUNT(DISTINCT m.MATERIAL)
                     FROM MT_MATERIALLISTA m
                     WHERE m.FATORPRECO = f.CODIGO AND m.VLRVDSUG > 0) as total_produtos,
                    (SELECT COUNT(DISTINCT fn.CODIGO)
                     FROM FN_FORNECEDORES fn
                     WHERE fn.FATORPRECO = f.CODIGO) as total_clientes
                FROM VE_FATORPRECO f
                ORDER BY f.CKATIVO DESC,
                    (SELECT COUNT(DISTINCT m2.MATERIAL)
                     FROM MT_MATERIALLISTA m2
                     WHERE m2.FATORPRECO = f.CODIGO AND m2.VLRVDSUG > 0) DESC
            ") ?? [];
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error fetching price lists: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Sync price for a specific SKU
     */
    public function syncBySku(string $sku, ?int $priceList = null): bool
    {
        try {
            $priceData = $this->getErpPrice($sku, $priceList);

            if (!$priceData) {
                return false;
            }

            $price = (float) ($priceData['VLRVENDA'] ?? 0);
            if ($price <= 0) {
                return false;
            }

            $product = $this->productRepository->get($sku);
            $currentPrice = (float) $product->getPrice();

            if (abs($currentPrice - $price) < 0.01) {
                return true; // already up to date
            }

            $product->setPrice($price);

            // Set cost if available
            $cost = (float) ($priceData['VLRCUSTO'] ?? 0);
            if ($cost > 0) {
                $product->setCustomAttribute('cost', $cost);
            }

            // Set MSRP (compare-at price) from VLRVDMAX if significantly different
            $maxPrice = (float) ($priceData['VLRVDMAX'] ?? 0);
            if ($maxPrice > $price * 1.05) {
                $product->setCustomAttribute('msrp', $maxPrice);
            }

            $this->productRepository->save($product);

            $this->syncLogResource->addLog(
                'price',
                'import',
                'success',
                sprintf(
                    'Preco atualizado SKU %s: R$ %.2f → R$ %.2f (Lista #%d)',
                    $sku,
                    $currentPrice,
                    $price,
                    $priceData['FATORPRECO'] ?? 0
                ),
                $sku,
                null,
                1
            );

            return true;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $this->logger->warning("[ERP] Product not found for price sync: {$sku}");
            return false;
        } catch (\Exception $e) {
            $this->logger->error("[ERP] Price sync error for SKU {$sku}: " . $e->getMessage());
            $this->syncLogResource->addLog('price', 'import', 'error', $e->getMessage(), $sku);
            return false;
        }
    }

    /**
     * Full price sync: MT_MATERIALLISTA → Magento
     *
     * Processes in batches. Uses base-SKU matching for variants.
     */
    public function syncAll(?int $priceList = null): array
    {
        $result = ['updated' => 0, 'errors' => 0, 'skipped' => 0, 'not_found' => 0];
        $fatorPreco = $priceList ?? $this->helper->getDefaultPriceList();
        $filial = $this->helper->getStockFilial();

        try {
            // 1. Get all ERP prices for the selected price list
            $sql = "SELECT
                        m.MATERIAL,
                        m.VLRVDSUG as VLRVENDA,
                        m.VLRVDMIN,
                        m.VLRVDMAX,
                        m.VLRCUSTO
                    FROM MT_MATERIALLISTA m
                    WHERE m.FATORPRECO = ?
                    AND m.FILIAL = ?
                    AND m.VLRVDSUG > 0
                    ORDER BY m.MATERIAL";

            $rows = $this->connection->query($sql, [$fatorPreco, $filial]);

            if (empty($rows)) {
                $this->logger->warning("[ERP] No prices found for list #{$fatorPreco}, filial {$filial}");
                return $result;
            }

            $this->logger->info(sprintf(
                '[ERP] Price sync starting: %d ERP prices from list #%d',
                count($rows),
                $fatorPreco
            ));

            // 2. Build SKU → price map (including base-SKU variants)
            $erpPriceMap = [];
            foreach ($rows as $row) {
                $sku = trim($row['MATERIAL']);
                $erpPriceMap[$sku] = $row;

                // Also map base SKU if different
                $baseSku = $this->getBaseSku($sku);
                if ($baseSku !== $sku && !isset($erpPriceMap[$baseSku])) {
                    $erpPriceMap[$baseSku] = $row;
                }
            }

            // 3. Load all Magento products in batches and update
            $offset = 0;
            while (true) {
                $searchCriteria = $this->searchCriteriaBuilder
                    ->setPageSize(self::BATCH_SIZE)
                    ->setCurrentPage((int) ($offset / self::BATCH_SIZE) + 1)
                    ->create();

                $productList = $this->productRepository->getList($searchCriteria);
                $products = $productList->getItems();

                if (empty($products)) {
                    break;
                }

                foreach ($products as $product) {
                    $sku = $product->getSku();
                    $priceRow = $erpPriceMap[$sku] ?? null;

                    // Try base SKU if exact match not found
                    if (!$priceRow) {
                        $baseSku = $this->getBaseSku($sku);
                        $priceRow = $erpPriceMap[$baseSku] ?? null;
                    }

                    if (!$priceRow) {
                        $result['not_found']++;
                        continue;
                    }

                    $newPrice = (float) $priceRow['VLRVENDA'];
                    if ($newPrice <= 0) {
                        $result['skipped']++;
                        continue;
                    }

                    $currentPrice = (float) $product->getPrice();

                    // Skip if price hasn't changed
                    if (abs($currentPrice - $newPrice) < 0.01) {
                        $result['skipped']++;
                        continue;
                    }

                    try {
                        $attrData = ['price' => $newPrice];

                        $cost = (float) ($priceRow['VLRCUSTO'] ?? 0);
                        if ($cost > 0) {
                            $attrData['cost'] = $cost;
                        }

                        $maxPrice = (float) ($priceRow['VLRVDMAX'] ?? 0);
                        if ($maxPrice > $newPrice * 1.05) {
                            $attrData['msrp'] = $maxPrice;
                        }

                        $this->productAction->updateAttributes(
                            [$product->getId()],
                            $attrData,
                            0  // admin store (global scope)
                        );
                        $result['updated']++;
                    } catch (\Exception $e) {
                        $result['errors']++;
                        $this->logger->error(sprintf(
                            '[ERP] Price sync error SKU %s: %s',
                            $sku,
                            $e->getMessage()
                        ));
                    }
                }

                $offset += self::BATCH_SIZE;

                // Check if we've processed all products
                if ($offset >= $productList->getTotalCount()) {
                    break;
                }
            }

            $this->syncLogResource->addLog(
                'price',
                'import',
                $result['errors'] > 0 ? 'error' : 'success',
                sprintf(
                    'Lista #%d: Atualizados: %d, Erros: %d, Sem alteracao: %d, Sem preco ERP: %d',
                    $fatorPreco,
                    $result['updated'],
                    $result['errors'],
                    $result['skipped'],
                    $result['not_found']
                ),
                null,
                null,
                $result['updated']
            );
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Price sync failed: ' . $e->getMessage());
            $this->syncLogResource->addLog('price', 'import', 'error', $e->getMessage());
        }

        return $result;
    }

    /**
     * Get customer's price list code from ERP
     */
    public function getCustomerPriceList(int $customerCode): ?int
    {
        try {
            $row = $this->connection->fetchOne(
                "SELECT FATORPRECO FROM FN_FORNECEDORES WHERE CODIGO = ?",
                [$customerCode]
            );

            if ($row && !empty($row['FATORPRECO'])) {
                return (int) $row['FATORPRECO'];
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error getting customer price list: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get prices for multiple SKUs from a specific price list
     */
    public function getPricesForSkus(array $skus, ?int $priceList = null): array
    {
        if (empty($skus)) {
            return [];
        }

        try {
            $fatorPreco = $priceList ?? $this->helper->getDefaultPriceList();
            $filial = $this->helper->getStockFilial();

            // Build IN clause with positional parameters
            $placeholders = [];
            $params = [$fatorPreco, $filial];
            foreach ($skus as $sku) {
                $placeholders[] = '?';
                $params[] = trim($sku);
            }
            $inClause = implode(',', $placeholders);

            $sql = "SELECT
                        m.MATERIAL as CODIGO,
                        m.VLRVDSUG as VLRVENDA,
                        m.VLRVDMIN,
                        m.VLRVDMAX,
                        m.VLRCUSTO
                    FROM MT_MATERIALLISTA m
                    WHERE m.FATORPRECO = ?
                    AND m.FILIAL = ?
                    AND m.MATERIAL IN ({$inClause})
                    AND m.VLRVDSUG > 0";

            $rows = $this->connection->query($sql, $params) ?? [];

            $result = [];
            foreach ($rows as $row) {
                $result[trim($row['CODIGO'])] = (float) $row['VLRVENDA'];
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error fetching prices for SKUs: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Backward-compatible alias for delta and external callers.
     */
    public function getPricesBySkus(array $skus, ?int $priceList = null): array
    {
        return $this->getPricesForSkus($skus, $priceList);
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

        // Space-separated variant: "1119 RS" → "1119"
        if (str_contains($sku, ' ')) {
            return trim(explode(' ', $sku)[0]);
        }

        // Dot-separated variant: "0045.01" → "0045", "2213.00" → "2213"
        if (preg_match('/^(\d{3,})\.\d+$/', $sku, $m)) {
            return $m[1];
        }

        // Alpha suffix variant: "1125NAO" → "1125"
        if (preg_match('/^(\d{3,})[A-Z]{2,3}$/i', $sku, $m)) {
            return $m[1];
        }

        return $sku;
    }
}
