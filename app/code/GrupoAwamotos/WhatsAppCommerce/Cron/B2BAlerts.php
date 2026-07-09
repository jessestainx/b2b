<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Cron;

use GrupoAwamotos\WhatsAppCommerce\Helper\Config;
use GrupoAwamotos\WhatsAppCommerce\Model\MessageSender;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * B2B Alerts Cron
 *
 * Sends WhatsApp notifications to B2B customers:
 * - Products they buy frequently are back in stock
 * - New products in categories they buy from
 * - Credit payment reminders
 */
class B2BAlerts
{
    private const CONFIG_PATH_ALERTS_ENABLED = 'whatsapp_commerce/b2b/alerts_enabled';
    private const CONFIG_PATH_STOCK_ALERT = 'whatsapp_commerce/b2b/stock_alert_enabled';
    private const CONFIG_PATH_NEW_PRODUCT_ALERT = 'whatsapp_commerce/b2b/new_product_alert_enabled';
    private const CONFIG_PATH_CREDIT_REMINDER = 'whatsapp_commerce/b2b/credit_reminder_enabled';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly MessageSender $messageSender,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Execute B2B alerts cron
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        if (!$this->scopeConfig->isSetFlag(self::CONFIG_PATH_ALERTS_ENABLED, ScopeInterface::SCOPE_STORE)) {
            return;
        }

        $this->logger->info('B2BAlerts cron started');

        try {
            if ($this->scopeConfig->isSetFlag(self::CONFIG_PATH_STOCK_ALERT, ScopeInterface::SCOPE_STORE)) {
                $this->processStockAlerts();
            }

            if ($this->scopeConfig->isSetFlag(self::CONFIG_PATH_NEW_PRODUCT_ALERT, ScopeInterface::SCOPE_STORE)) {
                $this->processNewProductAlerts();
            }

            if ($this->scopeConfig->isSetFlag(self::CONFIG_PATH_CREDIT_REMINDER, ScopeInterface::SCOPE_STORE)) {
                $this->processCreditReminders();
            }
        } catch (\Exception $e) {
            $this->logger->error('B2BAlerts cron error', ['error' => $e->getMessage()]);
        }

