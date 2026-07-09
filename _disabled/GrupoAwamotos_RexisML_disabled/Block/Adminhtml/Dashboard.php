<?php

declare(strict_types=1);

/**
 * Block do Dashboard REXIS ML
 * Fornece dados para KPIs, graficos e tabelas
 */

namespace GrupoAwamotos\RexisML\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;

class Dashboard extends Template
{
    protected $resource;
    protected $connection;

    public function __construct(
        Context $context,
        ResourceConnection $resource,
        array $data = []
    ) {
        $this->resource = $resource;
        $this->connection = $resource->getConnection();
        parent::__construct($context, $data);
    }

    /* ======================================================================
     * KPI DATA
     * ====================================================================== */

    public function getGeneralStats()
    {
        $t = $this->resource->getTableName('rexis_dataset_recomendacao');
        $sql = "
            SELECT
                COUNT(DISTINCT identificador_cliente) AS total_clientes,
                COUNT(DISTINCT identificador_produto) AS total_produtos,
                COUNT(*) AS total_recomendacoes,
                AVG(pred) AS score_medio,
                SUM(previsao_gasto_round_up) AS valor_potencial,
                SUM(CASE WHEN tipo_recomendacao = 'churn' OR classificacao_produto LIKE '%Churn%' THEN 1 ELSE 0 END) AS churn_count,
                SUM(CASE WHEN tipo_recomendacao = 'crosssell' OR classificacao_produto LIKE '%Cross%' THEN 1 ELSE 0 END) AS crosssell_count,
                SUM(COALESCE(valor_convertida, 0)) AS total_convertido,
                SUM(CASE WHEN COALESCE(valor_convertida, 0) > 0 THEN 1 ELSE 0 END) AS total_convertidos
            FROM {$t}
        ";
        return $this->connection->fetchRow($sql);
    }

    public function getConversionMetrics()
    {
        $t = $this->resource->getTableName('rexis_metricas_conversao');
        $sql = "SELECT * FROM {$t} ORDER BY mes_rexis_code DESC LIMIT 1";
        return $this->connection->fetchRow($sql) ?: [];
    }

    public function getLastSyncTime()
    {
        $t = $this->resource->getTableName('rexis_dataset_recomendacao');
        $sql = "SELECT MAX(updated_at) AS last_sync FROM {$t}";
        $row = $this->connection->fetchRow($sql);
        return $row['last_sync'] ?? null;
    }

    /* ======================================================================
     * TABLE DATA
     * ====================================================================== */

    public function getClassificacaoDistribution()
    {
        $t = $this->resource->getTableName('rexis_dataset_recomendacao');
        $sql = "
            SELECT
                COALESCE(tipo_recomendacao, classificacao_produto) AS tipo,
                COUNT(*) AS quantidade,
                AVG(pred) AS score_medio,
                SUM(previsao_gasto_round_up) AS valor_potencial
            FROM {$t}
            GROUP BY COALESCE(tipo_recomendacao, classificacao_produto)
            ORDER BY quantidade DESC
        ";
        return $this->connection->fetchAll($sql);
    }

    public function getTopChurnOpportunities()
    {
        $t = $this->resource->getTableName('rexis_dataset_recomendacao');
        $sql = "
            SELECT
                identificador_cliente,
                identificador_produto,
                pred AS score,
                probabilidade_compra,
                previsao_gasto_round_up,
                recencia
            FROM {$t}
            WHERE (tipo_recomendacao = 'churn' OR classificacao_produto LIKE '%Churn%')
              AND pred >= 0.3
            ORDER BY pred DESC
            LIMIT 10
        ";
        return $this->connection->fetchAll($sql);
    }

    public function getTopCrosssellRules()
    {
        $t = $this->resource->getTableName('rexis_network_rules');
        $sql = "
            SELECT antecedent, consequent, lift, confidence, support
            FROM {$t}
            WHERE lift >= 1.5 AND is_active = 1
            ORDER BY lift DESC
            LIMIT 10
        ";
        return $this->connection->fetchAll($sql);
    }

    public function getRfmDistribution()
    {
        $t = $this->resource->getTableName('rexis_customer_classification');
        $sql = "
            SELECT
                classificacao_cliente,
                COUNT(*) AS total_clientes,
                AVG(monetary) AS valor_medio,
                AVG(frequency) AS freq_media,
                AVG(recency) AS recencia_media
            FROM {$t}
            GROUP BY classificacao_cliente
            ORDER BY total_clientes DESC
        ";
        return $this->connection->fetchAll($sql);
    }

