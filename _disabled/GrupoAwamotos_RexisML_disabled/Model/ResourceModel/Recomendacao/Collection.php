<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Model\ResourceModel\Recomendacao;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \GrupoAwamotos\RexisML\Model\Recomendacao::class,
            \GrupoAwamotos\RexisML\Model\ResourceModel\Recomendacao::class
        );
    }

    /**
     * Filtra por classificação de produto
     */
    public function addClassificacaoFilter($classificacao)
    {
        return $this->addFieldToFilter('classificacao_produto', $classificacao);
    }

    /**
     * Filtra por mês REXIS
     */
    public function addMesRexisFilter($mesCode)
    {
        return $this->addFieldToFilter('mes_rexis_code', $mesCode);
    }

    /**
     * Filtra por cliente
     */
    public function addCustomerFilter($customerId)
    {
        return $this->addFieldToFilter('customer_id', $customerId);
    }

    /**
     * Ordena por score de predição (maior primeiro)
     */
    public function orderByPred()
    {
        return $this->setOrder('pred', 'DESC');
    }

    /**
     * Retorna apenas recomendações com alta probabilidade
     */
    public function getHighProbability($threshold = 0.7)
    {
        return $this->addFieldToFilter('pred', ['gteq' => $threshold]);
    }
}
