<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Model\ResourceModel\SalesForecast;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use GrupoAwamotos\SmartSuggestions\Model\SalesForecast as SalesForecastModel;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\SalesForecast as SalesForecastResource;

/**
 * Sales Forecast Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'forecast_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(SalesForecastModel::class, SalesForecastResource::class);
    }

    /**
     * Filter by date range
     */
    public function addDateRangeFilter(string $startDate, string $endDate): self
    {
        return $this->addFieldToFilter('forecast_date', ['gteq' => $startDate])
            ->addFieldToFilter('forecast_date', ['lteq' => $endDate]);
    }

    /**
     * Filter by forecast type
     */
    public function addTypeFilter(string $type): self
    {
        return $this->addFieldToFilter('forecast_type', $type);
    }

    /**
     * Get daily forecasts
     */
    public function getDailyForecasts(): self
    {
        return $this->addTypeFilter(SalesForecastModel::TYPE_DAILY);
    }

    /**
     * Get monthly forecasts
     */
    public function getMonthlyForecasts(): self
    {
        return $this->addTypeFilter(SalesForecastModel::TYPE_MONTHLY);
    }

    /**
     * Filter future forecasts only
     */
    public function addFutureFilter(): self
    {
        return $this->addFieldToFilter('forecast_date', ['gt' => date('Y-m-d')]);
    }

    /**
     * Filter forecasts with actual data
     */
    public function addHasActualDataFilter(): self
    {
        return $this->addFieldToFilter('actual_revenue', ['notnull' => true]);
    }

    /**
     * Order by date
     */
    public function orderByDate(string $direction = 'ASC'): self
    {
        return $this->setOrder('forecast_date', $direction);
    }

    /**
     * Get total predicted revenue for period
     */
    public function getTotalPredictedRevenue(): float
    {
        $this->getSelect()->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns(['total' => 'SUM(predicted_revenue)']);

        return (float) $this->getConnection()->fetchOne($this->getSelect());
    }

    /**
     * Get average confidence level
     */
    public function getAverageConfidence(): float
    {
        $this->getSelect()->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns(['avg_confidence' => 'AVG(confidence_level)']);

        return (float) $this->getConnection()->fetchOne($this->getSelect());
    }
}