    public function getMonthlyEvolution()
    {
        $t = $this->resource->getTableName('rexis_metricas_conversao');
        $sql = "
            SELECT
                mes_rexis_code,
                n_clientes_rec_mes_atual,
                n_cliente_comprou_mes_atual,
                perc_conversao_cliente,
                valor_esperado_atual,
                valor_convertido_atual
            FROM {$t}
            ORDER BY mes_rexis_code DESC
            LIMIT 12
        ";
        $result = $this->connection->fetchAll($sql);
        return array_reverse($result);
    }

    public function getTopRecommendedProducts()
    {
        $t = $this->resource->getTableName('rexis_dataset_recomendacao');
        $sql = "
            SELECT
                identificador_produto,
                COUNT(*) AS n_recomendacoes,
                AVG(pred) AS score_medio,
                SUM(previsao_gasto_round_up) AS valor_potencial,
                COALESCE(tipo_recomendacao, classificacao_produto) AS tipo
            FROM {$t}
            WHERE pred >= 0.2
            GROUP BY identificador_produto, COALESCE(tipo_recomendacao, classificacao_produto)
            ORDER BY n_recomendacoes DESC
            LIMIT 15
        ";
        return $this->connection->fetchAll($sql);
    }

    /* ======================================================================
     * CHART JSON DATA
     * ====================================================================== */

    public function getChartDataJson($type)
    {
        switch ($type) {
            case 'tipo_pie':
                return $this->getTipoPieData();
            case 'rfm_bar':
                return $this->getRfmBarData();
            case 'monthly_line':
                return $this->getMonthlyLineData();
            case 'top_products':
                return $this->getTopProductsData();
            case 'score_histogram':
                return $this->getScoreHistogramData();
            default:
                return json_encode([]);
        }
    }

    private function getTipoPieData()
    {
        $data = $this->getClassificacaoDistribution();
        $labelMap = [
            'churn' => 'Churn (Reativacao)',
            'crosssell' => 'Cross-sell',
            'Oportunidade Churn' => 'Churn (Legacy)',
            'Oportunidade Cross-sell' => 'Cross-sell (Legacy)',
            'Oportunidade Irregular' => 'Irregular',
        ];
        $labels = [];
        $series = [];
        foreach ($data as $row) {
            $raw = $row['tipo'] ?: 'Sem Tipo';
            $labels[] = $labelMap[$raw] ?? $raw;
            $series[] = (int)$row['quantidade'];
        }
        return json_encode(['labels' => $labels, 'series' => $series]);
    }

    private function getRfmBarData()
    {
        $data = $this->getRfmDistribution();
        $categories = [];
        $clientes = [];
        $valores = [];
        foreach ($data as $row) {
            $categories[] = $row['classificacao_cliente'];
            $clientes[] = (int)$row['total_clientes'];
            $valores[] = round((float)$row['valor_medio'], 2);
        }
        return json_encode([
            'categories' => $categories,
            'clientes' => $clientes,
            'valores' => $valores
        ]);
    }

    private function getMonthlyLineData()
    {
        // Try metrics table first
        $data = $this->getMonthlyEvolution();

        // If metrics table has >= 2 months, use it
        if (count($data) >= 2) {
            $months = [];
            $recomendados = [];
            $convertidos = [];
            $taxaConversao = [];
            foreach ($data as $row) {
                $months[] = $row['mes_rexis_code'];
                $recomendados[] = (int)$row['n_clientes_rec_mes_atual'];
                $convertidos[] = (int)$row['n_cliente_comprou_mes_atual'];
                $taxaConversao[] = round((float)$row['perc_conversao_cliente'], 2);
            }
            return json_encode([
                'months' => $months,
                'recomendados' => $recomendados,
                'convertidos' => $convertidos,
                'taxaConversao' => $taxaConversao
            ]);
        }

        // Fallback: aggregate recommendations by tipo and month from main table
        $t = $this->resource->getTableName('rexis_dataset_recomendacao');
        $sql = "
            SELECT
                mes_rexis_code,
                SUM(CASE WHEN tipo_recomendacao = 'churn' THEN 1 ELSE 0 END) AS churn_count,
                SUM(CASE WHEN tipo_recomendacao = 'crosssell' THEN 1 ELSE 0 END) AS crosssell_count,
                COUNT(DISTINCT identificador_cliente) AS unique_clients
            FROM {$t}
            GROUP BY mes_rexis_code
            ORDER BY mes_rexis_code ASC
            LIMIT 12
        ";
        $rows = $this->connection->fetchAll($sql);

        if (empty($rows)) {
            return json_encode(['months' => [], 'recomendados' => [], 'convertidos' => [], 'taxaConversao' => []]);
        }

        $months = [];
        $churn = [];
        $crosssell = [];
        $clients = [];
        foreach ($rows as $row) {
            $months[] = $row['mes_rexis_code'];
            $churn[] = (int)$row['churn_count'];
            $crosssell[] = (int)$row['crosssell_count'];
            $clients[] = (int)$row['unique_clients'];
        }
        return json_encode([
            'months' => $months,
            'churn' => $churn,
            'crosssell' => $crosssell,
            'clients' => $clients,
            'fallback' => true
        ]);
    }

