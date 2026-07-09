<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\WhatsApp;

use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

/**
 * Z-API WhatsApp Client
 *
 * Integrates with Z-API for WhatsApp messaging:
 * - Order status notifications
 * - Re-engagement messages with coupons
 * - Product suggestions
 * - General notifications
 *
 * @see https://developer.z-api.io/
 */
class ZApiClient
{
    private const API_BASE_URL = 'https://api.z-api.io/instances';
    private const WARNING_COOLDOWN_SECONDS = 21600;
    private const LOCK_DIR = '/var/locks';
    private const MISSING_CLIENT_TOKEN_LOCK = 'zapi_missing_client_token.lock';
    private const MISSING_CLIENT_TOKEN_MESSAGE =
        'Client Token required by this Z-API instance. Configure grupoawamotos_erp/whatsapp/zapi_client_token or ENV ZAPI_CLIENT_TOKEN.';

    private Helper $helper;
    private Curl $curl;
    private LoggerInterface $logger;
    private ?string $lastErrorMessage = null;

    public function __construct(
        Helper $helper,
        Curl $curl,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
        $this->curl = $curl;
        $this->logger = $logger;
    }

    /**
     * Get the full API URL for an endpoint
     */
    private function getApiUrl(string $endpoint): string
    {
        $instanceId = $this->helper->getZApiInstanceId();
        $token = $this->helper->getZApiToken();

        return sprintf(
            '%s/%s/token/%s/%s',
            self::API_BASE_URL,
            $instanceId,
            $token,
            $endpoint
        );
    }

    /**
     * Send a simple text message
     *
     * @param string $phoneNumber Phone number with country code (e.g., 5511999999999)
     * @param string $message Message text
     * @return array|null Response data or null on failure
     */
    public function sendTextMessage(string $phoneNumber, string $message): ?array
    {
        if (!$this->isConfigured()) {
            $this->logger->warning('[Z-API] WhatsApp not configured');
            return null;
        }

        $phoneNumber = $this->normalizePhoneNumber($phoneNumber);

        if (!$phoneNumber) {
            $this->logger->warning('[Z-API] Invalid phone number provided');
            return null;
        }

        $payload = [
            'phone' => $phoneNumber,
            'message' => $message,
        ];

        return $this->sendRequest('send-text', $payload);
    }

    /**
     * Send message with buttons
     *
     * @param string $phoneNumber Phone number
     * @param string $message Main message
     * @param array $buttons Array of buttons ['id' => 'btn1', 'text' => 'Button 1']
     * @param string|null $title Optional title
     * @param string|null $footer Optional footer
     * @return array|null
     */
    public function sendButtonMessage(
        string $phoneNumber,
        string $message,
        array $buttons,
        ?string $title = null,
        ?string $footer = null
    ): ?array {
        if (!$this->isConfigured()) {
            return null;
        }

        $phoneNumber = $this->normalizePhoneNumber($phoneNumber);

        if (!$phoneNumber) {
            return null;
        }

        $payload = [
            'phone' => $phoneNumber,
            'message' => $message,
            'buttons' => $buttons,
        ];

        if ($title) {
            $payload['title'] = $title;
        }

        if ($footer) {
            $payload['footer'] = $footer;
        }

        return $this->sendRequest('send-button-actions', $payload);
    }

    /**
     * Send message with link
     *
     * @param string $phoneNumber Phone number
     * @param string $message Message text
     * @param string $linkUrl URL to send
     * @param string|null $linkTitle Link title
     * @param string|null $linkDescription Link description
     * @return array|null
     */
    public function sendLinkMessage(
        string $phoneNumber,
        string $message,
        string $linkUrl,
        ?string $linkTitle = null,
        ?string $linkDescription = null
    ): ?array {
        if (!$this->isConfigured()) {
            return null;
        }

        $phoneNumber = $this->normalizePhoneNumber($phoneNumber);

        if (!$phoneNumber) {
            return null;
        }

        $payload = [
            'phone' => $phoneNumber,
            'message' => $message,
            'linkUrl' => $linkUrl,
        ];

        if ($linkTitle) {
            $payload['title'] = $linkTitle;
        }

        if ($linkDescription) {
            $payload['linkDescription'] = $linkDescription;
        }

        return $this->sendRequest('send-link', $payload);
    }

