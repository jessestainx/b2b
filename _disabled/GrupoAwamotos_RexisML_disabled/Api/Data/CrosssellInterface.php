<?php

declare(strict_types=1);

/**
 * Interface de dados para Cross-sell (Market Basket Analysis)
 */

namespace GrupoAwamotos\RexisML\Api\Data;

interface CrosssellInterface
{
    const ANTECEDENT = 'antecedent';
    const CONSEQUENT = 'consequent';
    const SUPPORT = 'support';
    const CONFIDENCE = 'confidence';
    const LIFT = 'lift';
    const CONVICTION = 'conviction';

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