    private function getTopProductsData()
    {
        $data = $this->getTopRecommendedProducts();
        $colorMap = [
            'churn' => '#ef4444',
            'crosssell' => '#10b981',
            'Oportunidade Churn' => '#ef4444',
            'Oportunidade Cross-Sell' => '#10b981',
            'Oportunidade Cross-sell' => '#10b981',
            'Oportunidade Irregular' => '#f59e0b',
        ];
        $products = [];
        $values = [];
        $colors = [];
        $potentials = [];
        foreach ($data as $row) {
            $name = $row['identificador_produto'];
            $products[] = strlen($name) > 25 ? substr($name, 0, 22) . '...' : $name;
            $values[] = (int)$row['n_recomendacoes'];
            $colors[] = $colorMap[$row['tipo']] ?? '#64748b';
            $potentials[] = round((float)$row['valor_potencial'], 2);
        }
        return json_encode([
            'products' => $products,
            'values' => $values,
            'colors' => $colors,
            'potentials' => $potentials
        ]);
    }

    private function getScoreHistogramData()
    {
        $t = $this->resource->getTableName('rexis_dataset_recomendacao');
        $sql = "
            SELECT
                CASE
                    WHEN pred < 0.1 THEN '0-10%'
                    WHEN pred < 0.2 THEN '10-20%'
                    WHEN pred < 0.3 THEN '20-30%'
                    WHEN pred < 0.4 THEN '30-40%'
                    WHEN pred < 0.5 THEN '40-50%'
                    WHEN pred < 0.6 THEN '50-60%'
                    WHEN pred < 0.7 THEN '60-70%'
                    WHEN pred < 0.8 THEN '70-80%'
                    WHEN pred < 0.9 THEN '80-90%'
                    ELSE '90-100%'
                END AS faixa,
                COUNT(*) AS quantidade
            FROM {$t}
            GROUP BY faixa
            ORDER BY MIN(pred) ASC
        ";
        $data = $this->connection->fetchAll($sql);
        $categories = [];
        $values = [];
        foreach ($data as $row) {
            $categories[] = $row['faixa'];
            $values[] = (int)$row['quantidade'];
        }
        return json_encode(['categories' => $categories, 'values' => $values]);
    }

    /* ======================================================================
     * FORMATTERS
     * ====================================================================== */

    public function formatMoney($value)
    {
        $v = (float)$value;
        if ($v >= 1000000) {
            return 'R$ ' . number_format($v / 1000000, 1, ',', '.') . 'M';
        }
        if ($v >= 1000) {
            return 'R$ ' . number_format($v / 1000, 1, ',', '.') . 'K';
        }
        return 'R$ ' . number_format($v, 2, ',', '.');
    }

    public function formatMoneyFull($value)
    {
        return 'R$ ' . number_format((float)$value, 2, ',', '.');
    }

    public function formatPercent($value)
    {
        return number_format((float)$value, 1, ',', '.') . '%';
    }

    public function formatNumber($value)
    {
        $v = (float)$value;
        if ($v >= 1000000) {
            return number_format($v / 1000000, 1, ',', '.') . 'M';
        }
        if ($v >= 1000) {
            return number_format($v / 1000, 1, ',', '.') . 'K';
        }
        return number_format($v, 0, ',', '.');
    }

    public function formatNumberFull($value)
    {
        return number_format((float)$value, 0, ',', '.');
    }
}
