<?php

declare(strict_types=1);

/**
 * Interface de dados para Recomendação
 */

namespace GrupoAwamotos\RexisML\Api\Data;

interface RecommendationInterface
{
    const CHAVE_GLOBAL = 'chave_global';
    const CUSTOMER_ID = 'identificador_cliente';
    const PRODUCT_SKU = 'identificador_produto';
    const CLASSIFICACAO = 'classificacao_produto';
    const PRED_SCORE = 'pred';
    const PROBABILIDADE = 'probabilidade_compra';
    const PREVISAO_GASTO = 'previsao_gasto_round_up';
    const RECENCIA = 'recencia';
    const FREQUENCIA = 'frequencia';
    const VALOR_MONETARIO = 'valor_monetario';

    /**
     * @return string
     */
    public function getChaveGlobal();

    /**
     * @return int
     */
    public function getCustomerId();

    /**
     * @return string
     */
    public function getProductSku();

    /**
     * @return string
     */
    public function getClassificacao();

    /**
     * @return float
     */
    public function getPredScore();

    /**
     * @return float
     */
    public function getProbabilidade();

    /**
     * @return float
     */
    public function getPrevisaoGasto();

    /**
     * @return int
     */
    public function getRecencia();

    /**
     * @return int
     */
    public function getFrequencia();

    /**
     * @return float
     */
    public function getValorMonetario();
}
