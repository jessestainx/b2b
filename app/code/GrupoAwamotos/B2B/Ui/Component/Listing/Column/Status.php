<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\Component\Listing\Column;

use GrupoAwamotos\B2B\Model\B2bCustomer;
use Magento\Ui\Component\Listing\Columns\Column;

class Status extends Column
{
    private const BADGE_MAP = [
        B2bCustomer::STATUS_PENDING  => ['label' => 'Pendente',  'class' => 'grid-severity-minor'],
        B2bCustomer::STATUS_APPROVED => ['label' => 'Aprovado',  'class' => 'grid-severity-notice'],
        B2bCustomer::STATUS_REJECTED => ['label' => 'Rejeitado', 'class' => 'grid-severity-critical'],
    ];

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            $status = (int) ($item['status'] ?? B2bCustomer::STATUS_PENDING);
            $badge  = self::BADGE_MAP[$status] ?? self::BADGE_MAP[B2bCustomer::STATUS_PENDING];
            $item[$this->getData('name')] = sprintf(
                '<span class="%s"><span>%s</span></span>',
                htmlspecialchars($badge['class']),
                htmlspecialchars($badge['label'])
            );
        }

        return $dataSource;
    }
}
