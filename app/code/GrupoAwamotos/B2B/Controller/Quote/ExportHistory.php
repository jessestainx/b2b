<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Quote;

use GrupoAwamotos\B2B\Helper\GuestLoginRedirect;
use GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest\CollectionFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Psr\Log\LoggerInterface;

class ExportHistory implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerSession $customerSession,
        private readonly CollectionFactory $collectionFactory,
        private readonly HttpResponse $response,
        private readonly LoggerInterface $logger,
        private readonly GuestLoginRedirect $guestLoginRedirect
    ) {
    }

    public function execute(): ResponseInterface|Redirect
    {
        $redirect = $this->redirectFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            return $this->guestLoginRedirect->create('b2b/quote/history');
        }

        try {
            $collection = $this->collectionFactory->create();
            $collection->addCustomerFilter((int) $this->customerSession->getCustomerId());
            $collection->setOrder('created_at', 'DESC');

            $rows = [[
                'Numero',
                'Data',
                'Status',
                'Total Cotado',
                'Itens',
                'Validade',
            ]];

            foreach ($collection as $quote) {
                $rows[] = [
                    (string) $quote->getRequestId(),
                    (string) $quote->getCreatedAt(),
                    (string) $quote->getStatusLabel(),
                    $quote->getQuotedTotal() !== null ? number_format((float) $quote->getQuotedTotal(), 2, ',', '.') : '',
                    (string) count($quote->getItems()),
                    (string) ($quote->getExpiresAt() ?? ''),
                ];
            }

            $csv = $this->buildCsv($rows);
            $filename = 'cotacoes-b2b-' . date('Y-m-d') . '.csv';

            $this->response->setHeader('Content-Type', 'text/csv; charset=UTF-8', true);
            $this->response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true);
            $this->response->setBody($csv);

            return $this->response;
        } catch (\Throwable $exception) {
            $this->logger->error('[B2B Quote Export] ' . $exception->getMessage());
            return $redirect->setPath('b2b/quote/history');
        }
    }

    /**
     * @param list<list<string>> $rows
     */
    private function buildCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }
}
