<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account\Subscription;

use Magento\Framework\View\Element\Template;
use Magento\Customer\Model\Session;
use GrupoAwamotos\B2B\Model\ResourceModel\Subscription\CollectionFactory;
use GrupoAwamotos\B2B\Model\Subscription;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class ListSubscription extends Template
{
    private $customerSession;
    private $collectionFactory;
    private $timezone;

    public function __construct(
        Template\Context $context,
        Session $customerSession,
        CollectionFactory $collectionFactory,
        array $data = [],
        ?TimezoneInterface $timezone = null
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->collectionFactory = $collectionFactory;
        // Optional after $data: compiled Interceptor omits TimezoneInterface.
        $this->timezone = $timezone ?? $context->getLocaleDate();
    }

    /**
     * Get customer subscriptions
     *
     * @return \GrupoAwamotos\B2B\Model\ResourceModel\Subscription\Collection
     */
    public function getSubscriptions()
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        return $this->collectionFactory->create()
            ->filterByCustomer($customerId)
            ->setOrder('created_at', 'DESC');
    }

    /**
     * Get status label
     *
     * @param string $status
     * @return string
     */
    public function getStatusLabel(string $status): string
    {
        $statuses = Subscription::getStatuses();
        return (string) ($statuses[$status] ?? $status);
    }

    /**
     * Get frequency label
     *
     * @param string $frequency
     * @return string
     */
    public function getFrequencyLabel(string $frequency): string
    {
        $frequencies = Subscription::getFrequencies();
        return (string) ($frequencies[$frequency] ?? $frequency);
    }

    /**
     * Get delete URL
     *
     * @param Subscription $subscription
     * @return string
     */
    public function getDeleteUrl(Subscription $subscription): string
    {
        return $this->getUrl('b2b/subscription/delete', ['id' => $subscription->getId()]);
    }

    /**
     * Get edit URL
     *
     * @param Subscription $subscription
     * @return string
     */
    public function getEditUrl(Subscription $subscription): string
    {
        return $this->getUrl('b2b/subscription/edit', ['id' => $subscription->getId()]);
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
