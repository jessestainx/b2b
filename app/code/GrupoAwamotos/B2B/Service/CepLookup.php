<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Service;

use Psr\Log\LoggerInterface;

/**
 * Consulta a API pública ViaCEP para preencher endereço automaticamente.
 * Usado no formulário de cadastro B2B e no checkout.
 *
 * API: https://viacep.com.br/ws/{cep}/json/
 * Gratuita, sem autenticação, limite razoável para uso em loja.
 */
class CepLookup
{
    private const VIACEP_URL  = 'https://viacep.com.br/ws/%s/json/';
    private const TIMEOUT_SEC = 5;

    /** Cache em memória para evitar chamadas duplicadas na mesma requisição */
    private array $cache = [];

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Retorna dados do endereço para um CEP, ou null se não encontrado.
     *
     * @return array{
     *   cep: string,
     *   logradouro: string,
     *   complemento: string,
     *   bairro: string,
     *   localidade: string,
     *   uf: string,
     *   ibge: string,
     *   ddd: string
     * }|null
     */
    public function lookup(string $cep): ?array
    {
        $cepClean = preg_replace('/[^0-9]/', '', $cep);

        if (strlen($cepClean) !== 8) {
            return null;
        }

        if (isset($this->cache[$cepClean])) {
            return $this->cache[$cepClean];
        }

        try {
            $url  = sprintf(self::VIACEP_URL, $cepClean);
            $body = $this->httpGet($url);

            if (!$body) {
                return null;
            }

            $data = json_decode($body, true);

            if (empty($data) || isset($data['erro'])) {
                $this->cache[$cepClean] = null;
                return null;
            }

            $this->cache[$cepClean] = $data;
            return $data;
        } catch (\Exception $e) {
            $this->logger->debug('[B2B CepLookup] Falha para CEP ' . $cepClean . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Converte a resposta ViaCEP para o formato de endereço do Magento.
     */
    public function toMagentoAddress(string $cep, int $streetNumber = 0): array
    {
        $data = $this->lookup($cep);
        if (!$data) {
            return [];
        }

        $street = array_filter([
            $data['logradouro'] ?? '',
            $streetNumber > 0 ? (string) $streetNumber : '',
            $data['complemento'] ?? '',
            $data['bairro'] ?? '',
        ]);

        return [
            'postcode'   => $data['cep'] ?? $cep,
            'city'       => $data['localidade'] ?? '',
            'region'     => $data['uf'] ?? '',
            'street'     => array_values($street),
        ];
    }

    private function httpGet(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SEC,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: AWA-Motos/1.0'],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body  = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $body === false) {
            throw new \RuntimeException($error ?: 'cURL returned false');
        }

        return $body ?: null;
    }
}
