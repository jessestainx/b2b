<?php

declare(strict_types=1);

namespace Meta\Conversion\Helper;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Stdlib\CookieManagerInterface;

/**
 * Builds Meta Conversions API user_data payload with frontend context.
 */
class UserDataBuilder
{
    private const COOKIE_FBP = '_fbp';
    private const COOKIE_FBC = '_fbc';
    private const MAX_UA_LENGTH = 1024;
    private const PHONE_MIN_LENGTH = 10;
    private const PHONE_MAX_LENGTH = 15;

    public function __construct(
        private readonly CookieManagerInterface $cookieManager,
        private readonly RequestInterface $request,
        private readonly RemoteAddress $remoteAddress
    ) {
    }

    /**
     * @param string|null $email
     * @param string|null $phone
     * @param string|null $externalId
     * @param string|null $firstName
     * @param string|null $lastName
     * @param string|null $city
     * @param string|null $state    Region code (e.g. "SP")
     * @param string|null $zip
     * @param string|null $country  2-letter ISO code (e.g. "BR")
     * @return array<string, string>
     */
    public function build(
        ?string $email = null,
        ?string $phone = null,
        ?string $externalId = null,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $city = null,
        ?string $state = null,
        ?string $zip = null,
        ?string $country = null
    ): array {
        $userData = [];

        $hashedEmail = $this->hashEmail($email);
        if ($hashedEmail !== null) {
            $userData['em'] = $hashedEmail;
        }

        $hashedPhone = $this->hashPhone($phone);
        if ($hashedPhone !== null) {
            $userData['ph'] = $hashedPhone;
        }

        $hashedExternalId = $this->hashExternalId($externalId);
        if ($hashedExternalId !== null) {
            $userData['external_id'] = $hashedExternalId;
        }

        $hashedFn = $this->hashName($firstName);
        if ($hashedFn !== null) {
            $userData['fn'] = $hashedFn;
        }

        $hashedLn = $this->hashName($lastName);
        if ($hashedLn !== null) {
            $userData['ln'] = $hashedLn;
        }

        $hashedCity = $this->hashNormalized($city);
        if ($hashedCity !== null) {
            $userData['ct'] = $hashedCity;
        }

        $hashedState = $this->hashNormalized($state);
        if ($hashedState !== null) {
            $userData['st'] = $hashedState;
        }

        $hashedZip = $this->hashZip($zip);
        if ($hashedZip !== null) {
            $userData['zp'] = $hashedZip;
        }

        $hashedCountry = $this->hashNormalized($country);
        if ($hashedCountry !== null) {
            $userData['country'] = $hashedCountry;
        }

        $fbp = $this->sanitizeCookieValue($this->cookieManager->getCookie(self::COOKIE_FBP));
        if ($fbp !== null) {
            $userData['fbp'] = $fbp;
        }

        $fbc = $this->sanitizeCookieValue($this->cookieManager->getCookie(self::COOKIE_FBC));
        if ($fbc !== null) {
            $userData['fbc'] = $fbc;
        }

        $rawIp = $this->remoteAddress->getRemoteAddress();
        $remoteIp = $this->sanitizeIp(is_string($rawIp) ? $rawIp : null);
        if ($remoteIp !== null) {
            $userData['client_ip_address'] = $remoteIp;
        }

        $userAgent = $this->sanitizeUserAgent((string) $this->request->getServer('HTTP_USER_AGENT', ''));
        if ($userAgent !== null) {
            $userData['client_user_agent'] = $userAgent;
        }

        return $userData;
    }

    public function getEventSourceUrl(): ?string
    {
        $host = trim((string) $this->request->getServer('HTTP_HOST', ''));
        $requestUri = trim((string) $this->request->getServer('REQUEST_URI', ''));

        if ($host === '' || $requestUri === '') {
            return null;
        }

        $scheme = 'https';
        $forwardedProto = trim((string) $this->request->getServer('HTTP_X_FORWARDED_PROTO', ''));
        if ($forwardedProto !== '') {
            $proto = strtolower(strtok($forwardedProto, ',') ?: $forwardedProto);
            if (in_array($proto, ['http', 'https'], true)) {
                $scheme = $proto;
            }
        } else {
            $https = strtolower((string) $this->request->getServer('HTTPS', ''));
            if ($https === '' || $https === 'off' || $https === '0') {
                $scheme = 'http';
            }
        }

        if ($requestUri[0] !== '/') {
            $requestUri = '/' . $requestUri;
        }

        $url = $scheme . '://' . $host . $requestUri;

        return mb_substr($url, 0, 2048);
    }

    /**
     * Ensures user_data has enough identifiers to avoid Meta CAPI rejection (subcode 2804050).
     *
     * @param array<string, string> $userData
     */
    public function hasMinimumSignals(array $userData): bool
    {
        foreach (['em', 'ph', 'external_id', 'fbp', 'fbc'] as $key) {
            if (!empty($userData[$key])) {
                return true;
            }
        }

        return false;
    }

    private function hashEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return hash('sha256', $email);
    }

    private function hashPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (!is_string($digits) || $digits === '') {
            return null;
        }

        $length = strlen($digits);
        if ($length < self::PHONE_MIN_LENGTH || $length > self::PHONE_MAX_LENGTH) {
            return null;
        }

        // Avoid obvious placeholders like 0000000000.
        if (count(array_unique(str_split($digits))) === 1) {
            return null;
        }

        return hash('sha256', $digits);
    }

    private function hashExternalId(?string $externalId): ?string
    {
        $externalId = trim((string) $externalId);
        if ($externalId === '') {
            return null;
        }

        return hash('sha256', $externalId);
    }

    /** SHA-256 of lowercase name (no leading/trailing spaces). */
    private function hashName(?string $name): ?string
    {
        $name = strtolower(trim((string) $name));
        $name = preg_replace('/[^a-zà-ÿ\\s]/iu', '', $name) ?? '';
        $name = preg_replace('/\\s+/', ' ', trim($name)) ?? '';

        if (mb_strlen($name) < 2) {
            return null;
        }

        if ($name === '') {
            return null;
        }

        return hash('sha256', $name);
    }

    /** SHA-256 of lowercase value stripped of whitespace (city, state, country). */
    private function hashNormalized(?string $value): ?string
    {
        $value = strtolower(preg_replace('/\s+/', '', trim((string) $value)) ?? '');
        if ($value === '') {
            return null;
        }

        return hash('sha256', $value);
    }

    /** SHA-256 of zip/postal code stripped of non-alphanumeric chars. */
    private function hashZip(?string $zip): ?string
    {
        $zip = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', trim((string) $zip)) ?? '');
        if ($zip === '') {
            return null;
        }

        return hash('sha256', $zip);
    }

    private function sanitizeCookieValue(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, 255);
    }

    private function sanitizeIp(?string $ip): ?string
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $ip;
    }

    private function sanitizeUserAgent(string $userAgent): ?string
    {
        $userAgent = trim($userAgent);
        if ($userAgent === '') {
            return null;
        }

        return mb_substr($userAgent, 0, self::MAX_UA_LENGTH);
    }
}
