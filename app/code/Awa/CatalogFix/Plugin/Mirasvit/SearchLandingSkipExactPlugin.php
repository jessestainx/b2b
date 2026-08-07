<?php

declare(strict_types=1);

namespace Awa\CatalogFix\Plugin\Mirasvit;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Mirasvit\SearchLanding\Observer\OnCatalogSearch;

/**
 * Skip Mirasvit SearchLanding 302 when exact=1 is present.
 *
 * Coupled to Mirasvit_SearchLanding — disable Awa_CatalogFix if Mirasvit is removed.
 */
class SearchLandingSkipExactPlugin
{
    public function __construct(
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @param OnCatalogSearch $subject
     * @param callable $proceed
     * @param EventObserver $observer
     * @return bool
     */
    public function aroundExecute(
        OnCatalogSearch $subject,
        callable $proceed,
        EventObserver $observer
    ): bool {
        $exact = $this->request->getParam('exact');

        if ($exact === 1 || $exact === '1') {
            return false;
        }

        return (bool) $proceed($observer);
    }
}
