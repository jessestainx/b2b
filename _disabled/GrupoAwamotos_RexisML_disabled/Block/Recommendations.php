<?php

declare(strict_types=1);

/**
 * Block para exibir recomendações personalizadas no frontend
 * Integrado com dados reais do pipeline ERP → RexisML
 */

namespace GrupoAwamotos\RexisML\Block;

use GrupoAwamotos\RexisML\Model\Product\BulkSkuProductLoader;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Block\Product\ListProduct;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

class Recommendations extends Template
{
    protected $customerSession;
    protected $customerRepository;
    protected $resource;
    protected $bulkSkuProductLoader;
    protected $listProductBlock;
    protected $scopeConfig;
    protected $logger;

    private $cachedProducts = null;
    private $cachedRfm = null;
    private $cachedCounts = null;
    private bool $erpCodeResolved = false;
    private ?string $resolvedErpCode = null;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        ResourceConnection $resource,
        BulkSkuProductLoader $bulkSkuProductLoader,
        ListProduct $listProductBlock,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->resource = $resource;
        $this->bulkSkuProductLoader = $bulkSkuProductLoader;
        $this->listProductBlock = $listProductBlock;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    /**
     * Verificar se REXIS ML está habilitado
     */
    public function isEnabled()
    {
        return (bool)$this->scopeConfig->getValue(
            'rexisml/general/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Resolve o código ERP do cliente logado
     * Fluxo: erp_code attribute → entity_map table → customer_id fallback
     */
    private function resolveErpCode(): ?string
    {
        if ($this->erpCodeResolved) {
            return $this->resolvedErpCode;
        }

        $customerId = $this->customerSession->getCustomerId();
        if (!$customerId) {
            $this->erpCodeResolved = true;

            return null;
        }

        try {
            // 1. Tentar pegar erp_code do atributo do cliente
            $customer = $this->customerRepository->getById($customerId);
            $erpAttr = $customer->getCustomAttribute('erp_code');
            if ($erpAttr && $erpAttr->getValue()) {
                $this->resolvedErpCode = (string)$erpAttr->getValue();
                $this->erpCodeResolved = true;

                return $this->resolvedErpCode;
            }

            // 2. Fallback: entity_map table
            $connection = $this->resource->getConnection();
            $mapTable = $this->resource->getTableName('grupoawamotos_erp_entity_map');
            $select = $connection->select()
                ->from($mapTable, 'erp_code')
                ->where('entity_type = ?', 'customer')
                ->where('magento_entity_id = ?', $customerId);
            $erpCode = $connection->fetchOne($select);
            if ($erpCode) {
                $this->resolvedErpCode = (string)$erpCode;
                $this->erpCodeResolved = true;

                return $this->resolvedErpCode;
            }
        } catch (\Exception $e) {
            $this->logger->debug('[RexisML] Could not resolve ERP code: ' . $e->getMessage());
        }

        // 3. Fallback final: usar o próprio Magento customer ID
        $this->resolvedErpCode = (string)$customerId;
        $this->erpCodeResolved = true;

        return $this->resolvedErpCode;
    }

    /**
     * Obter produtos recomendados para o cliente logado (dados reais do pipeline)
     */
    public function getRecommendedProducts()
    {
        if ($this->cachedProducts !== null) {
            return $this->cachedProducts;
        }

        $this->cachedProducts = [];

        if (!$this->customerSession->isLoggedIn()) {
            return $this->cachedProducts;
        }

        $erpCode = $this->resolveErpCode();
        if (!$erpCode) {
            return $this->cachedProducts;
        }

        $minScore = $this->scopeConfig->getValue(
            'rexisml/general/min_score',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ) ?: 0.15;

        $limit = (int)($this->getLimit() ?: 6);
        $classificacao = $this->getClassificacao();

        try {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('rexis_dataset_recomendacao');

            $select = $connection->select()
                ->from($table)
                ->where('identificador_cliente = ?', $erpCode)
                ->where('pred >= ?', $minScore)
                ->order('pred DESC')
                ->limit($limit);

            // Filtrar por classificação se especificado
            if ($classificacao) {
                $classMap = $this->getClassificationAliases($classificacao);
                $select->where(
                    'tipo_recomendacao IN (?)',
                    $classMap
                );
            }

            $rows = $connection->fetchAll($select);
            $productsBySku = $this->bulkSkuProductLoader->loadBySkus(array_column($rows, 'identificador_produto'));

            foreach ($rows as $row) {
                $product = $productsBySku[(string)($row['identificador_produto'] ?? '')] ?? null;
                if ($product === null || !$product->isSaleable()) {
                    continue;
                }

                $tipo = $row['tipo_recomendacao'] ?: $row['classificacao_produto'];
                $this->cachedProducts[] = [
                    'product' => $product,
                    'score' => (float)$row['pred'],
                    'classificacao' => $this->normalizeClassification($tipo),
                    'predicted_value' => (float)$row['previsao_gasto_round_up'],
                    'recencia' => (int)($row['recencia'] ?? 0),
                    'tipo' => $row['tipo_recomendacao'] ?? ''
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('[RexisML] Error loading recommendations: ' . $e->getMessage());
        }

        return $this->cachedProducts;
    }

    /**
     * Obter classificação RFM do cliente logado
     */
    public function getCustomerRfm()
    {
        if ($this->cachedRfm !== null) {
            return $this->cachedRfm;
        }

        $this->cachedRfm = [];
        $erpCode = $this->resolveErpCode();
        if (!$erpCode) {
            return $this->cachedRfm;
        }

        try {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('rexis_customer_classification');

            $select = $connection->select()
                ->from($table)
                ->where('identificador_cliente = ?', $erpCode)
                ->order('id DESC')
                ->limit(1);

            $this->cachedRfm = $connection->fetchRow($select) ?: [];
        } catch (\Exception $e) {
            $this->logger->debug('[RexisML] Error loading RFM: ' . $e->getMessage());
        }

        return $this->cachedRfm;
    }

    /**
     * Obter contadores por tipo de recomendação
     */
    public function getRecommendationCounts()
    {
        if ($this->cachedCounts !== null) {
            return $this->cachedCounts;
        }

        $erpCode = $this->resolveErpCode();
        if (!$erpCode) {
            $this->cachedCounts = ['churn' => 0, 'crosssell' => 0, 'total' => 0];

            return $this->cachedCounts;
        }

        try {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('rexis_dataset_recomendacao');

            $select = $connection->select()
                ->from($table, [
                    'total' => new \Zend_Db_Expr('COUNT(*)'),
                    'churn' => new \Zend_Db_Expr("SUM(CASE WHEN tipo_recomendacao = 'churn' OR classificacao_produto LIKE '%Churn%' THEN 1 ELSE 0 END)"),
                    'crosssell' => new \Zend_Db_Expr("SUM(CASE WHEN tipo_recomendacao = 'crosssell' OR classificacao_produto LIKE '%Cross%' THEN 1 ELSE 0 END)")
                ])
                ->where('identificador_cliente = ?', $erpCode);

            $this->cachedCounts = $connection->fetchRow($select) ?: ['churn' => 0, 'crosssell' => 0, 'total' => 0];

            return $this->cachedCounts;
        } catch (\Exception $e) {
            $this->cachedCounts = ['churn' => 0, 'crosssell' => 0, 'total' => 0];

            return $this->cachedCounts;
        }
    }

    /**
     * Normalizar nomes de classificação (pipeline gera 'churn', templates esperam 'Oportunidade Churn')
     */
    private function normalizeClassification(?string $tipo): string
    {
        $map = [
            'churn' => 'churn',
            'crosssell' => 'crosssell',
            'Oportunidade Churn' => 'churn',
            'Oportunidade Cross-sell' => 'crosssell',
            'Oportunidade Cross-Sell' => 'crosssell',
            'Oportunidade Irregular' => 'irregular',
        ];
        return $map[$tipo] ?? 'default';
    }

    /**
     * Mapear classificação para aliases usados nas queries
     */
    private function getClassificationAliases(string $classificacao): array
    {
        $aliases = [
            'churn' => ['churn', 'Oportunidade Churn'],
            'crosssell' => ['crosssell', 'Oportunidade Cross-sell', 'Oportunidade Cross-Sell'],
            'irregular' => ['irregular', 'Oportunidade Irregular'],
        ];
        return $aliases[$classificacao] ?? [$classificacao];
    }

    /**
     * Renderizar add to cart button
     */
    public function getAddToCartPostParams($product)
    {
        return $this->listProductBlock->getAddToCartPostParams($product);
    }

    /**
     * Get product image URL
     */
    public function getProductImageUrl($product)
    {
        try {
            return $this->listProductBlock->getImage($product, 'category_page_grid')->getImageUrl();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get title for recommendation section
     */
    public function getTitle()
    {
        if ($this->getData('title')) {
            return $this->getData('title');
        }

        $classificacao = $this->getClassificacao();
        switch ($classificacao) {
            case 'churn':
                return __('Produtos que voce amava - Promocao Especial!');
            case 'crosssell':
                return __('Recomendados para Voce');
            case 'irregular':
                return __('Produtos que voce compra regularmente');
            default:
                return __('Sugestoes Personalizadas');
        }
    }

    /**
     * Get CSS class and label based on classification
     */
    public function getClassificationInfo(string $classificacao): array
    {
        $info = [
            'churn' => [
                'class' => 'rexis-churn',
                'label' => 'Voce Amava!',
                'icon' => 'star',
                'color' => '#f87171'
            ],
            'crosssell' => [
                'class' => 'rexis-crosssell',
                'label' => 'Sugerido p/ Voce',
                'icon' => 'lightbulb',
                'color' => '#34d399'
            ],
            'irregular' => [
                'class' => 'rexis-irregular',
                'label' => 'Compra Recorrente',
                'icon' => 'refresh',
                'color' => '#fbbf24'
            ],
        ];
        return $info[$classificacao] ?? [
            'class' => 'rexis-default',
            'label' => 'Recomendado',
            'icon' => 'tag',
            'color' => '#94a3b8'
        ];
    }

    /**
     * @deprecated Use getClassificationInfo() instead
     */
    public function getClassificationClass($classificacao)
    {
        $info = $this->getClassificationInfo($this->normalizeClassification($classificacao));
        return $info['class'];
    }
}
