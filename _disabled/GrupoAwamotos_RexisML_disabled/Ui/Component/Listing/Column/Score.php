<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class Score extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['pred'])) {
                    $score = (float)$item['pred'] * 100;
                    $color = '#94a3b8';
                    if ($score >= 70) {
                        $color = '#059669';
                    } elseif ($score >= 40) {
                        $color = '#d97706';
                    } else {
                        $color = '#dc2626';
                    }
                    $item[$this->getData('name')] = sprintf(
                        '<span style="font-weight:700;color:%s">%.1f%%</span>',
                        $color,
                        $score
                    );
                }
            }
        }
        return $dataSource;
    }
}
