<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account\Cotacao;

use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer as B2bCustomerResource;
use GrupoAwamotos\B2B\Model\ResourceModel\Cotacao\CollectionFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Index extends Template
{
    protected $_template = 'GrupoAwamotos_B2B::cotacao/index.phtml';
    private readonly TimezoneInterface $timezone;

    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly CollectionFactory $collectionFactory,
        private readonly B2bCustomerResource $b2bCustomerResource,
        array $data = [],
        ?TimezoneInterface $timezone = null
    ) {
        // Optional after $data: compiled Interceptor omits TimezoneInterface.
        $this->timezone = $timezone ?? $context->getLocaleDate();
        parent::__construct($context, $data);
    }

    public function getCotacoes(): array
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        $row = $this->b2bCustomerResource->getByCustomerId($customerId);
        if (empty($row)) {
            return [];
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('b2b_customer_id', $row['b2b_customer_id']);
        $collection->setOrder('cotacao_id', 'DESC');

        return $collection->getItems();
    }

    public function getViewUrl(int $cotacaoId): string
    {
        return $this->getUrl('b2b/cotacao/view', ['id' => $cotacaoId]);
    }

    public function getNewQuoteUrl(): string
    {
        return $this->getUrl('b2b/quote');
    }

    /**
     * @param string|\DateTimeInterface|null $date
     * @param int $format
     * @param bool $showTime
     * @param string|null $timezone
     */
    public function formatDate(
        $date = null,
        $format = \IntlDateFormatter::SHORT,
        $showTime = false,
        $timezone = null
    ): string {
        if (func_num_args() <= 1) {
            if ($date === null || $date === '') {
                return '-';
            }

            $raw = $date instanceof \DateTimeInterface ? $date->format('c') : (string) $date;
            try {
                return $this->timezone->date(new \DateTime($raw))->format('d/m/Y');
            } catch (\Exception) {
                return substr($raw, 0, 10);
            }
        }

        return (string) parent::formatDate($date, $format, $showTime, $timezone);
    }
}
