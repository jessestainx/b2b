<?php

/**
 * Shopping List View Block
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Customer\ShoppingList;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use GrupoAwamotos\B2B\Model\ShoppingListService;
use GrupoAwamotos\B2B\Model\ShoppingList;

class ViewBlock extends Template
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ShoppingListService
     */
    private $shoppingListService;
    private TimezoneInterface $timezone;

    /**
     * @var ShoppingList|null
     */
    private $currentList;

    /**
     * @param Context $context
     * @param RequestInterface $request
     * @param ShoppingListService $shoppingListService
     * @param array $data
     */
    public function __construct(
        Context $context,
        RequestInterface $request,
        ShoppingListService $shoppingListService,
        array $data = [],
        ?TimezoneInterface $timezone = null
    ) {
        parent::__construct($context, $data);
        $this->request = $request;
        $this->shoppingListService = $shoppingListService;
        $this->timezone = $timezone ?? $context->getLocaleDate();
    }

    /**
     * Get current list
     *
     * @return ShoppingList|null
     */
    public function getCurrentList(): ?ShoppingList
    {
        if ($this->currentList === null) {
            $listId = (int)$this->request->getParam('id');
            if ($listId) {
                try {
                    $this->currentList = $this->shoppingListService->getList($listId);
                } catch (\Exception $e) {
                    $this->currentList = null;
                }
            }
        }
        return $this->currentList;
    }

    /**
     * Get back URL
     *
     * @return string
     */
    public function getBackUrl(): string
    {
        return $this->getUrl('b2b/shoppinglist');
    }

    /**
     * Get add to cart URL
     *
     * @return string
     */
    public function getAddToCartUrl(): string
    {
        $list = $this->getCurrentList();
        return $list ? $this->getUrl('b2b/shoppinglist/addtocart', ['id' => $list->getId()]) : '#';
    }

    /**
     * Get remove item URL
     *
     * @param int $itemId
     * @return string
     */
    public function getRemoveItemUrl(int $itemId): string
    {
        $list = $this->getCurrentList();
        return $this->getUrl('b2b/shoppinglist/removeitem', [
            'item_id' => $itemId,
            'list_id' => $list ? $list->getId() : 0
        ]);
    }

    /**
     * Get update item URL
     *
     * @return string
     */
    public function getUpdateItemUrl(): string
    {
        return $this->getUrl('b2b/shoppinglist/updateitem');
    }

    /**
     * Get product URL
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     */
    public function getProductUrl($product): string
    {
        return $product->getProductUrl();
    }

    /**
     * Get set recurring URL
     */
    public function getSetRecurringUrl(): string
    {
        $list = $this->getCurrentList();
        return $list ? $this->getUrl('b2b/shoppinglist/setrecurring', ['id' => $list->getId()]) : '#';
    }

    /**
     * @param string|\DateTimeInterface|null $date
     * @param int $format
     * @param bool $showTime
     * @param string|null $timezone
     */
    public function formatDate(
        $date = null,
        $format = \IntlDateFormatter::SHORT,
        $showTime = false,
        $timezone = null
    ): string {
        if (func_num_args() <= 1) {
            if ($date === null || $date === '') {
                return '-';
            }

            $raw = $date instanceof \DateTimeInterface ? $date->format('c') : (string) $date;
            try {
                return $this->timezone->date(new \DateTime($raw))->format('d/m/Y');
            } catch (\Exception) {
                return substr($raw, 0, 10);
            }
        }

        return (string) parent::formatDate($date, $format, $showTime, $timezone);
    }
}
