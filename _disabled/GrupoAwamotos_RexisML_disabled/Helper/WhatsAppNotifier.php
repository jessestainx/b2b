<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Helper;

use GrupoAwamotos\ERPIntegration\Model\WhatsApp\ZApiClient;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class WhatsAppNotifier extends AbstractHelper
{
    private ZApiClient $zApiClient;
    private CustomerRepositoryInterface $customerRepository;
    private ProductRepositoryInterface $productRepository;
    private ResourceConnection $resource;
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        ZApiClient $zApiClient,
        CustomerRepositoryInterface $customerRepository,
        ProductRepositoryInterface $productRepository,
        ResourceConnection $resource,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->zApiClient = $zApiClient;
        $this->customerRepository = $customerRepository;
        $this->productRepository = $productRepository;
        $this->resource = $resource;
        $this->logger = $logger;
    }

    /**
     * Send cross-sell alert to managers/sellers via WhatsApp
     *
     * @param array $rows Raw DB rows from rexis_dataset_recomendacao
     * @return bool
     */
    public function sendCrosssellAlert(array $rows): bool
    {
        if (empty($rows)) {
            return false;
        }

        try {
            $message = "*REXIS ML - Oportunidades de Cross-sell*\n\n";
            $message .= "*" . count($rows) . " oportunidades detectadas*\n\n";

            $count = 0;
            foreach ($rows as $row) {
                if ($count >= 5) {
                    break;
                }

                $erpCode = $row['identificador_cliente'] ?? '';
                $productCode = $row['identificador_produto'] ?? '';
                $score = round(((float)($row['pred'] ?? 0)) * 100, 1);
                $value = number_format((float)($row['previsao_gasto_round_up'] ?? 0), 2, ',', '.');

                $customerName = $this->resolveCustomerName($erpCode);
                $productName = $this->resolveProductName($productCode);

                $message .= sprintf(
                    "*%s*\n%s\nR$ %s | Score: %.1f%%\n\n",
                    $customerName,
                    $productName,
                    $value,
                    $score
                );
                $count++;
            }

            return $this->sendToRecipients($message);
        } catch (\Exception $e) {
            $this->logger->error('[RexisML WhatsApp] CrossSell: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send churn recovery alert to managers via WhatsApp
     *
     * @param array $rows Raw DB rows from rexis_dataset_recomendacao
     * @return bool
     */
    public function sendChurnRecovery(array $rows): bool
    {
        if (empty($rows)) {
            return false;
        }

        try {
            $message = "*REXIS ML - Alerta de Churn*\n\n";
            $message .= "*" . count($rows) . " clientes em risco de churn*\n\n";

            $totalValue = 0;
            $count = 0;
            foreach ($rows as $row) {
                if ($count >= 5) {
                    break;
                }

                $erpCode = $row['identificador_cliente'] ?? '';
                $score = round(((float)($row['pred'] ?? 0)) * 100, 1);
                $value = (float)($row['previsao_gasto_round_up'] ?? 0);
                $recency = (int)($row['recencia'] ?? 0);
                $totalValue += $value;

                $customerName = $this->resolveCustomerName($erpCode);

                $message .= sprintf(
                    "*%s*\nRisco: %.1f%% | Valor: R$ %s | %d dias\n\n",
                    $customerName,
                    $score,
                    number_format($value, 2, ',', '.'),
                    $recency
                );
                $count++;
            }

            $message .= sprintf(
                "*Valor total em risco: R$ %s*\n\n",
                number_format($totalValue, 2, ',', '.')
            );
            $message .= "Acesse o painel REXIS ML para detalhes.";

            return $this->sendToRecipients($message);
        } catch (\Exception $e) {
            $this->logger->error('[RexisML WhatsApp] ChurnRecovery: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send message to all configured WhatsApp recipients
     */
    private function sendToRecipients(string $message): bool
    {
        $recipients = $this->scopeConfig->getValue('rexisml/whatsapp/recipients');
        if (!$recipients) {
            $this->logger->warning('[RexisML WhatsApp] No recipients configured');
            return false;
        }

        $sent = 0;
        foreach (explode(',', $recipients) as $number) {
            $number = trim($number);
            if ($number) {
                $result = $this->zApiClient->sendTextMessage($number, $message);
                if ($result) {
                    $sent++;
                }
            }
        }

        $this->logger->info(sprintf(
            '[RexisML WhatsApp] Message sent to %d/%d recipients',
            $sent,
            count(explode(',', $recipients))
        ));

        return $sent > 0;
    }

    /**
     * Resolve ERP code to customer name
     */
    private function resolveCustomerName(string $erpCode): string
    {
        if (empty($erpCode)) {
            return 'Cliente desconhecido';
        }

        try {
            $connection = $this->resource->getConnection();
            $mapTable = $this->resource->getTableName('grupoawamotos_erp_entity_map');
            $magentoId = $connection->fetchOne(
                $connection->select()
                    ->from($mapTable, 'magento_entity_id')
                    ->where('entity_type = ?', 'customer')
                    ->where('erp_code = ?', $erpCode)
                    ->limit(1)
            );

            if ($magentoId) {
                $customer = $this->customerRepository->getById((int)$magentoId);
                return $customer->getFirstname() . ' ' . $customer->getLastname();
            }

            if (is_numeric($erpCode)) {
                try {
                    $customer = $this->customerRepository->getById((int)$erpCode);
                    return $customer->getFirstname() . ' ' . $customer->getLastname();
                } catch (\Exception $e) {
                    // Not a Magento ID
                }
            }
        } catch (\Exception $e) {
            // Fallback
        }

        return 'Cliente ' . $erpCode;
    }

    /**
     * Send a test message to a specific phone number
     */
    public function sendTestMessage(string $phone): bool
    {
        try {
            $message = "*REXIS ML - Teste de WhatsApp*\n\n";
            $message .= "Integracao funcionando!\n";
            $message .= "Data/Hora: " . date('d/m/Y H:i:s') . "\n\n";
            $message .= "Este e um teste do sistema de alertas REXIS ML.";

            $result = $this->zApiClient->sendTextMessage($phone, $message);
            return $result !== null;
        } catch (\Exception $e) {
            $this->logger->error('[RexisML WhatsApp] Test: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Resolve product code to name
     */
    private function resolveProductName(string $productCode): string
    {
        if (empty($productCode)) {
            return 'Produto desconhecido';
        }

        try {
            $product = $this->productRepository->get($productCode);
            return $product->getName();
        } catch (\Exception $e) {
            return $productCode;
        }
    }
}
