<?php

/**
 * GrupoAwamotos_MaintenanceMode
 * Newsletter subscription controller for maintenance/coming soon page
 */

declare(strict_types=1);

namespace GrupoAwamotos\MaintenanceMode\Controller\Newsletter;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class Subscribe implements HttpPostActionInterface
{
    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var SubscriberFactory
     */
    private SubscriberFactory $subscriberFactory;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var FormKeyValidator
     */
    private FormKeyValidator $formKeyValidator;

    /**
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param SubscriberFactory $subscriberFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param FormKeyValidator $formKeyValidator
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        SubscriberFactory $subscriberFactory,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        FormKeyValidator $formKeyValidator
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->subscriberFactory = $subscriberFactory;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->formKeyValidator = $formKeyValidator;
    }

    /**
     * Execute newsletter subscription
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        if (!$this->formKeyValidator->validate($this->request)) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Formulário inválido. Atualize a página e tente novamente.')
            ]);
        }

        // Check if newsletter is enabled for maintenance mode
        $newsletterEnabled = $this->scopeConfig->getValue(
            'grupoawamotos_maintenance/comingsoon/newsletter_enabled',
            ScopeInterface::SCOPE_STORE
        );

        if (!$newsletterEnabled) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Newsletter subscription is not available.')
            ]);
        }

        $email = (string)$this->request->getParam('email');

        // Validate email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Por favor, informe um e-mail válido.')
            ]);
        }

        try {
            // Create subscriber
            $subscriber = $this->subscriberFactory->create();

            // Check if already subscribed
            $subscriber->loadByEmail($email);

            if ($subscriber->getId() && $subscriber->getSubscriberStatus() == \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED) {
                return $resultJson->setData([
                    'success' => true,
                    'message' => __('Este e-mail já está inscrito na nossa newsletter!')
                ]);
            }

            // Subscribe the email
            $subscriber->subscribe($email);

            $this->logger->info('MaintenanceMode: Newsletter subscription successful for ' . $email);

            return $resultJson->setData([
                'success' => true,
                'message' => __('Obrigado! Você foi inscrito com sucesso. Avisaremos quando estivermos online!')
            ]);
        } catch (LocalizedException $e) {
            $this->logger->error('MaintenanceMode Newsletter Error: ' . $e->getMessage());
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('MaintenanceMode Newsletter Error: ' . $e->getMessage());
            return $resultJson->setData([
                'success' => false,
                'message' => __('Ocorreu um erro. Tente novamente mais tarde.')
            ]);
        }
    }
}
