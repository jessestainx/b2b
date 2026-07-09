<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Console\Command;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\B2BClientRegistration;
use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Diagnoses and reports the Sectra B2B integration status.
 *
 * Shows:
 *  - GR_INTEGRACAOVALIDADOR registration stats (registered vs unregistered)
 *  - Pending orders whose customers are not registered in Sectra
 *  - OpenCart bridge table health (oc_customer_id_map, oc_customer_b2b_confirmed)
 *  - Latest SQL export file path
 *  - VE_PEDIDO status distribution for web orders
 */
class SectraStatusCommand extends Command
{
    private const ORIGEM_CLIENTE = '7D4C6FBD-62CF-427F-A0ED-3C06602F05D7';
    private const ORIGEM_PROSPECT = '753ADB36-27F8-4910-84BB-D7E26279C5A8';

    public function __construct(
        private readonly ConnectionInterface $erpConnection,
        private readonly Helper $helper,
        private readonly B2BClientRegistration $b2bRegistration,
        private readonly ResourceConnection $resourceConnection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('erp:sectra:status')
            ->setAliases(['erp:sectra:diagnose'])
            ->setDescription('Status completo da integração Sectra B2B (GR_INTEGRACAOVALIDADOR + oc_* tables + pedidos)')
            ->addOption('orders', 'o', InputOption::VALUE_NONE, 'Mostra pedidos pendentes com clientes não registrados')
            ->addOption('prospect', 'p', InputOption::VALUE_NONE, 'Diagnóstico Importar Clientes Prospect (oc_pre_registration × CLIENTESPRE)')
            ->addOption('sql', 's', InputOption::VALUE_NONE, 'Gera SQL para registrar clientes pendentes')
            ->addOption('export-sql', null, InputOption::VALUE_NONE, 'Gera SQL Cadastro de Cliente só para CHAVEs dos pedidos em oc_order')
            ->addOption('batch-size', null, InputOption::VALUE_OPTIONAL, 'Tamanho do lote para --sql', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->helper->isEnabled()) {
            $output->writeln('<error>Integração ERP está desabilitada.</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>╔══════════════════════════════════════════════════╗</info>');
        $output->writeln('<info>║     STATUS DA INTEGRAÇÃO SECTRA B2B             ║</info>');
        $output->writeln('<info>╚══════════════════════════════════════════════════╝</info>');
        $output->writeln('');

        $this->showRegistrationStats($output);
        $this->showOrderBridgeStats($output);
        $this->showVePedidoStats($output);

        if ($input->getOption('prospect')) {
            $this->showProspectImportStats($output);
        }

        if ($input->getOption('orders')) {
            $this->showPendingOrdersWithUnregisteredClients($output);
            $this->showExportClientesQueue($output);
            $this->showOrderImportReadiness($output);
        }

        if ($input->getOption('sql')) {
            $batchSize = max(1, (int) $input->getOption('batch-size'));
            $this->generatePendingSQL($batchSize, $output);
        }

        if ($input->getOption('export-sql')) {
            $this->generateOrderClientsExportSQL($output);
        }

        $this->showSqlFiles($output);

        return Command::SUCCESS;
    }

    private function showRegistrationStats(OutputInterface $output): void
    {
        $output->writeln('<comment>── GR_INTEGRACAOVALIDADOR (Validador Sectra) ──</comment>');

        try {
            $conn = $this->erpConnection;

            $totalRegistered = (int) $conn->fetchColumn(
                "SELECT COUNT(*) FROM GR_INTEGRACAOVALIDADOR WHERE INTEGRACAOORIGEM = :o",
                [':o' => self::ORIGEM_CLIENTE]
            );

            $lastSync = $conn->fetchOne(
                "SELECT TOP 1 DTSINCRONIZACAO FROM GR_INTEGRACAOVALIDADOR
                 WHERE INTEGRACAOORIGEM = :o ORDER BY DTSINCRONIZACAO DESC",
                [':o' => self::ORIGEM_CLIENTE]
            );
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>  Erro ao consultar ERP: %s</error>', $e->getMessage()));
            $output->writeln('');
            return;
        }

        $magentoConn = $this->resourceConnection->getConnection();

        // Total B2B customers with ERP codes in Magento
        $totalMagento = (int) $magentoConn->fetchOne(
            $magentoConn->select()
                ->from('grupoawamotos_erp_entity_map', ['COUNT(*)'])
                ->where('entity_type = ?', 'customer')
                ->where('erp_code > ?', '0')
        );

        $unregistered = max(0, $totalMagento - $totalRegistered);
        $pct = $totalMagento > 0 ? round(($totalRegistered / $totalMagento) * 100, 1) : 0;

        $output->writeln(sprintf('  Registrados no Sectra : <info>%d</info>', $totalRegistered));
        $output->writeln(sprintf('  Clientes B2B Magento  : <info>%d</info>', $totalMagento));
        $output->writeln(sprintf(
            '  NÃO registrados       : %s',
            $unregistered > 0
                ? sprintf('<error>%d</error> (%.1f%% ainda faltam)', $unregistered, 100 - $pct)
                : '<info>0 (todos registrados!)</info>'
        ));

        if ($lastSync) {
            $dt = $lastSync['DTSINCRONIZACAO'] ?? reset($lastSync) ?? '?';
            $output->writeln(sprintf('  Último sync           : <comment>%s</comment>', $dt));
        }

        $output->writeln('');
    }

    private function showOrderBridgeStats(OutputInterface $output): void
    {
        $output->writeln('<comment>── OpenCart Bridge (oc_* tables) ──</comment>');

        $magentoConn = $this->resourceConnection->getConnection();

        try {
            $ocMap = (int) $magentoConn->fetchOne(
                $magentoConn->select()->from('oc_customer_id_map', ['COUNT(*)'])
            );
            $ocConfirmed = (int) $magentoConn->fetchOne(
                $magentoConn->select()->from('oc_customer_b2b_confirmed', ['COUNT(*)'])
            );
            $output->writeln(sprintf('  oc_customer_id_map       : <info>%d</info> mapeamentos', $ocMap));
            $output->writeln(sprintf('  oc_customer_b2b_confirmed: <info>%d</info> confirmados', $ocConfirmed));
        } catch (\Exception $e) {
            $output->writeln('  <comment>oc_* tables não acessíveis: ' . $e->getMessage() . '</comment>');
        }

        // Orders in oc_order view (Magento MySQL bridge)
        try {
            $ocOrders = (int) $magentoConn->fetchOne('SELECT COUNT(*) FROM oc_order');
            $output->writeln(sprintf('  oc_order (view ERP)      : <info>%d</info> pedidos visíveis', $ocOrders));
        } catch (\Exception $e) {
            $output->writeln('  <comment>oc_order: ' . $e->getMessage() . '</comment>');
        }

        try {
            $preRegTotal = (int) $magentoConn->fetchOne('SELECT COUNT(*) FROM oc_pre_registration');
            $prospectCodes = $this->b2bRegistration->getProspectRegisteredClientCodes();
            $available = $this->countPreRegistrationAvailableForSectra($magentoConn, $prospectCodes);
            $output->writeln(sprintf(
                '  oc_pre_registration      : <info>%d</info> total | <info>%d</info> disponíveis p/ Sectra',
                $preRegTotal,
                $available
            ));
        } catch (\Exception $e) {
            $output->writeln('  <comment>oc_pre_registration: ' . $e->getMessage() . '</comment>');
        }

        $output->writeln('');
    }

    private function showProspectImportStats(OutputInterface $output): void
    {
        $output->writeln('<comment>── Importar Clientes Prospect (Sectra OpenCardB2B) ──</comment>');

        $magentoConn = $this->resourceConnection->getConnection();

        try {
            $total = (int) $magentoConn->fetchOne('SELECT COUNT(*) FROM oc_pre_registration');
            $prospectCodes = $this->b2bRegistration->getProspectRegisteredClientCodes();
            $clientesPreCount = count($prospectCodes);
            $available = $this->countPreRegistrationAvailableForSectra($magentoConn, $prospectCodes);
            $invalidDoc = (int) $magentoConn->fetchOne(
                "SELECT COUNT(*) FROM oc_pre_registration
                 WHERE LENGTH(REPLACE(REPLACE(REPLACE(custom_field, '.', ''), '/', ''), '-', '')) < 11"
            );

            $output->writeln(sprintf('  Total oc_pre_registration     : <info>%d</info>', $total));
            $output->writeln(sprintf('  Já em CLIENTESPRE (validador) : <info>%d</info>', $clientesPreCount));
            $output->writeln(sprintf('  Disponíveis p/ importação     : <info>%d</info>', $available));
            $output->writeln(sprintf('  CNPJ/CPF inválido             : %s', $invalidDoc > 0 ? "<error>{$invalidDoc}</error>" : '<info>0</info>'));

            if ($available === 0 && $total > 0) {
                $output->writeln('  <comment>Todos os prospects já foram importados no Sectra.</comment>');
            } elseif ($available === 0 && $total === 0) {
                $output->writeln('  <error>Sectra verá "0 de 0" — verifique MySQL OpenCardB2B (host remoto Magento).</error>');
            } else {
                $output->writeln('  <info>Sectra deve exibir aproximadamente "' . $available . ' de ' . $available . '" ao importar.</info>');
            }

            $output->writeln('');
            $output->writeln('  <comment>MySQL OpenCardB2B no Sectra deve apontar para:</comment>');
            $output->writeln('    Host: <info>72.61.94.22</info> (ou srv1113343.hstgr.cloud) | Porta: <info>3306</info> | DB: <info>magento</info>');
            $output->writeln('  <comment>Teste no Sectra/HeidiSQL:</comment>');
            $output->writeln('    <info>SELECT COUNT(*) FROM oc_pre_registration;</info> → deve retornar ~' . $total);
        } catch (\Exception $e) {
            $output->writeln('  <error>Erro: ' . $e->getMessage() . '</error>');
        }

        $output->writeln('');
    }

    /**
     * @param list<int> $prospectCodes
     */
    private function countPreRegistrationAvailableForSectra(
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
        array $prospectCodes
    ): int {
        if ($prospectCodes === []) {
            return (int) $connection->fetchOne('SELECT COUNT(*) FROM oc_pre_registration');
        }

        $clientesPre = implode(',', array_map('intval', $prospectCodes));

        return (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM oc_pre_registration WHERE customer_id NOT IN ({$clientesPre})"
        );
    }

    private function showExportClientesQueue(OutputInterface $output): void
    {
        $output->writeln('<comment>── Exportar Clientes (fila Sectra) ──</comment>');

        $magentoConn = $this->resourceConnection->getConnection();

        try {
            $exportIds = array_map(
                'intval',
                $magentoConn->fetchCol(
                    "SELECT customer_id FROM oc_customer
                     WHERE code = '' AND status = 1
                     ORDER BY customer_id"
                )
            );
            $totalEmptyCode = (int) $magentoConn->fetchOne(
                "SELECT COUNT(*) FROM oc_customer WHERE code = '' OR code IS NULL"
            );

            $output->writeln(sprintf(
                '  Fila exportável (code=\'\' + status=1): <info>%d</info> (de %d com code vazio)',
                count($exportIds),
                $totalEmptyCode
            ));

            if ($exportIds !== []) {
                $output->writeln(sprintf(
                    '  customer_id na fila: <info>%s</info>',
                    implode(', ', $exportIds)
                ));
                $output->writeln('  <info>Sectra deve exibir ~"' . count($exportIds) . ' de ' . count($exportIds) . '" no Exportar Clientes.</info>');

                $missingB2b = [];
                foreach ($exportIds as $exportId) {
                    $confirmed = $magentoConn->fetchOne(
                        'SELECT 1 FROM oc_customer_b2b_confirmed WHERE customer_id = ?',
                        [$exportId]
                    );
                    if (!$confirmed) {
                        $missingB2b[] = $exportId;
                    }
                }
                if ($missingB2b === []) {
                    $output->writeln('  oc_customer_b2b_confirmed: <info>✓ todos os IDs da fila</info>');
                } else {
                    $output->writeln(sprintf(
                        '  oc_customer_b2b_confirmed ausente: <error>%s</error> (Exportar Clientes pode ignorar estes)',
                        implode(', ', $missingB2b)
                    ));
                }
            } else {
                $output->writeln('  <error>Fila vazia — Sectra verá "0 de 0" no Exportar Clientes.</error>');
                $output->writeln('  <comment>Rode: sudo -u www-data php bin/magento cron:run --group=default</comment>');
            }
        } catch (\Exception $e) {
            $output->writeln('  <error>Erro: ' . $e->getMessage() . '</error>');
        }

        $output->writeln('');
    }

    private function showOrderImportReadiness(OutputInterface $output): void
    {
        $output->writeln('<comment>── Prontidão Importar Pedidos AWA ──</comment>');

        $magentoConn = $this->resourceConnection->getConnection();

        try {
            $orders = $magentoConn->fetchAll(
                'SELECT order_id, customer_id, firstname, total FROM oc_order ORDER BY order_id'
            );
            $notReady = 0;
            $notInErp = 0;

            foreach ($orders as $row) {
                $chave = (int) ($row['customer_id'] ?? 0);
                $orderId = (int) ($row['order_id'] ?? 0);

                if (!$this->b2bRegistration->isClientReadyForSectraOrderImport($chave)) {
                    $notReady++;
                }

                try {
                    $erpRow = $this->erpConnection->query(
                        "SELECT TOP 1 CODIGO FROM VE_PEDIDO WHERE CAST(PEDIDOWEB AS VARCHAR(50)) = ?",
                        [(string) $orderId]
                    );
                    if ($erpRow === []) {
                        $notInErp++;
                    }
                } catch (\Exception) {
                    $notInErp++;
                }
            }

            $total = count($orders);
            $output->writeln(sprintf('  Pedidos em oc_order           : <info>%d</info>', $total));
            $output->writeln(sprintf(
                '  Clientes prontos p/ import    : %s',
                $notReady === 0
                    ? '<info>' . $total . '/' . $total . '</info>'
                    : '<error>' . ($total - $notReady) . '/' . $total . '</error>'
            ));
            $output->writeln(sprintf(
                '  Ainda não em VE_PEDIDO        : <info>%d</info> (execute Importar Pedidos AWA no Sectra)',
                $notInErp
            ));

            if ($total > 0 && $notInErp > 0) {
                $chaves = array_values(array_unique(array_map(
                    static fn (array $row): int => (int) ($row['customer_id'] ?? 0),
                    $orders
                )));
                $blockingClients = [];
                $nativeWithoutValidator = [];
                foreach ($chaves as $chave) {
                    if ($chave <= 0) {
                        continue;
                    }

                    if (!$this->b2bRegistration->isClientReadyForSectraOrderImport($chave)) {
                        $blockingClients[] = $chave;
                        continue;
                    }

                    if (!$this->b2bRegistration->isClientRegistered($chave)) {
                        $nativeWithoutValidator[] = $chave;
                    }
                }

                if ($blockingClients !== []) {
                    $output->writeln(sprintf(
                        '  <error>Clientes ainda bloqueados para importação: %s</error>',
                        implode(', ', $blockingClients)
                    ));
                    $output->writeln('  <comment>Execute Exportar Clientes ou aplique o SQL de cadastro antes de importar pedidos.</comment>');
                } elseif ($notReady === 0) {
                    $output->writeln('  <info>Todos os clientes estão prontos — execute Importar Pedidos AWA no Sectra.</info>');
                }

                if ($nativeWithoutValidator !== []) {
                    $output->writeln(sprintf(
                        '  <comment>Clientes nativos ERP liberados sem linha 7D4C6FBD: %s</comment>',
                        implode(', ', $nativeWithoutValidator)
                    ));
                }

                $output->writeln(sprintf(
                    '  <comment>Teste MySQL: SELECT order_id, customer_id, total FROM oc_order; → %d linhas</comment>',
                    $total
                ));
            }
        } catch (\Exception $e) {
            $output->writeln('  <error>Erro: ' . $e->getMessage() . '</error>');
        }

        $output->writeln('');
    }

    private function showVePedidoStats(OutputInterface $output): void
    {
        $output->writeln('<comment>── VE_PEDIDO — Status dos Pedidos Web ──</comment>');

        try {
            $rows = $this->erpConnection->query(
                "SELECT STATUS, COUNT(*) AS TOTAL
                 FROM VE_PEDIDO
                 WHERE PEDIDOWEB IS NOT NULL AND PEDIDOWEB <> ''
                 GROUP BY STATUS ORDER BY TOTAL DESC"
            );

            $statusLabels = [
                'W' => 'Aguardando importação Sectra',
                'A' => 'Aberto / Em andamento',
                'P' => 'Em processamento',
                'F' => 'Faturado',
                'E' => 'Encerrado/Completo',
                'C' => 'Cancelado',
                'D' => 'Em devolução/Holded',
                'T' => 'Transferido',
            ];

            $table = new Table($output);
            $table->setHeaders(['STATUS', 'Descrição', 'Total']);
            foreach ($rows as $row) {
                $status = trim($row['STATUS'] ?? '');
                $label = $statusLabels[$status] ?? '(desconhecido)';
                $table->addRow([$status ?: '(vazio)', $label, $row['TOTAL']]);
            }
            $table->render();
        } catch (\Exception $e) {
            $output->writeln('  <comment>VE_PEDIDO inacessível: ' . $e->getMessage() . '</comment>');
        }

        $output->writeln('');
    }

    private function showPendingOrdersWithUnregisteredClients(OutputInterface $output): void
    {
        $output->writeln('<comment>── Pedidos Pendentes — Prontidão de Clientes ──</comment>');

        $magentoConn = $this->resourceConnection->getConnection();

        try {
            $registeredCodes = array_fill_keys($this->b2bRegistration->getRegisteredClientCodes(), true);

            $erpAttrId = (int) $magentoConn->fetchOne(
                "SELECT ea.attribute_id
                 FROM eav_attribute ea
                 INNER JOIN eav_entity_type et ON et.entity_type_id = ea.entity_type_id
                 WHERE ea.attribute_code = 'erp_code'
                   AND et.entity_type_code = 'customer'"
            );

            if ($erpAttrId <= 0) {
                $output->writeln('  <comment>Atributo erp_code não encontrado.</comment>');
                $output->writeln('');
                return;
            }

            // Pending orders for Sectra pull (not canceled/closed and not marked imported in oc_order_imported)
            $select = $magentoConn->select()
                ->from(['o' => 'sales_order'], ['entity_id', 'increment_id', 'customer_id', 'status', 'state', 'sectra_import_status'])
                ->joinLeft(
                    ['erp_attr' => 'customer_entity_varchar'],
                    'erp_attr.entity_id = o.customer_id'
                    . ' AND erp_attr.attribute_id = ' . $erpAttrId
                    . " AND erp_attr.value REGEXP '^[0-9]+$'",
                    ['customer_erp_code' => 'erp_attr.value']
                )
                ->joinLeft(
                    ['oi' => 'oc_order_imported'],
                    'oi.order_id = (o.entity_id + 200000)',
                    []
                )
                ->where("o.state IN ('new','pending_payment','processing')")
                ->where('oi.order_id IS NULL')
                ->order('o.created_at DESC')
                ->limit(100);

            $orders = $magentoConn->fetchAll($select);

            if (empty($orders)) {
                $output->writeln('  <info>Nenhum pedido pendente encontrado.</info>');
                $output->writeln('');
                return;
            }

            $table = new Table($output);
            $table->setHeaders(['Pedido', 'State/Status', 'Sectra Status', 'ERP Cliente', 'Registrado?']);

            $unregisteredOrders = 0;
            foreach ($orders as $order) {
                $customerErpCode = (int) ($order['customer_erp_code'] ?? 0);

                $registered = $customerErpCode > 0
                    && $this->b2bRegistration->isClientReadyForSectraOrderImport($customerErpCode);
                if (!$registered) {
                    $unregisteredOrders++;
                }

                $table->addRow([
                    $order['increment_id'],
                    sprintf('%s / %s', $order['state'], $order['status']),
                    $order['sectra_import_status'] ?: 'NULL',
                    $customerErpCode ?: '(sem código)',
                    $registered ? '<info>✓ SIM</info>' : '<error>✗ NÃO</error>',
                ]);
            }

            $table->render();
            $output->writeln(sprintf(
                '  Total: <comment>%d pedidos</comment>, <error>%d com cliente não registrado</error>',
                count($orders),
                $unregisteredOrders
            ));
        } catch (\Exception $e) {
            $output->writeln(sprintf('  <error>Erro: %s</error>', $e->getMessage()));
        }

        $output->writeln('');
    }

    private function generatePendingSQL(int $limit, OutputInterface $output): void
    {
        $output->writeln('<comment>── Gerando SQL para Clientes Não Registrados ──</comment>');

        $magentoConn = $this->resourceConnection->getConnection();

        $select = $magentoConn->select()
            ->from('grupoawamotos_erp_entity_map', ['erp_code'])
            ->where('entity_type = ?', 'customer')
            ->where('erp_code > ?', '0')
            ->limit($limit * 3);

        $allCodes = array_unique(array_map('intval', $magentoConn->fetchCol($select)));
        $allCodes = array_filter($allCodes, fn($c) => $c > 0);

        $registeredCodes = array_fill_keys($this->b2bRegistration->getRegisteredClientCodes(), true);
        $unregistered = array_filter($allCodes, fn($c) => !isset($registeredCodes[$c]));
        $unregistered = array_slice(array_values($unregistered), 0, $limit);

        if (empty($unregistered)) {
            $output->writeln('  <info>Todos os clientes já estão registrados!</info>');
            $output->writeln('');
            return;
        }

        $sql = $this->b2bRegistration->generateRegistrationSQL($unregistered);
        $dir = BP . '/var/log';
        $file = $dir . '/sectra_register_clients_' . date('Ymd_His') . '.sql';
        $latest = $dir . '/sectra_register_clients_latest.sql';
        file_put_contents($file, $sql);
        file_put_contents($latest, $sql);

        $output->writeln(sprintf('  Clientes a registrar: <comment>%d</comment>', count($unregistered)));
        $output->writeln(sprintf('  SQL salvo em       : <info>%s</info>', $file));
        $output->writeln(sprintf('  SQL (latest)       : <info>%s</info>', $latest));
        $output->writeln('');
        $output->writeln('<comment>  Execute no SQL Server Management Studio (usuário com INSERT em GR_INTEGRACAOVALIDADOR):</comment>');
        $output->writeln(sprintf('  <comment>sqlcmd -S localhost -d INDUSTRIAL -i "%s"</comment>', basename($latest)));
        $output->writeln('');
    }

    /**
     * SQL de Cadastro de Cliente apenas para CHAVEs dos pedidos visíveis em oc_order.
     * Alternativa quando Exportar Clientes no Sectra desktop não conclui.
     */
    private function generateOrderClientsExportSQL(OutputInterface $output): void
    {
        $output->writeln('<comment>── SQL Cadastro — clientes dos pedidos em oc_order ──</comment>');

        $magentoConn = $this->resourceConnection->getConnection();

        try {
            $chaves = array_map(
                'intval',
                $magentoConn->fetchCol('SELECT DISTINCT customer_id FROM oc_order WHERE customer_id > 0 ORDER BY customer_id')
            );
            $chaves = array_values(array_filter($chaves, static fn (int $id): bool => $id > 0));

            if ($chaves === []) {
                $output->writeln('  <comment>Nenhum pedido em oc_order.</comment>');
                $output->writeln('');
                return;
            }

            $missing = array_values(array_filter(
                $chaves,
                fn (int $chave): bool => !$this->b2bRegistration->isClientRegistered($chave)
            ));

            if ($missing === []) {
                $output->writeln('  <info>Todos os clientes dos pedidos já têm Cadastro no validador.</info>');
                $output->writeln('');
                return;
            }

            $sql = $this->b2bRegistration->generateRegistrationSQL($missing);
            $dir = BP . '/var/log';
            $file = $dir . '/sectra_export_order_clients_' . date('Ymd_His') . '.sql';
            $latest = $dir . '/sectra_export_order_clients_latest.sql';
            file_put_contents($file, $sql);
            file_put_contents($latest, $sql);

            $output->writeln(sprintf('  Clientes sem Cadastro : <comment>%s</comment>', implode(', ', $missing)));
            $output->writeln(sprintf('  SQL salvo em          : <info>%s</info>', $file));
            $output->writeln(sprintf('  SQL (latest)          : <info>%s</info>', $latest));
            $output->writeln('  Download (PC Sectra)  : <info>https://awamotos.com/sectra-cadastro-sql.php</info>');
            $output->writeln('');
            $output->writeln('  <comment>Opção A — Sectra desktop: Exportar Clientes (6 de 6)</comment>');
            $output->writeln('  <comment>Opção B — No PC do Sectra (rede 192.168.x), execute o SQL acima no INDUSTRIAL:</comment>');
            $output->writeln('  <comment>  Utilitários → Editor SQL / SSMS local → banco INDUSTRIAL → usuário JESS</comment>');
            $output->writeln('  <comment>Depois: Importar Pedidos AWA no Sectra (12 pedidos)</comment>');
        } catch (\Exception $e) {
            $output->writeln('  <error>Erro: ' . $e->getMessage() . '</error>');
        }

        $output->writeln('');
    }

    private function showSqlFiles(OutputInterface $output): void
    {
        $dir = BP . '/var/log';
        $latest = $dir . '/sectra_register_clients_latest.sql';
        $legacyLatest = $dir . '/erp_register_clients_pending_latest.sql';
        $orderExportLatest = $dir . '/sectra_export_order_clients_latest.sql';

        $output->writeln('<comment>── Arquivos SQL Pendentes ──</comment>');

        foreach ([$orderExportLatest, $latest, $legacyLatest] as $file) {
            if (!file_exists($file)) {
                continue;
            }
            $size = filesize($file);
            $mtime = date('Y-m-d H:i:s', filemtime($file));
            if ($size > 10) { // Not just the "already registered" comment
                $output->writeln(sprintf(
                    '  <comment>%s</comment> (%d bytes, modificado: %s)',
                    $file,
                    $size,
                    $mtime
                ));
            }
        }

        // Count auto-generated files
        $autoFiles = glob($dir . '/erp_register_clients_auto_*.sql') ?: [];
        if (!empty($autoFiles)) {
            $output->writeln(sprintf(
                '  <comment>%d arquivo(s) auto-gerado(s) em var/log/erp_register_clients_auto_*.sql</comment>',
                count($autoFiles)
            ));
        }

        $output->writeln('');
        $output->writeln('<comment>Comandos úteis:</comment>');
        $output->writeln('  <info>bin/magento erp:sectra:status --prospect</info>     # Diagnóstico Importar Clientes Prospect');
        $output->writeln('  <info>bin/magento erp:sectra:status --orders</info>       # Ver pedidos + prontidão Importar Pedidos AWA');
        $output->writeln('  <info>bin/magento erp:sectra:status --export-sql</info>   # SQL Cadastro só dos 6 clientes dos pedidos');
        $output->writeln('  <info>bin/magento erp:sectra:status --sql</info>          # Gerar SQL para todos os pendentes');
        $output->writeln('  <info>bin/magento erp:client:register --all --generate-sql --save</info>  # Alternativa');
    }
}
