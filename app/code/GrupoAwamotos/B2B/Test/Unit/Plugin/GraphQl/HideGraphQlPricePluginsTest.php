<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Plugin\GraphQl;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use GrupoAwamotos\B2B\Plugin\GraphQl\HidePriceRangeDataPlugin;
use GrupoAwamotos\B2B\Plugin\GraphQl\HideSpecialPricePlugin;
use Magento\CatalogGraphQl\Model\PriceRangeDataProvider;
use Magento\CatalogGraphQl\Model\Resolver\Product\SpecialPrice;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HideGraphQlPricePluginsTest extends TestCase
{
    private PriceVisibilityInterface&MockObject $priceVisibility;

    protected function setUp(): void
    {
        $this->priceVisibility = $this->createMock(PriceVisibilityInterface::class);
    }

    public function testPriceRangeRemainsWhenVisible(): void
    {
        $this->priceVisibility->method('canViewPrices')->willReturn(true);
        $plugin = new HidePriceRangeDataPlugin($this->priceVisibility);
        $original = ['minimum_price' => ['final_price' => ['value' => 22.99, 'currency' => 'BRL']]];

        $result = $plugin->afterPrepare($this->createMock(PriceRangeDataProvider::class), $original);

        $this->assertSame($original, $result);
    }

    public function testPriceRangeIsRedactedWhenHidden(): void
    {
        $this->priceVisibility->method('canViewPrices')->willReturn(false);
        $plugin = new HidePriceRangeDataPlugin($this->priceVisibility);
        $original = ['minimum_price' => ['final_price' => ['value' => 22.99, 'currency' => 'BRL']]];

        $result = $plugin->afterPrepare($this->createMock(PriceRangeDataProvider::class), $original);

        $this->assertNull($result['minimum_price']['final_price']['value']);
        $this->assertNull($result['maximum_price']['regular_price']['value']);
    }

    public function testSpecialPriceIsNullWhenHidden(): void
    {
        $this->priceVisibility->method('canViewPrices')->willReturn(false);
        $plugin = new HideSpecialPricePlugin($this->priceVisibility);

        $result = $plugin->afterResolve(
            $this->createMock(SpecialPrice::class),
            19.9,
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class)
        );

        $this->assertNull($result);
    }
}
