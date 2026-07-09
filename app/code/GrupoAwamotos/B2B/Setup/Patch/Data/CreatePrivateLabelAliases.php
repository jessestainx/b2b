<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Setup\Patch\Data;

use GrupoAwamotos\B2B\Service\PrivateLabelDetector;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Pré-configura os aliases ERP conhecidos e executa o scan inicial
 * para proteger produtos Private Label já existentes no catálogo.
 *
 * Aliases iniciais:
 *   NACIONAL → customer_id 819  (MAM Distribuidora, CNPJ 10.860.222/0001-38)
 *
 * Para adicionar novos clientes Private Label no futuro, insira diretamente:
 *   INSERT INTO grupoawamotos_b2b_erp_alias (alias_name, customer_id, label)
 *   VALUES ('EDILSON', <customer_id>, 'Marca Edilson');
 * e depois rode: php bin/magento b2b:private-label:scan
 */
class CreatePrivateLabelAliases implements DataPatchInterface
{
    private const ALIAS_TABLE = 'grupoawamotos_b2b_erp_alias';

    /** Aliases conhecidos no momento do deploy. */
    private const KNOWN_ALIASES = [
        [
            'alias_name'  => 'NACIONAL',
            'customer_id' => 819,
            'label'       => 'Nacional Moto',
        ],
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly ResourceConnection $resourceConnection,
        private readonly PrivateLabelDetector $detector,
        private readonly LoggerInterface $logger
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName(self::ALIAS_TABLE);

        foreach (self::KNOWN_ALIASES as $alias) {
            $connection->insertOnDuplicate($table, $alias, ['customer_id', 'label']);
            $this->logger->info(sprintf(
                '[PrivateLabel] Alias configurado: %s → customer_id %d (%s)',
                $alias['alias_name'],
                $alias['customer_id'],
                $alias['label'] ?? ''
            ));
        }

        // Scan inicial: protege produtos já existentes no catálogo
        $count = $this->detector->scanAll();
        $this->logger->info(sprintf(
            '[PrivateLabel] Scan inicial concluído: %d produto(s) protegido(s).',
            $count
        ));

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
