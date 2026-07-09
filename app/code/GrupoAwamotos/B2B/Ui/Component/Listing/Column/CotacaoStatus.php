<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\Component\Listing\Column;

use GrupoAwamotos\B2B\Model\Cotacao;
use Magento\Ui\Component\Listing\Columns\Column;

class CotacaoStatus extends Column
{
    private const MAP = [
        Cotacao::STATUS_PENDING  => '<span class="grid-severity-minor"><span>Pendente</span></span>',
        Cotacao::STATUS_QUOTED   => '<span class="grid-severity-notice"><span>Cotado</span></span>',
        Cotacao::STATUS_ACCEPTED => '<span class="grid-severity-notice" style="background:#2e7d32"><span>Aceito</span></span>',
        Cotacao::STATUS_REJECTED => '<span class="grid-severity-critical"><span>Recusado</span></span>',
        Cotacao::STATUS_EXPIRED  => '<span class="grid-severity-critical"><span>Expirado</span></span>',
    ];

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $col = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $status = $item[$col] ?? '';
            $item[$col] = self::MAP[$status] ?? htmlspecialchars($status);
        }

        return $dataSource;
    }
}
