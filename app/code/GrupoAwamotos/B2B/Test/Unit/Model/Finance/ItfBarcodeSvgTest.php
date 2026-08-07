<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Model\Finance;

use GrupoAwamotos\B2B\Model\Finance\ItfBarcodeSvg;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\B2B\Model\Finance\ItfBarcodeSvg
 */
class ItfBarcodeSvgTest extends TestCase
{
    public function testRenderGeraSvgComBarrasParaBarcodeSicoob(): void
    {
        $svg = (new ItfBarcodeSvg())->render('75697155900003433681304101047747800057360001');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('fill="#000"', $svg);
        $this->assertGreaterThan(40, substr_count($svg, '<rect'));
    }

    public function testRenderRetornaVazioParaEntradaVazia(): void
    {
        $this->assertSame('', (new ItfBarcodeSvg())->render(''));
    }
}
