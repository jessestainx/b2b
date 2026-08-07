<?php

declare(strict_types=1);

namespace Awa\CatalogFix\Test\Unit\Plugin\Mirasvit;

use Awa\CatalogFix\Plugin\Mirasvit\MisspellSkipExactPlugin;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Mirasvit\Misspell\Observer\OnCatalogSearchObserver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MisspellSkipExactPluginTest extends TestCase
{
    private RequestInterface&MockObject $request;
    private OnCatalogSearchObserver&MockObject $subject;
    private EventObserver&MockObject $observer;

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->subject = $this->createMock(OnCatalogSearchObserver::class);
        $this->observer = $this->createMock(EventObserver::class);
    }

    private function createSut(): MisspellSkipExactPlugin
    {
        return new MisspellSkipExactPlugin($this->request);
    }

    public function testExactStringOneSkipsMisspell(): void
    {
        $this->request->method('getParam')->with('exact')->willReturn('1');
        $called = false;

        $this->createSut()->aroundExecute(
            $this->subject,
            function () use (&$called): void {
                $called = true;
            },
            $this->observer
        );

        $this->assertFalse($called);
    }

    public function testWithoutExactProceeds(): void
    {
        $this->request->method('getParam')->with('exact')->willReturn(null);
        $called = false;

        $this->createSut()->aroundExecute(
            $this->subject,
            function () use (&$called): void {
                $called = true;
            },
            $this->observer
        );

        $this->assertTrue($called);
    }

    public function testExactIntOneSkipsMisspell(): void
    {
        $this->request->method('getParam')->with('exact')->willReturn(1);
        $called = false;

        $this->createSut()->aroundExecute(
            $this->subject,
            function () use (&$called): void {
                $called = true;
            },
            $this->observer
        );

        $this->assertFalse($called);
    }
}
