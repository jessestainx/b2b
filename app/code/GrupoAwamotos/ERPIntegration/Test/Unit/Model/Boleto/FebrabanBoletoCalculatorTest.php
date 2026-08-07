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

    public function testCampoLivreSicoobBateComBoletoRealSectra190239(): void
    {
        // FN_RECEBERBOLETO.RECEBER=190239 — CEDENTE 118181 + DIG 5, nosso 0000348-9
        $campoLivre = $this->calculator->buildCampoLivreSicoob(
            '1',
            '3041',
            '01',
            '118181',
            '5',
            '00000348'
        );

        $this->assertSame('1181815', $this->calculator->normalizeCedenteSicoob('118181', '5'));
        $this->assertSame('9', $this->calculator->digitoNossoNumeroSicoob('3041', '1181815', '0000348'));
        $this->assertSame('1304101118181500003489001', $campoLivre);
    }

    public function testCampoLivreSicoobBoomerangCc23(): void
    {
        $campoLivre = $this->calculator->buildCampoLivreSicoob(
            '1',
            '3041',
            '01',
            '47747',
            '8',
            '0000005736'
        );

        $this->assertSame('1304101047747800057360001', $campoLivre);
        $result = $this->calculator->build(
            '756',
            '9',
            new \DateTimeImmutable('2026-09-04'),
            3433.68,
            $campoLivre
        );
        $this->assertSame(
            '75691.30417 01047.747801 00573.600012 7 15590000343368',
            $result['linha_digitavel']
        );
    }
}