        $this->logger->info('B2BAlerts cron completed');
    }

    /**
     * Notify B2B customers when products they frequently buy are back in stock
     */
    private function processStockAlerts(): void
    {
        $connection = $this->resourceConnection->getConnection();

        $query = $connection->select()
            ->from(['si' => $connection->getTableName('cataloginventory_stock_item')], [])
            ->join(
                ['soi' => $connection->getTableName('sales_order_item')],
                'soi.product_id = si.product_id',
                []
            )
            ->join(
                ['so' => $connection->getTableName('sales_order')],
                'so.entity_id = soi.order_id',
                []
            )
            ->join(
                ['ce' => $connection->getTableName('customer_entity')],
                'ce.entity_id = so.customer_id',
                []
            )
            ->joinLeft(
                ['optin' => $connection->getTableName('customer_entity_varchar')],
                "optin.entity_id = ce.entity_id AND optin.attribute_id = (
                    SELECT attribute_id FROM " . $connection->getTableName('eav_attribute') . "
                    WHERE attribute_code = 'whatsapp_optin' AND entity_type_id = 1 LIMIT 1
                )",
                []
            )
            ->columns([
                'customer_id' => 'ce.entity_id',
                'customer_name' => new \Zend_Db_Expr("CONCAT(ce.firstname, ' ', ce.lastname)"),
                'product_name' => 'soi.name',
                'sku' => 'soi.sku',
                'product_id' => 'si.product_id',
            ])
            ->where('si.qty > 0')
            ->where('si.is_in_stock = 1')
            ->where('so.created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY)')
            ->where('ce.group_id IN (?)', $this->getB2BGroupIds())
            ->where('optin.value = ?', '1')
            ->group(['ce.entity_id', 'si.product_id'])
            ->limit(50);

        $results = $connection->fetchAll($query);

        $customerAlerts = [];
        foreach ($results as $row) {
            $customerId = (int) $row['customer_id'];
            if (!isset($customerAlerts[$customerId])) {
                $customerAlerts[$customerId] = [
                    'name' => $row['customer_name'],
                    'products' => [],
                ];
            }
            $customerAlerts[$customerId]['products'][] = $row['product_name'] . ' (' . $row['sku'] . ')';
        }

        foreach ($customerAlerts as $customerId => $data) {
            $phone = $this->getCustomerPhone($customerId);
            if (empty($phone)) {
                continue;
            }

            $products = array_slice($data['products'], 0, 3);
            $message = "Ola, {$data['name']}!\n\n"
                . "Produtos que voce compra estao de volta ao estoque:\n\n"
                . implode("\n", array_map(fn($p) => "- {$p}", $products));

            if (count($data['products']) > 3) {
                $remaining = count($data['products']) - 3;
                $message .= "\n...e mais {$remaining} produtos!";
            }

            $message .= "\n\nAcesse: https://awamotos.com";

            $this->messageSender->send($phone, $message);
        }

        $this->logger->info('Stock alerts sent', ['customers' => count($customerAlerts)]);
    }

    /**
     * Notify B2B customers about new products in their frequent categories
     */
    private function processNewProductAlerts(): void
    {
        $connection = $this->resourceConnection->getConnection();

        $newProductsQuery = $connection->select()
            ->from(
                ['cpe' => $connection->getTableName('catalog_product_entity')],
                ['entity_id', 'sku']
            )
            ->join(
                ['cpev' => $connection->getTableName('catalog_product_entity_varchar')],
                "cpev.entity_id = cpe.entity_id AND cpev.attribute_id = (
                    SELECT attribute_id FROM " . $connection->getTableName('eav_attribute') . "
                    WHERE attribute_code = 'name' AND entity_type_id = 4 LIMIT 1
                ) AND cpev.store_id = 0",
                ['name' => 'value']
            )
            ->join(
                ['ccp' => $connection->getTableName('catalog_category_product')],
                'ccp.product_id = cpe.entity_id',
                ['category_id']
            )
            ->where('cpe.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')
            ->limit(20);

        $newProducts = $connection->fetchAll($newProductsQuery);

        if (empty($newProducts)) {
            return;
        }

        $categoryIds = array_unique(array_column($newProducts, 'category_id'));

        $customersQuery = $connection->select()
            ->from(['so' => $connection->getTableName('sales_order')], [])
            ->join(
                ['soi' => $connection->getTableName('sales_order_item')],
                'so.entity_id = soi.order_id',
                []
            )
            ->join(
                ['ccp' => $connection->getTableName('catalog_category_product')],
                'ccp.product_id = soi.product_id',
                []
            )
            ->join(
                ['ce' => $connection->getTableName('customer_entity')],
                'ce.entity_id = so.customer_id',
                []
            )
            ->joinLeft(
                ['optin' => $connection->getTableName('customer_entity_varchar')],
                "optin.entity_id = ce.entity_id AND optin.attribute_id = (
                    SELECT attribute_id FROM " . $connection->getTableName('eav_attribute') . "
                    WHERE attribute_code = 'whatsapp_optin' AND entity_type_id = 1 LIMIT 1
                )",
                []
            )
            ->columns([
                'customer_id' => 'ce.entity_id',
                'customer_name' => new \Zend_Db_Expr("CONCAT(ce.firstname, ' ', ce.lastname)"),
            ])
            ->where('ccp.category_id IN (?)', $categoryIds)
            ->where('so.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)')
            ->where('ce.group_id IN (?)', $this->getB2BGroupIds())
            ->where('optin.value = ?', '1')
            ->group('ce.entity_id')
            ->limit(30);

        $customers = $connection->fetchAll($customersQuery);

        $productNames = array_slice(
            array_unique(array_column($newProducts, 'name')),
            0,
            3
        );

        foreach ($customers as $customerRow) {
            $phone = $this->getCustomerPhone((int) $customerRow['customer_id']);
            if (empty($phone)) {
                continue;
            }

            $message = "Ola, {$customerRow['customer_name']}!\n\n"
                . "Novidades no catalogo AWA Motos:\n\n"
                . implode("\n", array_map(fn($p) => "- {$p}", $productNames));

            if (count($newProducts) > 3) {
                $message .= "\n...e mais novidades!";
            }

            $message .= "\n\nAcesse: https://awamotos.com";

            $this->messageSender->send($phone, $message);
        }

        $this->logger->info('New product alerts sent', ['customers' => count($customers)]);
    }

    /**
     * Send credit payment reminders to B2B customers
     */
    private function processCreditReminders(): void
    {
        $connection = $this->resourceConnection->getConnection();

        $tableName = $connection->getTableName('grupoawamotos_b2b_credit_limit');
        if (!$connection->isTableExists($tableName)) {
            return;
        }

        $query = $connection->select()
            ->from(['cl' => $tableName], [])
            ->join(
                ['ce' => $connection->getTableName('customer_entity')],
                'ce.entity_id = cl.customer_id',
                []
            )
            ->joinLeft(
                ['optin' => $connection->getTableName('customer_entity_varchar')],
                "optin.entity_id = ce.entity_id AND optin.attribute_id = (
                    SELECT attribute_id FROM " . $connection->getTableName('eav_attribute') . "
                    WHERE attribute_code = 'whatsapp_optin' AND entity_type_id = 1 LIMIT 1
                )",
                []
            )
            ->columns([
                'customer_id' => 'ce.entity_id',
                'customer_name' => new \Zend_Db_Expr("CONCAT(ce.firstname, ' ', ce.lastname)"),
                'credit_limit' => 'cl.credit_limit',
                'used_credit' => 'cl.used_credit',
                'available' => new \Zend_Db_Expr('cl.credit_limit - cl.used_credit'),
            ])
            ->where('cl.credit_limit > 0')
            ->where('cl.used_credit > cl.credit_limit * 0.7')
            ->where('optin.value = ?', '1');

        $results = $connection->fetchAll($query);

        foreach ($results as $row) {
            $phone = $this->getCustomerPhone((int) $row['customer_id']);
            if (empty($phone)) {
                continue;
            }

            $available = (float) $row['available'];
            $limit = (float) $row['credit_limit'];
            $used = (float) $row['used_credit'];
            $usedPercent = $limit > 0 ? round(($used / $limit) * 100) : 0;

            $message = "Ola, {$row['customer_name']}!\n\n"
                . "Lembrete do seu credito B2B AWA Motos:\n\n"
                . "Limite: R$ " . number_format($limit, 2, ',', '.') . "\n"
                . "Utilizado: R$ " . number_format($used, 2, ',', '.') . " ({$usedPercent}%)\n"
                . "Disponivel: R$ " . number_format($available, 2, ',', '.') . "\n\n";

            if ($usedPercent >= 90) {
                $message .= "Seu limite esta quase completo! Regularize para continuar comprando.";
            } else {
                $message .= "Regularize seus pagamentos para manter o limite disponivel.";
            }

            $this->messageSender->send($phone, $message);
        }

        $this->logger->info('Credit reminders sent', ['customers' => count($results)]);
    }

    /**
     * @return int[]
     */
    private function getB2BGroupIds(): array
    {
        $groups = [];

        $wholesale = $this->scopeConfig->getValue(
            'grupoawamotos_b2b/customer_groups/wholesale_group',
            ScopeInterface::SCOPE_STORE
        );
        $vip = $this->scopeConfig->getValue(
            'grupoawamotos_b2b/customer_groups/vip_group',
            ScopeInterface::SCOPE_STORE
        );
        $revendedor = $this->scopeConfig->getValue(
            'grupoawamotos_b2b/customer_groups/revendedor_group',
            ScopeInterface::SCOPE_STORE
        );

        if ($wholesale) {
            $groups[] = (int) $wholesale;
        }
        if ($vip) {
            $groups[] = (int) $vip;
        }
        if ($revendedor) {
            $groups[] = (int) $revendedor;
        }

        return $groups ?: [0]; // Prevent empty IN clause
    }

    private function getCustomerPhone(int $customerId): ?string
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $phoneAttr = $customer->getCustomAttribute('telephone');
            return $phoneAttr ? (string) $phoneAttr->getValue() : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