    /**
     * Send order status update notification
     *
     * @param string $phoneNumber Customer phone
     * @param string $orderNumber Order increment ID
     * @param string $status Order status
     * @param string|null $trackingCode Optional tracking code
     * @return array|null
     */
    public function sendOrderStatus(
        string $phoneNumber,
        string $orderNumber,
        string $status,
        ?string $trackingCode = null
    ): ?array {
        $statusLabels = [
            'pending' => 'Pendente',
            'pending_payment' => 'Aguardando Pagamento',
            'processing' => 'Em Processamento',
            'complete' => 'Entregue',
            'shipped' => 'Enviado',
            'canceled' => 'Cancelado',
            'holded' => 'Em Espera',
            'payment_review' => 'Revisao de Pagamento',
        ];

        $statusLabel = $statusLabels[$status] ?? ucfirst($status);
        $storeName = $this->helper->getStoreName();

        // Build message
        $message = "*{$storeName}* - Atualizacao do Pedido\n\n";
        $message .= "Pedido: *#{$orderNumber}*\n";
        $message .= "Status: *{$statusLabel}*\n";

        if ($trackingCode && in_array($status, ['shipped', 'complete'])) {
            $message .= "\nCodigo de Rastreio: *{$trackingCode}*\n";
            $message .= "Rastreie em: https://rastreamento.correios.com.br/app/resultado.php?objeto={$trackingCode}";
        }

        $message .= "\n\nDuvidas? Responda esta mensagem!";

        $result = $this->sendTextMessage($phoneNumber, $message);

        if ($result) {
            $this->logger->info(sprintf(
                '[Z-API] Order status sent: Order #%s, Status: %s, Phone: %s',
                $orderNumber,
                $status,
                $this->maskPhoneNumber($phoneNumber)
            ));
        }

        return $result;
    }

    /**
     * Send re-engagement message with coupon
     *
     * @param string $phoneNumber Customer phone
     * @param string $customerName Customer first name
     * @param string $couponCode Coupon code
     * @param int $discountPercent Discount percentage
     * @param int $validDays Days until coupon expires
     * @return array|null
     */
    public function sendReengagementMessage(
        string $phoneNumber,
        string $customerName,
        string $couponCode,
        int $discountPercent,
        int $validDays = 30
    ): ?array {
        $storeName = $this->helper->getStoreName();
        $storeUrl = $this->helper->getStoreUrl();

        $message = "Ola {$customerName}! Sentimos sua falta na *{$storeName}*.\n\n";
        $message .= "Preparamos um cupom especial para voce:\n\n";
        $message .= "*{$discountPercent}% OFF* em todo o site!\n\n";
        $message .= "Cupom: *{$couponCode}*\n";
        $message .= "Valido por {$validDays} dias\n\n";
        $message .= "Acesse agora: {$storeUrl}\n\n";
        $message .= "Estamos com novidades incriveis esperando por voce!";

        $result = $this->sendTextMessage($phoneNumber, $message);

        if ($result) {
            $this->logger->info(sprintf(
                '[Z-API] Re-engagement sent to %s (coupon: %s, %d%% off)',
                $this->maskPhoneNumber($phoneNumber),
                $couponCode,
                $discountPercent
            ));
        }

        return $result;
    }

    /**
     * Send product suggestions message
     *
     * @param string $phoneNumber Customer phone
     * @param string $customerName Customer name
     * @param array $products Array of suggested products
     * @return array|null
     */
    public function sendProductSuggestions(
        string $phoneNumber,
        string $customerName,
        array $products
    ): ?array {
        if (empty($products)) {
            return null;
        }

        $storeName = $this->helper->getStoreName();

        $message = "Ola {$customerName}!\n\n";
        $message .= "Baseado em suas compras na *{$storeName}*, selecionamos produtos especiais para voce:\n\n";

        foreach (array_slice($products, 0, 5) as $index => $product) {
            $name = $product['name'] ?? 'Produto';
            $price = isset($product['price'])
                ? 'R$ ' . number_format((float)$product['price'], 2, ',', '.')
                : '';
            $sku = $product['sku'] ?? '';

            $message .= ($index + 1) . ". *{$name}*\n";
            if ($price) {
                $message .= "   {$price}\n";
            }
            if (isset($product['url'])) {
                $message .= "   {$product['url']}\n";
            }
            $message .= "\n";
        }

        $message .= "Responda com o numero do produto para mais informacoes!";

        return $this->sendTextMessage($phoneNumber, $message);
    }

