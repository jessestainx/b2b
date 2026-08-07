<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Ui\DataProvider;

use GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence\InactiveCustomerService;
use Magento\Framework\Api\Filter;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class InactiveCustomerDataProvider extends AbstractDataProvider
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $loadedItems = null;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        private readonly InactiveCustomerService $inactiveCustomerService,
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
            [$minDays, $maxDays] = $this->resolveDayFilter();
            $this->loadedItems = $this->inactiveCustomerService->getInactiveCustomers($minDays, $maxDays);
        }

        return [
            'totalRecords' => count($this->loadedItems),
            'items' => $this->loadedItems,
        ];
    }

    /**
     * @return array{0: int, 1: int|null}
     */
    private function resolveDayFilter(): array
    {
        $preset = (int) $this->request->getParam('inactive_days', 30);
        $customMin = (int) $this->request->getParam('inactive_days_custom', 0);

        if ($customMin > 0) {
            return [$customMin, null];
        }

        if (!in_array($preset, InactiveCustomerService::PRESET_DAYS, true)) {
            $preset = 30;
        }

        return [$preset, null];
    }
}
