<?php

/**
 * Interface de dados para Métricas
 */

declare(strict_types=1);

namespace GrupoAwamotos\ProductIntelligence\Api\Data;

interface MetricsInterface
{
    public const TOTAL_RECOMENDACOES = 'total_recomendacoes';
    public const OPORTUNIDADES_CHURN = 'oportunidades_churn';
    public const OPORTUNIDADES_CROSSSELL = 'oportunidades_crosssell';
    public const VALOR_POTENCIAL = 'valor_potencial';
    public const CLIENTES_ANALISADOS = 'clientes_analisados';
    public const PRODUTOS_RECOMENDADOS = 'produtos_recomendados';
    public const SCORE_MEDIO = 'score_medio';
    public const TAXA_CONVERSAO = 'taxa_conversao';

    /**
     * @return int
     */
    public function getTotalRecomendacoes();

    /**
     * @return int
     */
    public function getOportunidadesChurn();

    /**
     * @return int
     */
    public function getOportunidadesCrosssell();

    /**
     * @return float
     */
    public function getValorPotencial();

    /**
     * @return int
     */
    public function getClientesAnalisados();

    /**
     * @return int
     */
    public function getProdutosRecomendados();

    /**
     * @return float
     */
    public function getScoreMedio();

    /**
     * @return float
     */
    public function getTaxaConversao();
}
