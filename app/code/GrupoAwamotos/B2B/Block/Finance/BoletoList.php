<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Finance;

use GrupoAwamotos\B2B\Model\Order\CustomerFinanceData;
use GrupoAwamotos\ERPIntegration\Api\BoletoSyncInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Bloco de apresentacao da pagina "Financeiro" -- delega toda a logica de dados e
 * de resolucao de identidade para GrupoAwamotos\B2B\Model\Order\CustomerFinanceData.
 */
class BoletoList extends Template
{
    private const VALID_TABS = [
        BoletoSyncInterface::SITUACAO_ABERTO,
        BoletoSyncInterface::SITUACAO_VENCIDO,
        BoletoSyncInterface::SITUACAO_A_VENCER,
        BoletoSyncInterface::SITUACAO_PAGO,
    ];

    public function __construct(
        Context $context,
        private readonly CustomerFinanceData $customerFinanceData,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * A pagina so exibe conteudo se o cliente logado tiver erp_code resolvido
     * (fail-closed: sem erp_code, sem dado nenhum exibido).
     */
    public function isAvailable(): bool
    {
        return $this->customerFinanceData->getErpCodeForLoggedCustomer() !== null;
    }

    public function getActiveTab(): string
    {
        $tab = (string) $this->getRequest()->getParam('situacao', BoletoSyncInterface::SITUACAO_ABERTO);

        return in_array($tab, self::VALID_TABS, true) ? $tab : BoletoSyncInterface::SITUACAO_ABERTO;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReceivables(): array
    {
        return $this->customerFinanceData->getReceivables($this->getActiveTab());
    }

    /**
     * @return array{aberto: int, vencido: int, a_vencer: int, pago: int}
     */
    public function getSummary(): array
    {
        return $this->customerFinanceData->getReceivablesSummary();
    }

    public function getTabUrl(string $situacao): string
    {
        return $this->getUrl('b2b/finance/index', ['situacao' => $situacao]);
    }

    public function getPrintUrl(int $receberCodigo): string
    {
        return $this->getUrl('b2b/finance/printBoleto', ['receber' => $receberCodigo]);
    }

    public function getTabLabel(string $situacao): string
    {
        return match ($situacao) {
            BoletoSyncInterface::SITUACAO_ABERTO => (string) __('Em aberto'),
            BoletoSyncInterface::SITUACAO_VENCIDO => (string) __('Vencidos'),
            BoletoSyncInterface::SITUACAO_A_VENCER => (string) __('A vencer'),
            BoletoSyncInterface::SITUACAO_PAGO => (string) __('Pagos'),
            default => (string) __('Em aberto'),
        };
    }

    /**
     * @return string[]
     */
    public function getTabs(): array
    {
        return self::VALID_TABS;
    }

    /**
     * Runtime probe for finance template render and empty-state standardization.
     */
}
