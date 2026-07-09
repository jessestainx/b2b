<?php

declare(strict_types=1);

namespace Meta\Conversion\Observer;

use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Meta\BusinessExtension\Api\SystemConfigInterface;
use Meta\Conversion\Model\CapiEventDispatcher;
use Meta\Conversion\Helper\B2BSignalBuilder;
use Meta\Conversion\Helper\UserDataBuilder;
use Psr\Log\LoggerInterface;

/**
 * Sends a Search event to Meta CAPI when a customer performs a catalog search.
 *
 * Triggered by `controller_action_postdispatch_catalogsearch_result_index`.
 * Uses postdispatch so the search has been executed and results are available.
 */
class Search implements ObserverInterface
{
    public function __construct(
        private readonly SystemConfigInterface $config,
        private readonly CapiEventDispatcher $capiDispatcher,
        private readonly LoggerInterface $logger,
        private readonly RequestInterface $request,
        private readonly B2BHelper $b2bHelper,
        private readonly B2BSignalBuilder $b2bSignalBuilder,
        private readonly UserDataBuilder $userDataBuilder
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            $searchQuery = trim((string) $this->request->getParam('q'));
            if ($searchQuery === '') {
                return;
            }

            $storeId = null;
            $controller = $observer->getEvent()->getData('controller_action');
            if ($controller !== null && method_exists($controller, 'getRequest')) {
                $controllerRequest = $controller->getRequest();
                if (method_exists($controllerRequest, 'getParam')) {
                    $storeId = $controllerRequest->getParam('store');
                    $storeId = $storeId !== null ? (int) $storeId : null;
                }
            }

            if (!$this->config->isActive($storeId)) {
                return;
            }

            $pixelId = $this->config->getPixelId($storeId);
            if ($pixelId === null) {
                return;
            }

            $userData = $this->userDataBuilder->build();
            $eventSourceUrl = $this->userDataBuilder->getEventSourceUrl();

            $customData = [
                'search_string' => mb_substr($searchQuery, 0, 200),
            ];

            // B2B enrichment
            if ($this->b2bHelper->isB2BCustomer()) {
                $customData = array_merge($customData, $this->b2bSignalBuilder->build([
                    'lead_type' => 'b2b_search',
                    'register_channel' => 'website',
                ]));
                $customData['funnel_stage'] = 'discovery';
            }

            $capiEvent = [
                'event_name' => 'Search',
                'event_time' => time(),
                'event_id' => sprintf('search-%s-%d', hash('xxh3', $searchQuery), time()),
                'action_source' => 'website',
                'user_data' => $userData,
                'custom_data' => $customData,
            ];

            if ($eventSourceUrl !== null) {
                $capiEvent['event_source_url'] = $eventSourceUrl;
            }

            $this->capiDispatcher->sendEvents($pixelId, [$capiEvent], $storeId, 'Search');
        } catch (\Throwable $e) {
            $this->logger->error('[Meta CAPI] Search event failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
