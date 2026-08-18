<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Model;

use Magento\Framework\App\Cache\Type\Config as ConfigCacheType;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Psr\Log\LoggerInterface;

class TransactionalEmailConfigSynchronizer
{
    /**
     * @var array<string, string>
     */
    private const CANONICAL_EMAIL = 'awamotos@awamotos.com.br';

    /** From SMTP autenticado (Hostinger rejeita From que não pertence ao usuário). */
    private const SMTP_FROM_EMAIL = 'sac@awamotos.com';

    private const DEFAULT_VALUES = [
        'trans_email/ident_general/name' => 'AWA Motos',
        'trans_email/ident_general/email' => self::SMTP_FROM_EMAIL,
        'trans_email/ident_support/name' => 'Suporte AWA Motos',
        'trans_email/ident_support/email' => self::SMTP_FROM_EMAIL,
        'trans_email/ident_sales/name' => 'Vendas AWA Motos',
        'trans_email/ident_sales/email' => self::SMTP_FROM_EMAIL,
        'trans_email/ident_custom1/name' => 'Contato AWA Motos',
        'trans_email/ident_custom1/email' => self::SMTP_FROM_EMAIL,
        'trans_email/ident_custom2/name' => 'Atacado AWA Motos',
        'trans_email/ident_custom2/email' => self::SMTP_FROM_EMAIL,
        'trans_email/ident_storepickup/name' => 'AWA Motos Retirada',
        'trans_email/ident_storepickup/email' => self::SMTP_FROM_EMAIL,
        'grupoawamotos_theme/contact/email' => self::CANONICAL_EMAIL,
        'contact/email/recipient_email' => self::CANONICAL_EMAIL,
        'grupoawamotos_maintenance/contact/email' => self::CANONICAL_EMAIL,
        'grupoawamotos_b2b/general/admin_email' => self::CANONICAL_EMAIL,
        'grupoawamotos_erp/rfm/alert_email' => self::CANONICAL_EMAIL,
        'marketplace/general_settings/adminemail' => self::CANONICAL_EMAIL,
        'ayo_curriculo/general/recipient_email' => self::CANONICAL_EMAIL,
        'ayo_curriculo/general/copy_to' => self::CANONICAL_EMAIL,
        'salesintelligence/alerts/recipient_email' => self::CANONICAL_EMAIL,
        'rexisml/alerts/churn_email_recipients' => self::CANONICAL_EMAIL,
        'rexisml/alerts/crosssell_email_recipients' => self::CANONICAL_EMAIL,
        'sales_email/order/copy_to' => self::CANONICAL_EMAIL,
        'sales_email/order_comment/copy_to' => self::CANONICAL_EMAIL,
        'sales_email/invoice/copy_to' => self::CANONICAL_EMAIL,
        'sales_email/invoice_comment/copy_to' => self::CANONICAL_EMAIL,
        'sales_email/shipment/copy_to' => self::CANONICAL_EMAIL,
        'sales_email/shipment_comment/copy_to' => self::CANONICAL_EMAIL,
        'sales_email/creditmemo/copy_to' => self::CANONICAL_EMAIL,
        'sales_email/creditmemo_comment/copy_to' => self::CANONICAL_EMAIL,
        'sales_email/order_ready_for_pickup/copy_to' => self::CANONICAL_EMAIL,
        'system/gmailsmtpapp/debug/from_email' => self::SMTP_FROM_EMAIL,
    ];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter,
        private readonly TypeListInterface $cacheTypeList,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array{
     *     saved:int,
     *     unchanged:int,
     *     failed:int,
     *     changed_paths:string[],
     *     failed_paths:string[]
     * }
     */
    public function synchronizeDefaultScope(): array
    {
        $saved = 0;
        $unchanged = 0;
        $failed = 0;
        $changedPaths = [];
        $failedPaths = [];

        foreach (self::DEFAULT_VALUES as $path => $value) {
            $currentValue = (string) $this->scopeConfig->getValue($path);

            if ($currentValue === $value) {
                $unchanged++;
                continue;
            }

            try {
                $this->configWriter->save($path, $value, 'default', 0);
                $saved++;
                $changedPaths[] = $path;
            } catch (\Throwable $exception) {
                $failed++;
                $failedPaths[] = $path;
                $this->logger->error(
                    sprintf(
                        '[TransactionalEmailConfigSynchronizer] Erro ao salvar "%s": %s',
                        $path,
                        $exception->getMessage()
                    )
                );
            }
        }

        if ($saved > 0) {
            $this->cacheTypeList->cleanType(ConfigCacheType::TYPE_IDENTIFIER);
        }

        return [
            'saved' => $saved,
            'unchanged' => $unchanged,
            'failed' => $failed,
            'changed_paths' => $changedPaths,
            'failed_paths' => $failedPaths,
        ];
    }
}
