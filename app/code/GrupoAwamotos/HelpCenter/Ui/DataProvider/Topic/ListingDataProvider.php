<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Ui\DataProvider\Topic;

use GrupoAwamotos\HelpCenter\Model\ResourceModel\Topic\CollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;

class ListingDataProvider extends AbstractDataProvider
{
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        private readonly CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $this->collectionFactory->create();
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        if (!$this->getCollection()->isLoaded()) {
            $this->getCollection()->load();
        }

        $data = $this->getCollection()->toArray();

        return [
            'totalRecords' => (int) ($data['totalRecords'] ?? 0),
            'items'        => is_array($data['items'] ?? null) ? $data['items'] : [],
        ];
    }
}
