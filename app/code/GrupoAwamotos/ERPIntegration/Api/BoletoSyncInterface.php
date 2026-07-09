<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

/**
 * Leitura (somente leitura) de titulos financeiros (contas a receber / boletos) no ERP Sectra.
 *
 * Fonte de dados: FN_RECEBER + FN_RECEBERBOLETO (ver
 * app/code/GrupoAwamotos/B2B/BOLETOS_NFE_IMPLEMENTACAO.md para o mapeamento completo do schema).
 *
 * Nenhum metodo desta interface escreve no ERP.
 */
interface BoletoSyncInterface
{
    /**
     * Situacao: titulo em aberto (nao pago), sem distincao de prazo.
     */
    public const SITUACAO_ABERTO = 'aberto';

    /**
     * Situacao: titulo em aberto com vencimento futuro (ou igual a hoje).
     */
    public const SITUACAO_A_VENCER = 'a_vencer';

    /**
     * Situacao: titulo em aberto com vencimento no passado.
     */
    public const SITUACAO_VENCIDO = 'vencido';

    /**
     * Situacao: titulo com DTPAGAMENTO preenchida (baixado/pago).
     */
    public const SITUACAO_PAGO = 'pago';

    /**
     * Obtem os titulos financeiros (contas a receber) de um cliente no ERP, com a
     * situacao (aberto / a_vencer / vencido / pago) ja calculada.
     *
     * Filtra sempre por FN_RECEBER.STATUS = 'A' (Ativas) -- titulos Bloqueados/Cancelados
     * nao sao expostos ao cliente. Titulos "Pendentes" (STATUS = 'P') tambem sao
     * excluidos por padrao por representarem um estado transitorio no ERP.
     *
     * @param int $erpClientCode Codigo do cliente no ERP (FN_FORNECEDORES.CODIGO)
     * @param string|null $situacao Filtro opcional: uma das constantes SITUACAO_*. Null retorna todas.
     * @param int $limit Quantidade maxima de registros retornados na pagina
     * @param int $offset Deslocamento de pagina (0, limit, 2*limit, ...)
     * @return array Lista de titulos: [codigo, pedido, nro_documento, nro_boleto, nro_duplicata,
     *               data_emissao, data_vencimento, valor_devido, valor_total, data_pagamento, situacao]
     */
    public function getReceivablesByErpCode(
        int $erpClientCode,
        ?string $situacao = null,
        int $limit = 200,
        int $offset = 0
    ): array;

    /**
     * Obtem os dados de boleto (linha digitavel, banco, cedente, sacado, instrucoes) de um
     * titulo especifico, validando que o titulo pertence ao cliente informado (defesa em
     * profundidade -- a camada B2B tambem deve validar a propriedade do titulo).
     *
     * Nao retorna as imagens binarias (CODIGOBARRA/LOGOTIPO) armazenadas no ERP; a decisao de
     * como renderizar o codigo de barras para impressao fica para a Fase 4 do plano.
     *
     * @deprecated 2026-07-08 Fluxo oficial de impressao usa getBoletoPrintableRawData()
     *             + CustomerFinanceData::getBoletoImprimivel(). Mantido apenas para compatibilidade.
     *
     * @param int $receberCodigo Codigo do titulo (FN_RECEBER.CODIGO)
     * @param int $erpClientCode Codigo do cliente no ERP, usado para validar propriedade do titulo
     * @return array|null Dados do boleto ou null se nao encontrado / nao pertencente ao cliente
     */
    public function getBoletoDetails(int $receberCodigo, int $erpClientCode): ?array;

    /**
     * Obtem os dados brutos necessarios para calcular/imprimir um boleto (Fase 4), validando
     * que o titulo pertence ao cliente informado e esta no status exposto (STATUS='A').
     *
     * Diferente de getBoletoDetails() (que consulta FN_RECEBERBOLETO, praticamente vazia em
     * producao), este metodo busca os dados diretamente de FN_RECEBER + GR_FILIAL (beneficiario)
     * + FN_FORNECEDORES (sacado) -- a mesma fonte que o Sectra usa para montar o boleto na
     * impressao de "Notas Fiscais de Saida" (ver BOLETOS_NFE_IMPLEMENTACAO.md, Fase 4).
     *
     * @param int $receberCodigo Codigo do titulo (FN_RECEBER.CODIGO)
     * @param int $erpClientCode Codigo do cliente no ERP, usado para validar propriedade do titulo
     * @return array{
     *     codigo: int, filial: int, pedido: int|null, banco: string, carteira: string,
     *     nro_boleto: string, nro_documento: string, data_vencimento: mixed, valor_devido: float,
     *     beneficiario_nome: string, beneficiario_cnpj: string, beneficiario_endereco: string,
     *     sacado_nome: string, sacado_cnpj: string
     * }|null
     */
    public function getBoletoPrintableRawData(int $receberCodigo, int $erpClientCode): ?array;

    /**
     * Retorna o resumo agregado por situacao sem depender da listagem paginada.
     *
     * @param int $erpClientCode Codigo do cliente no ERP (FN_FORNECEDORES.CODIGO)
     * @return array{aberto: int, vencido: int, a_vencer: int, pago: int}
     */
    public function getReceivablesSummaryByErpCode(int $erpClientCode): array;
}
