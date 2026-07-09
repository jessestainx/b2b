<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Order;

use GrupoAwamotos\B2B\Model\Order\CustomerOrderErpData;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class DownloadInvoice implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly CustomerSession $customerSession,
        private readonly CustomerOrderErpData $customerOrderErpData,
        private readonly FileFactory $fileFactory,
        private readonly ResultFactory $resultFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return ResultInterface|ResponseInterface
     */
    public function execute()
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        if (!$this->customerSession->isLoggedIn()) {
            return $redirect->setPath('b2b/account/login');
        }

        $orderId = (int) $this->request->getParam('order_id');
        $type = strtolower(trim((string) $this->request->getParam('type', 'xml')));

        if ($orderId <= 0) {
            return $redirect->setPath('sales/order/history');
        }

        try {
            $order = $this->customerOrderErpData->getCustomerOrder(
                $orderId,
                (int) $this->customerSession->getCustomerId()
            );

            $invoice = $this->customerOrderErpData->getInvoiceSummary((int) $order->getId());
            if ($invoice === null || ($invoice['chave'] ?? '') === '') {
                return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
            }

            if ($type === 'danfe') {
                $danfeUrl = (string) ($invoice['url_danfe'] ?? '');
                if ($danfeUrl === '') {
                    return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
                }

                $this->logger->info('[B2B NF-e] DANFE consulta', [
                    'order_id' => $orderId,
                    'customer_id' => (int) $this->customerSession->getCustomerId(),
                ]);

                return $redirect->setUrl($danfeUrl);
            }

            $xml = $this->customerOrderErpData->buildInvoiceXml([
                'chave' => $invoice['chave'],
                'numero' => $invoice['numero'],
                'serie' => $invoice['serie'],
                'data_emissao' => $invoice['data_emissao'],
                'emitente' => ['razao_social' => $invoice['emitente']],
                'url_danfe' => $invoice['url_danfe'],
            ]);

            $filename = 'NFe-' . preg_replace('/\D+/', '', (string) $invoice['chave']) . '.xml';

            $this->logger->info('[B2B NF-e] XML resumo download', [
                'order_id' => $orderId,
                'customer_id' => (int) $this->customerSession->getCustomerId(),
            ]);

            return $this->fileFactory->create(
                $filename,
                [
                    'type' => 'string',
                    'value' => $xml,
                    'rm' => true,
                ],
                \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR,
                'application/xml; charset=UTF-8'
            );
        } catch (NoSuchEntityException) {
            $redirect->setPath('sales/order/history');
            return $redirect;
        } catch (\Throwable $exception) {
            $this->logger->error('[B2B NF-e] Download failed: ' . $exception->getMessage(), [
                'order_id' => $orderId,
            ]);

            return $redirect->setPath('sales/order/history');
        }
    }
}
