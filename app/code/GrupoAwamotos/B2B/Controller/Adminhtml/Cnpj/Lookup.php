<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Cnpj;

use GrupoAwamotos\B2B\Helper\CnpjValidator;
use GrupoAwamotos\B2B\Model\Cnpj\RequestRateLimiter;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Psr\Log\LoggerInterface;

class Lookup extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::customer_approval';

    private JsonFactory $jsonFactory;
    private CnpjValidator $cnpjValidator;
    private RequestRateLimiter $requestRateLimiter;
    private RemoteAddress $remoteAddress;
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        CnpjValidator $cnpjValidator,
        RequestRateLimiter $requestRateLimiter,
        RemoteAddress $remoteAddress,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->cnpjValidator = $cnpjValidator;
        $this->requestRateLimiter = $requestRateLimiter;
        $this->remoteAddress = $remoteAddress;
        $this->logger = $logger;
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $clientIp = (string) (
            $this->remoteAddress->getRemoteAddress()
            ?: $this->getRequest()->getServer('REMOTE_ADDR')
            ?: 'unknown'
        );
        $rateLimit = $this->requestRateLimiter->consume('admin:' . $clientIp);

        if (!$rateLimit['allowed']) {
            $retryAfter = (int) ($rateLimit['retry_after'] ?? 60);

            return $result->setData([
                'success' => false,
                'rate_limited' => true,
                'retry_after' => $retryAfter,
                'message' => (string) __(
                    'Muitas consultas de CNPJ em pouco tempo. Aguarde %1 segundos e tente novamente.',
                    $retryAfter
                )
            ]);
        }

        $cnpj = (string) preg_replace('/\D/', '', (string) $this->getRequest()->getParam('cnpj', ''));
        $forceRefresh = filter_var(
            $this->getRequest()->getParam('force_refresh', false),
            FILTER_VALIDATE_BOOLEAN
        );

        try {
            return $result->setData($this->cnpjValidator->lookupHttp($cnpj, $forceRefresh));
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf(
                    '[B2B][Admin][CNPJ Lookup] Erro para CNPJ %s: %s',
                    $cnpj,
                    $exception->getMessage()
                )
            );

            return $result->setData([
                'success' => false,
                'message' => 'CNPJ não encontrado na Receita Federal.'
            ]);
        }
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('GrupoAwamotos_B2B::customer_approval')
            || $this->_authorization->isAllowed('Magento_Sales::create')
            || $this->_authorization->isAllowed('Magento_Customer::manage');
    }
}
