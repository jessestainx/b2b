<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Model;

use GrupoAwamotos\WhatsAppCommerce\Api\CartInterface;
use GrupoAwamotos\WhatsAppCommerce\Helper\Config;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartItemInterfaceFactory;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Model\GuestCart\GuestCartRepository;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class WhatsAppCart implements CartInterface
{
    private const CACHE_PREFIX = 'wac_phone_cart_';
    private const CACHE_TTL = 86400; // 24h

    public function __construct(
        private readonly GuestCartManagementInterface $guestCartManagement,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CartItemInterfaceFactory $cartItemFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly CacheInterface $cache,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function createCart(string $phone): array
    {
        $phone = $this->normalizePhone($phone);

        try {
            // Check if phone already has an active cart
            $maskedId = $this->getCartIdForPhone($phone);

            if ($maskedId !== null) {
                $quoteId = $this->maskedQuoteIdToQuoteId->execute($maskedId);
                $quote = $this->cartRepository->get($quoteId);

                if ($quote->getIsActive()) {
                    return [
                        'cart_id' => $maskedId,
                        'items_count' => (int) $quote->getItemsCount(),
                        'message' => 'Carrinho existente recuperado',
                    ];
                }
            }

            // Create new guest cart
            $maskedId = $this->guestCartManagement->createEmptyCart();
            $this->saveCartIdForPhone($phone, $maskedId);

            return [
                'cart_id' => $maskedId,
                'items_count' => 0,
                'message' => 'Carrinho criado',
            ];
        } catch (\Exception $e) {
            $this->logger->error('WhatsAppCart::createCart error', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return ['error' => 'Erro ao criar carrinho'];
        }
    }

    /**
     * @inheritDoc
     */
    public function addItem(string $phone, string $sku, int $qty = 1): array
    {
        $phone = $this->normalizePhone($phone);

        try {
            $maskedId = $this->getCartIdForPhone($phone);

            if ($maskedId === null) {
                $result = $this->createCart($phone);
                if (isset($result['error'])) {
                    return $result;
                }
                $maskedId = $result['cart_id'];
            }

            $quoteId = $this->maskedQuoteIdToQuoteId->execute($maskedId);
            $quote = $this->cartRepository->get($quoteId);
            $product = $this->productRepository->get($sku);

            $quote->addProduct($product, $qty);
            $this->cartRepository->save($quote);

            return $this->formatCartSummary($quote, $maskedId);
        } catch (\Exception $e) {
            $this->logger->error('WhatsAppCart::addItem error', [
                'phone' => $phone,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
            return ['error' => 'Erro ao adicionar item: ' . $e->getMessage()];
        }
    }

    /**
     * @inheritDoc
     */
    public function viewCart(string $phone): array
    {
        $phone = $this->normalizePhone($phone);

        try {
            $maskedId = $this->getCartIdForPhone($phone);

            if ($maskedId === null) {
                return ['items' => [], 'subtotal' => 0, 'message' => 'Carrinho vazio'];
            }

            $quoteId = $this->maskedQuoteIdToQuoteId->execute($maskedId);
            $quote = $this->cartRepository->get($quoteId);

            if (!$quote->getIsActive() || $quote->getItemsCount() === 0) {
                return ['items' => [], 'subtotal' => 0, 'message' => 'Carrinho vazio'];
            }

            return $this->formatCartSummary($quote, $maskedId);
        } catch (\Exception $e) {
            $this->logger->error('WhatsAppCart::viewCart error', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return ['items' => [], 'subtotal' => 0, 'error' => 'Erro ao recuperar carrinho'];
        }
    }

    /**
     * @inheritDoc
     */
    public function getCheckoutLink(string $phone): array
    {
        $phone = $this->normalizePhone($phone);

        try {
            $maskedId = $this->getCartIdForPhone($phone);

            if ($maskedId === null) {
                return ['error' => 'Nenhum carrinho encontrado para este telefone'];
            }

            $baseUrl = $this->config->getCheckoutBaseUrl();
            if ($baseUrl === '') {
                $baseUrl = $this->storeManager->getStore()->getBaseUrl();
            }

            $checkoutUrl = rtrim($baseUrl, '/') . '/checkout/cart/index?cart_id=' . $maskedId;

            return [
                'checkout_url' => $checkoutUrl,
                'message' => 'Clique para finalizar sua compra',
            ];
        } catch (\Exception $e) {
            $this->logger->error('WhatsAppCart::getCheckoutLink error', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return ['error' => 'Erro ao gerar link de checkout'];
        }
    }

    /**
     * Get cached cart masked ID for a phone
     */
    private function getCartIdForPhone(string $phone): ?string
    {
        $cached = $this->cache->load(self::CACHE_PREFIX . $phone);
        return $cached !== false ? (string) $cached : null;
    }

    /**
     * Store cart masked ID for a phone
     */
    private function saveCartIdForPhone(string $phone, string $maskedId): void
    {
        $this->cache->save($maskedId, self::CACHE_PREFIX . $phone, [], self::CACHE_TTL);
    }

    /**
     * Normalize phone to digits only
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) > 11 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    /**
     * Format cart summary for WhatsApp response
     */
    private function formatCartSummary($quote, string $maskedId): array
    {
        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $items[] = [
                'name' => $item->getName(),
                'sku' => $item->getSku(),
                'qty' => (int) $item->getQty(),
                'price' => (float) $item->getPrice(),
                'price_formatted' => 'R$ ' . number_format((float) $item->getPrice(), 2, ',', '.'),
                'row_total' => (float) $item->getRowTotal(),
                'row_total_formatted' => 'R$ ' . number_format((float) $item->getRowTotal(), 2, ',', '.'),
            ];
        }

        $subtotal = (float) $quote->getSubtotal();

        return [
            'cart_id' => $maskedId,
            'items' => $items,
            'items_count' => (int) $quote->getItemsCount(),
            'subtotal' => $subtotal,
            'subtotal_formatted' => 'R$ ' . number_format($subtotal, 2, ',', '.'),
        ];
    }
}
