<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\Component\Listing\Column;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Coluna "Private Label" no grid de produtos do admin.
 * Mostra "✔ [Label] → [e-mail do cliente]" para produtos exclusivos
 * e fica vazia para produtos normais.
 */
class PrivateLabelColumn extends Column
{
    private const EXCLUSIVE_TABLE = 'grupoawamotos_b2b_exclusive_product';

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly ResourceConnection $resourceConnection,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (empty($dataSource['data']['items'])) {
            return $dataSource;
        }

        $productIds = array_column($dataSource['data']['items'], 'entity_id');
        $map        = $this->loadExclusiveMap($productIds);

        foreach ($dataSource['data']['items'] as &$item) {
            $id = (int) ($item['entity_id'] ?? 0);
            $item[$this->getData('name')] = isset($map[$id])
                ? sprintf('✔ %s → %s', $map[$id]['label'] ?? 'Private Label', $map[$id]['email'])
                : '';
        }
        unset($item);

        return $dataSource;
    }

    /**
     * Retorna array [ product_id => ['label' => ..., 'email' => ...] ]
     * para os IDs recebidos que forem exclusivos.
     *
     * @param int[] $productIds
     * @return array<int,array{label:string|null,email:string}>
     */
    private function loadExclusiveMap(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $connection     = $this->resourceConnection->getConnection();
        $exclusiveTable = $this->resourceConnection->getTableName(self::EXCLUSIVE_TABLE);
        $customerTable  = $this->resourceConnection->getTableName('customer_entity');

        $select = $connection->select()
            ->from(['ep' => $exclusiveTable], ['product_id', 'label'])
            ->join(['ce' => $customerTable], 'ce.entity_id = ep.customer_id', ['email'])
            ->where('ep.product_id IN (?)', $productIds);

        $rows = $connection->fetchAll($select);
        $map  = [];

        foreach ($rows as $row) {
            $map[(int) $row['product_id']] = [
                'label' => $row['label'],
                'email' => $row['email'],
            ];
        }

        return $map;
    }
}