    /**
     * Send welcome message to new customer
     *
     * @param string $phoneNumber Customer phone
     * @param string $customerName Customer name
     * @return array|null
     */
    public function sendWelcomeMessage(
        string $phoneNumber,
        string $customerName
    ): ?array {
        $storeName = $this->helper->getStoreName();
        $storeUrl = $this->helper->getStoreUrl();

        $message = "Bem-vindo(a) a *{$storeName}*, {$customerName}!\n\n";
        $message .= "Obrigado por se cadastrar em nossa loja.\n\n";
        $message .= "Voce agora tem acesso a:\n";
        $message .= "- Precos especiais\n";
        $message .= "- Ofertas exclusivas\n";
        $message .= "- Atualizacoes de pedidos via WhatsApp\n\n";
        $message .= "Acesse: {$storeUrl}\n\n";
        $message .= "Duvidas? Estamos aqui para ajudar!";

        return $this->sendTextMessage($phoneNumber, $message);
    }

    /**
     * Send payment confirmation
     *
     * @param string $phoneNumber Customer phone
     * @param string $orderNumber Order number
     * @param float $amount Payment amount
     * @return array|null
     */
    public function sendPaymentConfirmation(
        string $phoneNumber,
        string $orderNumber,
        float $amount
    ): ?array {
        $storeName = $this->helper->getStoreName();
        $formattedAmount = 'R$ ' . number_format($amount, 2, ',', '.');

        $message = "*{$storeName}* - Pagamento Confirmado!\n\n";
        $message .= "Pedido: *#{$orderNumber}*\n";
        $message .= "Valor: *{$formattedAmount}*\n\n";
        $message .= "Seu pedido esta sendo preparado!\n";
        $message .= "Voce recebera atualizacoes por aqui.";

        return $this->sendTextMessage($phoneNumber, $message);
    }

    /**
     * Send image with caption
     *
     * @param string $phoneNumber Phone number
     * @param string $imageUrl URL of the image
     * @param string|null $caption Optional caption
     * @return array|null
     */
    public function sendImage(
        string $phoneNumber,
        string $imageUrl,
        ?string $caption = null
    ): ?array {
        if (!$this->isConfigured()) {
            return null;
        }

        $phoneNumber = $this->normalizePhoneNumber($phoneNumber);

        if (!$phoneNumber) {
            return null;
        }

        $payload = [
            'phone' => $phoneNumber,
            'image' => $imageUrl,
        ];

        if ($caption) {
            $payload['caption'] = $caption;
        }

        return $this->sendRequest('send-image', $payload);
    }

    /**
     * Send document/file
     *
     * @param string $phoneNumber Phone number
     * @param string $documentUrl URL of the document
     * @param string $fileName File name to display
     * @return array|null
     */
    public function sendDocument(
        string $phoneNumber,
        string $documentUrl,
        string $fileName
    ): ?array {
        if (!$this->isConfigured()) {
            return null;
        }

        $phoneNumber = $this->normalizePhoneNumber($phoneNumber);

        if (!$phoneNumber) {
            return null;
        }

        $payload = [
            'phone' => $phoneNumber,
            'document' => $documentUrl,
            'fileName' => $fileName,
        ];

        return $this->sendRequest('send-document-url', $payload);
    }

