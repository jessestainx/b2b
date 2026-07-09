<?php

/**
 * Interface de dados para RFM
 */

declare(strict_types=1);

namespace GrupoAwamotos\ProductIntelligence\Api\Data;

interface RfmInterface
{
    public const CUSTOMER_ID = 'identificador_cliente';
    public const RECENCY_SCORE = 'recency_score';
    public const FREQUENCY_SCORE = 'frequency_score';
    public const MONETARY_SCORE = 'monetary_score';
    public const RFM_SCORE = 'rfm_score';
    public const SEGMENT = 'segmento';
    public const ULTIMA_COMPRA = 'ultima_compra';
    public const TOTAL_COMPRAS = 'total_compras';
    public const VALOR_TOTAL = 'valor_total';

    /**
     * @return int
     */
    public function getCustomerId();

    /**
     * @return int
     */
    public function getRecencyScore();

    /**
     * @return int
     */
    public function getFrequencyScore();

    /**
     * @return int
     */
    public function getMonetaryScore();

    /**
     * @return int
     */
    public function getRfmScore();

    /**
     * @return string
     */
    public function getSegment();

    /**
     * @return string
     */
    public function getUltimaCompra();

    /**
     * @return int
     */
    public function getTotalCompras();

    /**
     * @return float
     */
    public function getValorTotal();
}
