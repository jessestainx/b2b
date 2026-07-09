<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

use GrupoAwamotos\ERPIntegration\Api\BoletoSyncInterface;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

/**
 * Leitura somente-leitura de titulos financeiros (FN_RECEBER) e dados de boleto
 * (FN_RECEBERBOLETO) no ERP Sectra.
 *
 * Nenhum metodo desta classe executa INSERT/UPDATE/DELETE no ERP.
 *
 * Ver app/code/GrupoAwamotos/B2B/BOLETOS_NFE_IMPLEMENTACAO.md para o mapeamento
 * completo do schema e a regra de classificacao de situacao.
 */
class BoletoSync implements BoletoSyncInterface
{
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 500;
    private const DEFAULT_OFFSET = 0;

    /**
     * Unico status de FN_RECEBER exposto ao cliente B2B.
     * 'B' (Bloqueadas), 'C' (Canceladas) e 'P' (Pendentes) nao sao expostos.
     */
    private const EXPOSED_STATUS = 'A';

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly Helper $helper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getReceivablesByErpCode(
        int $erpClientCode,
        ?string $situacao = null,
        int $limit = self::DEFAULT_LIMIT,
        int $offset = self::DEFAULT_OFFSET
    ): array {
        if ($erpClientCode <= 0 || !$this->helper->isEnabled()) {
            return [];
        }

        $safeLimit = max(1, min(self::MAX_LIMIT, $limit));
        $safeOffset = max(self::DEFAULT_OFFSET, $offset);

        try {
            $sql = "SELECT
                        r.CODIGO,
                        r.PEDIDO,
                        r.NRODOCUMENTO,
                        r.NROBOLETO,
                        r.NRODUPLICATA,
                        r.DTEMISSAO,
                        r.DTVENCIMENTO,
                        r.VLRDEVIDO,
                        r.VLRTOTAL,
                        r.DTPAGAMENTO
                    FROM FN_RECEBER r
                    WHERE r.CODCLIENTE = :codcliente
                      AND r.STATUS = :status
                    ORDER BY r.DTVENCIMENTO DESC, r.CODIGO DESC
                    OFFSET {$safeOffset} ROWS
                    FETCH NEXT {$safeLimit} ROWS ONLY";

            $rows = $this->connection->query($sql, [
                ':codcliente' => $erpClientCode,
                ':status' => self::EXPOSED_STATUS,
            ]);

            $receivables = [];
            foreach ($rows as $row) {
                $item = $this->mapReceivableRow($row);

                if ($situacao !== null && $situacao !== self::SITUACAO_ABERTO && $item['situacao'] !== $situacao) {
                    continue;
                }

                // SITUACAO_ABERTO e um filtro agregado: todo titulo nao pago (vencido + a vencer).
                if ($situacao === self::SITUACAO_ABERTO && $item['situacao'] === self::SITUACAO_PAGO) {
                    continue;
                }

                $receivables[] = $item;
            }

            return $receivables;
        } catch (\Exception $e) {
            $this->logger->error('[ERP-Boleto] Falha ao buscar titulos do cliente ' . $erpClientCode . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getReceivablesSummaryByErpCode(int $erpClientCode): array
    {
        $empty = [
            self::SITUACAO_ABERTO => 0,
            self::SITUACAO_VENCIDO => 0,
            self::SITUACAO_A_VENCER => 0,
            self::SITUACAO_PAGO => 0,
        ];

        if ($erpClientCode <= 0 || !$this->helper->isEnabled()) {
            return $empty;
        }

        try {
            $sql = "SELECT
                        SUM(CASE WHEN r.DTPAGAMENTO IS NOT NULL THEN 1 ELSE 0 END) AS TOTAL_PAGO,
                        SUM(CASE
                            WHEN r.DTPAGAMENTO IS NULL
                             AND CONVERT(date, r.DTVENCIMENTO) < CONVERT(date, GETDATE())
                            THEN 1 ELSE 0 END) AS TOTAL_VENCIDO,
                        SUM(CASE
                            WHEN r.DTPAGAMENTO IS NULL
                             AND CONVERT(date, r.DTVENCIMENTO) >= CONVERT(date, GETDATE())
                            THEN 1 ELSE 0 END) AS TOTAL_A_VENCER
                    FROM FN_RECEBER r
                    WHERE r.CODCLIENTE = :codcliente
                      AND r.STATUS = :status";

            $row = $this->connection->fetchOne($sql, [
                ':codcliente' => $erpClientCode,
                ':status' => self::EXPOSED_STATUS,
            ]);

            $pago = (int) ($row['TOTAL_PAGO'] ?? 0);
            $vencido = (int) ($row['TOTAL_VENCIDO'] ?? 0);
            $aVencer = (int) ($row['TOTAL_A_VENCER'] ?? 0);

            return [
                self::SITUACAO_ABERTO => $vencido + $aVencer,
                self::SITUACAO_VENCIDO => $vencido,
                self::SITUACAO_A_VENCER => $aVencer,
                self::SITUACAO_PAGO => $pago,
            ];
        } catch (\Exception $e) {
            $this->logger->error('[ERP-Boleto] Falha ao resumir titulos do cliente ' . $erpClientCode . ': ' . $e->getMessage());
            return $empty;
        }
    }

    /**
     * @inheritDoc
     */
    public function getBoletoDetails(int $receberCodigo, int $erpClientCode): ?array
    {
        if ($receberCodigo <= 0 || $erpClientCode <= 0 || !$this->helper->isEnabled()) {
            return null;
        }

        try {
            $sql = "SELECT
                        b.RECEBER,
                        b.LOCALPAGAMENTO,
                        b.ESPECIEDOCUMENTO,
                        b.AGENCIACODIGOCEDENTE,
                        b.NOSSONUMERO,
                        b.DIGITONOSSONUMERO,
                        b.CODIGOBANCO,
                        b.DTVENCIMENTO,
                        b.NOMECEDENTE,
                        b.NUMERODOCUMENTO,
                        b.VALORDOCUMENTO,
                        b.DATADOCUMENTO,
                        b.SACADONOME,
                        b.SACADOCPFCGC,
                        b.SACADORUANUMEROCOMPLEMENTO,
                        b.SACADOCEPBAIRROCIDADEESTADO,
                        b.ENDERECOCEDENTE,
                        b.CNPJCEDENTE,
                        b.ACEITE,
                        b.CARTEIRA,
                        b.INSTRUCOES,
                        b.VALORDESCONTOABATIMENTO,
                        b.VALORMORAMULTA,
                        b.LINHADIGITAVEL
                    FROM FN_RECEBERBOLETO b
                    INNER JOIN FN_RECEBER r
                        ON r.CODIGO = b.RECEBER
                       AND r.CODCLIENTE = :codcliente
                       AND r.STATUS = :status
                    WHERE b.RECEBER = :receber";

            $row = $this->connection->fetchOne($sql, [
                ':receber' => $receberCodigo,
                ':codcliente' => $erpClientCode,
                ':status' => self::EXPOSED_STATUS,
            ]);

            if ($row === null) {
                $this->logger->warning('[ERP-Boleto] Titulo nao encontrado ou nao pertence ao cliente informado', [
                    'receber' => $receberCodigo,
                    'erp_client_code' => $erpClientCode,
                ]);
                return null;
            }

            return $this->mapBoletoRow($row);
        } catch (\Exception $e) {
            $this->logger->error('[ERP-Boleto] Falha ao buscar dados de boleto ' . $receberCodigo . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function getBoletoPrintableRawData(int $receberCodigo, int $erpClientCode): ?array
    {
        if ($receberCodigo <= 0 || $erpClientCode <= 0 || !$this->helper->isEnabled()) {
            return null;
        }

        try {
            $sql = "SELECT
                        r.CODIGO,
                        r.FILIAL,
                        r.PEDIDO,
                        r.BANCO,
                        r.CARTEIRA,
                        r.NROBOLETO,
                        r.NRODOCUMENTO,
                        r.DTVENCIMENTO,
                        r.VLRDEVIDO,
                        f.RSOCIAL AS BENEFICIARIO_NOME,
                        f.CNPJ AS BENEFICIARIO_CNPJ,
                        f.ENDERECO AS BENEFICIARIO_ENDERECO,
                        f.BAIRRO AS BENEFICIARIO_BAIRRO,
                        f.CIDADE AS BENEFICIARIO_CIDADE,
                        f.ESTADO AS BENEFICIARIO_ESTADO,
                        f.CEP AS BENEFICIARIO_CEP,
                        c.RAZAO AS SACADO_NOME,
                        c.CGC AS SACADO_CNPJ
                    FROM FN_RECEBER r
                    LEFT JOIN GR_FILIAL f ON f.CODIGO = r.FILIAL
                    LEFT JOIN FN_FORNECEDORES c ON c.CODIGO = r.CODCLIENTE
                    WHERE r.CODIGO = :receber
                      AND r.CODCLIENTE = :codcliente
                      AND r.STATUS = :status";

            $row = $this->connection->fetchOne($sql, [
                ':receber' => $receberCodigo,
                ':codcliente' => $erpClientCode,
                ':status' => self::EXPOSED_STATUS,
            ]);

            if ($row === null) {
                $this->logger->warning('[ERP-Boleto] Titulo nao encontrado para impressao (ownership/status)', [
                    'receber' => $receberCodigo,
                    'erp_client_code' => $erpClientCode,
                ]);
                return null;
            }

            return [
                'codigo' => (int) ($row['CODIGO'] ?? 0),
                'filial' => (int) ($row['FILIAL'] ?? 0),
                'pedido' => isset($row['PEDIDO']) && $row['PEDIDO'] !== '' ? (int) $row['PEDIDO'] : null,
                'banco' => (string) ($row['BANCO'] ?? ''),
                'carteira' => (string) ($row['CARTEIRA'] ?? ''),
                'nro_boleto' => (string) ($row['NROBOLETO'] ?? ''),
                'nro_documento' => (string) ($row['NRODOCUMENTO'] ?? ''),
                'data_vencimento' => $row['DTVENCIMENTO'] ?? null,
                'valor_devido' => (float) ($row['VLRDEVIDO'] ?? 0),
                'beneficiario_nome' => (string) ($row['BENEFICIARIO_NOME'] ?? ''),
                'beneficiario_cnpj' => (string) ($row['BENEFICIARIO_CNPJ'] ?? ''),
                'beneficiario_endereco' => trim(implode(', ', array_filter([
                    (string) ($row['BENEFICIARIO_ENDERECO'] ?? ''),
                    (string) ($row['BENEFICIARIO_BAIRRO'] ?? ''),
                    (string) ($row['BENEFICIARIO_CIDADE'] ?? ''),
                    (string) ($row['BENEFICIARIO_ESTADO'] ?? ''),
                    (string) ($row['BENEFICIARIO_CEP'] ?? ''),
                ]))),
                'sacado_nome' => (string) ($row['SACADO_NOME'] ?? ''),
                'sacado_cnpj' => (string) ($row['SACADO_CNPJ'] ?? ''),
            ];
        } catch (\Exception $e) {
            $this->logger->error('[ERP-Boleto] Falha ao buscar dados brutos para impressao ' . $receberCodigo . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapReceivableRow(array $row): array
    {
        $dtVencimento = $row['DTVENCIMENTO'] ?? null;
        $dtPagamento = $row['DTPAGAMENTO'] ?? null;

        return [
            'codigo' => (int) ($row['CODIGO'] ?? 0),
            'pedido' => isset($row['PEDIDO']) ? (int) $row['PEDIDO'] : null,
            'nro_documento' => (string) ($row['NRODOCUMENTO'] ?? ''),
            'nro_boleto' => (string) ($row['NROBOLETO'] ?? ''),
            'nro_duplicata' => (string) ($row['NRODUPLICATA'] ?? ''),
            'data_emissao' => $row['DTEMISSAO'] ?? null,
            'data_vencimento' => $dtVencimento,
            'valor_devido' => (float) ($row['VLRDEVIDO'] ?? 0),
            'valor_total' => (float) ($row['VLRTOTAL'] ?? 0),
            'data_pagamento' => $dtPagamento,
            'situacao' => $this->resolveSituacao($dtVencimento, $dtPagamento),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapBoletoRow(array $row): array
    {
        return [
            'receber' => (int) ($row['RECEBER'] ?? 0),
            'local_pagamento' => (string) ($row['LOCALPAGAMENTO'] ?? ''),
            'especie_documento' => (string) ($row['ESPECIEDOCUMENTO'] ?? ''),
            'agencia_codigo_cedente' => (string) ($row['AGENCIACODIGOCEDENTE'] ?? ''),
            'nosso_numero' => (string) ($row['NOSSONUMERO'] ?? ''),
            'digito_nosso_numero' => (string) ($row['DIGITONOSSONUMERO'] ?? ''),
            'codigo_banco' => (string) ($row['CODIGOBANCO'] ?? ''),
            'data_vencimento' => $row['DTVENCIMENTO'] ?? null,
            'nome_cedente' => (string) ($row['NOMECEDENTE'] ?? ''),
            'numero_documento' => (string) ($row['NUMERODOCUMENTO'] ?? ''),
            'valor_documento' => (float) ($row['VALORDOCUMENTO'] ?? 0),
            'data_documento' => $row['DATADOCUMENTO'] ?? null,
            'sacado_nome' => (string) ($row['SACADONOME'] ?? ''),
            'sacado_cpf_cgc' => (string) ($row['SACADOCPFCGC'] ?? ''),
            'sacado_endereco' => (string) ($row['SACADORUANUMEROCOMPLEMENTO'] ?? ''),
            'sacado_cep_bairro_cidade_estado' => (string) ($row['SACADOCEPBAIRROCIDADEESTADO'] ?? ''),
            'endereco_cedente' => (string) ($row['ENDERECOCEDENTE'] ?? ''),
            'cnpj_cedente' => (string) ($row['CNPJCEDENTE'] ?? ''),
            'aceite' => (string) ($row['ACEITE'] ?? ''),
            'carteira' => (string) ($row['CARTEIRA'] ?? ''),
            'instrucoes' => (string) ($row['INSTRUCOES'] ?? ''),
            'valor_desconto_abatimento' => (float) ($row['VALORDESCONTOABATIMENTO'] ?? 0),
            'valor_mora_multa' => (float) ($row['VALORMORAMULTA'] ?? 0),
            'linha_digitavel' => (string) ($row['LINHADIGITAVEL'] ?? ''),
        ];
    }

    /**
     * @param mixed $dtVencimento
     * @param mixed $dtPagamento
     */
    private function resolveSituacao($dtVencimento, $dtPagamento): string
    {
        if ($dtPagamento !== null && $dtPagamento !== '') {
            return self::SITUACAO_PAGO;
        }

        try {
            $vencimento = $dtVencimento instanceof \DateTimeInterface
                ? $dtVencimento
                : new \DateTimeImmutable((string) $dtVencimento);
        } catch (\Exception) {
            // Data invalida/ausente: trata como a vencer para nao esconder o titulo do cliente.
            return self::SITUACAO_A_VENCER;
        }

        $today = new \DateTimeImmutable('today');

        return $vencimento < $today ? self::SITUACAO_VENCIDO : self::SITUACAO_A_VENCER;
    }
}
