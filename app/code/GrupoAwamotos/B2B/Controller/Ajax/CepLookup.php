<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Ajax;

use GrupoAwamotos\B2B\Model\Cep\RequestRateLimiter;
use GrupoAwamotos\B2B\Service\CepLookup as CepLookupService;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

/**
 * GET /b2b/ajax/ceplookup?cep=01310100
 * Retorna dados do endereço via ViaCEP para autopreenchimento no formulário.
 */
class CepLookup implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly CepLookupService $cepLookup,
        private readonly RequestRateLimiter $requestRateLimiter,
        private readonly RemoteAddress $remoteAddress
    ) {
    }

    public function execute(): \Magento\Framework\Controller\Result\Json
    {
        $result = $this->jsonFactory->create();

        $clientIp = (string) (
            $this->remoteAddress->getRemoteAddress()
            ?: $this->request->getServer('REMOTE_ADDR')
            ?: 'unknown'
        );
        $rateLimit = $this->requestRateLimiter->consume($clientIp);

        if (!$rateLimit['allowed']) {
            $retryAfter = (int) ($rateLimit['retry_after'] ?? 60);
            return $result->setData([
                'error' => (string) __(
                    'Muitas consultas de CEP em pouco tempo. Aguarde %1 segundos e tente novamente.',
                    $retryAfter
                ),
                'rate_limited' => true,
                'retry_after' => $retryAfter,
            ]);
        }

        $cep = preg_replace('/\D/', '', (string) $this->request->getParam('cep', ''));

        if (!$cep || strlen($cep) !== 8) {
            return $result->setData(['error' => (string) __('CEP inválido')]);
        }

        $data = $this->cepLookup->lookup($cep);

        if (!$data) {
            return $result->setData(['error' => (string) __('CEP não encontrado')]);
        }

        return $result->setData([
            'success'     => true,
            'logradouro'  => $data['logradouro'] ?? '',
            'bairro'      => $data['bairro'] ?? '',
            'localidade'  => $data['localidade'] ?? '',
            'uf'          => $data['uf'] ?? '',
            'cep'         => $data['cep'] ?? $cep,
            'ibge'        => $data['ibge'] ?? '',
            'ddd'         => $data['ddd'] ?? '',
        ]);
    }
}
