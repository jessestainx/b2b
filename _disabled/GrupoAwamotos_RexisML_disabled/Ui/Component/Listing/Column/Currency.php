<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class Currency extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                $value = (float)($item[$fieldName] ?? 0);
                if ($value > 0) {
                    $item[$fieldName] = 'R$ ' . number_format($value, 2, ',', '.');
                } else {
                    $item[$fieldName] = '-';
                }
            }
        }
        return $dataSource;
    }
}
