<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Ui\DataProvider;

use GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\RepurchaseSuggestionService;
use Magento\Framework\Api\Filter;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class RepurchaseSuggestionDataProvider extends AbstractDataProvider
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $loadedItems = null;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        private readonly RepurchaseSuggestionService $repurchaseSuggestionService,
        private readonly RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Array-backed provider: UI paging must not touch a null collection.
     *
     * @param int $offset
     * @param int $size
     */
    public function setLimit($offset, $size): void
    {
    }

    /**
     * @inheritdoc
     */
    public function addFilter(Filter $filter)
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addOrder($field, $direction)
    {
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        if ($this->loadedItems === null) {
            $this->loadedItems = $this->repurchaseSuggestionService->getSuggestions(500);
        }

        return [
            'totalRecords' => count($this->loadedItems),
            'items' => $this->loadedItems,
        ];
    }
}
