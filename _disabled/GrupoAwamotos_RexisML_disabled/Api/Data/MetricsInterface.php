<?php

declare(strict_types=1);

/**
 * Interface de dados para Métricas
 */

namespace GrupoAwamotos\RexisML\Api\Data;

interface MetricsInterface
{
    const TOTAL_RECOMENDACOES = 'total_recomendacoes';
    const OPORTUNIDADES_CHURN = 'oportunidades_churn';
    const OPORTUNIDADES_CROSSSELL = 'oportunidades_crosssell';
    const VALOR_POTENCIAL = 'valor_potencial';
    const CLIENTES_ANALISADOS = 'clientes_analisados';
    const PRODUTOS_RECOMENDADOS = 'produtos_recomendados';
    const SCORE_MEDIO = 'score_medio';
    const TAXA_CONVERSAO = 'taxa_conversao';

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
