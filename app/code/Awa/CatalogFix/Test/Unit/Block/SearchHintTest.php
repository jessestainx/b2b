<?php

declare(strict_types=1);

namespace Awa\CatalogFix\Test\Unit\Block;

use Awa\CatalogFix\Block\SearchHint;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Search\Model\QueryFactory;
use Mirasvit\SearchLanding\Api\Data\PageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SearchHintTest extends TestCase
{
    private Registry&MockObject $registry;
    private RequestInterface&MockObject $request;
    private QueryFactory&MockObject $queryFactory;
    private UrlInterface&MockObject $urlBuilder;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(Registry::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->queryFactory = $this->createMock(QueryFactory::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
    }

    private function createSut(): SearchHint
    {
        return new SearchHint(
            $this->registry,
            $this->request,
            $this->queryFactory,
            $this->urlBuilder
        );
    }

    public function testShouldRenderTrueWhenLandingAndQueryExist(): void
    {
        $page = $this->createMock(PageInterface::class);
        $page->method('getQueryText')->willReturn('capacete');

        $this->registry->method('registry')
            ->with('search_landing_page')
            ->willReturn($page);

        $this->request->method('getParam')
            ->with(QueryFactory::QUERY_VAR_NAME, '')
            ->willReturn('capacete');

        $this->assertTrue($this->createSut()->shouldRender());
    }

    public function testShouldRenderFalseWithoutLandingPage(): void
    {
        $this->registry->method('registry')
            ->with('search_landing_page')
            ->willReturn(null);

        $this->request->method('getParam')->willReturn('capacete');

        $this->assertFalse($this->createSut()->shouldRender());
    }

    public function testGetQuerySanitized(): void
    {
        $this->registry->method('registry')->willReturn(null);
        $this->request->method('getParam')
            ->with(QueryFactory::QUERY_VAR_NAME, '')
            ->willReturn("  <b>capacete</b>\n  ");

        $this->assertSame('capacete', $this->createSut()->getQuery());
    }

    public function testGetExactUrlIncludesExactParam(): void
    {
        $this->registry->method('registry')->willReturn(null);
        $this->request->method('getParam')
            ->with(QueryFactory::QUERY_VAR_NAME, '')
            ->willReturn('capacete');

        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with(
                'catalogsearch/result',
                [
                    '_query' => [
                        QueryFactory::QUERY_VAR_NAME => 'capacete',
                        'exact' => '1',
                    ],
                ]
            )
            ->willReturn('https://awamotos.local/catalogsearch/result/?q=capacete&exact=1');

        $this->assertSame(
            'https://awamotos.local/catalogsearch/result/?q=capacete&exact=1',
            $this->createSut()->getExactUrl()
        );
    }
}
