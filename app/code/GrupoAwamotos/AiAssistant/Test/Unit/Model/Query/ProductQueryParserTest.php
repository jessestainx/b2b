<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Test\Unit\Model\Query;

use GrupoAwamotos\AiAssistant\Model\Query\ProductQueryParser;
use PHPUnit\Framework\TestCase;

class ProductQueryParserTest extends TestCase
{
    private ProductQueryParser $parser;

    protected function setUp(): void
    {
        if (!class_exists(ProductQueryParser::class, false)) {
            require_once dirname(__DIR__, 4) . '/Model/Query/ProductQueryParser.php';
        }
        $this->parser = new ProductQueryParser();
    }

    public function testTitanMirrorPrefersFitment(): void
    {
        $intent = $this->parser->parse('preciso de retrovisor titan 150');

        $this->assertTrue($intent['is_product_query']);
        $this->assertTrue($intent['prefers_fitment']);
        $this->assertSame('Honda', $intent['brand']);
        $this->assertSame('Titan 150', $intent['model']);
        $this->assertStringContainsString('retrovisor', $intent['part']);
    }

    public function testGreetingIsNotProductQuery(): void
    {
        $this->assertFalse($this->parser->isProductQuery('oi'));
        $this->assertFalse($this->parser->isProductQuery('Bom dia!'));
    }

    public function testOrderTrackingIsNotProductQuery(): void
    {
        $this->assertFalse($this->parser->isProductQuery('quero rastrear meu pedido 1234'));
    }

    public function testOilWithoutBikeUsesCatalog(): void
    {
        $intent = $this->parser->parse('quero óleo 20w50');

        $this->assertTrue($intent['is_product_query']);
        $this->assertFalse($intent['prefers_fitment']);
        $this->assertSame('', $intent['model']);
        $this->assertStringContainsString('óleo', $intent['part']);
    }

    public function testGenericOilQuestionNeedsMoreDetailsBeforeCatalogSearch(): void
    {
        $intent = $this->parser->parse('tem óleo para moto?');

        $this->assertFalse($intent['is_product_query']);
        $this->assertFalse($intent['prefers_fitment']);
    }

    public function testTwoStrokeOilUsesCatalog(): void
    {
        $intent = $this->parser->parse('preciso de óleo 2T');

        $this->assertTrue($intent['is_product_query']);
        $this->assertStringContainsString('óleo 2t', $intent['search_query']);
    }

    public function testWhatFitsFazerHasEmptyPart(): void
    {
        $intent = $this->parser->parse('o que serve na fazer 250');

        $this->assertTrue($intent['is_product_query']);
        $this->assertTrue($intent['prefers_fitment']);
        $this->assertSame('Fazer 250', $intent['model']);
        $this->assertSame('Yamaha', $intent['brand']);
        $this->assertSame('', $intent['part']);
    }
}
