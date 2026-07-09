<?php

declare(strict_types=1);

/**
 * Interface de dados para RFM
 */

namespace GrupoAwamotos\RexisML\Api\Data;

interface RfmInterface
{
    const CUSTOMER_ID = 'identificador_cliente';
    const RECENCY_SCORE = 'recency_score';
    const FREQUENCY_SCORE = 'frequency_score';
    const MONETARY_SCORE = 'monetary_score';
    const RFM_SCORE = 'rfm_score';
    const SEGMENT = 'segmento';
    const ULTIMA_COMPRA = 'ultima_compra';
    const TOTAL_COMPRAS = 'total_compras';
    const VALOR_TOTAL = 'valor_total';

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
