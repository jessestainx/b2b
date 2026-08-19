<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account\Cotacao;

use GrupoAwamotos\B2B\Model\Cotacao;
use GrupoAwamotos\B2B\Model\ResourceModel\CotacaoItem as CotacaoItemResource;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class View extends Template
{
    protected $_template = 'GrupoAwamotos_B2B::cotacao/view.phtml';

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly CotacaoItemResource $cotacaoItemResource,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly TimezoneInterface $timezone,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCotacao(): ?Cotacao
    {
        return $this->registry->registry('current_cotacao');
    }

    public function getItems(): array
    {
        $cotacao = $this->getCotacao();
        if (!$cotacao) {
            return [];
        }
        return $this->cotacaoItemResource->getItemsByCotacaoId((int) $cotacao->getId());
    }

    public function getRespondUrl(): string
    {
        return $this->getUrl('b2b/cotacao/respond');
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('b2b/cotacao');
    }

    public function getCreateUrl(): string
    {
        return $this->getUrl('b2b/cotacao/create');
    }

    public function formatPrice(float $price): string
    {
        return $this->priceCurrency->format($price, false);
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

    /**
     * Runtime probe for duplicate heading validation.
     */
}
