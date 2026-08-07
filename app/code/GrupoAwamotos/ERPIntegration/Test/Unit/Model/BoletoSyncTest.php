<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model;

use GrupoAwamotos\ERPIntegration\Api\BoletoSyncInterface;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\BoletoSync;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Cobre a classificacao de situacao (aberto/vencido/a_vencer/pago) e as
 * guardas de fail-closed (ownership/status) de BoletoSync.
 */
class BoletoSyncTest extends TestCase
{
    private ConnectionInterface&MockObject $connection;
    private Helper&MockObject $helper;
    private LoggerInterface&MockObject $logger;
    private BoletoSync $boletoSync;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->helper = $this->createMock(Helper::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->helper->method('isEnabled')->willReturn(true);

        $this->boletoSync = new BoletoSync($this->connection, $this->helper, $this->logger);
    }

    public function testGetReceivablesByErpCodeRetornaVazioQuandoErpCodeInvalido(): void
    {
        $this->connection->expects($this->never())->method('query');

        $this->assertSame([], $this->boletoSync->getReceivablesByErpCode(0));
        $this->assertSame([], $this->boletoSync->getReceivablesByErpCode(-5));
    }

    public function testGetReceivablesByErpCodeRetornaVazioQuandoIntegracaoDesabilitada(): void
    {
        $this->helper = $this->createMock(Helper::class);
        $this->helper->method('isEnabled')->willReturn(false);
        $this->boletoSync = new BoletoSync($this->connection, $this->helper, $this->logger);

        $this->connection->expects($this->never())->method('query');

        $this->assertSame([], $this->boletoSync->getReceivablesByErpCode(123));
    }

    public function testGetReceivablesByErpCodeClassificaTodasAsSituacoes(): void
    {
        $today = new \DateTimeImmutable('today');
        $ontem = $today->modify('-1 day')->format('Y-m-d');
        $amanha = $today->modify('+1 day')->format('Y-m-d');

        $this->connection->method('query')->willReturn([
            // vencido: sem pagamento, vencimento no passado
            [
                'CODIGO' => 1, 'PEDIDO' => 100, 'NRODOCUMENTO' => 'DOC-1', 'NROBOLETO' => 'B1',
                'NRODUPLICATA' => 'D1', 'DTEMISSAO' => $ontem, 'DTVENCIMENTO' => $ontem,
                'VLRDEVIDO' => 100.0, 'VLRTOTAL' => 100.0, 'DTPAGAMENTO' => null,
            ],
            // a_vencer: sem pagamento, vencimento no futuro
            [
                'CODIGO' => 2, 'PEDIDO' => 101, 'NRODOCUMENTO' => 'DOC-2', 'NROBOLETO' => 'B2',
                'NRODUPLICATA' => 'D2', 'DTEMISSAO' => $ontem, 'DTVENCIMENTO' => $amanha,
                'VLRDEVIDO' => 200.0, 'VLRTOTAL' => 200.0, 'DTPAGAMENTO' => null,
            ],
            // pago: tem data de pagamento (independente do vencimento)
            [
                'CODIGO' => 3, 'PEDIDO' => 102, 'NRODOCUMENTO' => 'DOC-3', 'NROBOLETO' => 'B3',
                'NRODUPLICATA' => 'D3', 'DTEMISSAO' => $ontem, 'DTVENCIMENTO' => $ontem,
                'VLRDEVIDO' => 300.0, 'VLRTOTAL' => 300.0, 'DTPAGAMENTO' => $ontem,
            ],
        ]);

        $result = $this->boletoSync->getReceivablesByErpCode(7219);

        $this->assertCount(3, $result);
        $situacoes = array_column($result, 'situacao', 'codigo');
        $this->assertSame(BoletoSyncInterface::SITUACAO_VENCIDO, $situacoes[1]);
        $this->assertSame(BoletoSyncInterface::SITUACAO_A_VENCER, $situacoes[2]);
        $this->assertSame(BoletoSyncInterface::SITUACAO_PAGO, $situacoes[3]);
    }

