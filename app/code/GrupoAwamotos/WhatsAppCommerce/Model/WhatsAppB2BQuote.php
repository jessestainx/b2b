<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Model;

use GrupoAwamotos\B2B\Api\Data\QuoteRequestInterface;
use GrupoAwamotos\B2B\Api\QuoteRequestRepositoryInterface;
use GrupoAwamotos\B2B\Model\Notification\WhatsAppService as B2BWhatsAppService;
use GrupoAwamotos\B2B\Model\QuoteRequestFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest\CollectionFactory as QuoteCollectionFactory;
use GrupoAwamotos\WhatsAppCommerce\Api\B2BQuoteInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class WhatsAppB2BQuote implements B2BQuoteInterface
{
    public function __construct(
        private readonly QuoteRequestRepositoryInterface $quoteRequestRepository,
        private readonly QuoteRequestFactory $quoteRequestFactory,
        private readonly QuoteCollectionFactory $quoteCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly B2BWhatsAppService $b2bWhatsAppService,
        private readonly LoggerInterface $logger,
        private readonly ResourceConnection $resource,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function submitQuote(string $phone, array $items, ?string $message = null): array
    {
        if (empty($items)) {
            return ['success' => false, 'message' => 'A lista de itens esta vazia.'];
        }

        $customer = $this->findCustomerByPhone($phone);
        if ($customer === null) {
            return [
                'success' => false,
                'message' => 'Nenhum cadastro encontrado para este telefone. Faca seu cadastro B2B primeiro.',
            ];
        }

        try {
            $validatedItems = [];
            $itemsJson = [];

            foreach ($items as $item) {
                $sku = $item['sku'] ?? '';
                $qty = (float) ($item['qty'] ?? 1);

                if (empty($sku) || $qty <= 0) {
                    continue;
                }

                try {
                    $product = $this->productRepository->get($sku);
                    $validatedItems[] = [
                        'product_id' => (int) $product->getId(),
                        'sku' => $product->getSku(),
                        'name' => $product->getName(),
                        'qty' => $qty,
                        'original_price' => (float) $product->getFinalPrice(),
                    ];
                    $itemsJson[] = [
                        'sku' => $product->getSku(),
                        'name' => $product->getName(),
                        'qty' => $qty,
                        'price' => (float) $product->getFinalPrice(),
                    ];
                } catch (NoSuchEntityException $e) {
                    $this->logger->warning('B2B Quote: SKU not found', ['sku' => $sku]);
                }
            }

            if (empty($validatedItems)) {
                return ['success' => false, 'message' => 'Nenhum produto valido encontrado na lista.'];
            }

            $customerData = [
                'customer_id' => (int) $customer->getId(),
                'customer_email' => $customer->getEmail(),
                'customer_name' => $customer->getFirstname() . ' ' . $customer->getLastname(),
            ];

            $cnpjAttr = $customer->getCustomAttribute('cnpj');
            if ($cnpjAttr) {
                $customerData['cnpj'] = $cnpjAttr->getValue();
            }

            $quoteRequest = $this->quoteRequestFactory->create();
            $quoteRequest->setCustomerId((int) $customer->getId());
            $quoteRequest->setCustomerEmail($customer->getEmail());
            $quoteRequest->setCustomerName($customerData['customer_name']);
            $quoteRequest->setCnpj($customerData['cnpj'] ?? null);
            $quoteRequest->setPhone($phone);
            $quoteRequest->setStatus(QuoteRequestInterface::STATUS_PENDING);
            $quoteRequest->setItemsJson(json_encode($itemsJson));
            $quoteRequest->setMessage($message ?? 'Cotacao solicitada via WhatsApp');

            $saved = $this->quoteRequestRepository->save($quoteRequest);
            $requestId = (int) $saved->getRequestId();

            $this->b2bWhatsAppService->notifyNewQuote([
                'request_id' => $requestId,
                'customer_name' => $customerData['customer_name'],
                'cnpj' => $customerData['cnpj'] ?? 'N/A',
                'phone' => $phone,
                'items_count' => count($validatedItems),
                'message' => $message ?? '',
            ]);

            $this->logger->info('B2B Quote submitted via WhatsApp', [
                'request_id' => $requestId,
                'customer_id' => (int) $customer->getId(),
                'items_count' => count($validatedItems),
            ]);

            $summary = "Cotacao #{$requestId} recebida!\n\nItens:\n";
            foreach ($validatedItems as $vi) {
                $summary .= "- {$vi['qty']}x {$vi['name']} (SKU: {$vi['sku']})\n";
            }
            $summary .= "\nNossa equipe vai analisar e enviar os precos em ate 24h.";

            return [
                'success' => true,
                'request_id' => $requestId,
                'status' => 'pending',
                'items_count' => count($validatedItems),
                'message' => $summary,
            ];
        } catch (\Exception $e) {
            $this->logger->error('B2B Quote submission error', [
                'phone' => $this->maskPhone($phone),
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Erro ao processar cotacao. Tente novamente.'];
        }
    }

    /**
     * @inheritDoc
     */
    public function getQuotes(string $phone): array
    {
        $customer = $this->findCustomerByPhone($phone);
        if ($customer === null) {
            return ['quotes' => [], 'message' => 'Nenhum cadastro encontrado.'];
        }

        $collection = $this->quoteCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', (int) $customer->getId());
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize(10);

        $quotes = [];
        $statusLabels = [
            'pending' => 'Pendente',
            'processing' => 'Em analise',
            'quoted' => 'Cotado',
            'accepted' => 'Aceito',
            'rejected' => 'Recusado',
            'expired' => 'Expirado',
            'converted' => 'Convertido em pedido',
        ];

        foreach ($collection as $quote) {
            $items = json_decode($quote->getData('items_json') ?: '[]', true);
            $quotes[] = [
                'request_id' => (int) $quote->getData('request_id'),
                'status' => $quote->getData('status'),
                'status_label' => $statusLabels[$quote->getData('status')] ?? $quote->getData('status'),
                'items_count' => is_array($items) ? count($items) : 0,
                'quoted_total' => $quote->getData('quoted_total') ? (float) $quote->getData('quoted_total') : null,
                'created_at' => $quote->getData('created_at'),
                'expires_at' => $quote->getData('expires_at'),
            ];
        }

        return ['quotes' => $quotes, 'total' => count($quotes)];
    }

    /**
     * @inheritDoc
     */
    public function getQuoteDetail(int $requestId, string $phone): array
    {
        $customer = $this->findCustomerByPhone($phone);
        if ($customer === null) {
            return ['success' => false, 'message' => 'Cadastro nao encontrado.'];
        }

        try {
            $quoteRequest = $this->quoteRequestRepository->getById($requestId);

            if ((int) $quoteRequest->getCustomerId() !== (int) $customer->getId()) {
                return ['success' => false, 'message' => 'Cotacao nao encontrada.'];
            }

            $items = json_decode($quoteRequest->getItemsJson(), true);

            return [
                'success' => true,
                'request_id' => (int) $quoteRequest->getRequestId(),
                'status' => $quoteRequest->getStatus(),
                'items' => $items,
                'quoted_total' => $quoteRequest->getQuotedTotal(),
                'message' => $quoteRequest->getMessage(),
                'admin_notes' => $quoteRequest->getAdminNotes(),
                'created_at' => $quoteRequest->getCreatedAt(),
                'expires_at' => $quoteRequest->getExpiresAt(),
                'can_accept' => $quoteRequest->getStatus() === QuoteRequestInterface::STATUS_QUOTED,
            ];
        } catch (NoSuchEntityException $e) {
            return ['success' => false, 'message' => 'Cotacao nao encontrada.'];
        }
    }

    /**
     * @inheritDoc
     */
    public function acceptQuote(int $requestId, string $phone): array
    {
        $customer = $this->findCustomerByPhone($phone);
        if ($customer === null) {
            return ['success' => false, 'message' => 'Cadastro nao encontrado.'];
        }

        try {
            $quoteRequest = $this->quoteRequestRepository->getById($requestId);

            if ((int) $quoteRequest->getCustomerId() !== (int) $customer->getId()) {
                return ['success' => false, 'message' => 'Cotacao nao encontrada.'];
            }

            if ($quoteRequest->getStatus() !== QuoteRequestInterface::STATUS_QUOTED) {
                return [
                    'success' => false,
                    'message' => 'Esta cotacao nao pode ser aceita no status atual: ' . $quoteRequest->getStatus(),
                ];
            }

            $expiresAt = $quoteRequest->getExpiresAt();
            if ($expiresAt && strtotime($expiresAt) < time()) {
                $this->quoteRequestRepository->updateStatus($requestId, QuoteRequestInterface::STATUS_EXPIRED);
                return ['success' => false, 'message' => 'Esta cotacao expirou. Solicite uma nova cotacao.'];
            }

            $this->quoteRequestRepository->updateStatus($requestId, QuoteRequestInterface::STATUS_ACCEPTED);
            $orderId = $this->quoteRequestRepository->convertToOrder($requestId);

            $this->logger->info('B2B Quote accepted via WhatsApp', [
                'request_id' => $requestId,
                'order_id' => $orderId,
                'customer_id' => (int) $customer->getId(),
            ]);

            return [
                'success' => true,
                'message' => "Cotacao #{$requestId} aceita! Pedido #{$orderId} criado com sucesso.",
                'order_id' => $orderId,
                'request_id' => $requestId,
            ];
        } catch (NoSuchEntityException $e) {
            return ['success' => false, 'message' => 'Cotacao nao encontrada.'];
        } catch (\Exception $e) {
            $this->logger->error('B2B Quote accept error', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Erro ao processar aceite da cotacao.'];
        }
    }

    private function findCustomerByPhone(string $phone): ?\Magento\Customer\Api\Data\CustomerInterface
    {
        $cleanPhone = preg_replace('/\D/', '', $phone);
        if (strlen($cleanPhone) >= 13 && str_starts_with($cleanPhone, '55')) {
            $cleanPhone = substr($cleanPhone, 2);
        }
        $lastDigits = substr($cleanPhone, -8);
        if (strlen($lastDigits) < 8) {
            return null;
        }

        $connection = $this->resource->getConnection();
        $sql = $connection->select()
            ->from(['a' => $this->resource->getTableName('customer_address_entity')], ['parent_id'])
            ->where(
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(a.telephone, '(', ''), ')', ''), '-', ''), ' ', ''), '+', '') LIKE ?",
                '%' . $lastDigits
            )
            ->limit(1);
        $customerId = (int) $connection->fetchOne($sql);

        if (!$customerId) {
            $collection = $this->customerCollectionFactory->create();
            $collection->addAttributeToFilter('b2b_phone', ['like' => "%" . $lastDigits . "%"]);
            $collection->setPageSize(1);
            $customerId = (int) $collection->getFirstItem()->getId();
        }

        if (!$customerId) {
            return null;
        }

        try {
            return $this->customerRepository->getById($customerId);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function maskPhone(string $phone): string
    {
        $clean = preg_replace('/\D/', '', $phone);
        return strlen($clean) >= 6
            ? substr($clean, 0, 4) . '****' . substr($clean, -2)
            : '***';
    }
}
