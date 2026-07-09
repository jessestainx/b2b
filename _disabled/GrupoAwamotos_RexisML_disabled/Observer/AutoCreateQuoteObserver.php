<?php

declare(strict_types=1);

/**
 * Observer para criar automaticamente cotacoes para oportunidades de Cross-sell
 * Dispara quando um pedido e concluido (sales_order_place_after)
 */

namespace GrupoAwamotos\RexisML\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class AutoCreateQuoteObserver implements ObserverInterface
{
    private ResourceConnection $resource;
    private CustomerRepositoryInterface $customerRepository;
    private ProductRepositoryInterface $productRepository;
    private CartManagementInterface $cartManagement;
    private CartRepositoryInterface $cartRepository;
    private StoreManagerInterface $storeManager;
    private LoggerInterface $logger;

    public function __construct(
        ResourceConnection $resource,
        CustomerRepositoryInterface $customerRepository,
        ProductRepositoryInterface $productRepository,
        CartManagementInterface $cartManagement,
        CartRepositoryInterface $cartRepository,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->resource = $resource;
        $this->customerRepository = $customerRepository;
        $this->productRepository = $productRepository;
        $this->cartManagement = $cartManagement;
        $this->cartRepository = $cartRepository;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        try {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $observer->getEvent()->getOrder();

            if (!$order || !$order->getCustomerId()) {
                return;
            }

            $customerId = $order->getCustomerId();

            // Resolve ERP code for this customer
            $erpCode = $this->resolveErpCode((int)$customerId);

            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('rexis_dataset_recomendacao');

            // Buscar cross-sell recommendations com score alto
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from($table, ['identificador_produto', 'pred', 'previsao_gasto_round_up'])
                    ->where('identificador_cliente = ?', $erpCode)
                    ->where('tipo_recomendacao = ?', 'crosssell')
                    ->where('pred >= ?', 0.80)
                    ->where('previsao_gasto_round_up >= ?', 200)
                    ->order('pred DESC')
                    ->limit(3)
            );

            if (empty($rows)) {
                return;
            }

            // Criar carrinho de cotacao
            $customer = $this->customerRepository->getById($customerId);
            $cartId = $this->cartManagement->createEmptyCartForCustomer($customerId);
            $quote = $this->cartRepository->get($cartId);

            $quote->setIsActive(false);
            $quote->setCustomer($customer);
            $quote->setStore($this->storeManager->getStore());

            $addedProducts = 0;
            foreach ($rows as $row) {
                try {
                    $product = $this->productRepository->get($row['identificador_produto']);
                    $quote->addProduct($product, 1);
                    $addedProducts++;
                } catch (\Exception $e) {
                    $this->logger->debug('[RexisML AutoQuote] Produto nao encontrado: ' . $row['identificador_produto']);
                    continue;
                }
            }

            if ($addedProducts > 0) {
                $quote->setCustomerNote(
                    'Cotacao automatica gerada pelo REXIS ML baseada em analise preditiva. ' .
                    'Produtos recomendados com alta probabilidade de compra.'
                );
                $quote->collectTotals();
                $this->cartRepository->save($quote);

                $this->logger->info(sprintf(
                    '[RexisML] Cotacao automatica #%s criada para cliente #%d com %d produtos',
                    $quote->getId(),
                    $customerId,
                    $addedProducts
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error('[RexisML AutoQuote] ' . $e->getMessage());
        }
    }

    private function resolveErpCode(int $customerId): string
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $erpAttr = $customer->getCustomAttribute('erp_code');
            if ($erpAttr && $erpAttr->getValue()) {
                return (string)$erpAttr->getValue();
            }
        } catch (\Exception $e) {
            $this->logger->debug('[RexisML AutoQuote] Erro ao resolver ERP code: ' . $e->getMessage());
        }

        try {
            $connection = $this->resource->getConnection();
            $result = $connection->fetchOne(
                $connection->select()
                    ->from($this->resource->getTableName('grupoawamotos_erp_entity_map'), 'erp_code')
                    ->where('entity_type = ?', 'customer')
                    ->where('magento_entity_id = ?', $customerId)
            );
            return $result ?: (string)$customerId;
        } catch (\Exception $e) {
            return (string)$customerId;
        }
    }
}
