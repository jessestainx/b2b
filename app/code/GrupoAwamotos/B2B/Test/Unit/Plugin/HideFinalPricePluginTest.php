<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Plugin;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use GrupoAwamotos\B2B\Plugin\HideFinalPricePlugin;
use Magento\Catalog\Pricing\Render\FinalPriceBox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HideFinalPricePluginTest extends TestCase
{
    private PriceVisibilityInterface&MockObject $priceVisibility;

    protected function setUp(): void
    {
        $this->priceVisibility = $this->createMock(PriceVisibilityInterface::class);
    }

    public function testAroundToHtmlReturnsProceededHtmlWhenPriceIsVisible(): void
    {
        $plugin = new HideFinalPricePlugin($this->priceVisibility);
        $subject = $this->createMock(FinalPriceBox::class);

        $this->priceVisibility->method('canViewPrices')->willReturn(true);

        $result = $plugin->aroundToHtml($subject, static fn (): string => '<span class="price">R$ 10,00</span>');

        $this->assertSame('<span class="price">R$ 10,00</span>', $result);
    }

    public function testAroundToHtmlReturnsReplacementWhenPriceIsHidden(): void
    {
        $plugin = new HideFinalPricePlugin($this->priceVisibility);
        $subject = $this->createMock(FinalPriceBox::class);

        $this->priceVisibility->method('canViewPrices')->willReturn(false);
        $this->priceVisibility->method('getPriceReplacementMessage')->willReturn('Faça login para ver os preços');

        $result = $plugin->aroundToHtml($subject, static fn (): string => '<span class="price">R$ 10,00</span>');

        $this->assertStringContainsString('b2b-login-to-see-price', $result);
        $this->assertStringContainsString('Faça login para ver os preços', $result);
    }

    public function testAroundToHtmlFailsClosedOnVisibilityException(): void
    {
        $plugin = new HideFinalPricePlugin($this->priceVisibility);
        $subject = $this->createMock(FinalPriceBox::class);

        $this->priceVisibility->method('canViewPrices')
            ->willThrowException(new \RuntimeException('Erro de sessão'));
        $this->priceVisibility->method('getPriceReplacementMessage')
            ->willReturn('Entre para ver os preços');

        $proceedCalled = 0;
        $result = $plugin->aroundToHtml(
            $subject,
            static function () use (&$proceedCalled): string {
                $proceedCalled++;
                return '<span class="price">R$ 10,00</span>';
            }
        );

        $this->assertSame(0, $proceedCalled);
        $this->assertStringContainsString('b2b-login-to-see-price', $result);
        $this->assertStringNotContainsString('R$ 10,00', $result);
    }
}
