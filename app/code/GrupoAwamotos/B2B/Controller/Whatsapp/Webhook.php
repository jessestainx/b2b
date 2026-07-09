<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Whatsapp;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditLimit\CollectionFactory as CreditCollectionFactory;
use GrupoAwamotos\ERPIntegration\Helper\Data as ErpHelper;
use GrupoAwamotos\ERPIntegration\Model\WhatsApp\ZApiClient;
use Psr\Log\LoggerInterface;

/**
 * WhatsApp Webhook Controller
 *
 * Handles incoming messages from Z-API (WhatsApp Gateway)
 * Provides automated responses for B2B customers.
 */
class Webhook extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private JsonFactory $resultJsonFactory;
    private CustomerCollectionFactory $customerCollectionFactory;
    private OrderCollectionFactory $orderCollectionFactory;
    private CreditCollectionFactory $creditCollectionFactory;
    private ZApiClient $zapiClient;
    private ErpHelper $erpHelper;
    private LoggerInterface $logger;
    private StoreManagerInterface $storeManager;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CustomerCollectionFactory $customerCollectionFactory,
        OrderCollectionFactory $orderCollectionFactory,
        CreditCollectionFactory $creditCollectionFactory,
        ZApiClient $zapiClient,
        ErpHelper $erpHelper,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->creditCollectionFactory = $creditCollectionFactory;
        $this->zapiClient = $zapiClient;
        $this->erpHelper = $erpHelper;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->verifyWebhookToken()) {
            $this->logger->warning('[WhatsApp Bot] Webhook rejected: invalid or missing Client-Token');
            return $result->setHttpResponseCode(403)
                ->setData(['status' => 'forbidden']);
        }

        $payload = $this->getRequest()->getContent();
        $data = json_decode($payload, true);

        if (!$data || !isset($data['text']['message'])) {
            return $result->setData(['status' => 'ignored']);
        }

        $from = $data['phone'] ?? '';
        $message = trim(strtolower($data['text']['message']));

        // 1. Identify Customer by Phone
        $customer = $this->identifyCustomer($from);
        if (!$customer) {
            // Not a registered B2B customer phone or multiple matches
            return $result->setData(['status' => 'customer_not_found']);
        }

        // 2. Process Intent
        $response = '';
        if (str_contains($message, 'pedido') || str_contains($message, 'status')) {
            $response = $this->getOrderStatusResponse($customer);
        } elseif (str_contains($message, 'limite') || str_contains($message, 'credito')) {
            $response = $this->getCreditLimitResponse($customer);
        } elseif (str_contains($message, 'ajuda') || str_contains($message, 'oi') || str_contains($message, 'ola')) {
            $response = $this->getHelpResponse($customer);
        }

        // 3. Send Response
        if ($response) {
            try {
                $this->zapiClient->sendTextMessage($from, $response);
            } catch (\Exception $e) {
                $this->logger->error('[WhatsApp Bot] Failed to send response: ' . $e->getMessage());
            }
        }

        return $result->setData(['status' => 'processed']);
    }

    /**
     * Identify customer by phone number
     */
    private function identifyCustomer($phone)
    {
        if (empty($phone)) {
            return null;
        }

        // Clean phone (keep only digits)
        $cleanPhone = preg_replace('/\D/', '', $phone);
        // Remove country code if present (e.g. 55)
        if (str_starts_with($cleanPhone, '55')) {
            $cleanPhone = substr($cleanPhone, 2);
        }

        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToSelect(['firstname', 'lastname', 'group_id'])
            ->addAttributeToFilter([
                ['attribute' => 'whatsapp', 'like' => '%' . $cleanPhone . '%'],
                ['attribute' => 'celular', 'like' => '%' . $cleanPhone . '%'],
                ['attribute' => 'telephone', 'like' => '%' . $cleanPhone . '%']
            ]);

        if ($collection->getSize() === 1) {
            return $collection->getFirstItem();
        }

        return null;
    }

    /**
     * Get orders status response
     */
    private function getOrderStatusResponse($customer)
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customer->getId())
            ->setOrder('created_at', 'DESC')
            ->setPageSize(3);

        if ($collection->getSize() === 0) {
            return "Olá {$customer->getFirstname()}, você ainda não possui pedidos registrados conosco.";
        }

        $response = "Olá {$customer->getFirstname()}! Aqui estão seus últimos pedidos:\n\n";
        foreach ($collection as $order) {
            $status = $order->getStatusLabel();
            $total = number_format((float)$order->getGrandTotal(), 2, ',', '.');
            $date = date('d/m/Y', strtotime($order->getCreatedAt()));
            $response .= "📦 *#{$order->getIncrementId()}* ({$date})\n";
            $response .= "Status: *{$status}*\n";
            $response .= "Total: R$ {$total}\n\n";
        }
        $response .= "Acesse o portal para mais detalhes: " . $this->getDashboardUrl();

        return $response;
    }

    /**
     * Get credit limit response
     */
    private function getCreditLimitResponse($customer)
    {
        $collection = $this->creditCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customer->getId())
            ->setOrder('created_at', 'DESC')
            ->setPageSize(1);

        $credit = $collection->getFirstItem();
        if (!$credit || !$credit->getId()) {
            return "Olá {$customer->getFirstname()}, não encontramos informações de limite de crédito para sua conta.";
        }

        $limit = number_format((float)$credit->getCreditLimit(), 2, ',', '.');
        $used = number_format((float)$credit->getUsedCredit(), 2, ',', '.');
        $available = number_format((float)$credit->getCreditLimit() - (float)$credit->getUsedCredit(), 2, ',', '.');

        return "Olá {$customer->getFirstname()}! Seu status de crédito atual:\n\n"
            . "💳 *Limite Total:* R$ {$limit}\n"
            . "🔴 *Utilizado:* R$ {$used}\n"
            . "🟢 *Disponível:* R$ {$available}\n\n"
            . "Boas compras!";
    }

    /**
     * Build the B2B account dashboard URL using the current store's base URL.
     */
    private function getDashboardUrl(): string
    {
        try {
            return rtrim($this->storeManager->getStore()->getBaseUrl(), '/') . '/b2b/account/dashboard';
        } catch (\Exception $e) {
            $this->logger->error('[WhatsApp Bot] Failed to resolve store base URL: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Get help response
     */
    private function getHelpResponse($customer)
    {
        return "Olá {$customer->getFirstname()}! Eu sou o assistente virtual do Grupo AWA Motos. 🏍️\n\n"
            . "Você pode me perguntar sobre:\n"
            . "👉 *Pedidos* - Para ver o status das suas compras\n"
            . "👉 *Limite* - Para consultar seu saldo de crédito\n\n"
            . "Estou aqui para ajudar!";
    }

    /**
     * Z-API envia Client-Token no header.
     * Aceitamos X-Webhook-Token apenas por compatibilidade de proxy legado.
     */
    private function verifyWebhookToken(): bool
    {
        $expected = $this->erpHelper->getZApiClientToken();
        if ($expected === '') {
            $this->logger->warning('[WhatsApp Bot] Webhook rejected: ZAPI_CLIENT_TOKEN not configured');
            return false;
        }

        $request = $this->getRequest();
        $token = (string) $request->getHeader('Client-Token');
        if ($token === '') {
            $token = (string) $request->getHeader('X-Webhook-Token');
        }

        // Security: never accept token via query string (leaks in logs/proxies/referers).
        if ($token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
