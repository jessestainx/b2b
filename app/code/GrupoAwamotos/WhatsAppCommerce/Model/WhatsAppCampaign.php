<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Model;

use GrupoAwamotos\WhatsAppCommerce\Api\CampaignInterface;
use GrupoAwamotos\WhatsAppCommerce\Helper\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class WhatsAppCampaign implements CampaignInterface
{
    private const MAX_BAILEYS_BATCH = 50;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly MessageSender $messageSender,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function sendBroadcast(string $segment, string $message, int $batchSize = 50): array
    {
        if (!$this->config->isEnabled()) {
            return ['success' => false, 'message' => 'WhatsApp Commerce desabilitado.'];
        }

        if (empty(trim($message))) {
            return ['success' => false, 'message' => 'Mensagem nao pode ser vazia.'];
        }

        $validSegments = ['all_optin', 'recent_90d', 'b2b'];
        if (!in_array($segment, $validSegments, true)) {
            return [
                'success' => false,
                'message' => 'Segmento invalido. Use: ' . implode(', ', $validSegments),
            ];
        }

        $batchSize = min($batchSize, self::MAX_BAILEYS_BATCH);

        try {
            $phones = $this->getPhonesForSegment($segment, $batchSize);

            if (empty($phones)) {
                return [
                    'success' => false,
                    'message' => "Nenhum destinatario encontrado para o segmento '{$segment}'.",
                ];
            }

            $sent = 0;
            $failed = 0;

            foreach ($phones as $phone) {
                try {
                    $this->messageSender->send($phone, $message);
                    $sent++;
                } catch (\Exception $e) {
                    $failed++;
                    $this->logger->warning('Campaign send failed', [
                        'phone' => substr($phone, 0, 6) . '****',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->logger->info('Campaign broadcast completed', [
                'segment' => $segment,
                'sent' => $sent,
                'failed' => $failed,
                'batch_size' => $batchSize,
            ]);

            return [
                'success' => true,
                'segment' => $segment,
                'sent' => $sent,
                'failed' => $failed,
                'total_recipients' => count($phones),
                'message' => "Campanha enviada: {$sent} de " . count($phones) . " mensagens"
                    . ($failed > 0 ? " ({$failed} falhas)" : ''),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Campaign broadcast error', [
                'segment' => $segment,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Erro ao enviar campanha.'];
        }
    }

    /**
     * @inheritDoc
     */
    public function getSegmentStats(): array
    {
        try {
            $connection = $this->resource->getConnection();

            $optinAttrId = $this->getOptinAttributeId($connection);
            if ($optinAttrId === 0) {
                return [
                    'all_optin' => 0,
                    'recent_90d' => 0,
                    'b2b' => 0,
                    'message' => 'Atributo whatsapp_optin nao encontrado.',
                ];
            }

            $allOptin = (int) $connection->fetchOne(
                $connection->select()
                    ->from(
                        ['cev' => $connection->getTableName('customer_entity_varchar')],
                        [new \Zend_Db_Expr('COUNT(DISTINCT cev.entity_id)')]
                    )
                    ->where('cev.attribute_id = ?', $optinAttrId)
                    ->where('cev.value = ?', '1')
            );

            $recent90d = (int) $connection->fetchOne(
                $connection->select()
                    ->from(
                        ['so' => $connection->getTableName('sales_order')],
                        [new \Zend_Db_Expr('COUNT(DISTINCT so.customer_id)')]
                    )
                    ->join(
                        ['cev' => $connection->getTableName('customer_entity_varchar')],
                        'cev.entity_id = so.customer_id',
                        []
                    )
                    ->where('cev.attribute_id = ?', $optinAttrId)
                    ->where('cev.value = ?', '1')
                    ->where('so.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)')
                    ->where('so.state NOT IN (?)', ['canceled'])
            );

            $b2bGroupIds = $this->getB2BGroupIds();
            $b2b = 0;
            if (!empty($b2bGroupIds)) {
                $b2b = (int) $connection->fetchOne(
                    $connection->select()
                        ->from(
                            ['ce' => $connection->getTableName('customer_entity')],
                            [new \Zend_Db_Expr('COUNT(DISTINCT ce.entity_id)')]
                        )
                        ->join(
                            ['cev' => $connection->getTableName('customer_entity_varchar')],
                            'cev.entity_id = ce.entity_id',
                            []
                        )
                        ->where('cev.attribute_id = ?', $optinAttrId)
                        ->where('cev.value = ?', '1')
                        ->where('ce.group_id IN (?)', $b2bGroupIds)
                );
            }

            return [
                'all_optin' => $allOptin,
                'recent_90d' => $recent90d,
                'b2b' => $b2b,
                'message' => sprintf(
                    "Segmentos: Todos opt-in=%d | Compraram 90d=%d | B2B=%d",
                    $allOptin,
                    $recent90d,
                    $b2b
                ),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Campaign getSegmentStats error', ['error' => $e->getMessage()]);
            return ['error' => true, 'message' => 'Erro ao consultar segmentos.'];
        }
    }

    /**
     * Get phone numbers for a given segment
     *
     * @param string $segment
     * @param int $limit
     * @return string[]
     */
    private function getPhonesForSegment(string $segment, int $limit): array
    {
        $connection = $this->resource->getConnection();
        $optinAttrId = $this->getOptinAttributeId($connection);
        $phoneAttrId = $this->getPhoneAttributeId($connection);

        if ($optinAttrId === 0 || $phoneAttrId === 0) {
            return [];
        }

        $select = $connection->select()
            ->from(
                ['cev_phone' => $connection->getTableName('customer_entity_varchar')],
                ['phone' => 'cev_phone.value']
            )
            ->join(
                ['cev_optin' => $connection->getTableName('customer_entity_varchar')],
                'cev_optin.entity_id = cev_phone.entity_id',
                []
            )
            ->where('cev_phone.attribute_id = ?', $phoneAttrId)
            ->where('cev_optin.attribute_id = ?', $optinAttrId)
            ->where('cev_optin.value = ?', '1')
            ->where('cev_phone.value IS NOT NULL')
            ->where("cev_phone.value != ''");

        if ($segment === 'recent_90d') {
            $select->join(
                ['so' => $connection->getTableName('sales_order')],
                'so.customer_id = cev_phone.entity_id',
                []
            )
            ->where('so.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)')
            ->where('so.state NOT IN (?)', ['canceled'])
            ->group('cev_phone.entity_id');
        } elseif ($segment === 'b2b') {
            $b2bGroupIds = $this->getB2BGroupIds();
            if (empty($b2bGroupIds)) {
                return [];
            }
            $select->join(
                ['ce' => $connection->getTableName('customer_entity')],
                'ce.entity_id = cev_phone.entity_id',
                []
            )
            ->where('ce.group_id IN (?)', $b2bGroupIds);
        }

        $select->limit($limit);

        $phones = $connection->fetchCol($select);

        return array_filter($phones, fn(string $p) => strlen(preg_replace('/\D/', '', $p)) >= 10);
    }

    /**
     * Get the EAV attribute ID for whatsapp_optin
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return int
     */
    private function getOptinAttributeId(\Magento\Framework\DB\Adapter\AdapterInterface $connection): int
    {
        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('eav_attribute'), ['attribute_id'])
                ->where('attribute_code = ?', 'whatsapp_optin')
                ->where('entity_type_id = ?', 1)
                ->limit(1)
        );
    }

    /**
     * Get the EAV attribute ID for telephone
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return int
     */
    private function getPhoneAttributeId(\Magento\Framework\DB\Adapter\AdapterInterface $connection): int
    {
        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('eav_attribute'), ['attribute_id'])
                ->where('attribute_code = ?', 'telephone')
                ->where('entity_type_id = ?', 1)
                ->limit(1)
        );
    }

    /**
     * Get B2B customer group IDs from config
     *
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

        return $groups;
    }
}
