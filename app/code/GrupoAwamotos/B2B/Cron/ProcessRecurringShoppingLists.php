<?php

/**
 * Cron Job: Process Recurring Shopping Lists
 *
 * Creates shopping carts for customers with due recurring lists.
 * The customer then proceeds to checkout on their next login.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Cron;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use GrupoAwamotos\B2B\Model\ShoppingListService;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\DataObject;
use Psr\Log\LoggerInterface;

class ProcessRecurringShoppingLists
{
    private ShoppingListService $shoppingListService;
    private Config $config;
    private CartManagementInterface $cartManagement;
    private CartRepositoryInterface $cartRepository;
    private ProductRepositoryInterface $productRepository;
    private StoreManagerInterface $storeManager;
    private CustomerRepositoryInterface $customerRepository;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private LoggerInterface $logger;

    public function __construct(
        ShoppingListService $shoppingListService,
        Config $config,
        CartManagementInterface $cartManagement,
        CartRepositoryInterface $cartRepository,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        CustomerRepositoryInterface $customerRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger
    ) {
        $this->shoppingListService = $shoppingListService;
        $this->config = $config;
        $this->cartManagement = $cartManagement;
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->customerRepository = $customerRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $this->logger->info('[B2B Recurring] Iniciando processamento de listas recorrentes...');

        try {
            $dueLists = $this->shoppingListService->getRecurringListsDue();
        } catch (\Exception $e) {
            $this->logger->error('[B2B Recurring] Erro ao buscar listas: ' . $e->getMessage());
            return;
        }

        $customerIds = [];
        foreach ($dueLists as $list) {
            $customerIds[] = (int) $list->getCustomerId();
        }
        $approvedCustomerIds = $this->getApprovedCustomerIds($customerIds);

        $processed = 0;
        $errors = 0;

        foreach ($dueLists as $list) {
            try {
                $this->processRecurringList($list, $approvedCustomerIds);
                $processed++;
            } catch (\Exception $e) {
                $errors++;
                $this->logger->error(sprintf(
                    '[B2B Recurring] Erro na lista #%d: %s',
                    $list->getId(),
                    $e->getMessage()
                ));
            }
        }

        $this->logger->info(sprintf(
            '[B2B Recurring] Concluido: %d processadas, %d erros.',
            $processed,
            $errors
        ));
    }

    private function processRecurringList($list, array $approvedCustomerIds): void
    {
        $customerId = (int) $list->getCustomerId();
        $listId = (int) $list->getId();

        if (!isset($approvedCustomerIds[$customerId])) {
            $this->rescheduleList($list, sprintf(
                'Cliente #%d não está aprovado para recorrência automática.',
                $customerId
            ));
            return;
        }

        // Create a new cart for the customer
        $cartId = $this->cartManagement->createEmptyCartForCustomer($customerId);
        $quote = $this->cartRepository->get($cartId);

        $store = $this->storeManager->getDefaultStoreView();
        if ($store) {
            $quote->setStoreId((int) $store->getId());
        }

        // Load list items and add to cart (batch-loading products avoids N+1 queries)
        $items = $list->getItemsCollection();

        $productIds = [];
        foreach ($items as $item) {
            $productIds[] = (int) $item->getProductId();
        }
        $products = $this->getProductsByIds($productIds);

        $added = 0;

        foreach ($items as $item) {
            try {
                $product = $products[(int) $item->getProductId()] ?? null;
                if ($product === null) {
                    throw new NoSuchEntityException(__('Produto não encontrado.'));
                }

                if (!$product->isSalable()) {
                    $this->logger->warning(sprintf(
                        '[B2B Recurring] Produto SKU %s indisponivel, lista #%d',
                        $item->getSku(),
                        $listId
                    ));
                    continue;
                }

                $quote->addProduct($product, new DataObject([
                    'qty' => (float) $item->getQty()
                ]));
                $added++;
            } catch (\Exception $e) {
                $this->logger->warning(sprintf(
                    '[B2B Recurring] Nao foi possivel adicionar SKU %s: %s',
                    $item->getSku(),
                    $e->getMessage()
                ));
            }
        }

        if ($added > 0) {
            $quote->collectTotals();
            $this->cartRepository->save($quote);
        }

        // Update next_order_date
        $nextDate = $this->calculateNextDate($list);
        $list->setData('next_order_date', $nextDate);
        $list->save();

        $this->logger->info(sprintf(
            '[B2B Recurring] Lista #%d processada: %d itens adicionados ao carrinho do cliente #%d. Proxima: %s',
            $listId,
            $added,
            $customerId,
            $nextDate
        ));
    }

    /**
     * Batch-load customers and resolve which ones are B2B-approved, avoiding
     * one customerRepository->getById() call per due list.
     *
     * @param int[] $customerIds
     * @return array<int, bool> Map of approved customer IDs (present => approved)
     */
    private function getApprovedCustomerIds(array $customerIds): array
    {
        $customerIds = array_values(array_unique(array_filter($customerIds, static fn ($id) => $id > 0)));
        if ($customerIds === []) {
            return [];
        }

        $approved = [];
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('entity_id', $customerIds, 'in')
                ->create();

            foreach ($this->customerRepository->getList($searchCriteria)->getItems() as $customer) {
                $statusAttribute = $customer->getCustomAttribute('b2b_approval_status');
                $status = $statusAttribute ? (string) $statusAttribute->getValue() : '';

                if ($status === ApprovalStatus::STATUS_APPROVED) {
                    $approved[(int) $customer->getId()] = true;
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning(
                '[B2B Recurring] Falha ao pré-carregar aprovação de clientes: ' . $e->getMessage()
            );
        }

        return $approved;
    }

    /**
     * Batch-load products by ID to avoid N+1 queries when iterating list items.
     *
     * @param int[] $productIds
     * @return \Magento\Catalog\Api\Data\ProductInterface[] Indexed by product ID
     */
    private function getProductsByIds(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter($productIds)));
        if ($productIds === []) {
            return [];
        }

        $products = [];
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('entity_id', $productIds, 'in')
                ->create();

            foreach ($this->productRepository->getList($searchCriteria)->getItems() as $product) {
                $products[(int) $product->getId()] = $product;
            }
        } catch (\Exception $e) {
            $this->logger->warning('[B2B Recurring] Falha ao pré-carregar produtos: ' . $e->getMessage());
        }

        return $products;
    }

    private function calculateNextDate($list): string
    {
        $intervalDays = (int) $list->getData('recurring_interval');
        if ($intervalDays < 1) {
            $intervalDays = 30;
        }

        return date('Y-m-d', strtotime("+{$intervalDays} days"));
    }

    private function rescheduleList($list, string $reason): void
    {
        $nextDate = $this->calculateNextDate($list);
        $list->setData('next_order_date', $nextDate);
        $list->save();

        $this->logger->info(sprintf(
            '[B2B Recurring] Lista #%d reagendada sem gerar carrinho. Motivo: %s Próxima: %s',
            (int) $list->getId(),
            $reason,
            $nextDate
        ));
    }
}
