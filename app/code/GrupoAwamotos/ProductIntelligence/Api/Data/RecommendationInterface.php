<?php

/**
 * Interface de dados para Recomendação
 */

declare(strict_types=1);

namespace GrupoAwamotos\ProductIntelligence\Api\Data;

interface RecommendationInterface
{
    public const CHAVE_GLOBAL = 'chave_global';
    public const CUSTOMER_ID = 'identificador_cliente';
    public const PRODUCT_SKU = 'identificador_produto';
    public const CLASSIFICACAO = 'classificacao_produto';
    public const PRED_SCORE = 'pred';
    public const PROBABILIDADE = 'probabilidade_compra';
    public const PREVISAO_GASTO = 'previsao_gasto_round_up';
    public const RECENCIA = 'recencia';
    public const FREQUENCIA = 'frequencia';
    public const VALOR_MONETARIO = 'valor_monetario';

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
