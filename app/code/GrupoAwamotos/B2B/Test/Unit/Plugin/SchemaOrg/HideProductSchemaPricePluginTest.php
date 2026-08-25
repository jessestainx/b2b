<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Plugin\SchemaOrg;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use GrupoAwamotos\B2B\Plugin\SchemaOrg\HideProductSchemaPricePlugin;
use GrupoAwamotos\SchemaOrg\Block\ProductSchema;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HideProductSchemaPricePluginTest extends TestCase
{
    private PriceVisibilityInterface&MockObject $priceVisibility;

    protected function setUp(): void
    {
        $this->priceVisibility = $this->createMock(PriceVisibilityInterface::class);
    }

    public function testKeepsPriceWhenVisible(): void
    {
        $this->priceVisibility->method('canViewPrices')->willReturn(true);
        $plugin = new HideProductSchemaPricePlugin($this->priceVisibility);
        $schema = ['offers' => ['price' => '22.99', 'priceCurrency' => 'BRL']];

        $this->assertSame($schema, $plugin->afterGetProductSchemaData(
            $this->createMock(ProductSchema::class),
            $schema
        ));
    }

    public function testRemovesPriceWhenHidden(): void
    {
        $this->priceVisibility->method('canViewPrices')->willReturn(false);
        $plugin = new HideProductSchemaPricePlugin($this->priceVisibility);
        $schema = [
            'offers' => [
                'price' => '22.99',
                'priceCurrency' => 'BRL',
                'priceSpecification' => ['price' => '19.90'],
                'availability' => 'https://schema.org/InStock',
            ],
        ];

        $result = $plugin->afterGetProductSchemaData($this->createMock(ProductSchema::class), $schema);

        $this->assertArrayNotHasKey('price', $result['offers']);
        $this->assertArrayNotHasKey('priceSpecification', $result['offers']);
        $this->assertSame('https://schema.org/InStock', $result['offers']['availability']);
    }
}
