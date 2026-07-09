<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Order;

use GrupoAwamotos\B2B\Model\Sectra\ValidatorChecker;
use GrupoAwamotos\ERPIntegration\Api\BoletoSyncInterface;
use GrupoAwamotos\ERPIntegration\Model\Boleto\FebrabanBoletoCalculator;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Psr\Log\LoggerInterface;

/**
 * Apresentacao (somente leitura) de titulos financeiros/boletos do cliente B2B logado.
 *
 * Toda resolucao de identidade parte EXCLUSIVAMENTE da sessao do cliente autenticado
 * (CustomerSession) -- nenhum metodo publico aceita customerId/erpCode vindo de request,
 * evitando acesso cruzado entre clientes (ver Fase 5 do plano de implementacao em
 * app/code/GrupoAwamotos/B2B/BOLETOS_NFE_IMPLEMENTACAO.md).
 */
class CustomerFinanceData
{
    /**
     * Escopo validado da Fase 4 (ver BOLETOS_NFE_IMPLEMENTACAO.md): geracao de codigo de
     * barras/linha digitavel SOMENTE para FILIAL=2 (Boomerang) + Banco do Brasil (001) +
     * carteira 017/17. Qualquer outra combinacao cai no fallback informativo (sem barcode).
     */
    private const SUPPORTED_FILIAL = 2;
    private const SUPPORTED_BANCO = '001';
    private const SUPPORTED_CARTEIRAS = ['017', '17'];
    private const CONFIG_PATH_CONVENIO_FILIAL_2 = 'grupoawamotos_b2b/finance/bb_convenio_filial_2';
    private const DEFAULT_PAGE_SIZE = 50;
    private const MAX_PAGE_SIZE = 500;

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $receivablesCache = [];

    /** @var array{aberto: int, vencido: int, a_vencer: int, pago: int}|null */
    private ?array $receivablesSummaryCache = null;

    private bool $erpCodeResolved = false;
    private ?int $cachedErpCode = null;

    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly ValidatorChecker $validatorChecker,
        private readonly BoletoSyncInterface $boletoSync,
        private readonly FebrabanBoletoCalculator $febrabanCalculator,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly TimezoneInterface $timezone,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Resolve o codigo ERP (FN_FORNECEDORES.CODIGO) do cliente atualmente logado.
     *
     * Fail-closed: retorna null (nunca lanca excecao) se o cliente nao estiver logado
     * ou nao houver erp_code resolvido -- o chamador deve tratar null como "nada a exibir".
     */
    public function getErpCodeForLoggedCustomer(): ?int
    {
        if ($this->erpCodeResolved) {
            return $this->cachedErpCode;
        }

        $this->erpCodeResolved = true;

        if (!$this->customerSession->isLoggedIn()) {
            $this->cachedErpCode = null;
            return null;
        }

        $customerId = (int) $this->customerSession->getCustomerId();
        if ($customerId <= 0) {
            $this->cachedErpCode = null;
            return null;
        }

        try {
            $this->cachedErpCode = $this->validatorChecker->getCustomerErpCode($customerId);
        } catch (\Exception $e) {
            $this->logger->warning('[B2B-Finance] Falha ao resolver erp_code do cliente logado: ' . $e->getMessage());
            $this->cachedErpCode = null;
        }

        return $this->cachedErpCode;
    }

    /**
     * Lista os titulos financeiros do cliente logado, com valores/datas ja formatados
     * para exibicao.
     *
     * @param string|null $situacao Uma das constantes BoletoSyncInterface::SITUACAO_*, ou null para todas.
     * @param int $page Numero da pagina (base 1)
     * @param int $pageSize Quantidade por pagina
     * @return array<int, array<string, mixed>>
     */
    public function getReceivables(?string $situacao = null, int $page = 1, int $pageSize = self::DEFAULT_PAGE_SIZE): array
    {
        $erpCode = $this->getErpCodeForLoggedCustomer();
        if ($erpCode === null) {
            return [];
        }

        $safePage = max(1, $page);
        $safePageSize = max(1, min(self::MAX_PAGE_SIZE, $pageSize));
        $offset = ($safePage - 1) * $safePageSize;
        $cacheKey = sprintf('%s|%d|%d', (string) ($situacao ?? 'all'), $safePage, $safePageSize);

        if (isset($this->receivablesCache[$cacheKey])) {
            return $this->receivablesCache[$cacheKey];
        }

        $rows = $this->boletoSync->getReceivablesByErpCode($erpCode, $situacao, $safePageSize, $offset);

        $formatted = array_map(function (array $row): array {
            $row['data_vencimento_fmt'] = $this->formatDate($row['data_vencimento'] ?? null);
            $row['data_emissao_fmt'] = $this->formatDate($row['data_emissao'] ?? null);
            $row['data_pagamento_fmt'] = $this->formatDate($row['data_pagamento'] ?? null);
            $row['valor_devido_fmt'] = $this->formatCurrency((float) ($row['valor_devido'] ?? 0));
            $row['valor_total_fmt'] = $this->formatCurrency((float) ($row['valor_total'] ?? 0));

            return $row;
        }, $rows);

        $this->receivablesCache[$cacheKey] = $formatted;

        return $formatted;
    }

