<?php

declare(strict_types=1);

/**
 * Cron para processar alertas automaticos de Churn e Cross-sell
 * Executa diariamente as 9h (configurado em crontab.xml)
 */

namespace GrupoAwamotos\RexisML\Cron;

use GrupoAwamotos\RexisML\Helper\EmailNotifier;
use GrupoAwamotos\RexisML\Helper\WhatsAppNotifier;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

class ProcessAlerts
{
    private EmailNotifier $emailNotifier;
    private WhatsAppNotifier $whatsappNotifier;
    private ResourceConnection $resource;
    private ScopeConfigInterface $scopeConfig;
    private LoggerInterface $logger;

    public function __construct(
        EmailNotifier $emailNotifier,
        WhatsAppNotifier $whatsappNotifier,
        ResourceConnection $resource,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->emailNotifier = $emailNotifier;
        $this->whatsappNotifier = $whatsappNotifier;
        $this->resource = $resource;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        try {
            $this->logger->info('[RexisML] Iniciando processamento de alertas automaticos');

            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('rexis_dataset_recomendacao');

            // Config thresholds (with fallback defaults)
            $churnMinScore = (float)($this->scopeConfig->getValue('rexisml/alerts/churn_min_score') ?: 0.85);
            $churnMinValue = (float)($this->scopeConfig->getValue('rexisml/alerts/churn_min_value') ?: 500);
            $xsMinScore = (float)($this->scopeConfig->getValue('rexisml/alerts/crosssell_min_score') ?: 0.75);
            $xsMinValue = (float)($this->scopeConfig->getValue('rexisml/alerts/crosssell_min_value') ?: 300);

            // 1. Churn alerts (email)
            $churnEnabled = $this->scopeConfig->getValue('rexisml/alerts/churn_enabled');
            if ($churnEnabled) {
                $churnRows = $connection->fetchAll(
                    $connection->select()
                        ->from($table)
                        ->where('tipo_recomendacao = ?', 'churn')
                        ->where('pred >= ?', $churnMinScore)
                        ->where('previsao_gasto_round_up >= ?', $churnMinValue)
                        ->order('pred DESC')
                        ->limit(20)
                );

                if (!empty($churnRows)) {
                    $this->emailNotifier->sendChurnAlert($churnRows);
                    $this->logger->info(sprintf(
                        '[RexisML] Email de Churn enviado com %d oportunidades',
                        count($churnRows)
                    ));
                }
            }

            // 2. Cross-sell alerts (email + whatsapp)
            $xsEnabled = $this->scopeConfig->getValue('rexisml/alerts/crosssell_enabled');
            if ($xsEnabled) {
                $xsRows = $connection->fetchAll(
                    $connection->select()
                        ->from($table)
                        ->where('tipo_recomendacao = ?', 'crosssell')
                        ->where('pred >= ?', $xsMinScore)
                        ->where('previsao_gasto_round_up >= ?', $xsMinValue)
                        ->order('pred DESC')
                        ->limit(10)
                );

                if (!empty($xsRows)) {
                    $this->emailNotifier->sendCrosssellAlert($xsRows);
                    $this->logger->info(sprintf(
                        '[RexisML] Email de Cross-sell enviado com %d oportunidades',
                        count($xsRows)
                    ));
                }
            }

            // 3. WhatsApp alerts (if configured)
            $whatsappEnabled = $this->scopeConfig->getValue('rexisml/whatsapp/enabled');
            if ($whatsappEnabled) {
                $topChurn = $connection->fetchAll(
                    $connection->select()
                        ->from($table)
                        ->where('tipo_recomendacao = ?', 'churn')
                        ->where('pred >= ?', 0.90)
                        ->order('previsao_gasto_round_up DESC')
                        ->limit(5)
                );
                if (!empty($topChurn)) {
                    $this->whatsappNotifier->sendChurnRecovery($topChurn);
                }
            }

            $this->logger->info('[RexisML] Processamento de alertas concluido');
        } catch (\Exception $e) {
            $this->logger->error('[RexisML] Erro ao processar alertas: ' . $e->getMessage());
        }
    }
}
