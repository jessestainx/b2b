<?php

declare(strict_types=1);

/**
 * Model de Recomendação
 * Representa um registro da tabela rexis_dataset_recomendacao
 */

namespace GrupoAwamotos\RexisML\Model;

use Magento\Framework\Model\AbstractModel;

class Recomendacao extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\GrupoAwamotos\RexisML\Model\ResourceModel\Recomendacao::class);
    }

    /**
     * Verifica se é uma oportunidade de Churn
     */
    public function isChurn(): bool
    {
        return $this->getClassificacaoProduto() === 'Oportunidade Churn';
    }

    /**
     * Verifica se é uma oportunidade de Cross-sell
     */
    public function isCrossSell(): bool
    {
        return $this->getClassificacaoProduto() === 'Oportunidade Cross-Sell';
    }

    /**
     * Verifica se é uma oportunidade Irregular
     */
    public function isIrregular(): bool
    {
        return $this->getClassificacaoProduto() === 'Oportunidade Irregular';
    }

    /**
     * Calcula a prioridade da recomendação
     * Baseado em: pred (score ML) e classificação
     */
    public function getPrioridade(): string
    {
        $pred = (float) $this->getPred();

        if ($pred >= 0.8) {
            return 'Alta';
        } elseif ($pred >= 0.5) {
            return 'Média';
        } else {
            return 'Baixa';
        }
    }

    /**
     * Retorna cor do badge de classificação
     */
    public function getBadgeColor(): string
    {
        switch ($this->getClassificacaoProduto()) {
            case 'Oportunidade Churn':
                return 'red';
            case 'Oportunidade Cross-Sell':
                return 'green';
            case 'Oportunidade Irregular':
                return 'orange';
            default:
                return 'gray';
        }
    }
}