    public function testGetReceivablesByErpCodeFiltraPorSituacaoEspecifica(): void
    {
        $today = new \DateTimeImmutable('today');
        $ontem = $today->modify('-1 day')->format('Y-m-d');
        $amanha = $today->modify('+1 day')->format('Y-m-d');

        $this->connection->method('query')->willReturn([
            ['CODIGO' => 1, 'PEDIDO' => 1, 'NRODOCUMENTO' => 'D1', 'NROBOLETO' => 'B1', 'NRODUPLICATA' => '',
                'DTEMISSAO' => $ontem, 'DTVENCIMENTO' => $ontem, 'VLRDEVIDO' => 1, 'VLRTOTAL' => 1, 'DTPAGAMENTO' => null],
            ['CODIGO' => 2, 'PEDIDO' => 2, 'NRODOCUMENTO' => 'D2', 'NROBOLETO' => 'B2', 'NRODUPLICATA' => '',
                'DTEMISSAO' => $ontem, 'DTVENCIMENTO' => $amanha, 'VLRDEVIDO' => 1, 'VLRTOTAL' => 1, 'DTPAGAMENTO' => null],
        ]);

        $vencidos = $this->boletoSync->getReceivablesByErpCode(7219, BoletoSyncInterface::SITUACAO_VENCIDO);
        $this->assertCount(1, $vencidos);
        $this->assertSame(1, $vencidos[0]['codigo']);

        // SITUACAO_ABERTO e agregado: retorna vencidos + a_vencer, excluindo pagos.
        $abertos = $this->boletoSync->getReceivablesByErpCode(7219, BoletoSyncInterface::SITUACAO_ABERTO);
        $this->assertCount(2, $abertos);
    }

    public function testGetReceivablesByErpCodeRetornaVazioEmExcecaoDeConexao(): void
    {
        $this->connection->method('query')->willThrowException(new \RuntimeException('timeout'));
        $this->logger->expects($this->once())->method('error');

        $this->assertSame([], $this->boletoSync->getReceivablesByErpCode(7219));
    }

    public function testGetBoletoDetailsRetornaNullQuandoNaoEncontradoOuNaoPertenceAoCliente(): void
    {
        $this->connection->method('fetchOne')->willReturn(null);

        $this->assertNull($this->boletoSync->getBoletoDetails(190239, 7219));
    }

    public function testGetBoletoDetailsRetornaDadosMapeadosQuandoEncontrado(): void
    {
        $this->connection->method('fetchOne')->willReturn([
            'RECEBER' => 247091,
            'LINHADIGITAVEL' => '00190.00009 02467.742009 00003.599172 6 15300000065161',
            'VALORDOCUMENTO' => 651.61,
            'CARTEIRA' => '017',
            'CODIGOBANCO' => '001',
        ]);

        $result = $this->boletoSync->getBoletoDetails(247091, 7219);

        $this->assertIsArray($result);
        $this->assertSame(247091, $result['receber']);
        $this->assertSame('00190.00009 02467.742009 00003.599172 6 15300000065161', $result['linha_digitavel']);
    }

    public function testGetBoletoDetailsRetornaNullComErpCodeOuReceberInvalido(): void
    {
        $this->connection->expects($this->never())->method('fetchOne');

        $this->assertNull($this->boletoSync->getBoletoDetails(0, 7219));
        $this->assertNull($this->boletoSync->getBoletoDetails(247091, 0));
    }

    public function testGetBoletoPrintableRawDataRetornaNullQuandoNaoEncontrado(): void
    {
        $this->connection->method('fetchOne')->willReturn(null);

        $this->assertNull($this->boletoSync->getBoletoPrintableRawData(190239, 7219));
    }

    public function testGetBoletoPrintableRawDataMontaEnderecoBeneficiarioCorretamente(): void
    {
        $this->connection->method('fetchOne')->willReturn([
            'CODIGO' => 247091,
            'FILIAL' => 2,
            'PEDIDO' => 194122,
            'BANCO' => '001',
            'CARTEIRA' => '017',
            'CC' => 2,
            'NROBOLETO' => '3599',
            'NRODOCUMENTO' => 'OUT:000010547/E',
            'DTVENCIMENTO' => '2026-08-06',
            'VLRDEVIDO' => 651.61,
            'BENEFICIARIO_NOME' => 'BOOMERANG MOTO PECAS',
            'BENEFICIARIO_CNPJ' => '10.350.477/0001-50',
            'BENEFICIARIO_ENDERECO' => 'RUA PROFESSORA ERGILIA MICELLI',
            'BENEFICIARIO_BAIRRO' => 'JD REGINA',
            'BENEFICIARIO_CIDADE' => 'ARARAQUARA',
            'BENEFICIARIO_ESTADO' => 'SP',
            'BENEFICIARIO_CEP' => '14808-110',
            'SACADO_NOME' => 'FERNANDO JOSE PAVAO & CIA LTDA',
            'SACADO_CNPJ' => '66.618.406/0001-40',
            'SICOOB_AGENCIA' => '',
            'SICOOB_CEDENTE' => '',
            'SICOOB_DIGCEDENTE' => '',
            'SICOOB_MODALIDADE' => '',
        ]);

        $result = $this->boletoSync->getBoletoPrintableRawData(247091, 7219);

        $this->assertSame(2, $result['filial']);
        $this->assertSame('001', $result['banco']);
        $this->assertSame('017', $result['carteira']);
        $this->assertSame(2, $result['cc']);
        $this->assertSame(
            'RUA PROFESSORA ERGILIA MICELLI, JD REGINA, ARARAQUARA, SP, 14808-110',
            $result['beneficiario_endereco']
        );
    }
}
