<?php

declare(strict_types=1);

namespace Awa\CatalogFix\Block;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Search\Model\QueryFactory;
use Mirasvit\SearchLanding\Api\Data\PageInterface;

/**
 * Presentation data for search-landing redirect hint banner.
 */
class SearchHint implements ArgumentInterface
{
    public function __construct(
        private readonly Registry $registry,
        private readonly RequestInterface $request,
        private readonly QueryFactory $queryFactory,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function shouldRender(): bool
    {
        $page = $this->getLandingPage();
        if ($page === null) {
            return false;
        }

        return $this->getQuery() !== '';
    }

    public function getQuery(): string
    {
        $fromRequest = trim((string) $this->request->getParam(QueryFactory::QUERY_VAR_NAME, ''));
        if ($fromRequest !== '') {
            return $this->sanitizeQuery($fromRequest);
        }

        $fromFactory = trim((string) $this->queryFactory->get()->getQueryText());
        if ($fromFactory !== '') {
            return $this->sanitizeQuery($fromFactory);
        }

        $page = $this->getLandingPage();
        if ($page !== null && method_exists($page, 'getQueryText')) {
            $parts = preg_split('~\s*,\s*~', (string) $page->getQueryText()) ?: [];
            $first = trim((string) ($parts[0] ?? ''));

            return $this->sanitizeQuery($first);
        }

        return '';
    }

    public function getLandingLabel(): string
    {
        $page = $this->getLandingPage();
        if ($page === null) {
            return '';
        }

        if (method_exists($page, 'getTitle')) {
            $title = trim((string) $page->getTitle());
            if ($title !== '') {
                return $title;
            }
        }

        if (method_exists($page, 'getUrlKey')) {
            return trim((string) $page->getUrlKey());
        }

        return '';
    }

    public function getExactUrl(): string
    {
        $query = $this->getQuery();
        if ($query === '') {
            return '';
        }

        return $this->urlBuilder->getUrl(
            'catalogsearch/result',
            [
                '_query' => [
                    QueryFactory::QUERY_VAR_NAME => $query,
                    'exact' => '1',
                ],
            ]
        );
    }

    /**
     * Resolve Mirasvit landing from registry, tolerating Mirasvit uninstalled.
     */
    private function getLandingPage(): ?object
    {
        if (!interface_exists(PageInterface::class)) {
            return null;
        }

        $page = $this->registry->registry('search_landing_page');

        return $page instanceof PageInterface ? $page : null;
    }

    private function sanitizeQuery(string $query): string
    {
        $clean = strip_tags($query);
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? '';

        return trim($clean);
    }
}
