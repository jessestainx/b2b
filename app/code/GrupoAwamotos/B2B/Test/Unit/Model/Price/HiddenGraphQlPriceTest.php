<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Model\Price;

use GrupoAwamotos\B2B\Model\Price\HiddenGraphQlPrice;
use PHPUnit\Framework\TestCase;

class HiddenGraphQlPriceTest extends TestCase
{
    public function testEmptyRangeHasNullValues(): void
    {
        $range = HiddenGraphQlPrice::emptyRange();
        $this->assertNull($range['minimum_price']['final_price']['value']);
        $this->assertNull($range['maximum_price']['regular_price']['currency']);
    }

    public function testRedactLegacyPriceZerosNumericValues(): void
    {
        $redacted = HiddenGraphQlPrice::redactLegacyPrice([
            'minimalPrice' => ['amount' => ['value' => 22.99, 'currency' => 'BRL']],
        ]);

        $this->assertSame(0, $redacted['minimalPrice']['amount']['value']);
        $this->assertSame('BRL', $redacted['minimalPrice']['amount']['currency']);
    }
}
