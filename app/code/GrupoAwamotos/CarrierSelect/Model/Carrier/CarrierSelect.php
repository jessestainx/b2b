<?php

/**
 * Carrier Method - Seleção de Transportadora
 */

declare(strict_types=1);

namespace GrupoAwamotos\CarrierSelect\Model\Carrier;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Psr\Log\LoggerInterface;
use GrupoAwamotos\CarrierSelect\Model\ResourceModel\Carrier\CollectionFactory;

class CarrierSelect extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'carrierselect';

    /**
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @var ResultFactory
     */
    private ResultFactory $rateResultFactory;

    /**
     * @var MethodFactory
     */
    private MethodFactory $rateMethodFactory;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $carrierCollectionFactory;

    private CustomerSession $customerSession;

    private CustomerRepositoryInterface $customerRepository;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param CollectionFactory $carrierCollectionFactory
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        CollectionFactory $carrierCollectionFactory,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        array $data = []
    ) {
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->carrierCollectionFactory = $carrierCollectionFactory;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    private function getPreferredCarrierCode(): ?string
    {
        if (!$this->customerSession->isLoggedIn()) {
            return null;
        }

        try {
            $customerId = (int)$this->customerSession->getCustomerId();
            if ($customerId <= 0) {
                return null;
            }

            $customer = $this->customerRepository->getById($customerId);
            $attr = $customer->getCustomAttribute('b2b_carrier_code');
            $value = $attr ? trim((string)$attr->getValue()) : '';

            return $value !== '' ? $value : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * Collect and get rates
     *
     * @param RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $result = $this->rateResultFactory->create();

        $preferredCode = $this->getPreferredCarrierCode();

        if ($preferredCode !== null) {
            // Cliente tem transportadora preferencial (do ERP) — mostra só ela
            $preferredCarriers = $this->carrierCollectionFactory->create()
                ->addActiveFilter()
                ->addFieldToFilter('code', $preferredCode);

            if ($preferredCarriers->getSize() > 0) {
                foreach ($preferredCarriers as $carrier) {
                    $method = $this->rateMethodFactory->create();
                    $method->setCarrier($this->_code);
                    $method->setCarrierTitle((string) $carrier->getName());
                    $method->setMethod($carrier->getCode());
                    $method->setMethodTitle(__('Frete a combinar'));
                    $method->setPrice(0);
                    $method->setCost(0);
                    $result->append($method);
                }
                return $result;
            }
        }

        // Fallback: cliente sem transportadora definida — mostra opção genérica
        $method = $this->rateMethodFactory->create();
        $method->setCarrier($this->_code);
        $method->setCarrierTitle(__('Frete'));
        $method->setMethod('acombinar');
        $method->setMethodTitle(__('Frete a combinar — transportadora será definida'));
        $method->setPrice(0);
        $method->setCost(0);
        $result->append($method);

        return $result;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods(): array
    {
        $methods = ['acombinar' => __('Frete a combinar')];

        $preferredCode = $this->getPreferredCarrierCode();
        if ($preferredCode !== null) {
            $carriers = $this->carrierCollectionFactory->create()
                ->addActiveFilter()
                ->addFieldToFilter('code', $preferredCode);

            foreach ($carriers as $carrier) {
                $methods[$carrier->getCode()] = $carrier->getName();
            }
        }

        return $methods;
    }
}
