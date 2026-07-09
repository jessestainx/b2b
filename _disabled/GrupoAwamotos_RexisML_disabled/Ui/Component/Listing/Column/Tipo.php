<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class Tipo extends Column
{
    private const BADGE_MAP = [
        'churn' => ['label' => 'Churn', 'bg' => '#fecaca', 'color' => '#991b1b'],
        'crosssell' => ['label' => 'Cross-sell', 'bg' => '#d1fae5', 'color' => '#065f46'],
    ];

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $tipo = $item['tipo_recomendacao'] ?? '';
                $badge = self::BADGE_MAP[$tipo] ?? ['label' => $tipo ?: '-', 'bg' => '#e2e8f0', 'color' => '#334155'];
                $item[$this->getData('name')] = sprintf(
                    '<span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:%s;color:%s">%s</span>',
                    $badge['bg'],
                    $badge['color'],
                    $badge['label']
                );
            }
        }
        return $dataSource;
    }
}
