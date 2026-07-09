<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Ajax;

use GrupoAwamotos\B2B\Model\Cnpj\RequestRateLimiter;
use GrupoAwamotos\B2B\Service\CnpjLookup as CnpjLookupService;
use GrupoAwamotos\B2B\Service\CnpjValidator;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

/**
 * GET /b2b/ajax/cnpjlookup?cnpj=00000000000000
 * Retorna dados cadastrais do CNPJ via ReceitaWS para autopreenchimento do formulário.
 */
class CnpjLookup implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly CnpjLookupService $cnpjLookup,
        private readonly CnpjValidator $cnpjValidator,
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
                    'Muitas consultas de CNPJ em pouco tempo. Aguarde %1 segundos e tente novamente.',
                    $retryAfter
                ),
                'rate_limited' => true,
                'retry_after' => $retryAfter,
            ]);
        }

        $cnpj   = (string) $this->request->getParam('cnpj', '');

        if (!$cnpj || !$this->cnpjValidator->isValid($cnpj)) {
            return $result->setData(['error' => 'CNPJ inválido']);
        }

        $data = $this->cnpjLookup->lookup($cnpj);

        if (!$data) {
            return $result->setData(['error' => 'CNPJ não encontrado na Receita Federal']);
        }

        return $result->setData([
            'success'      => true,
            'razao_social' => $data['nome']       ?? '',
            'fantasia'     => $data['fantasia']   ?? '',
            'situacao'     => $data['situacao']   ?? '',
            'logradouro'   => $data['logradouro'] ?? '',
            'numero'       => $data['numero']     ?? '',
            'complemento'  => $data['complemento'] ?? '',
            'bairro'       => $data['bairro']     ?? '',
            'cidade'       => $data['municipio']  ?? '',
            'uf'           => $data['uf']         ?? '',
            'cep'          => $data['cep']        ?? '',
            'telefone'     => $data['telefone']   ?? '',
            'email'        => $data['email']      ?? '',
        ]);
    }
}
