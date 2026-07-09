<?php

/**
 * Interface de dados para Cross-sell (Market Basket Analysis)
 */

declare(strict_types=1);

namespace GrupoAwamotos\ProductIntelligence\Api\Data;

interface CrosssellInterface
{
    public const ANTECEDENT = 'antecedent';
    public const CONSEQUENT = 'consequent';
    public const SUPPORT = 'support';
    public const CONFIDENCE = 'confidence';
    public const LIFT = 'lift';
    public const CONVICTION = 'conviction';

    /**
     * @return string
     */
    public function getAntecedent();

    /**
     * @return string
     */
    public function getConsequent();

    /**
     * @return float
     */
    public function getSupport();

    /**
     * @return float
     */
    public function getConfidence();

    /**
     * @return float
     */
    public function getLift();

    /**
     * @return float|null
     */
    public function getConviction();
}
