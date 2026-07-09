<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\Component\Listing;

use GrupoAwamotos\B2B\Model\ResourceModel\Cotacao\CollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;

class CotacaoDataProvider extends AbstractDataProvider
{
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    public function getData(): array
    {
        if (!$this->getCollection()->isLoaded()) {
            $this->getCollection()->load();
        }

        $items = $this->getCollection()->toArray();

        return [
            'totalRecords' => $this->getCollection()->getSize(),
            'items'        => array_values($items['items'] ?? $items),
        ];
    }
}