    /**
     * Check connection status
     *
     * @return array Status information
     */
    public function getStatus(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'connected' => false,
                'message' => 'Z-API not configured',
            ];
        }

        try {
            $response = $this->sendRequest('status', [], 'GET');

            if ($response && isset($response['connected'])) {
                return [
                    'success' => true,
                    'connected' => $response['connected'],
                    'phone' => $response['phoneNumber'] ?? null,
                    'message' => $response['connected'] ? 'Connected' : 'Disconnected',
                ];
            }

            return [
                'success' => false,
                'connected' => false,
                'message' => $this->lastErrorMessage ?? 'Could not retrieve status',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'connected' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test connection by sending a test message
     *
     * @param string|null $phoneNumber Phone to send test message (optional, uses admin phone)
     * @return array Test result
     */
    public function testConnection(?string $phoneNumber = null): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Z-API nao configurada. Preencha Instance ID e Token.',
            ];
        }

        // Get status first
        $status = $this->getStatus();

        if (!$status['success']) {
            return [
                'success' => false,
                'message' => 'Erro ao verificar status: ' . $status['message'],
            ];
        }

        if (!$status['connected']) {
            return [
                'success' => false,
                'message' => 'WhatsApp nao conectado. Acesse o painel Z-API e escaneie o QR Code.',
            ];
        }

        // If phone provided, send test message
        if ($phoneNumber) {
            $testMessage = "Teste de conexao - " . $this->helper->getStoreName() . "\n\n";
            $testMessage .= "Integracao WhatsApp funcionando!\n";
            $testMessage .= "Data/Hora: " . date('d/m/Y H:i:s');

            $result = $this->sendTextMessage($phoneNumber, $testMessage);

            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Conexao OK! Mensagem de teste enviada.',
                    'phone_connected' => $status['phone'] ?? 'N/A',
                    'message_id' => $result['zapiMessageId'] ?? $result['messageId'] ?? null,
                ];
            }

            return [
                'success' => false,
                'message' => 'WhatsApp conectado, mas falha ao enviar mensagem de teste.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Conexao OK! WhatsApp conectado.',
            'phone_connected' => $status['phone'] ?? 'N/A',
        ];
    }

    /**
     * Get QR Code for connecting WhatsApp
     *
     * @return array|null QR Code data
     */
    public function getQrCode(): ?array
    {
        if (!$this->helper->getZApiInstanceId() || !$this->helper->getZApiToken()) {
            return null;
        }

        return $this->sendRequest('qr-code', [], 'GET');
    }

    /**
     * Disconnect WhatsApp session
     *
     * @return array|null
     */
    public function disconnect(): ?array
    {
        return $this->sendRequest('disconnect', [], 'GET');
    }

    /**
     * Restart instance
     *
     * @return array|null
     */
    public function restart(): ?array
    {
        return $this->sendRequest('restart', [], 'GET');
    }

    /**
     * Send API request
     *
     * @param string $endpoint API endpoint
     * @param array $payload Request payload
     * @param string $method HTTP method (POST or GET)
     * @return array|null
     */
    private function sendRequest(string $endpoint, array $payload = [], string $method = 'POST'): ?array
    {
        $this->lastErrorMessage = null;

        if ($this->shouldSkipRequestForKnownMissingClientToken()) {
            $this->lastErrorMessage = self::MISSING_CLIENT_TOKEN_MESSAGE;
            $this->logMissingClientTokenWarning($endpoint);
            return null;
        }

        $url = $this->getApiUrl($endpoint);

        // Add Client-Token header if configured
        $clientToken = $this->helper->getZApiClientToken();

        try {
            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($clientToken) {
                $headers['Client-Token'] = $clientToken;
            }

            $this->curl->setHeaders($headers);
            $this->curl->setOption(CURLOPT_TIMEOUT, 30);
            $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 10);

            if ($method === 'GET') {
                $this->curl->get($url);
            } else {
                $this->curl->post($url, json_encode($payload));
            }

            $response = $this->curl->getBody();
            $httpCode = $this->curl->getStatus();

            $responseData = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                return $responseData;
            }

            $errorMessage = $responseData['message'] ?? $responseData['error'] ?? 'Unknown error';
            $this->lastErrorMessage = $errorMessage;

            if ($this->isMissingClientTokenError($errorMessage)) {
                $this->rememberMissingClientTokenRequirement();
                $this->lastErrorMessage = self::MISSING_CLIENT_TOKEN_MESSAGE;
                $this->logMissingClientTokenWarning($endpoint);
                return null;
            }

            $this->logger->error(sprintf(
                '[Z-API] Error (%d) on %s: %s',
                $httpCode,
                $endpoint,
                $errorMessage
            ));

            return null;
        } catch (\Exception $e) {
            $this->lastErrorMessage = $e->getMessage();
            $this->logger->error('[Z-API] Request error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Normalize phone number to WhatsApp format
     *
     * @param string $phone Phone number
     * @return string|null Normalized phone or null if invalid
     */
    private function normalizePhoneNumber(string $phone): ?string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (empty($phone)) {
            return null;
        }

        // Add Brazil country code if not present
        if (strlen($phone) === 11 || strlen($phone) === 10) {
            $phone = '55' . $phone;
        }

        // Validate length (Brazil: 12-13 digits with country code)
        if (strlen($phone) < 12 || strlen($phone) > 15) {
            return null;
        }

        return $phone;
    }

    /**
     * Mask phone number for logging
     *
     * @param string $phone Phone number
     * @return string Masked phone
     */
    private function maskPhoneNumber(string $phone): string
    {
        if (strlen($phone) <= 6) {
            return '***';
        }

        return substr($phone, 0, 4) . '****' . substr($phone, -4);
    }

    /**
     * Check if Z-API is properly configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return $this->helper->isWhatsAppEnabled()
            && !empty($this->helper->getZApiInstanceId())
            && !empty($this->helper->getZApiToken());
    }

    /**
     * Check if WhatsApp is enabled for order notifications
     *
     * @return bool
     */
    public function isOrderNotificationEnabled(): bool
    {
        return $this->isConfigured() && $this->helper->isWhatsAppOrderStatusEnabled();
    }

    /**
     * Check if WhatsApp is enabled for re-engagement
     *
     * @return bool
     */
    public function isReengagementEnabled(): bool
    {
        return $this->isConfigured() && $this->helper->isWhatsAppReengagementEnabled();
    }

    private function isMissingClientTokenError(string $errorMessage): bool
    {
        return stripos($errorMessage, 'client-token') !== false
            && stripos($errorMessage, 'not configured') !== false;
    }

    private function shouldSkipRequestForKnownMissingClientToken(): bool
    {
        if ($this->helper->getZApiClientToken() !== '') {
            return false;
        }

        $state = $this->readMissingClientTokenState();
        if ($state === null) {
            return false;
        }

        return $state['fingerprint'] === $this->buildMissingClientTokenFingerprint()
            && (time() - $state['timestamp']) < self::WARNING_COOLDOWN_SECONDS;
    }

    private function rememberMissingClientTokenRequirement(): void
    {
        $filePath = $this->getLockFilePath(self::MISSING_CLIENT_TOKEN_LOCK);
        if ($filePath === null) {
            return;
        }

        @file_put_contents(
            $filePath,
            time() . '|' . $this->buildMissingClientTokenFingerprint(),
            LOCK_EX
        );
        @chmod($filePath, 0664);
    }

    /**
     * @return array{timestamp: int, fingerprint: string}|null
     */
    private function readMissingClientTokenState(): ?array
    {
        $filePath = $this->getLockFilePath(self::MISSING_CLIENT_TOKEN_LOCK);
        if ($filePath === null || !is_file($filePath)) {
            return null;
        }

        $raw = trim((string) @file_get_contents($filePath));
        if ($raw === '') {
            return null;
        }

        [$timestampRaw, $fingerprint] = array_pad(explode('|', $raw, 2), 2, '');
        $timestamp = (int) $timestampRaw;

        if ($timestamp <= 0 || $fingerprint === '') {
            return null;
        }

        return [
            'timestamp' => $timestamp,
            'fingerprint' => $fingerprint,
        ];
    }

    private function buildMissingClientTokenFingerprint(): string
    {
        return hash('xxh128', implode('|', [
            (string) (int) $this->helper->isWhatsAppEnabled(),
            $this->helper->getZApiInstanceId(),
            $this->helper->getZApiToken(),
            $this->helper->getZApiClientToken(),
        ]));
    }

    private function logMissingClientTokenWarning(string $endpoint): void
    {
        if (!$this->shouldLogWarningWithCooldown('zapi_missing_client_token')) {
            return;
        }

        $this->logger->warning('[Z-API] ' . self::MISSING_CLIENT_TOKEN_MESSAGE, [
            'endpoint' => $endpoint,
            'config_path' => 'grupoawamotos_erp/whatsapp/zapi_client_token',
            'env_var' => 'ZAPI_CLIENT_TOKEN',
        ]);
    }

    private function getLockFilePath(string $filename): ?string
    {
        $basePath = defined('BP') ? BP : sys_get_temp_dir();
        $lockDir = rtrim($basePath, '/') . self::LOCK_DIR;

        if (!is_dir($lockDir) && !@mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
            return null;
        }

        return $lockDir . '/' . $filename;
    }

    private function shouldLogWarningWithCooldown(string $key): bool
    {
        $filePath = $this->getLockFilePath('zapi_warn_' . preg_replace('/[^a-z0-9_]+/i', '_', $key) . '.lock');
        if ($filePath === null) {
            return true;
        }

        $handle = @fopen($filePath, 'c+');
        if ($handle === false) {
            return true;
        }

        @chmod($filePath, 0664);

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return true;
        }

        try {
            rewind($handle);
            $last = (int) trim((string) stream_get_contents($handle));
            $now = time();

            if ($last > 0 && ($now - $last) < self::WARNING_COOLDOWN_SECONDS) {
                return false;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) $now);
            fflush($handle);

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
