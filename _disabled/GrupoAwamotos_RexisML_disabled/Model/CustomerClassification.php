<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Model;

use Magento\Framework\Model\AbstractModel;

class CustomerClassification extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\GrupoAwamotos\RexisML\Model\ResourceModel\CustomerClassification::class);
    }

    public function getIdentificadorCliente()
    {
        return $this->getData('identificador_cliente');
    }

    public function getRecencyScore()
    {
        return $this->getData('recency_score');
    }

    public function getFrequencyScore()
    {
        return $this->getData('frequency_score');
    }

    public function getMonetaryScore()
    {
        return $this->getData('monetary_score');
    }

    public function getRfmScore()
    {
        return $this->getData('rfm_score');
    }

    public function getSegmento()
    {
        return $this->getData('segmento');
    }

    public function getUltimaCompra()
    {
        return $this->getData('ultima_compra');
    }

    public function getTotalCompras()
    {
        return $this->getData('total_compras');
    }

    public function getValorTotal()
    {
        return $this->getData('valor_total');
    }
}
