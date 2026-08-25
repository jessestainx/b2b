<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Plugin;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use GrupoAwamotos\B2B\Plugin\HidePricePlugin;
use Magento\Catalog\Block\Product\AbstractProduct;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HidePricePluginTest extends TestCase
{
    private PriceVisibilityInterface&MockObject $priceVisibility;

    protected function setUp(): void
    {
        $this->priceVisibility = $this->createMock(PriceVisibilityInterface::class);
    }

    public function testAfterGetProductPriceReturnsOriginalWhenPriceIsVisible(): void
    {
        $plugin = new HidePricePlugin($this->priceVisibility);
        $subject = $this->createMock(AbstractProduct::class);

        $this->priceVisibility->method('canViewPrices')->willReturn(true);

        $result = $plugin->afterGetProductPrice($subject, '<span class="price">R$ 10,00</span>');

        $this->assertSame('<span class="price">R$ 10,00</span>', $result);
    }

    public function testAfterGetProductPriceReturnsReplacementWhenPriceIsHidden(): void
    {
        $plugin = new HidePricePlugin($this->priceVisibility);
        $subject = $this->createMock(AbstractProduct::class);

        $this->priceVisibility->method('canViewPrices')->willReturn(false);
        $this->priceVisibility->method('getPriceReplacementMessage')->willReturn('Faça login para ver os preços');

        $result = $plugin->afterGetProductPrice($subject, '<span class="price">R$ 10,00</span>');

        $this->assertStringContainsString('b2b-login-to-see-price', $result);
        $this->assertStringContainsString('Faça login para ver os preços', $result);
    }

    public function testAfterGetProductPriceFailsClosedOnVisibilityException(): void
    {
        $plugin = new HidePricePlugin($this->priceVisibility);
        $subject = $this->createMock(AbstractProduct::class);

        $this->priceVisibility->method('canViewPrices')
            ->willThrowException(new \RuntimeException('Erro de sessão'));
        $this->priceVisibility->method('getPriceReplacementMessage')
            ->willReturn('Entre para ver os preços');

        $result = $plugin->afterGetProductPrice($subject, '<span class="price">R$ 10,00</span>');

        $this->assertStringContainsString('b2b-login-to-see-price', $result);
        $this->assertStringNotContainsString('R$ 10,00', $result);
    }
}
