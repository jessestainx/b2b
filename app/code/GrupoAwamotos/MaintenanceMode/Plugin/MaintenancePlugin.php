<?php

declare(strict_types=1);

namespace GrupoAwamotos\MaintenanceMode\Plugin;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class MaintenancePlugin
{
    private const COOKIE_NAME = 'awa_maintenance_access';
    private const CONFIG_PATH = 'grupoawamotos_maintenance/';

    private ScopeConfigInterface $scopeConfig;
    private DeploymentConfig $deploymentConfig;
    private RemoteAddress $remoteAddress;
    private ResponseHttp $response;
    private CookieManagerInterface $cookieManager;
    private CookieMetadataFactory $cookieMetadataFactory;
    private StoreManagerInterface $storeManager;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        DeploymentConfig $deploymentConfig,
        RemoteAddress $remoteAddress,
        ResponseHttp $response,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->deploymentConfig = $deploymentConfig;
        $this->remoteAddress = $remoteAddress;
        $this->response = $response;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->storeManager = $storeManager;
    }

    public function aroundDispatch(
        FrontControllerInterface $subject,
        callable $proceed,
        RequestInterface $request
    ) {
        // Não executar se não estiver habilitado
        if (!$this->isEnabled()) {
            return $proceed($request);
        }

        // Verificar código secreto na URL (?preview=CODIGO)
        if ($this->checkSecretKey($request)) {
            $result = $proceed($request);
            $this->setAccessCookieViaHeader();
            $this->setNoCacheHeaders();
            return $result;
        }

        // Verificar cookie de acesso válido
        if ($this->hasAccessCookie()) {
            $result = $proceed($request);
            $this->setNoCacheHeaders();
            return $result;
        }

        // Verificar se IP está na whitelist
        if ($this->isWhitelisted()) {
            $result = $proceed($request);
            $this->setNoCacheHeaders();
            return $result;
        }

        // Assets estáticos na raiz (favicon, robots) — não bloquear com 503
        if ($this->isStaticAssetRequest($request)) {
            return $proceed($request);
        }

        // Verificar se é rota permitida (admin, newsletter, etc.)
        if ($this->isAllowedRoute($request)) {
            return $proceed($request);
        }

        // Verificar se é página CMS permitida
        if ($this->isAllowedCmsPage($request)) {
            return $proceed($request);
        }

        // Mostrar página de manutenção/em breve
        return $this->getMaintenanceResponse();
    }

    /**
     * Impede FPC de cachear ao setar no-cache no objeto Response.
     * Sem isso, Kernel::process() vê s-maxage e faz header_remove('Set-Cookie').
     */
    private function setNoCacheHeaders(): void
    {
        $this->response->setNoCacheHeaders();
        $this->response->clearHeader('X-Magento-Tags');
        header('X-Maintenance-Bypass: 1', false);
    }

    private function getConfig(string $path, $default = null)
    {
        $value = $this->scopeConfig->getValue(
            self::CONFIG_PATH . $path,
            ScopeInterface::SCOPE_STORE
        );
        return $value ?? $default;
    }

    private function isEnabled(): bool
    {
        return (bool) $this->getConfig('general/enabled');
    }

    private function checkSecretKey(RequestInterface $request): bool
    {
        $previewParam = $request->getParam('preview');
        if (empty($previewParam)) {
            return false;
        }
        $secretKey = $this->getConfig('general/secret_key');
        return !empty($secretKey) && $previewParam === $secretKey;
    }

    private function setAccessCookieViaHeader(): void
    {
        $duration = (int) $this->getConfig('general/cookie_duration', 72);
        $value = hash('sha256', $this->getConfig('general/secret_key') . '_awamotos_access');
        $expires = gmdate('D, d M Y H:i:s T', time() + ($duration * 3600));

        $cookieString = sprintf(
            '%s=%s; Expires=%s; Max-Age=%d; Path=/; Secure; HttpOnly; SameSite=Lax',
            self::COOKIE_NAME,
            $value,
            $expires,
            $duration * 3600
        );
        header('Set-Cookie: ' . $cookieString, false);
    }

    private function hasAccessCookie(): bool
    {
        $cookie = $this->cookieManager->getCookie(self::COOKIE_NAME);
        if (empty($cookie)) {
            return false;
        }
        $secretKey = $this->getConfig('general/secret_key');
        return $cookie === hash('sha256', $secretKey . '_awamotos_access');
    }

    private function isWhitelisted(): bool
    {
        $clientIp = $this->remoteAddress->getRemoteAddress();
        $whitelist = $this->getConfig('general/whitelist_ips');
        if (empty($whitelist)) {
            return false;
        }
        $whitelistArray = array_map('trim', explode("\n", $whitelist));
        return in_array($clientIp, array_filter($whitelistArray), true);
    }

    /**
     * Pedidos de assets na raiz que o browser faz automaticamente (não devem receber 503).
     */
    private function isStaticAssetRequest(RequestInterface $request): bool
    {
        $pathInfo = trim((string) $request->getPathInfo(), '/');
        return in_array($pathInfo, ['favicon.ico', 'robots.txt', 'apple-touch-icon.png'], true);
    }

    private function isAllowedRoute(RequestInterface $request): bool
    {
        // Usar pathInfo porque getModuleName()/getFullActionName() são null
        // no estágio do FrontController (antes do routing)
        $pathInfo = trim((string) $request->getPathInfo(), '/');
        $firstSegment = explode('/', $pathInfo)[0] ?? '';

        // Admin: comparar com o frontName real do backend (env.php)
        $adminFrontName = $this->getAdminFrontName();
        if ($firstSegment === $adminFrontName) {
            return true;
        }

        // Rotas do módulo de manutenção sempre permitidas
        if ($firstSegment === 'maintenance') {
            return true;
        }

        // Rotas configuradas pelo admin (newsletter, contact, etc.)
        $allowedRoutes = $this->getConfig('general/allowed_routes', '');
        if (!empty($allowedRoutes)) {
            $routesArray = array_map('trim', explode(',', $allowedRoutes));
            if (in_array($firstSegment, $routesArray, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retorna o frontName do admin configurado em env.php
     */
    private function getAdminFrontName(): string
    {
        try {
            return (string) $this->deploymentConfig->get('backend/frontName', 'admin');
        } catch (\Exception $e) {
            return 'admin';
        }
    }

    private function isAllowedCmsPage(RequestInterface $request): bool
    {
        $currentPath = trim($request->getPathInfo(), '/');
        $allowedPages = $this->getConfig('general/allowed_cms_pages', '');

        if (empty($allowedPages)) {
            return false;
        }

        $pagesArray = array_map('trim', explode(',', $allowedPages));
        return in_array($currentPath, $pagesArray);
    }

    private function getMaintenanceResponse(): ResponseInterface
    {
        // Detectar modo (manutenção ou em breve)
        $mode = $this->getConfig('general/mode', 'maintenance');
        $isComingSoon = ($mode === 'coming_soon');
        $configPath = $isComingSoon ? 'coming_soon' : 'maintenance';

        // Configurações de conteúdo
        $title = $this->getConfig($configPath . '/title', 'Estamos em Manutenção');
        $message = $this->getConfig($configPath . '/message', '<p>Voltamos em breve!</p>');
        $showCountdown = (bool) $this->getConfig($configPath . '/show_countdown');
        $countdownDate = $this->getConfig($configPath . '/countdown_date');

        // Design
        $bgType = $this->getConfig('design/background_type', 'gradient');
        $bgColor = $this->getConfig('design/background_color', '#1a237e');
        $bgGradient = $this->getConfig('design/background_gradient', '#1a237e,#000428');
        $textColor = $this->getConfig('design/text_color', '#ffffff');
        $customCss = $this->getConfig('design/custom_css', '');
        $logo = $this->getConfig('design/logo');

        // Newsletter
        $showNewsletter = (bool) $this->getConfig('newsletter/enabled');
        $newsletterTitle = $this->getConfig('newsletter/title', 'Seja avisado!');
        $newsletterButton = $this->getConfig('newsletter/button_text', 'Cadastrar');

        // Social
        $showSocial = (bool) $this->getConfig('social/enabled');
        $facebook = $this->getConfig('social/facebook');
        $instagram = $this->getConfig('social/instagram');
        $youtube = $this->getConfig('social/youtube');
        $whatsappSocial = $this->getConfig('social/whatsapp');

        // Contato
        $showContact = (bool) $this->getConfig('contact/show_info');
        $phone = $this->getConfig('contact/phone');
        $whatsappContact = $this->getConfig('contact/whatsapp');
        $email = $this->getConfig('contact/email');

        // URLs
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        // Build CSS de fundo
        $backgroundCss = $this->buildBackgroundCss($bgType, $bgColor, $bgGradient);
        $safeTextColor = preg_replace('/[^a-zA-Z0-9#(),.\/\s%+]/u', '', $textColor) ?: '#ffffff';
        $safeCustomCss = strip_tags($customCss);

        // URLs de mídia
        $logoUrl = $logo ? $mediaUrl . 'maintenance/' . $logo : '';

        // Build HTML sections
        $logoHtml = $logoUrl ? '<div class="logo"><img src="' . htmlspecialchars($logoUrl) . '" alt="AWA Motos"></div>' : '';
        $countdownHtml = ($showCountdown && $countdownDate) ? '<div id="countdown" class="countdown"></div>' : '';
        $countdownScript = ($showCountdown && $countdownDate) ? $this->getCountdownScript($countdownDate) : '';
        $newsletterHtml = $showNewsletter ? $this->getNewsletterHtml($baseUrl, $newsletterTitle, $newsletterButton) : '';
        $socialHtml = $showSocial ? $this->getSocialHtml($facebook, $instagram, $youtube, $whatsappSocial) : '';
        $contactHtml = $showContact ? $this->getContactHtml($phone, $whatsappContact, $email) : '';

        $icon = $isComingSoon ? '🚀' : '🔧';
        $httpCode = $isComingSoon ? 200 : 503;
        $accessFormHtml = $this->getAccessFormHtml();

        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{$title} | AWA Motos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
            {$backgroundCss}
            color: {$textColor};
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-align: center;
        }
        .container { max-width: 700px; width: 100%; animation: fadeIn 1s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .logo { margin-bottom: 30px; }
        .logo img { max-width: 250px; height: auto; }
        .icon { font-size: 80px; margin-bottom: 20px; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }
        .content h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 20px; }
        .content p { font-size: 1.1rem; line-height: 1.8; margin-bottom: 15px; opacity: 0.9; }
        a { color: #4fc3f7; text-decoration: none; }
        .countdown { display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; margin: 30px 0; padding: 25px; background: rgba(255,255,255,0.1); border-radius: 16px; }
        .countdown-item { display: flex; flex-direction: column; align-items: center; min-width: 80px; }
        .countdown-value { font-size: 3rem; font-weight: 700; }
        .countdown-label { font-size: 0.75rem; text-transform: uppercase; opacity: 0.7; }
        .newsletter { margin: 30px 0; padding: 25px; background: rgba(255,255,255,0.1); border-radius: 16px; }
        .newsletter h3 { margin-bottom: 15px; }
        .newsletter-form { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; }
        .newsletter-form input[type="email"] { flex: 1; min-width: 250px; padding: 15px 20px; border: none; border-radius: 50px; font-size: 1rem; }
        .newsletter-form button { padding: 15px 30px; background: #4fc3f7; color: #000; border: none; border-radius: 50px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .social-links { margin-top: 30px; display: flex; justify-content: center; gap: 15px; }
        .social-links a { display: inline-flex; align-items: center; justify-content: center; width: 55px; height: 55px; background: rgba(255,255,255,0.15); border-radius: 50%; font-size: 26px; transition: all 0.3s; }
        .social-links a:hover { background: rgba(255,255,255,0.3); transform: translateY(-5px); }
        .contact-info { margin-top: 40px; padding-top: 30px; border-top: 1px solid rgba(255,255,255,0.2); }
        .access-form { margin-top: 30px; padding: 20px; background: rgba(255,255,255,0.08); border-radius: 12px; }
        .access-form p { font-size: 0.85rem; opacity: 0.7; margin-bottom: 10px; }
        .access-form form { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .access-form input[type="text"] { padding: 10px 16px; border: 1px solid rgba(255,255,255,0.3); border-radius: 50px; background: rgba(255,255,255,0.1); color: inherit; font-size: 0.9rem; min-width: 200px; text-align: center; }
        .access-form input[type="text"]::placeholder { color: rgba(255,255,255,0.5); }
        .access-form button { padding: 10px 24px; background: rgba(255,255,255,0.2); color: inherit; border: 1px solid rgba(255,255,255,0.3); border-radius: 50px; font-size: 0.9rem; cursor: pointer; transition: all 0.3s; }
        .access-form button:hover { background: rgba(255,255,255,0.3); }
        @media (max-width: 600px) { .content h1 { font-size: 1.8rem; } .countdown-value { font-size: 2rem; } }
        {$safeCustomCss}
    </style>
</head>
<body>
    <div class="container">
        {$logoHtml}
        <div class="icon">{$icon}</div>
        <div class="content">{$message}</div>
        {$countdownHtml}
        {$newsletterHtml}
        {$socialHtml}
        {$contactHtml}
        {$accessFormHtml}
    </div>
    {$countdownScript}
</body>
</html>
HTML;

        $this->response->setHttpResponseCode($httpCode);
        if (!$isComingSoon) {
            $this->response->setHeader('Retry-After', '3600');
        }
        $this->response->setBody($html);
        return $this->response;
    }

    private function buildBackgroundCss(string $type, string $color, string $gradient): string
    {
        switch ($type) {
            case 'color':
                return "background: {$color};";
            case 'gradient':
                $colors = explode(',', $gradient);
                $color1 = trim($colors[0] ?? '#1a237e');
                $color2 = trim($colors[1] ?? '#000428');
                return "background: linear-gradient(135deg, {$color1} 0%, {$color2} 100%);";
            default:
                return "background: linear-gradient(135deg, #1a237e 0%, #000428 100%);";
        }
    }

    private function getNewsletterHtml(string $baseUrl, string $title, string $button): string
    {
        return <<<HTML
<div class="newsletter">
    <h3>{$title}</h3>
    <form class="newsletter-form" action="{$baseUrl}newsletter/subscriber/new/" method="post">
        <input type="hidden" name="form_key" value="">
        <input type="email" name="email" placeholder="Seu melhor e-mail" required>
        <button type="submit">{$button}</button>
    </form>
</div>
<script>
(function () {
    var form = document.querySelector('.newsletter-form');
    if (!form) return;
    var key = (document.cookie.match(/(?:^|; )form_key=([^;]*)/) || [])[1] || '';
    var input = form.querySelector('input[name="form_key"]');
    if (input && key) input.value = decodeURIComponent(key);
})();
</script>
HTML;
    }

    private function getSocialHtml(?string $facebook, ?string $instagram, ?string $youtube, ?string $whatsapp): string
    {
        $links = '';
        if ($whatsapp) {
            $links .= '<a href="https://wa.me/' . htmlspecialchars($whatsapp) . '" title="WhatsApp" target="_blank">📱</a>';
        }
        if ($facebook) {
            $links .= '<a href="' . htmlspecialchars($facebook) . '" title="Facebook" target="_blank">📘</a>';
        }
        if ($instagram) {
            $links .= '<a href="' . htmlspecialchars($instagram) . '" title="Instagram" target="_blank">📷</a>';
        }
        if ($youtube) {
            $links .= '<a href="' . htmlspecialchars($youtube) . '" title="YouTube" target="_blank">🎬</a>';
        }
        return $links ? '<div class="social-links">' . $links . '</div>' : '';
    }

    private function getContactHtml(?string $phone, ?string $whatsapp, ?string $email): string
    {
        $html = '<div class="contact-info"><p><strong>AWA Motos</strong> - Peças e Acessórios para Motos</p>';
        $contacts = [];
        if ($phone) {
            $contacts[] = '📞 ' . htmlspecialchars($phone);
        }
        if ($whatsapp) {
            $contacts[] = '💬 ' . htmlspecialchars($whatsapp);
        }
        if ($email) {
            $contacts[] = '✉️ <a href="mailto:' . htmlspecialchars($email) . '">' . htmlspecialchars($email) . '</a>';
        }
        if ($contacts) {
            $html .= '<p>' . implode(' | ', $contacts) . '</p>';
        }
        return $html . '</div>';
    }

    private function getAccessFormHtml(): string
    {
        return <<<HTML
<div class="access-form">
    <p>Acesso autorizado?</p>
    <form onsubmit="event.preventDefault();var c=document.getElementById('awa_code').value;if(c){window.location.href=window.location.pathname+'?preview='+encodeURIComponent(c);}">
        <input type="text" id="awa_code" placeholder="Código de acesso" autocomplete="off">
        <button type="submit">Entrar</button>
    </form>
</div>
HTML;
    }

    private function getCountdownScript(string $targetDate): string
    {
        return <<<SCRIPT
<script>
(function() {
    const targetDate = new Date('{$targetDate}'.replace(' ', 'T')).getTime();
    const countdown = document.getElementById('countdown');
    if (!countdown) return;
    function updateCountdown() {
        const now = new Date().getTime();
        const distance = targetDate - now;
        if (distance < 0) { countdown.innerHTML = '<p style="font-size:1.5rem;">🎉 Estamos quase prontos!</p>'; return; }
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        countdown.innerHTML = '<div class="countdown-item"><span class="countdown-value">' + days + '</span><span class="countdown-label">Dias</span></div><div class="countdown-item"><span class="countdown-value">' + String(hours).padStart(2,'0') + '</span><span class="countdown-label">Horas</span></div><div class="countdown-item"><span class="countdown-value">' + String(minutes).padStart(2,'0') + '</span><span class="countdown-label">Min</span></div><div class="countdown-item"><span class="countdown-value">' + String(seconds).padStart(2,'0') + '</span><span class="countdown-label">Seg</span></div>';
    }
    updateCountdown();
    setInterval(updateCountdown, 1000);
})();
</script>
SCRIPT;
    }
}