    /**
     * Retorna quantos titulos existem em cada situacao, para exibir contadores nas abas
     * (Aberto / Vencido / A vencer) sem depender da listagem paginada.
     *
     * @return array{aberto: int, vencido: int, a_vencer: int, pago: int}
     */
    public function getReceivablesSummary(): array
    {
        if ($this->receivablesSummaryCache !== null) {
            return $this->receivablesSummaryCache;
        }

        $erpCode = $this->getErpCodeForLoggedCustomer();
        if ($erpCode === null) {
            $this->receivablesSummaryCache = [
                BoletoSyncInterface::SITUACAO_ABERTO => 0,
                BoletoSyncInterface::SITUACAO_VENCIDO => 0,
                BoletoSyncInterface::SITUACAO_A_VENCER => 0,
                BoletoSyncInterface::SITUACAO_PAGO => 0,
            ];

            return $this->receivablesSummaryCache;
        }

        $this->receivablesSummaryCache = $this->boletoSync->getReceivablesSummaryByErpCode($erpCode);

        return $this->receivablesSummaryCache;
    }

    /**
     * Monta os dados completos para a pagina de impressao do boleto (Fase 4), incluindo
     * codigo de barras + linha digitavel quando o titulo esta no escopo validado (FILIAL=2,
     * Banco do Brasil, carteira 017), ou apenas os dados informativos do titulo (sem
     * codigo de barras) para qualquer outra combinacao ainda nao validada.
     *
     * @return array<string, mixed>|null Null se nao logado, sem erp_code, ou titulo nao encontrado/nao pertencente ao cliente.
     */
    public function getBoletoImprimivel(int $receberCodigo): ?array
    {
        if ($receberCodigo <= 0) {
            return null;
        }

        $erpCode = $this->getErpCodeForLoggedCustomer();
        if ($erpCode === null) {
            return null;
        }

        $raw = $this->boletoSync->getBoletoPrintableRawData($receberCodigo, $erpCode);
        if ($raw === null) {
            return null;
        }

        $carteiraTrim = trim((string) $raw['carteira']);
        $supported = (int) $raw['filial'] === self::SUPPORTED_FILIAL
            && (string) $raw['banco'] === self::SUPPORTED_BANCO
            && in_array($carteiraTrim, self::SUPPORTED_CARTEIRAS, true);

        $vencimento = $raw['data_vencimento'];
        $vencimentoFmt = $this->formatDate($vencimento);

        $result = [
            'supported' => $supported,
            'codigo' => $raw['codigo'],
            'nro_documento' => $raw['nro_documento'],
            'nro_boleto' => $raw['nro_boleto'],
            'data_vencimento_fmt' => $vencimentoFmt,
            'valor_devido_fmt' => $this->formatCurrency($raw['valor_devido']),
            'beneficiario_nome' => $raw['beneficiario_nome'],
            'beneficiario_cnpj' => $raw['beneficiario_cnpj'],
            'beneficiario_endereco' => $raw['beneficiario_endereco'],
            'sacado_nome' => $raw['sacado_nome'],
            'sacado_cnpj' => $raw['sacado_cnpj'],
            'banco' => $raw['banco'],
            'carteira' => $carteiraTrim,
            'linha_digitavel' => null,
            'barcode' => null,
        ];

        if (!$supported) {
            return $result;
        }

        try {
            $vencimentoDate = $vencimento instanceof \DateTimeInterface
                ? $vencimento
                : new \DateTimeImmutable((string) $vencimento);

            $convenio = (string) $this->scopeConfig->getValue(self::CONFIG_PATH_CONVENIO_FILIAL_2);
            if ($convenio === '') {
                $this->logger->warning('[B2B-Finance] Convenio Banco do Brasil (filial 2) nao configurado -- fallback sem codigo de barras');
                $result['supported'] = false;
                return $result;
            }

            $campoLivre = $this->febrabanCalculator->buildCampoLivreBancoBrasil(
                $convenio,
                $raw['nro_boleto'],
                $raw['carteira']
            );

            $calculo = $this->febrabanCalculator->build(
                $raw['banco'],
                '9',
                $vencimentoDate,
                $raw['valor_devido'],
                $campoLivre
            );

            $result['linha_digitavel'] = $calculo['linha_digitavel'];
            $result['barcode'] = $calculo['barcode'];
        } catch (\Exception $e) {
            $this->logger->error('[B2B-Finance] Falha ao calcular boleto (titulo ' . $receberCodigo . '): ' . $e->getMessage());
            $result['supported'] = false;
        }

        return $result;
    }

    /**
     * @deprecated 2026-07-08 Mantido apenas para compatibilidade. Fluxo oficial
     *             de impressao usa getBoletoImprimivel().
     *
     * @return array<string, mixed>|null
     */
    public function getBoletoForPrint(int $receberCodigo): ?array
    {
        return $this->getBoletoImprimivel($receberCodigo);
    }

    private function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return $this->timezone->formatDate(
                (string) $value,
                \IntlDateFormatter::MEDIUM,
                false
            );
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function formatCurrency(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}
