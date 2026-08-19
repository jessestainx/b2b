<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Finance;

use GrupoAwamotos\B2B\Model\Finance\RequestRateLimiter;
use GrupoAwamotos\B2B\Model\Order\CustomerFinanceData;
use Magento\Customer\Controller\AbstractAccount;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;

/**
 * Pagina de impressao do boleto (Fase 4) -- somente leitura, exige login.
 *
 * Valida propriedade do titulo atraves de CustomerFinanceData::getBoletoImprimivel(),
 * que so consulta o ERP usando o erp_code resolvido da sessao autenticada -- nunca
 * a partir de parametro de request.
 *
 * Fase 5 (hardening): rate limiting por cliente + log de auditoria de quem imprimiu
 * qual titulo e quando (sem logar CPF/CNPJ do sacado).
 */
class PrintBoleto extends AbstractAccount implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly CustomerFinanceData $customerFinanceData,
        private readonly RequestRateLimiter $rateLimiter,
        private readonly CustomerSession $customerSession,
        private readonly Registry $registry,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\View\Result\Page|Redirect
     */
    public function execute()
    {

        $receberCodigo = (int) $this->getRequest()->getParam('receber');
        $customerId = (int) $this->customerSession->getCustomerId();

        if ($receberCodigo <= 0) {
            $this->messageManager->addErrorMessage(
                __('Não foi possível localizar o boleto solicitado.')
            );
            return $this->resultRedirectFactory->create()->setPath('b2b/finance/index');
        }

        $rateLimit = $this->rateLimiter->consume('customer_' . $customerId);
        if (!$rateLimit['allowed']) {
            $this->logger->warning('[B2B-Finance] Rate limit atingido na impressao de boleto', [
                'customer_id' => $customerId,
            ]);

            $this->messageManager->addErrorMessage(
                __('Muitas tentativas de impressão em pouco tempo. Aguarde %1 segundos e tente novamente.', (int) $rateLimit['retry_after'])
            );

            return $this->resultRedirectFactory->create()->setPath('b2b/finance/index');
        }

        $boleto = $this->customerFinanceData->getBoletoImprimivel($receberCodigo);

        if ($boleto === null) {
            $this->messageManager->addErrorMessage(
                __('O boleto solicitado não está disponível para a sua conta.')
            );
            // Log de auditoria de acesso negado/nao encontrado -- sem dados sensiveis (CPF/CNPJ).
            $this->logger->info('[B2B-Finance] Tentativa de impressao de titulo nao encontrado/nao pertencente ao cliente', [
                'receber' => $receberCodigo,
                'customer_id' => $customerId,
            ]);

            return $this->resultRedirectFactory->create()->setPath('b2b/finance/index');
        }

        // Log de auditoria de sucesso: quem imprimiu qual titulo e quando.
        $this->logger->info('[B2B-Finance] Boleto impresso', [
            'receber' => $receberCodigo,
            'customer_id' => $customerId,
            'nro_documento' => $boleto['nro_documento'] ?? '',
            'supported' => !empty($boleto['supported']),
        ]);

        $this->registry->register('b2b_finance_boleto_imprimivel', $boleto);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(
            __('Boleto %1', $boleto['nro_documento'] ?? '')
        );

        return $resultPage;
    }
}
