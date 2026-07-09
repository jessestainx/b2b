<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model\Boleto;

use GrupoAwamotos\ERPIntegration\Model\Boleto\FebrabanBoletoCalculator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\ERPIntegration\Model\Boleto\FebrabanBoletoCalculator
 *
 * Regressao com caso REAL: boleto emitido pelo Sectra (Notas Fiscais de Saida -> Imprimir)
 * para FN_RECEBER.CODIGO=247091 (Banco do Brasil, carteira 017, FILIAL=2/Boomerang).
 * Se este teste falhar apos qualquer alteracao na classe, o calculo de boleto ESTA QUEBRADO
 * e nao deve ir para producao -- ver app/code/GrupoAwamotos/B2B/BOLETOS_NFE_IMPLEMENTACAO.md.
 */
class FebrabanBoletoCalculatorTest extends TestCase
{
    private FebrabanBoletoCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new FebrabanBoletoCalculator();
    }

    public function testLinhaDigitavelBateComBoletoRealSectra(): void
    {
        $campoLivre = $this->calculator->buildCampoLivreBancoBrasil('2467742', '0000003599', '017');
        $result = $this->calculator->build(
            '001',
            '9',
            new \DateTimeImmutable('2026-08-06'),
            651.61,
            $campoLivre
        );

        $this->assertSame('0000002467742000000359917', $campoLivre);
        $this->assertSame('00196153000000651610000002467742000000359917', $result['barcode']);
        $this->assertSame(
            '00190.00009 02467.742009 00003.599172 6 15300000065161',
            $result['linha_digitavel']
        );
    }

    public function testFatorVencimentoUsaBaseNovaPosFevereiro2025(): void
    {
        // 1000 na data-base (22/02/2025) apos a mudanca FEBRABAN de fevereiro/2025
        $this->assertSame(1000, $this->calculator->fatorVencimento(new \DateTimeImmutable('2025-02-22')));
        $this->assertSame(1530, $this->calculator->fatorVencimento(new \DateTimeImmutable('2026-08-06')));
    }

    public function testCampoLivreRejeitaTamanhoInvalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->build('001', '9', new \DateTimeImmutable('2026-08-06'), 100.0, '123');
    }
}
