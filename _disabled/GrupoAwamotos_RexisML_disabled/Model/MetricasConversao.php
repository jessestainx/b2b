<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Model;

use Magento\Framework\Model\AbstractModel;

class MetricasConversao extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\GrupoAwamotos\RexisML\Model\ResourceModel\MetricasConversao::class);
    }

    public function getMesRexisCode()
    {
        return $this->getData('mes_rexis_code');
    }

    public function getClientesRecomendados()
    {
        return $this->getData('n_clientes_rec_mes_atual');
    }

    public function getClientesCompraram()
    {
        return $this->getData('n_cliente_comprou_mes_atual');
    }

    public function getPercConversaoCliente()
    {
        return $this->getData('perc_conversao_cliente');
    }

    public function getProdutosRecomendados()
    {
        return $this->getData('n_produto_rec_mes_atual');
    }

    public function getProdutosComprados()
    {
        return $this->getData('n_produto_comprou_mes_atual');
    }

    public function getPercConversaoProduto()
    {
        return $this->getData('perc_conversao_produto');
    }

    public function getValorEsperado()
    {
        return $this->getData('valor_esperado_atual');
    }

    public function getValorConvertido()
    {
        return $this->getData('valor_convertido_atual');
    }
}
