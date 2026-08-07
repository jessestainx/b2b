<?php

declare(strict_types=1);

namespace Awa\CatalogFix\Test\Unit\Plugin\Mirasvit;

use Awa\CatalogFix\Plugin\Mirasvit\SearchLandingSkipExactPlugin;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Mirasvit\SearchLanding\Observer\OnCatalogSearch;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SearchLandingSkipExactPluginTest extends TestCase
{
    private RequestInterface&MockObject $request;
    private OnCatalogSearch&MockObject $subject;
    private EventObserver&MockObject $observer;

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->subject = $this->createMock(OnCatalogSearch::class);
        $this->observer = $this->createMock(EventObserver::class);
    }

    private function createSut(): SearchLandingSkipExactPlugin
    {
        return new SearchLandingSkipExactPlugin($this->request);
    }

    public function testExactIntOneSkipsRedirect(): void
    {
        $this->request->method('getParam')->with('exact')->willReturn(1);
        $called = false;

        $result = $this->createSut()->aroundExecute(
            $this->subject,
            function () use (&$called): bool {
                $called = true;
                return true;
            },
            $this->observer
        );

        $this->assertFalse($result);
        $this->assertFalse($called);
    }

    public function testWithoutExactProceeds(): void
    {
        $this->request->method('getParam')->with('exact')->willReturn(null);

        $result = $this->createSut()->aroundExecute(
            $this->subject,
            static fn (): bool => true,
            $this->observer
        );

        $this->assertTrue($result);
    }

    public function testExactStringOneSkipsRedirect(): void
    {
        $this->request->method('getParam')->with('exact')->willReturn('1');
        $called = false;

        $result = $this->createSut()->aroundExecute(
            $this->subject,
            function () use (&$called): bool {
                $called = true;
                return true;
            },
            $this->observer
        );

        $this->assertFalse($result);
        $this->assertFalse($called);
    }
}
