<?php

declare(strict_types=1);

namespace GrupoAwamotos\LeadLovers\Model;

use GrupoAwamotos\LeadLovers\Helper\Config;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class LeadLoversClient
{
    private const API_URL = 'https://llapi.leadlovers.com/webapi/lead';

    private const FIELD_CNPJ          = 119189;
    private const FIELD_CEP           = 119233;
    private const FIELD_COMPLEMENTO   = 119232;
    private const FIELD_IE            = 11228;
    private const FIELD_LOGRADOURO    = 119230;
    private const FIELD_NOME_FANTASIA = 119227;
    private const FIELD_NUMERO        = 119231;
    private const FIELD_RAZAO_SOCIAL  = 119226;
    private const FIELD_ESTADO        = 119234;

    public function __construct(
        private readonly Config $config,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly Curl $curl,
        private readonly LoggerInterface $logger
    ) {
    }

    public function sendLeadFromCustomer(CustomerInterface $customer): bool
    {
        if (!$this->config->isEnabled()) {
            return false;
        }

        $token = $this->config->getApiToken();
        if ($token === '') {
            $this->logger->error('[LeadLovers] API token nao configurado.');
            return false;
        }

        try {
            $payload = $this->buildCustomerPayload($customer);
            return $this->post($token, $payload, 'customer#' . (string) $customer->getId());
        } catch (\Throwable $e) {
            $this->logger->error('[LeadLovers] Excecao ao enviar lead (cadastro).', [
                'customer_id' => $customer->getId(),
                'exception'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function sendLeadFromOrder(Order $order): bool
    {
        if (!$this->config->isEnabled()) {
            return false;
        }

        $token = $this->config->getApiToken();
        if ($token === '') {
            $this->logger->error('[LeadLovers] API token nao configurado.');
            return false;
        }

        try {
            $payload = $this->buildPayload($order);
            return $this->post($token, $payload, (string) $order->getIncrementId());
        } catch (\Throwable $e) {
            $this->logger->error('[LeadLovers] Excecao ao enviar lead.', [
                'order'     => $order->getIncrementId(),
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCustomerPayload(CustomerInterface $customer): array
    {
        $name  = trim($customer->getFirstname() . ' ' . $customer->getLastname());
        $email = (string) $customer->getEmail();

        $cnpj        = $this->getCustomAttr($customer, 'cnpj');
        $ie          = $this->getCustomAttr($customer, 'ie');
        $tradeName   = $this->getCustomAttr($customer, 'trade_name');
        $companyName = $this->getCustomAttr($customer, 'company_name');

        $dynamicFields = [
            ['Id' => self::FIELD_CNPJ,         'Value' => $cnpj],
            ['Id' => self::FIELD_IE,            'Value' => $ie],
            ['Id' => self::FIELD_NOME_FANTASIA, 'Value' => $tradeName],
            ['Id' => self::FIELD_RAZAO_SOCIAL,  'Value' => $companyName],
        ];

        // Remove campos vazios para nao poluir o lead com dados em branco
        $dynamicFields = array_values(array_filter(
            $dynamicFields,
            static fn(array $f): bool => $f['Value'] !== ''
        ));

        return [
            'Name'              => $name,
            'Email'             => $email,
            'MachineCode'       => $this->config->getMachineCode(),
            'EmailSequenceCode' => $this->config->getSequenceCode(),
            'SequenceLevelCode' => $this->config->getSequenceLevel(),
            'Company'           => $companyName,
            'Tag'               => $this->config->getTagId(),
            'DynamicFields'     => $dynamicFields,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Order $order): array
    {
        $billing  = $order->getBillingAddress();
        $customer = $this->loadCustomer((int) $order->getCustomerId());

        $name     = trim($order->getCustomerFirstname() . ' ' . $order->getCustomerLastname());
        $email    = (string) $order->getCustomerEmail();
        $phone    = $billing !== null ? (string) $billing->getTelephone() : '';
        $city     = $billing !== null ? (string) $billing->getCity() : '';
        $region   = $billing !== null ? (string) $billing->getRegion() : '';
        $postcode = $billing !== null ? (string) $billing->getPostcode() : '';

        $logradouro  = $billing !== null ? (string) $billing->getStreetLine(1) : '';
        $numero      = $billing !== null ? (string) $billing->getStreetLine(2) : '';
        $complemento = $billing !== null ? (string) $billing->getStreetLine(3) : '';

        $cnpj        = $this->getCustomAttr($customer, 'cnpj');
        $ie          = $this->getCustomAttr($customer, 'ie');
        $tradeName   = $this->getCustomAttr($customer, 'trade_name');
        $companyName = $this->getCustomAttr($customer, 'company_name');

        $company = $companyName !== ''
            ? $companyName
            : ($billing !== null ? (string) $billing->getCompany() : '');

        return [
            'Name'              => $name,
            'Email'             => $email,
            'MachineCode'       => $this->config->getMachineCode(),
            'EmailSequenceCode' => $this->config->getSequenceCode(),
            'SequenceLevelCode' => $this->config->getSequenceLevel(),
            'Company'           => $company,
            'Phone'             => $phone,
            'City'              => $city,
            'Tag'               => $this->config->getTagId(),
            'DynamicFields'     => [
                ['Id' => self::FIELD_CNPJ,          'Value' => $cnpj],
                ['Id' => self::FIELD_CEP,            'Value' => $postcode],
                ['Id' => self::FIELD_COMPLEMENTO,    'Value' => $complemento],
                ['Id' => self::FIELD_IE,             'Value' => $ie],
                ['Id' => self::FIELD_LOGRADOURO,     'Value' => $logradouro],
                ['Id' => self::FIELD_NOME_FANTASIA,  'Value' => $tradeName],
                ['Id' => self::FIELD_NUMERO,         'Value' => $numero],
                ['Id' => self::FIELD_RAZAO_SOCIAL,   'Value' => $companyName],
                ['Id' => self::FIELD_ESTADO,         'Value' => $region],
            ],
        ];
    }

    private function loadCustomer(int $customerId): ?CustomerInterface
    {
        if ($customerId <= 0) {
            return null;
        }

        try {
            return $this->customerRepository->getById($customerId);
        } catch (\Throwable $e) {
            $this->logger->warning(
                '[LeadLovers] Nao foi possivel carregar cliente #' . $customerId . ': ' . $e->getMessage()
            );
            return null;
        }
    }

    private function getCustomAttr(?CustomerInterface $customer, string $code): string
    {
        if ($customer === null) {
            return '';
        }

        $attr = $customer->getCustomAttribute($code);
        return $attr !== null ? (string) $attr->getValue() : '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(string $token, array $payload, string $orderId): bool
    {
        $url  = self::API_URL . '?' . http_build_query(['token' => $token]);
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->addHeader('Accept', 'application/json');
        $this->curl->setTimeout(15);
        $this->curl->post($url, $body);

        $status   = $this->curl->getStatus();
        $response = $this->curl->getBody();

        if ($status === 0) {
            $this->logger->error('[LeadLovers] Falha de conexao (timeout ou DNS). Ref: ' . $orderId);
            return false;
        }

        if ($status >= 200 && $status < 300) {
            $this->logger->info('[LeadLovers] Lead enviado com sucesso.', [
                'ref'    => $orderId,
                'email'  => $payload['Email'],
                'status' => $status,
            ]);
            return true;
        }

        $this->logger->error('[LeadLovers] Falha ao enviar lead.', [
            'ref'      => $orderId,
            'email'    => $payload['Email'],
            'status'   => $status,
            'response' => substr($response, 0, 500),
        ]);
        return false;
    }
}
