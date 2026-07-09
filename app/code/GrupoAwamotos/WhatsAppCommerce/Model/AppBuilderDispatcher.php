<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Model;

use GrupoAwamotos\WhatsAppCommerce\Helper\Config;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

/**
 * Dispatches order-notification payloads to the Adobe App Builder "notify-order"
 * action instead of calling the WhatsApp API in-process.
 *
 * Why this exists: WhatsappSenderInterface::sendMessage() makes a synchronous
 * outbound HTTP call to the WhatsApp/Evolution/Twilio API. When invoked from
 * sales_order_place_after (checkout) or sales_order_invoice_pay/shipment/
 * creditmemo (admin) observers, that call holds a PHP-FPM worker on this VPS
 * for as long as the provider takes to respond. This dispatcher hands the
 * whole job off to serverless Adobe I/O Runtime with a short, bounded
 * timeout — the Magento request only waits long enough to confirm the action
 * *received* the job, not for the WhatsApp round-trip itself.
 *
 * Payloads are authenticated with an HMAC-SHA256 signature (shared secret,
 * configured in both this module and the action's WEBHOOK_SHARED_SECRET
 * input) since the action endpoint has no Adobe IMS session to check against.
 */
class AppBuilderDispatcher
{
    public function __construct(
        private readonly Curl $curl,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return bool true if the action accepted the job (2xx), false otherwise.
     *              A false result means the caller should fall back to the
     *              synchronous send path.
     */
    public function dispatch(string $phone, string $orderId, string $event, array $data = []): bool
    {
        $webhookUrl = $this->config->getAppBuilderWebhookUrl();
        $sharedSecret = $this->config->getAppBuilderSharedSecret();

        if ($webhookUrl === '' || $sharedSecret === '') {
            return false;
        }

        $payload = array_merge($data, [
            'order_id' => $orderId,
            'event' => $event,
            'phone' => $phone,
        ]);

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            $this->logger->error('AppBuilderDispatcher: failed to encode payload', ['order_id' => $orderId]);
            return false;
        }

        $signature = hash_hmac('sha256', $body, $sharedSecret);

        try {
            $this->curl->setHeaders([
                'Content-Type' => 'application/json',
                'X-Awa-Signature' => $signature,
            ]);
            $this->curl->setTimeout($this->config->getAppBuilderTimeoutSeconds());
            $this->curl->post($webhookUrl, $body);

            $status = $this->curl->getStatus();
            if ($status >= 200 && $status < 300) {
                return true;
            }

            $this->logger->warning('AppBuilderDispatcher: action returned non-2xx status', [
                'order_id' => $orderId,
                'event' => $event,
                'status' => $status,
                'response' => substr((string) $this->curl->getBody(), 0, 500),
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('AppBuilderDispatcher: dispatch failed, falling back to sync send', [
                'order_id' => $orderId,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
