<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Psr\Log\LoggerInterface;

/**
 * Persiste auditoria de alteração do opt-in de WhatsApp.
 */
class LogConsentChange implements ObserverInterface
{
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;
    /**
     * @var HttpRequest
     */
    private HttpRequest $request;
    /**
     * @var RemoteAddress
     */
    private RemoteAddress $remoteAddress;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constrói o observer com dependências de persistência e contexto HTTP.
     *
     * @param ResourceConnection $resourceConnection
     * @param HttpRequest $request
     * @param RemoteAddress $remoteAddress
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        HttpRequest $request,
        RemoteAddress $remoteAddress,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->request = $request;
        $this->remoteAddress = $remoteAddress;
        $this->logger = $logger;
    }

    /**
     * Registra mudanças de consentimento quando o cliente é salvo.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var \Magento\Customer\Model\Customer $customer */
        $customer = $observer->getEvent()->getCustomer();

        if (!$customer || !$customer->getId()) {
            return;
        }

        $newOptin = (int) $customer->getData('whatsapp_optin');
        $origOptin = (int) ($customer->getOrigData('whatsapp_optin') ?? 0);

        if ($newOptin === $origOptin) {
            return;
        }

        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('awa_whatsapp_consent_log');
            $source = $this->detectSource();

            $phone = $customer->getData('telephone')
                ?? $customer->getDefaultBillingAddress()?->getTelephone()
                ?? '';

            $connection->insert($tableName, [
                'customer_id' => (int) $customer->getId(),
                'phone' => (string) $phone,
                'optin' => $newOptin,
                'source' => $source,
                'ip_address' => $this->remoteAddress->getRemoteAddress(),
                'user_agent' => $this->request->getServer('HTTP_USER_AGENT'),
            ]);

            $this->logger->info('WhatsApp consent changed', [
                'customer_id' => $customer->getId(),
                'optin' => $newOptin,
                'source' => $source,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to log WhatsApp consent change: ' . $e->getMessage(), [
                'customer_id' => $customer->getId(),
            ]);
        }
    }

    /**
     * Determina a origem da alteração com base na URL atual.
     *
     * @return string
     */
    private function detectSource(): string
    {
        $uri = (string) ($this->request->getRequestUri() ?? '');

        if (str_contains($uri, '/admin/') || str_contains($uri, 'adminhtml')) {
            return 'admin';
        }

        if (str_contains($uri, '/rest/') || str_contains($uri, '/graphql')) {
            return 'api';
        }

        if (str_contains($uri, 'checkout')) {
            return 'checkout';
        }

        return 'account';
    }
}
