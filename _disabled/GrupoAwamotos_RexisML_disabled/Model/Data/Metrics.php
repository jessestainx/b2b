<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Model\Data;

use GrupoAwamotos\RexisML\Api\Data\MetricsInterface;
use Magento\Framework\DataObject;

/**
 * Concrete DTO for REXIS ML metrics exposed via REST API.
 */
class Metrics extends DataObject implements MetricsInterface
{
    /**
     * @inheritDoc
     */
    public function getTotalRecomendacoes(): int
    {
        return (int) $this->getData(self::TOTAL_RECOMENDACOES);
    }

    /**
     * @inheritDoc
     */
    public function getOportunidadesChurn(): int
    {
        return (int) $this->getData(self::OPORTUNIDADES_CHURN);
    }

    /**
     * @inheritDoc
     */
    public function getOportunidadesCrosssell(): int
    {
        return (int) $this->getData(self::OPORTUNIDADES_CROSSSELL);
    }

    /**
     * @inheritDoc
     */
    public function getValorPotencial(): float
    {
        return (float) $this->getData(self::VALOR_POTENCIAL);
    }

    /**
     * @inheritDoc
     */
    public function getClientesAnalisados(): int
    {
        return (int) $this->getData(self::CLIENTES_ANALISADOS);
    }

    /**
     * @inheritDoc
     */
    public function getProdutosRecomendados(): int
    {
        return (int) $this->getData(self::PRODUTOS_RECOMENDADOS);
    }

    /**
     * @inheritDoc
     */
    public function getScoreMedio(): float
    {
        return (float) $this->getData(self::SCORE_MEDIO);
    }

    /**
     * @inheritDoc
     */
    public function getTaxaConversao(): float
    {
        return (float) $this->getData(self::TAXA_CONVERSAO);
    }
}
