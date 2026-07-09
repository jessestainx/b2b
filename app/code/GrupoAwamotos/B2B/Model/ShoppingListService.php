<?php

/**
 * B2B Shopping List Service
 * Manages shopping lists and their items
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Api\ProductRepositoryInterface;

class ShoppingListService
{
    /**
     * @var ShoppingListFactory
     */
    private $listFactory;

    /**
     * @var ShoppingListItemFactory
     */
    private $itemFactory;

    /**
     * @var ResourceModel\ShoppingList
     */
    private $listResource;

    /**
     * @var ResourceModel\ShoppingListItem
     */
    private $itemResource;

    /**
     * @var ResourceModel\ShoppingList\CollectionFactory
     */
    private $listCollectionFactory;

    /**
     * @var ResourceModel\ShoppingListItem\CollectionFactory
     */
    private $itemCollectionFactory;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var Cart
     */
    private $cart;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @param ShoppingListFactory $listFactory
     * @param ShoppingListItemFactory $itemFactory
     * @param ResourceModel\ShoppingList $listResource
     * @param ResourceModel\ShoppingListItem $itemResource
     * @param ResourceModel\ShoppingList\CollectionFactory $listCollectionFactory
     * @param ResourceModel\ShoppingListItem\CollectionFactory $itemCollectionFactory
     * @param CustomerSession $customerSession
     * @param Cart $cart
     * @param ProductRepositoryInterface $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        ShoppingListFactory $listFactory,
        ShoppingListItemFactory $itemFactory,
        ResourceModel\ShoppingList $listResource,
        ResourceModel\ShoppingListItem $itemResource,
        ResourceModel\ShoppingList\CollectionFactory $listCollectionFactory,
        ResourceModel\ShoppingListItem\CollectionFactory $itemCollectionFactory,
        CustomerSession $customerSession,
        Cart $cart,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->listFactory = $listFactory;
        $this->itemFactory = $itemFactory;
        $this->listResource = $listResource;
        $this->itemResource = $itemResource;
        $this->listCollectionFactory = $listCollectionFactory;
        $this->itemCollectionFactory = $itemCollectionFactory;
        $this->customerSession = $customerSession;
        $this->cart = $cart;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
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

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $productIds, 'in')
            ->create();

        $products = [];
        foreach ($this->productRepository->getList($searchCriteria)->getItems() as $product) {
            $products[(int)$product->getId()] = $product;
        }

        return $products;
    }

    /**
     * Get customer lists
     *
     * @param int|null $customerId
     * @return ResourceModel\ShoppingList\Collection
     */
    public function getCustomerLists(?int $customerId = null): ResourceModel\ShoppingList\Collection
    {
        if ($customerId === null) {
            $customerId = (int)$this->customerSession->getCustomerId();
        }

        return $this->listCollectionFactory->create()
            ->addCustomerFilter($customerId)
            ->setOrder('updated_at', 'DESC');
    }

    /**
     * Create new shopping list
     *
     * @param string $name
     * @param string $description
     * @param int|null $customerId
     * @return ShoppingList
     * @throws LocalizedException
     */
    public function createList(string $name, string $description = '', ?int $customerId = null): ShoppingList
    {
        if ($customerId === null) {
            $customerId = (int)$this->customerSession->getCustomerId();
        }

        if ($customerId === 0) {
            throw new LocalizedException(__('Cliente não autenticado.'));
        }

        if (empty(trim($name))) {
            throw new LocalizedException(__('O nome da lista é obrigatório.'));
        }

        $list = $this->listFactory->create();
        $list->setData([
            'customer_id' => $customerId,
            'name' => $name,
            'description' => $description,
            'is_active' => 1,
            'is_recurring' => 0,
            'recurring_interval' => null
        ]);

        $this->listResource->save($list);

        return $list;
    }

    /**
     * Get list by ID
     *
     * @param int $listId
     * @param int|null $customerId
     * @return ShoppingList
     * @throws NoSuchEntityException
     */
    public function getList(int $listId, ?int $customerId = null): ShoppingList
    {
        $list = $this->listFactory->create();
        $this->listResource->load($list, $listId);

        if ($list->getId() === null) {
            throw new NoSuchEntityException(__('Lista não encontrada.'));
        }

        // Verify ownership
        if ($customerId === null) {
            $customerId = (int)$this->customerSession->getCustomerId();
        }

        if ((int)$list->getCustomerId() !== $customerId) {
            throw new NoSuchEntityException(__('Lista não encontrada.'));
        }

        return $list;
    }

    /**
     * Update shopping list
     *
     * @param int $listId
     * @param array $data
     * @return ShoppingList
     * @throws NoSuchEntityException
     */
    public function updateList(int $listId, array $data): ShoppingList
    {
        $list = $this->getList($listId);

        $allowedFields = ['name', 'description', 'is_active', 'is_recurring', 'recurring_interval'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $list->setData($field, $data[$field]);
            }
        }

        $this->listResource->save($list);

        return $list;
    }

    /**
     * Delete shopping list
     *
     * @param int $listId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function deleteList(int $listId): bool
    {
        $list = $this->getList($listId);

        // Delete all items first
        $items = $this->itemCollectionFactory->create()->addListFilter($listId);
        foreach ($items as $item) {
            $this->itemResource->delete($item);
        }

        $this->listResource->delete($list);

        return true;
    }

    /**
     * Add item to list
     *
     * @param int $listId
     * @param int $productId
     * @param float $qty
     * @param array $options
     * @return ShoppingListItem
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function addItem(int $listId, int $productId, float $qty = 1, array $options = []): ShoppingListItem
    {
        // Verify list exists and belongs to customer
        $list = $this->getList($listId);

        // Verify product exists
        try {
            $product = $this->productRepository->getById($productId);
        } catch (\Exception $e) {
            throw new NoSuchEntityException(__('Produto não encontrado.'));
        }

        if ($qty <= 0) {
            $qty = 1;
        }

        // Check if item already exists
        $existingItem = $this->itemCollectionFactory->create()
            ->addListFilter($listId)
            ->addFieldToFilter('product_id', $productId)
            ->getFirstItem();

        if ($existingItem->getId() !== null) {
            // Update quantity
            $newQty = (float)$existingItem->getQty() + $qty;
            $existingItem->setQty($newQty);
            $this->itemResource->save($existingItem);
            return $existingItem;
        }

        // Create new item
        $item = $this->itemFactory->create();
        $item->setData([
            'list_id' => $listId,
            'product_id' => $productId,
            'sku' => $product->getSku(),
            'qty' => $qty,
            'options' => count($options) > 0 ? json_encode($options) : null
        ]);

        $this->itemResource->save($item);

        // Update list timestamp
        $list->setData('updated_at', date('Y-m-d H:i:s'));
        $this->listResource->save($list);

        return $item;
    }

    /**
     * Update item quantity
     *
     * @param int $itemId
     * @param float $qty
     * @return ShoppingListItem
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function updateItem(int $itemId, float $qty): ShoppingListItem
    {
        $item = $this->itemFactory->create();
        $this->itemResource->load($item, $itemId);

        if ($item->getId() === null) {
            throw new NoSuchEntityException(__('Item não encontrado.'));
        }

        // Verify list belongs to customer
        $list = $this->getList((int)$item->getListId());

        if ($qty <= 0) {
            // Remove item if qty is 0 or less
            $this->itemResource->delete($item);
            return $item;
        }

        $item->setQty($qty);
        $this->itemResource->save($item);

        // Update list timestamp
        $list->setData('updated_at', date('Y-m-d H:i:s'));
        $this->listResource->save($list);

        return $item;
    }

    /**
     * Remove item from list
     *
     * @param int $itemId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function removeItem(int $itemId): bool
    {
        $item = $this->itemFactory->create();
        $this->itemResource->load($item, $itemId);

        if ($item->getId() === null) {
            throw new NoSuchEntityException(__('Item não encontrado.'));
        }

        // Verify list belongs to customer
        $list = $this->getList((int)$item->getListId());

        $this->itemResource->delete($item);

        // Update list timestamp
        $list->setData('updated_at', date('Y-m-d H:i:s'));
        $this->listResource->save($list);

        return true;
    }

    /**
     * Add all items from list to cart
     *
     * @param int $listId
     * @return array ['added' => int, 'failed' => array]
     * @throws NoSuchEntityException
     */
    public function addToCart(int $listId): array
    {
        $list = $this->getList($listId);
        $items = $this->itemCollectionFactory->create()->addListFilter($listId);

        $added = 0;
        $failed = [];

        $productIds = [];
        foreach ($items as $item) {
            $productIds[] = (int)$item->getProductId();
        }
        $products = $this->getProductsByIds($productIds);

        foreach ($items as $item) {
            try {
                $productId = (int)$item->getProductId();
                if (!isset($products[$productId])) {
                    throw new NoSuchEntityException(__('Produto não encontrado.'));
                }
                $product = $products[$productId];

                $options = $item->getOptions();
                $request = new \Magento\Framework\DataObject([
                    'qty' => $item->getQty()
                ]);

                if ($options !== null && $options !== '') {
                    $decodedOptions = json_decode($options, true);
                    if (is_array($decodedOptions)) {
                        $request->addData($decodedOptions);
                    }
                }

                $this->cart->addProduct($product, $request);
                $added++;
            } catch (\Exception $e) {
                $failed[] = [
                    'sku' => $item->getSku(),
                    'error' => $e->getMessage()
                ];
            }
        }

        if ($added > 0) {
            $this->cart->save();
        }

        return [
            'added' => $added,
            'failed' => $failed
        ];
    }

    /**
     * Create list from current cart
     *
     * @param string $name
     * @param string $description
     * @return ShoppingList
     * @throws LocalizedException
     */
    public function createFromCart(string $name, string $description = ''): ShoppingList
    {
        $quote = $this->cart->getQuote();
        $items = $quote->getAllVisibleItems();

        if (count($items) === 0) {
            throw new LocalizedException(__('O carrinho está vazio.'));
        }

        $list = $this->createList($name, $description);

        foreach ($items as $quoteItem) {
            $this->addItem(
                (int)$list->getId(),
                (int)$quoteItem->getProductId(),
                (float)$quoteItem->getQty()
            );
        }

        return $list;
    }

    /**
     * Duplicate a list
     *
     * @param int $listId
     * @param string|null $newName
     * @return ShoppingList
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function duplicateList(int $listId, ?string $newName = null): ShoppingList
    {
        $originalList = $this->getList($listId);

        if ($newName === null) {
            $newName = $originalList->getName() . ' (Cópia)';
        }

        $newList = $this->createList($newName, $originalList->getDescription());
        $newList->setData('is_recurring', $originalList->getIsRecurring());
        $newList->setData('recurring_interval', $originalList->getRecurringInterval());
        $this->listResource->save($newList);

        // Copy items
        $items = $this->itemCollectionFactory->create()->addListFilter($listId);
        foreach ($items as $item) {
            $this->addItem(
                (int)$newList->getId(),
                (int)$item->getProductId(),
                (float)$item->getQty()
            );
        }

        return $newList;
    }

    /**
     * Set list as recurring
     *
     * @param int $listId
     * @param int $intervalDays
     * @return ShoppingList
     * @throws NoSuchEntityException
     */
    public function setRecurring(int $listId, int $intervalDays): ShoppingList
    {
        $list = $this->getList($listId);

        if ($intervalDays < 1) {
            $intervalDays = 30; // Default to monthly
        }

        $list->setData('is_recurring', 1);
        $list->setData('recurring_interval', $intervalDays);
        $list->setData('next_order_date', date('Y-m-d', strtotime("+{$intervalDays} days")));

        $this->listResource->save($list);

        return $list;
    }

    /**
     * Disable recurring for list
     *
     * @param int $listId
     * @return ShoppingList
     * @throws NoSuchEntityException
     */
    public function disableRecurring(int $listId): ShoppingList
    {
        $list = $this->getList($listId);

        $list->setData('is_recurring', 0);
        $list->setData('recurring_interval', null);
        $list->setData('next_order_date', null);

        $this->listResource->save($list);

        return $list;
    }

    /**
     * Get recurring lists due for order
     *
     * @return ResourceModel\ShoppingList\Collection
     */
    public function getRecurringListsDue(): ResourceModel\ShoppingList\Collection
    {
        return $this->listCollectionFactory->create()
            ->addFieldToFilter('is_recurring', 1)
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('next_order_date', ['lteq' => date('Y-m-d')]);
    }

    /**
     * Get list items
     *
     * @param int $listId
     * @return ResourceModel\ShoppingListItem\Collection
     * @throws NoSuchEntityException
     */
    public function getListItems(int $listId): ResourceModel\ShoppingListItem\Collection
    {
        // Verify list exists and belongs to customer
        $this->getList($listId);

        return $this->itemCollectionFactory->create()->addListFilter($listId);
    }

    /**
     * Calculate list total
     *
     * @param int $listId
     * @return float
     * @throws NoSuchEntityException
     */
    public function getListTotal(int $listId): float
    {
        $items = $this->getListItems($listId);
        $total = 0;

        $productIds = [];
        foreach ($items as $item) {
            $productIds[] = (int)$item->getProductId();
        }
        $products = $this->getProductsByIds($productIds);

        foreach ($items as $item) {
            $product = $products[(int)$item->getProductId()] ?? null;
            if ($product === null) {
                // Product not found, skip
                continue;
            }
            $total += $product->getFinalPrice() * $item->getQty();
        }

        return $total;
    }
}
