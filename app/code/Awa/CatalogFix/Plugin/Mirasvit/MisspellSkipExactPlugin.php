<?php

declare(strict_types=1);

namespace Awa\CatalogFix\Plugin\Mirasvit;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Mirasvit\Misspell\Observer\OnCatalogSearchObserver;

/**
 * Skip Mirasvit Misspell/fallback 302 when exact=1 is present.
 *
 * After SearchLanding is bypassed, Misspell may still rewrite
 * e.g. capacete → cavalete. exact=1 must stay on the original query.
 */
class MisspellSkipExactPlugin
{
    public function __construct(
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @param OnCatalogSearchObserver $subject
     * @param callable $proceed
     * @param EventObserver $observer
     */
    public function aroundExecute(
        OnCatalogSearchObserver $subject,
        callable $proceed,
        EventObserver $observer
    ): void {
        $exact = $this->request->getParam('exact');

        if ($exact === 1 || $exact === '1') {
            return;
        }

        $proceed($observer);
    }
}
