<?php

/**
 * Helper para validação de CNPJ via API ReceitaWS
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Helper;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;

class CnpjValidator extends AbstractHelper
{
    private const CONFIG_PATH_LOOKUP_ENABLED = 'grupoawamotos_b2b/cnpj_lookup/enabled';
    private const CONFIG_PATH_LOOKUP_API_URL = 'grupoawamotos_b2b/cnpj_lookup/api_url';
    private const CONFIG_PATH_LOOKUP_TIMEOUT = 'grupoawamotos_b2b/cnpj_lookup/timeout';
    private const CONFIG_PATH_LOOKUP_REQUIRE_ACTIVE = 'grupoawamotos_b2b/cnpj_lookup/require_active_status';
    private const CONFIG_PATH_LOOKUP_ALLOW_FALLBACK = 'grupoawamotos_b2b/cnpj_lookup/allow_local_fallback';
    private const CONFIG_PATH_LOOKUP_CACHE_ENABLED = 'grupoawamotos_b2b/cnpj_lookup/cache_enabled';
    private const CONFIG_PATH_LOOKUP_CACHE_TTL = 'grupoawamotos_b2b/cnpj_lookup/cache_ttl';
    private const CONFIG_PATH_LOOKUP_RATE_LIMIT_ENABLED = 'grupoawamotos_b2b/cnpj_lookup/rate_limit_enabled';
    private const CONFIG_PATH_LOOKUP_RATE_LIMIT_MAX = 'grupoawamotos_b2b/cnpj_lookup/rate_limit_max_requests';
    private const CONFIG_PATH_LOOKUP_RATE_LIMIT_WINDOW = 'grupoawamotos_b2b/cnpj_lookup/rate_limit_window_seconds';

    private const DEFAULT_API_URL = 'https://receitaws.com.br/v1/cnpj/';
    private const FALLBACK_API_URL = 'https://brasilapi.com.br/api/cnpj/v1/';
    private const DEFAULT_TIMEOUT = 10;
    private const DEFAULT_CACHE_TTL = 86400;
    private const NEGATIVE_CACHE_TTL = 3600;
    private const DEFAULT_RATE_LIMIT_MAX = 20;
    private const DEFAULT_RATE_LIMIT_WINDOW = 60;

    private const CACHE_KEY_PREFIX = 'grupoawamotos_b2b_cnpj_lookup_';
    private const CACHE_TAG = 'GRUPOAWAMOTOS_B2B_CNPJ_LOOKUP';

    private Curl $curl;
    private CacheInterface $cache;
    private Json $json;

    public function __construct(
        Context $context,
        Curl $curl,
        CacheInterface $cache,
        Json $json
    ) {
        $this->curl = $curl;
        $this->cache = $cache;
        $this->json = $json;
        parent::__construct($context);
    }

    /**
     * Validar CNPJ localmente (algoritmo)
     *
     * @param string $cnpj
     * @return bool
     */
    public function validateLocal(string $cnpj): bool
    {
        $cnpj = $this->clean($cnpj);

        // Verifica se tem 14 dígitos
        if (strlen($cnpj) !== 14) {
            return false;
        }

        // Verifica se todos os dígitos são iguais
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        // Validação do primeiro dígito verificador
        $soma = 0;
        $multiplicadores1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        for ($i = 0; $i < 12; $i++) {
            $soma += (int) $cnpj[$i] * $multiplicadores1[$i];
        }

        $resto = $soma % 11;
        $digito1 = $resto < 2 ? 0 : 11 - $resto;

        if ((int) $cnpj[12] !== $digito1) {
            return false;
        }

        // Validação do segundo dígito verificador
        $soma = 0;
        $multiplicadores2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        for ($i = 0; $i < 13; $i++) {
            $soma += (int) $cnpj[$i] * $multiplicadores2[$i];
        }

        $resto = $soma % 11;
        $digito2 = $resto < 2 ? 0 : 11 - $resto;

        return (int) $cnpj[13] === $digito2;
    }

    /**
     * Validar CNPJ via API ReceitaWS
     *
     * @param string $cnpj
     * @return array|null Dados da empresa ou null se inválido
     */
    public function validateApi(string $cnpj, bool $forceRefresh = false): ?array
    {
        // Primeiro valida localmente
        if (!$this->validateLocal($cnpj)) {
            return null;
        }

        $cnpjClean = $this->clean($cnpj);

        if (!$this->isLookupEnabled()) {
            $this->audit('lookup_disabled', $cnpjClean);

            return $this->buildLocalFallbackPayload(
                'Consulta externa desabilitada. CNPJ validado localmente.'
            );
        }

        if ($forceRefresh) {
            $this->audit('force_refresh', $cnpjClean);
        }

        if (!$forceRefresh) {
            $cachedPayload = $this->loadFromCache($cnpjClean);
            if ($cachedPayload !== null) {
                $this->audit('cache_hit', $cnpjClean);
                return $cachedPayload;
            }
        }

        try {
            $this->curl->setOption(CURLOPT_TIMEOUT, $this->getLookupTimeout());
            $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, true);
            $this->curl->setHeaders([
                'Accept' => 'application/json'
            ]);

            $endpoint = rtrim($this->getLookupApiUrl(), '/') . '/' . $cnpjClean;
            $this->curl->get($endpoint);

            $httpStatus = (int) $this->curl->getStatus();
            $response = (string) $this->curl->getBody();
            $data = json_decode($response, true);

            if ($this->isRateLimitedResponse($httpStatus, is_array($data) ? $data : null)) {
                $this->audit('api_rate_limited', $cnpjClean, ['http_status' => $httpStatus]);
                // Do not cache rate-limit as success — try BrasilAPI, else null
                $fallbackPayload = $this->fetchFromBrasilApi($cnpjClean);
                if ($fallbackPayload !== null) {
                    $this->saveToCache($cnpjClean, $fallbackPayload);
                    $this->audit('api_rate_limited_fallback_ok', $cnpjClean);
                    return $fallbackPayload;
                }

                return null;
            }

            if (!is_array($data) || (isset($data['status']) && strtoupper((string) $data['status']) === 'ERROR')) {
                $this->audit('api_not_found_or_error', $cnpjClean);
                return null;
            }

            if (!$this->isCompleteCompanyPayload($data)) {
                $this->audit('api_incomplete_payload', $cnpjClean, [
                    'http_status' => $httpStatus,
                    'message' => (string) ($data['message'] ?? ''),
                ]);
                $fallbackPayload = $this->fetchFromBrasilApi($cnpjClean);
                if ($fallbackPayload !== null) {
                    $this->saveToCache($cnpjClean, $fallbackPayload);
                    $this->audit('api_incomplete_fallback_ok', $cnpjClean);
                    return $fallbackPayload;
                }

                return null;
            }

            if (
                $this->isRequireActiveStatusEnabled()
                && isset($data['situacao'])
                && strtoupper((string) $data['situacao']) !== 'ATIVA'
            ) {
                // Cross-verify with BrasilAPI before rejecting
                $fallbackSituacao = $this->crossVerifyWithFallbackApi($cnpjClean);

                if ($fallbackSituacao !== null && strtoupper($fallbackSituacao) === 'ATIVA') {
                    $this->audit('api_status_mismatch_resolved', $cnpjClean, [
                        'primary_situacao' => (string) $data['situacao'],
                        'fallback_situacao' => $fallbackSituacao
                    ]);
                    // Primary API had stale data — override situacao and continue
                    $data['situacao'] = $fallbackSituacao;
                } else {
                    $invalidPayload = [
                        'valid' => false,
                        'message' => (string) __('CNPJ com situação: %1', $data['situacao']),
                        'source' => 'api',
                        'data' => $data
                    ];

                    // Negative results use shorter TTL so stale data expires sooner
                    $this->saveToCache($cnpjClean, $invalidPayload, self::NEGATIVE_CACHE_TTL);
                    $this->audit('api_invalid_status', $cnpjClean, [
                        'situacao' => (string) $data['situacao'],
                        'fallback_situacao' => $fallbackSituacao ?? 'unavailable'
                    ]);

                    return $invalidPayload;
                }
            }

            $payload = [
                'valid' => true,
                'source' => 'api',
                'razao_social' => $data['nome'] ?? '',
                'nome_fantasia' => $data['fantasia'] ?? '',
                'cnpj' => $data['cnpj'] ?? $cnpj,
                'situacao' => $data['situacao'] ?? '',
                'tipo' => $data['tipo'] ?? '',
                'porte' => $data['porte'] ?? '',
                'natureza_juridica' => $data['natureza_juridica'] ?? '',
                'atividade_principal' => $data['atividade_principal'][0]['text'] ?? '',
                'logradouro' => $data['logradouro'] ?? '',
                'numero' => $data['numero'] ?? '',
                'complemento' => $data['complemento'] ?? '',
                'bairro' => $data['bairro'] ?? '',
                'municipio' => $data['municipio'] ?? '',
                'uf' => $data['uf'] ?? '',
                'cep' => $data['cep'] ?? '',
                'telefone' => $data['telefone'] ?? '',
                'email' => $data['email'] ?? '',
                'data' => $data
            ];

            $this->saveToCache($cnpjClean, $payload);
            $this->audit('api_success', $cnpjClean);

            return $payload;
        } catch (\Throwable $exception) {
            $this->_logger->error(
                sprintf(
                    'Erro ao validar CNPJ via API (%s): %s',
                    $cnpjClean,
                    $exception->getMessage()
                )
            );

            if ($this->isLocalFallbackEnabled()) {
                $this->audit('api_exception_fallback', $cnpjClean, [
                    'error' => $exception->getMessage()
                ]);

                return $this->buildLocalFallbackPayload(
                    'Validação via API indisponível. CNPJ validado localmente.'
                );
            }

            $this->audit('api_exception_no_fallback', $cnpjClean, [
                'error' => $exception->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Formatar CNPJ para exibição
     *
     * @param string $cnpj
     * @return string
     */
    public function format(string $cnpj): string
    {
        $cnpj = $this->clean($cnpj);

        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($cnpj, 0, 2),
            substr($cnpj, 2, 3),
            substr($cnpj, 5, 3),
            substr($cnpj, 8, 4),
            substr($cnpj, 12, 2)
        );
    }

    /**
     * Limpar CNPJ (remover formatação)
     *
     * @param string $cnpj
     * @return string
     */
    public function clean(string $cnpj): string
    {
        return (string) preg_replace('/[^0-9]/', '', $cnpj);
    }

    public function clearCache(?string $cnpj = null): bool
    {
        if ($cnpj !== null && trim($cnpj) !== '') {
            $cleanCnpj = $this->clean($cnpj);
            if (strlen($cleanCnpj) !== 14) {
                return false;
            }

            $removed = $this->cache->remove($this->getCacheId($cleanCnpj));
            $this->audit('cache_clear_single', $cleanCnpj, ['removed' => $removed ? 1 : 0]);

            return $removed;
        }

        $cleaned = $this->cache->clean([self::CACHE_TAG]);
        $this->audit('cache_clear_all', 'all', ['removed' => $cleaned ? 1 : 0]);

        return $cleaned;
    }

    private function isLookupEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_LOOKUP_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    private function getLookupApiUrl(): string
    {
        $configuredUrl = trim((string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_LOOKUP_API_URL,
            ScopeInterface::SCOPE_STORE
        ));

        return $configuredUrl !== '' ? $configuredUrl : self::DEFAULT_API_URL;
    }

    private function getLookupTimeout(): int
    {
        $timeout = (int) $this->scopeConfig->getValue(
            self::CONFIG_PATH_LOOKUP_TIMEOUT,
            ScopeInterface::SCOPE_STORE
        );

        return $timeout > 0 ? $timeout : self::DEFAULT_TIMEOUT;
    }

    private function isRequireActiveStatusEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_LOOKUP_REQUIRE_ACTIVE,
            ScopeInterface::SCOPE_STORE
        );
    }

    private function isLocalFallbackEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_LOOKUP_ALLOW_FALLBACK,
            ScopeInterface::SCOPE_STORE
        );
    }

    private function isLookupCacheEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_LOOKUP_CACHE_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    private function getLookupCacheTtl(): int
    {
        $ttl = (int) $this->scopeConfig->getValue(
            self::CONFIG_PATH_LOOKUP_CACHE_TTL,
            ScopeInterface::SCOPE_STORE
        );

        return $ttl > 0 ? $ttl : self::DEFAULT_CACHE_TTL;
    }

    public function isRateLimitEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_LOOKUP_RATE_LIMIT_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getRateLimitMaxRequests(): int
    {
        $value = (int) $this->scopeConfig->getValue(
            self::CONFIG_PATH_LOOKUP_RATE_LIMIT_MAX,
            ScopeInterface::SCOPE_STORE
        );

        return $value > 0 ? $value : self::DEFAULT_RATE_LIMIT_MAX;
    }

    public function getRateLimitWindowSeconds(): int
    {
        $value = (int) $this->scopeConfig->getValue(
            self::CONFIG_PATH_LOOKUP_RATE_LIMIT_WINDOW,
            ScopeInterface::SCOPE_STORE
        );

        return $value > 0 ? $value : self::DEFAULT_RATE_LIMIT_WINDOW;
    }

    private function getCacheId(string $cnpj): string
    {
        return self::CACHE_KEY_PREFIX . $cnpj;
    }

    private function loadFromCache(string $cnpj): ?array
    {
        if (!$this->isLookupCacheEnabled()) {
            return null;
        }

        $cached = $this->cache->load($this->getCacheId($cnpj));
        if (!$cached) {
            return null;
        }

        try {
            $decoded = $this->json->unserialize($cached);
        } catch (\InvalidArgumentException $exception) {
            $this->_logger->warning(
                sprintf('Cache de CNPJ inválido para %s: %s', $cnpj, $exception->getMessage())
            );

            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        $decoded['source'] = 'cache';

        // Reject poisoned cache (rate-limit / incomplete saved as valid=true historically)
        if ($this->isPoisonedCachePayload($decoded)) {
            $this->cache->remove($this->getCacheId($cnpj));
            $this->audit('cache_poison_ignored', $cnpj);
            return null;
        }

        return $decoded;
    }

    /**
     * True when HTTP/body indicates ReceitaWS rate limiting.
     *
     * @param array<string, mixed>|null $data
     */
    private function isRateLimitedResponse(int $httpStatus, ?array $data): bool
    {
        if ($httpStatus === 429) {
            return true;
        }

        if ($data === null) {
            return false;
        }

        $message = strtolower((string) ($data['message'] ?? ''));
        if ($message !== '' && str_contains($message, 'too many requests')) {
            return true;
        }

        return false;
    }

    /**
     * ReceitaWS success payloads include company identity fields.
     *
     * @param array<string, mixed> $data
     */
    private function isCompleteCompanyPayload(array $data): bool
    {
        if (isset($data['nome']) && trim((string) $data['nome']) !== '') {
            return true;
        }

        if (isset($data['atividade_principal']) && is_array($data['atividade_principal']) && $data['atividade_principal'] !== []) {
            return true;
        }

        // BrasilAPI-shaped (if primary URL was swapped)
        if (isset($data['razao_social']) && trim((string) $data['razao_social']) !== '') {
            return true;
        }

        if (isset($data['cnae_fiscal'])) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isPoisonedCachePayload(array $payload): bool
    {
        if (($payload['valid'] ?? null) === false) {
            return false;
        }

        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            // Local fallback payloads without data are allowed (api_error path)
            return false;
        }

        if ($this->isRateLimitedResponse(0, $data)) {
            return true;
        }

        return !$this->isCompleteCompanyPayload($data);
    }

    /**
     * Full company lookup via BrasilAPI when ReceitaWS is rate-limited or incomplete.
     *
     * @return array<string, mixed>|null
     */
    private function fetchFromBrasilApi(string $cnpjClean): ?array
    {
        try {
            $fallbackCurl = clone $this->curl;
            $fallbackCurl->setOption(CURLOPT_TIMEOUT, $this->getLookupTimeout());
            $fallbackCurl->setOption(CURLOPT_SSL_VERIFYPEER, true);
            $fallbackCurl->setHeaders(['Accept' => 'application/json']);
            $fallbackCurl->get(self::FALLBACK_API_URL . $cnpjClean);

            $status = (int) $fallbackCurl->getStatus();
            $body = (string) $fallbackCurl->getBody();
            $fallbackData = json_decode($body, true);

            if ($status >= 400 || !is_array($fallbackData) || !$this->isCompleteCompanyPayload($fallbackData)) {
                $this->audit('brasilapi_unavailable', $cnpjClean, ['http_status' => $status]);
                return null;
            }

            $situacao = $fallbackData['descricao_situacao_cadastral']
                ?? $fallbackData['situacao_cadastral']
                ?? '';
            if (is_numeric($situacao)) {
                $situacao = ((int) $situacao === 2) ? 'ATIVA' : (string) $situacao;
            }

            $cnaeCode = isset($fallbackData['cnae_fiscal'])
                ? (string) $fallbackData['cnae_fiscal']
                : '';
            $cnaeText = (string) ($fallbackData['cnae_fiscal_descricao'] ?? '');

            // Keep BrasilAPI keys in data so CnaeClassifier::extractCnaeCode works,
            // and also expose ReceitaWS-compatible atividade_principal for callers.
            if ($cnaeCode !== '' && !isset($fallbackData['atividade_principal'])) {
                $fallbackData['atividade_principal'] = [
                    [
                        'code' => $cnaeCode,
                        'text' => $cnaeText,
                    ],
                ];
            }
            if (!isset($fallbackData['nome']) && isset($fallbackData['razao_social'])) {
                $fallbackData['nome'] = $fallbackData['razao_social'];
            }
            if ($situacao !== '' && !isset($fallbackData['situacao'])) {
                $fallbackData['situacao'] = strtoupper((string) $situacao);
            }

            return [
                'valid' => true,
                'source' => 'brasilapi',
                'razao_social' => (string) ($fallbackData['razao_social'] ?? $fallbackData['nome'] ?? ''),
                'nome_fantasia' => (string) ($fallbackData['nome_fantasia'] ?? $fallbackData['fantasia'] ?? ''),
                'cnpj' => $cnpjClean,
                'situacao' => strtoupper((string) ($fallbackData['situacao'] ?? $situacao)),
                'tipo' => (string) ($fallbackData['descricao_tipo_de_logradouro'] ?? ''),
                'porte' => (string) ($fallbackData['porte'] ?? ''),
                'natureza_juridica' => (string) ($fallbackData['natureza_juridica'] ?? ''),
                'atividade_principal' => $cnaeText,
                'logradouro' => (string) ($fallbackData['logradouro'] ?? ''),
                'numero' => (string) ($fallbackData['numero'] ?? ''),
                'complemento' => (string) ($fallbackData['complemento'] ?? ''),
                'bairro' => (string) ($fallbackData['bairro'] ?? ''),
                'municipio' => (string) ($fallbackData['municipio'] ?? ''),
                'uf' => (string) ($fallbackData['uf'] ?? ''),
                'cep' => (string) ($fallbackData['cep'] ?? ''),
                'telefone' => (string) ($fallbackData['ddd_telefone_1'] ?? ''),
                'email' => (string) ($fallbackData['email'] ?? ''),
                'data' => $fallbackData,
            ];
        } catch (\Throwable $e) {
            $this->_logger->warning('BrasilAPI full lookup failed: ' . $e->getMessage());
            $this->audit('brasilapi_exception', $cnpjClean, ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function saveToCache(string $cnpj, array $payload, ?int $ttlOverride = null): void
    {
        if (!$this->isLookupCacheEnabled()) {
            return;
        }

        // Never persist rate-limit / incomplete bodies as successful company data
        if ($this->isPoisonedCachePayload($payload)) {
            $this->audit('cache_skip_poison', $cnpj);
            return;
        }

        try {
            $this->cache->save(
                $this->json->serialize($payload),
                $this->getCacheId($cnpj),
                [self::CACHE_TAG],
                $ttlOverride ?? $this->getLookupCacheTtl()
            );
        } catch (\Throwable $exception) {
            $this->_logger->warning(
                sprintf('Falha ao salvar cache de CNPJ %s: %s', $cnpj, $exception->getMessage())
            );
        }
    }

    /**
     * Cross-verify CNPJ situacao using BrasilAPI when primary API returns non-ATIVA.
     * Returns the situacao string from the fallback API, or null on failure.
     */
    private function crossVerifyWithFallbackApi(string $cnpjClean): ?string
    {
        try {
            $fallbackCurl = clone $this->curl;
            $fallbackCurl->setOption(CURLOPT_TIMEOUT, 5);
            $fallbackCurl->setOption(CURLOPT_SSL_VERIFYPEER, true);
            $fallbackCurl->setHeaders(['Accept' => 'application/json']);

            $fallbackCurl->get(self::FALLBACK_API_URL . $cnpjClean);
            $body = (string) $fallbackCurl->getBody();
            $fallbackData = json_decode($body, true);

            if (!is_array($fallbackData)) {
                return null;
            }

            // BrasilAPI returns "descricao_situacao_cadastral" (e.g. "ATIVA", "BAIXADA")
            $situacao = $fallbackData['descricao_situacao_cadastral']
                ?? $fallbackData['situacao_cadastral'] // numeric code fallback
                ?? null;

            if ($situacao !== null) {
                // Normalize: BrasilAPI may return numeric code 2 = ATIVA, 8 = BAIXADA
                if (is_numeric($situacao)) {
                    $situacao = ((int) $situacao === 2) ? 'ATIVA' : 'BAIXADA';
                }
                return strtoupper((string) $situacao);
            }

            return null;
        } catch (\Throwable $e) {
            $this->_logger->warning('BrasilAPI fallback failed: ' . $e->getMessage());
            return null;
        }
    }

    private function buildLocalFallbackPayload(string $message): array
    {
        return [
            'valid' => true,
            'api_error' => true,
            'source' => 'fallback',
            'message' => $message
        ];
    }

    private function audit(string $event, string $cnpj, array $context = []): void
    {
        $payload = array_merge(
            [
                'event' => $event,
                'cnpj' => $this->maskForLog($cnpj)
            ],
            $context
        );

        $this->_logger->info('[B2B][CNPJ] ' . $this->json->serialize($payload));
    }

    private function maskForLog(string $cnpj): string
    {
        $clean = $this->clean($cnpj);
        if (strlen($clean) !== 14) {
            return $clean;
        }

        return substr($clean, 0, 2) . '******' . substr($clean, -4);
    }
}
