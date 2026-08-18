<?php

/**
 * AJAX Controller para validação de CNPJ e consulta ReceitaWS + ERP
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Register;

use GrupoAwamotos\B2B\Helper\CnpjValidator;
use GrupoAwamotos\B2B\Model\CnaeClassifier;
use GrupoAwamotos\B2B\Model\Cnpj\RequestRateLimiter;
use GrupoAwamotos\B2B\Model\ErpIntegration;
use GrupoAwamotos\B2B\Service\RegistrationCnpjService;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Psr\Log\LoggerInterface;

class ValidateCnpj implements HttpPostActionInterface
{
    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private CnpjValidator $cnpjValidator;
    private RequestRateLimiter $requestRateLimiter;
    private RemoteAddress $remoteAddress;
    private CustomerCollectionFactory $customerCollectionFactory;
    private ErpIntegration $erpIntegration;
    private CnaeClassifier $cnaeClassifier;
    private FormKeyValidator $formKeyValidator;
    private LoggerInterface $logger;
    private RegistrationCnpjService $registrationCnpjService;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        CnpjValidator $cnpjValidator,
        RequestRateLimiter $requestRateLimiter,
        RemoteAddress $remoteAddress,
        CustomerCollectionFactory $customerCollectionFactory,
        ErpIntegration $erpIntegration,
        CnaeClassifier $cnaeClassifier,
        FormKeyValidator $formKeyValidator,
        LoggerInterface $logger,
        ?RegistrationCnpjService $registrationCnpjService = null
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->cnpjValidator = $cnpjValidator;
        $this->requestRateLimiter = $requestRateLimiter;
        $this->remoteAddress = $remoteAddress;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->erpIntegration = $erpIntegration;
        $this->cnaeClassifier = $cnaeClassifier;
        $this->formKeyValidator = $formKeyValidator;
        $this->logger = $logger;
        $this->registrationCnpjService = $registrationCnpjService ?? new RegistrationCnpjService(
            $cnpjValidator,
            $customerCollectionFactory,
            $erpIntegration,
            $cnaeClassifier
        );
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->logger->warning('[B2B] CSRF attempt on CNPJ validation', [
                'ip' => $this->remoteAddress->getRemoteAddress()
            ]);
            return $result->setData([
                'success' => false,
                'message' => (string) __('Requisição inválida. Recarregue a página e tente novamente.')
            ]);
        }

        $clientIp = (string) (
            $this->remoteAddress->getRemoteAddress()
            ?: $this->request->getServer('REMOTE_ADDR')
            ?: 'unknown'
        );
        $rateLimit = $this->requestRateLimiter->consume($clientIp);

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

        $cnpj = (string) preg_replace('/\D/', '', (string) $this->request->getParam('cnpj', ''));
        $forceRefresh = filter_var(
            $this->request->getParam('force_refresh', false),
            FILTER_VALIDATE_BOOLEAN
        );

        return $result->setData(
            $this->registrationCnpjService->lookupForRegistration($cnpj, $forceRefresh)
        );
    }
}
