<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Model\Order;

use GrupoAwamotos\B2B\Model\Order\CustomerFinanceData;
use GrupoAwamotos\B2B\Model\Sectra\ValidatorChecker;
use GrupoAwamotos\ERPIntegration\Api\BoletoSyncInterface;
use GrupoAwamotos\ERPIntegration\Model\Boleto\FebrabanBoletoCalculator;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\B2B\Model\Order\CustomerFinanceData
 *
 * Fase 5 (hardening) do plano BOLETOS_NFE_IMPLEMENTACAO.md -- garante que um cliente
 * nunca consegue imprimir/ver dados de titulo de OUTRO cliente, mesmo adivinhando o
 * codigo (receberCodigo) na URL.
 */
class CustomerFinanceDataTest extends TestCase
{
    private CustomerFinanceData $model;

    /** @var CustomerSession|MockObject */
    private $customerSession;

    /** @var ValidatorChecker|MockObject */
    private $validatorChecker;

    /** @var BoletoSyncInterface|MockObject */
    private $boletoSync;

    /** @var ScopeConfigInterface|MockObject */
    private $scopeConfig;

    /** @var TimezoneInterface|MockObject */
    private $timezone;

    protected function setUp(): void
    {
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->validatorChecker = $this->createMock(ValidatorChecker::class);
        $this->boletoSync = $this->createMock(BoletoSyncInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->timezone = $this->createMock(TimezoneInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->timezone->method('formatDate')->willReturn('06/08/2026');

        $this->model = new CustomerFinanceData(
            $this->customerSession,
            $this->validatorChecker,
            $this->boletoSync,
            new FebrabanBoletoCalculator(),
            $this->scopeConfig,
            $this->timezone,
            $logger
        );
    }

    public function testGetBoletoImprimivelRetornaNullQuandoTituloNaoPertenceAoClienteLogado(): void
    {
        // Cliente A esta logado com erp_code=500
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->customerSession->method('getCustomerId')->willReturn(100);
        $this->validatorChecker->method('getCustomerErpCode')->with(100)->willReturn(500);

        // Cliente A tenta adivinhar o receberCodigo=999, que na verdade pertence ao Cliente B.
        // O ERP (BoletoSync) ja valida CODCLIENTE=erpClientCode no JOIN -- simula retorno vazio.
        $this->boletoSync->expects($this->once())
            ->method('getBoletoPrintableRawData')
            ->with(999, 500)
            ->willReturn(null);

        $result = $this->model->getBoletoImprimivel(999);

        $this->assertNull($result, 'Titulo de outro cliente NUNCA deve ser retornado.');
    }

    public function testGetBoletoImprimivelRetornaNullQuandoClienteNaoEstaLogado(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(false);

        $this->boletoSync->expects($this->never())->method('getBoletoPrintableRawData');

        $result = $this->model->getBoletoImprimivel(247091);

        $this->assertNull($result);
    }

    public function testGetBoletoImprimivelCalculaBarcodeParaFilial2BancoBrasilCarteira017(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->customerSession->method('getCustomerId')->willReturn(536);
        $this->validatorChecker->method('getCustomerErpCode')->willReturn(536);
        $this->scopeConfig->method('getValue')->willReturn('2467742');

        $this->boletoSync->method('getBoletoPrintableRawData')->willReturn([
            'codigo' => 247091,
            'filial' => 2,
            'pedido' => 194122,
            'banco' => '001',
            'carteira' => '017',
            'nro_boleto' => '0000003599',
            'nro_documento' => 'OUT:000010547/A',
            'data_vencimento' => '2026-08-06',
            'valor_devido' => 651.61,
            'beneficiario_nome' => 'BOOMERANG MOTO PECAS',
            'beneficiario_cnpj' => '10.350.477/0001-50',
            'beneficiario_endereco' => 'RUA PROFESSORA ERGILIA MICELLI',
            'sacado_nome' => 'FERNANDO JOSE PAVAO & CIA LTDA',
            'sacado_cnpj' => '66.618.406/0001-40',
        ]);

        $result = $this->model->getBoletoImprimivel(247091);

        $this->assertNotNull($result);
        $this->assertTrue($result['supported']);
        $this->assertSame(
            '00190.00009 02467.742009 00003.599172 6 15300000065161',
            $result['linha_digitavel']
        );
    }

    public function testGetBoletoImprimivelCaiEmFallbackParaFilialNaoValidada(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->customerSession->method('getCustomerId')->willReturn(1122);
        $this->validatorChecker->method('getCustomerErpCode')->willReturn(1122);

        $this->boletoSync->method('getBoletoPrintableRawData')->willReturn([
            'codigo' => 24616,
            'filial' => 1,
            'pedido' => null,
            'banco' => '104',
            'carteira' => 'RG',
            'nro_boleto' => '0000000001',
            'nro_documento' => 'OUT:000010547/A',
            'data_vencimento' => '2008-06-25',
            'valor_devido' => 3443.6,
            'beneficiario_nome' => 'W MURARI BORRACHAS',
            'beneficiario_cnpj' => '',
            'beneficiario_endereco' => '',
            'sacado_nome' => '',
            'sacado_cnpj' => '',
        ]);

        $result = $this->model->getBoletoImprimivel(24616);

        $this->assertNotNull($result);
        $this->assertFalse($result['supported'], 'Filial 1 ainda nao validada -- deve cair em fallback sem barcode.');
        $this->assertNull($result['linha_digitavel']);
        $this->assertNull($result['barcode']);
    }
}
