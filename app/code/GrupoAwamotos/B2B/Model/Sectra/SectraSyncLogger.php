<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Sectra;

use GrupoAwamotos\B2B\Model\ResourceModel\SectraSyncLog;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as ErpSyncLogResource;
use Psr\Log\LoggerInterface;

/**
 * Dual logging: dedicated B2B Sectra table + ERP sync log for admin visibility.
 */
class SectraSyncLogger
{
    public function __construct(
        private readonly SectraSyncLog $sectraSyncLog,
        private readonly ErpSyncLogResource $erpSyncLog,
        private readonly LoggerInterface $logger
    ) {
    }

    public function log(
        string $eventType,
        string $message,
        ?int $customerId = null,
        ?int $orderId = null,
        ?string $cnpj = null,
        ?int $sectraChave = null,
        string $level = 'info'
    ): void {
        $maskedCnpj = $cnpj !== null ? $this->maskCnpj($cnpj) : null;
        $fullMessage = sprintf('[%s] %s', $eventType, $message);
        if ($sectraChave !== null) {
            $fullMessage .= sprintf(' (CHAVE Sectra: %d)', $sectraChave);
        }
        if ($maskedCnpj !== null) {
            $fullMessage .= sprintf(' (CNPJ: %s)', $maskedCnpj);
        }

        $logLevel = $level === 'error' ? 'error' : ($level === 'success' ? 'success' : 'info');
        $this->logger->log(
            $level === 'error' ? \Monolog\Logger::ERROR : \Monolog\Logger::INFO,
            '[B2B-Sectra] ' . $fullMessage,
            array_filter([
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'event_type' => $eventType,
            ])
        );

        $this->sectraSyncLog->addEvent(
            $eventType,
            $message,
            $customerId,
            $orderId,
            $maskedCnpj,
            $sectraChave,
            $level
        );

        $entityType = $orderId !== null ? 'sectra_b2b_order' : 'sectra_b2b_customer';
        $magentoId = $orderId ?? $customerId;
        $this->erpSyncLog->addLog(
            $entityType,
            'export',
            $logLevel,
            $fullMessage,
            $sectraChave !== null ? (string) $sectraChave : null,
            $magentoId,
            1
        );
    }

    private function maskCnpj(string $cnpj): string
    {
        $digits = preg_replace('/\D/', '', $cnpj);
        if (strlen($digits) !== 14) {
            return '***';
        }

        return substr($digits, 0, 2) . '********' . substr($digits, -4);
    }
}
