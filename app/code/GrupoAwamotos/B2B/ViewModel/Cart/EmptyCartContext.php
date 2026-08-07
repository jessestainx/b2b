<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\ViewModel\Cart;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\Checkout\OrderSuccessPresenter;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Model\Session\SuccessValidator;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\PhpCookieManager;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

class EmptyCartContext implements ArgumentInterface
{
    public const SESSION_KEY_EXPRESS_REDIRECT = 'awa_express_checkout_redirect';

    public const COOKIE_KEY_EXPRESS_REDIRECT = 'awa_express_checkout_redirect';

    private const ORDER_SUCCESS_WINDOW_HOURS = 6;

    /**
     * @var list<array{label: \Magento\Framework\Phrase, url: string}>
     */
    private const FEATURED_CATEGORIES = [
        ['label' => 'Bauletos', 'url' => 'bauletos'],
        ['label' => 'Retrovisores', 'url' => 'retrovisores'],
        ['label' => 'Bagageiros', 'url' => 'bagageiros'],
        ['label' => 'Manetes', 'url' => 'manetes'],
    ];

    public function __construct(
        private readonly Config $config,
        private readonly UrlInterface $urlBuilder,
        private readonly CheckoutSession $checkoutSession,
        private readonly HttpRequest $request,
        private readonly PhpCookieManager $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly TimezoneInterface $timezone,
        private readonly SuccessValidator $successValidator,
        private readonly OrderSuccessPresenter $orderSuccessPresenter
    ) {
    }

    public function getCatalogUrl(): string
    {
        return $this->urlBuilder->getUrl('', ['_direct' => 'catalogo']);
    }

    public function getAllCategoriesUrl(): string
    {
        return $this->getCatalogUrl();
    }

    /**
     * @return list<array{label: \Magento\Framework\Phrase, url: string}>
     */
    public function getFeaturedCategories(): array
    {
        $categories = [];

        foreach (self::FEATURED_CATEGORIES as $category) {
            $categories[] = [
                'label' => __($category['label']),
                'url' => $this->urlBuilder->getUrl('', ['_direct' => $category['url']]),
            ];
        }

        return $categories;
    }

    public function shouldShowMinOrderHint(): bool
    {
        if ($this->isOrderSuccessState()) {
            return false;
        }

        return $this->config->isEnabled()
            && $this->config->isMinQtyEnabled()
            && $this->getMinOrderAmount() > 0;
    }

    public function getMinOrderAmount(): float
    {
        return $this->config->getMinOrderAmount();
    }

    public function getMinOrderAmountFormatted(): string
    {
        return 'R$ ' . number_format($this->getMinOrderAmount(), 2, ',', '.');
    }

    public function getMinOrderHint(): string
    {
        return (string) __(
            'Pedido mínimo B2B: %1. Adicione peças ao carrinho para seguir à finalização do pedido.',
            $this->getMinOrderAmountFormatted()
        );
    }

    public function shouldShowExpressCheckoutNotice(): bool
    {
        if ($this->isOrderSuccessState()) {
            return false;
        }

        if ($this->checkoutSession->getData(self::SESSION_KEY_EXPRESS_REDIRECT)) {
            return true;
        }

        return $this->request->getCookie(self::COOKIE_KEY_EXPRESS_REDIRECT) === '1';
    }

    public function consumeExpressCheckoutNotice(): bool
    {
        if (!$this->shouldShowExpressCheckoutNotice()) {
            return false;
        }

        $this->checkoutSession->unsetData(self::SESSION_KEY_EXPRESS_REDIRECT);
        $this->deleteExpressRedirectCookie();

        return true;
    }

    private function deleteExpressRedirectCookie(): void
    {
        try {
            $metadata = $this->cookieMetadataFactory->createCookieMetadata();
            $metadata->setPath('/');
            $this->cookieManager->deleteCookie(self::COOKIE_KEY_EXPRESS_REDIRECT, $metadata);
        } catch (\Exception) {
            // Cookie ausente ou headers já enviados — segue sem bloquear render.
        }
    }

    public function getExpressCheckoutNotice(): string
    {
        return (string) __(
            'Você veio da finalização expressa. Adicione peças ao carrinho para continuar.'
        );
    }

    public function getEmptyCartSubtitle(): string
    {
        if ($this->shouldShowMinOrderHint()) {
            return (string) __(
                'Explore o catálogo ou fale com nossa equipe comercial para montar seu pedido.'
            );
        }

        return (string) __(
            'Adicione peças ao carrinho para seguir à finalização do pedido B2B.'
        );
    }

    public function isOrderSuccessState(): bool
    {
        return $this->getOrderSuccessPresentation() !== null;
    }

    /**
     * Dados para a tela de confirmação pós-pedido no carrinho vazio.
     *
     * @return array{
     *     incrementId: string,
     *     orderUrl: string,
     *     formattedDate: string,
     *     grandTotalFormatted: string,
     *     itemsCount: int,
     *     paymentLabel: string,
     *     headline: string,
     *     subline: string,
     *     nextStep: string
     * }|null
     */
    public function getOrderSuccessPresentation(): ?array
    {
        if ($this->quoteHasItems()) {
            return null;
        }

        $order = $this->resolveSuccessOrder();
        if ($order === null) {
            return null;
        }

        return $this->orderSuccessPresenter->present($order);
    }

    /**
     * @deprecated Use getOrderSuccessPresentation() — mantido para compatibilidade interna.
     *
     * @return array{message: string, orderUrl: string, incrementId: string}|null
     */
    public function getRecentOrderContext(): ?array
    {
        $presentation = $this->getOrderSuccessPresentation();
        if ($presentation === null) {
            return null;
        }

        return [
            'message' => (string) __(
                'Seu pedido %1 foi registrado em %2.',
                $presentation['incrementId'],
                $presentation['formattedDate']
            ),
            'orderUrl' => $presentation['orderUrl'],
            'incrementId' => $presentation['incrementId'],
        ];
    }

    private function quoteHasItems(): bool
    {
        try {
            return $this->checkoutSession->getQuote()->hasItems();
        } catch (\Exception) {
            return false;
        }
    }

    private function resolveSuccessOrder(): ?Order
    {
        if ($this->successValidator->isValid()) {
            $sessionOrder = $this->checkoutSession->getLastRealOrder();
            if ($sessionOrder instanceof Order && $sessionOrder->getId()) {
                return $sessionOrder;
            }
        }

        $customerId = (int) $this->checkoutSession->getQuote()->getCustomerId();
        if ($customerId <= 0) {
            return null;
        }

        $since = $this->timezone->date()->modify(
            sprintf('-%d hours', self::ORDER_SUCCESS_WINDOW_HOURS)
        )->format('Y-m-d H:i:s');

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('created_at', ['gteq' => $since])
            ->setOrder('created_at', 'DESC')
            ->setPageSize(1);

        $order = $collection->getFirstItem();

        return $order->getId() ? $order : null;
    }
}
