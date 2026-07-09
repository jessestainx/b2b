<?php

declare(strict_types=1);


// IP Whitelist — acesso restrito a conexões locais e túneis SSH autorizados.
(static function(): void {
    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $xff      = (string) ($_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    $allowed  = ['127.0.0.1', '::1'];
    $isLocal  = in_array($clientIp, $allowed, true);
    $xffOk    = ($xff === '' || in_array(trim(explode(',', $xff)[0]), $allowed, true));
    if (!$isLocal || !$xffOk) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Acesso restrito. Use túnel SSH autorizado.']);
        exit;
    }
})();
/**
 * Diagnóstico Exportar Clientes — use no PC do Sectra antes de Exportar/Importar.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Surrogate-Control: no-store');
header('CDN-Cache-Control: no-store');

$envFile = dirname(__DIR__) . '/app/etc/env.php';
if (!is_readable($envFile)) {
    http_response_code(503);
    echo json_encode(['error' => 'env.php unreadable']);
    exit;
}

$env = require $envFile;
$db = $env['db']['connection']['default'] ?? null;
if (!$db) {
    http_response_code(503);
    echo json_encode(['error' => 'db config missing']);
    exit;
}

$dsn = isset($db['unix_socket'])
    ? "mysql:unix_socket={$db['unix_socket']};dbname={$db['dbname']};charset=utf8"
    : "mysql:host={$db['host']};dbname={$db['dbname']};charset=utf8";

try {
    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['error' => 'mysql: ' . $e->getMessage()]);
    exit;
}

$remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
// #region agent log
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS sectra_http_probe_hit (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        endpoint VARCHAR(64) NOT NULL,
        remote_addr VARCHAR(45) NOT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        query_string VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);
$probeStmt = $pdo->prepare(
    'INSERT INTO sectra_http_probe_hit (endpoint, remote_addr, user_agent, query_string)
     VALUES (:endpoint, :remote, :ua, :qs)'
);
$probeStmt->execute([
    ':endpoint' => 'sectra-export-status',
    ':remote' => $remoteAddr,
    ':ua' => mb_substr($userAgent, 0, 255),
    ':qs' => mb_substr($queryString, 0, 255),
]);
// #endregion

$exportIds = $pdo->query(
    "SELECT customer_id FROM oc_customer WHERE code = '' AND status = 1 ORDER BY customer_id"
)->fetchAll(PDO::FETCH_COLUMN);

$clients = [];
$allMagentoReady = true;
foreach ($exportIds as $exportId) {
    $exportId = (int) $exportId;
    $row = $pdo->prepare(
        'SELECT customer_id, firstname, lastname, email, telephone, code, status, address_id,
                JSON_UNQUOTE(JSON_EXTRACT(custom_field, \'$."6"\')) AS cnpj
         FROM oc_customer WHERE customer_id = ?'
    );
    $row->execute([$exportId]);
    $customer = $row->fetch(PDO::FETCH_ASSOC) ?: [];

    $b2bStmt = $pdo->prepare('SELECT 1 FROM oc_customer_b2b_confirmed WHERE customer_id = ?');
    $b2bStmt->execute([$exportId]);
    $b2bConfirmed = (bool) $b2bStmt->fetchColumn();

    $addrStmt = $pdo->prepare('SELECT COUNT(*) FROM oc_address WHERE customer_id = ?');
    $addrStmt->execute([$exportId]);
    $addressCount = (int) $addrStmt->fetchColumn();

    $tel = preg_replace('/\D/', '', (string) ($customer['telephone'] ?? ''));
    $ready = $b2bConfirmed
        && $addressCount > 0
        && strlen($tel) >= 10
        && trim((string) ($customer['cnpj'] ?? '')) !== ''
        && (string) ($customer['code'] ?? '') === '';

    if (!$ready) {
        $allMagentoReady = false;
    }

    $clients[] = [
        'export_customer_id' => $exportId,
        'name' => trim(($customer['firstname'] ?? '') . ' ' . ($customer['lastname'] ?? '')),
        'cnpj' => $customer['cnpj'] ?? '',
        'code' => $customer['code'] ?? '',
        'b2b_confirmed' => $b2bConfirmed,
        'addresses' => $addressCount,
        'magento_ready' => $ready,
    ];
}

$ocOrder = (int) $pdo->query('SELECT COUNT(*) FROM oc_order')->fetchColumn();

$exportCompleted = array_reduce(
    $clients,
    static fn (bool $carry, array $c): bool => $carry || trim((string) ($c['code'] ?? '')) !== '',
    false
);

$expectedIds = [2541, 11134, 16161, 17421, 18202, 18771];
$actualIds = array_map('intval', $exportIds);
$idsMatch = ($actualIds === $expectedIds);

$response = [
    'status' => 'ok',
    'magento_ready_for_export' => $allMagentoReady,
    'export_count' => count($exportIds),
    'exportar_clientes_expected' => count($exportIds) . ' de ' . count($exportIds),
    'oc_order' => $ocOrder,
    'importar_pedidos_expected' => $ocOrder . ' de ' . $ocOrder,
    'clients' => $clients,
    'export_completed' => $exportCompleted,
    'blocker' => $exportCompleted
        ? null
        : ($allMagentoReady && $idsMatch
            ? 'PENDENTE NO SECTRA DESKTOP: execute Exportar Clientes (6 de 6). Magento OK; oc_customer.code ainda vazio.'
            : 'Magento fila export inconsistente — rode cron ou verifique MySQL OpenCardB2B.'),
    'sectra_step_1' => 'OpenCardB2B → Exportar Clientes / Cadastro de Cliente (' . count($exportIds) . ' clientes)',
    'sectra_step_2' => 'OpenCardB2B → Importar Pedidos AWA (' . $ocOrder . ' pedidos)',
    'warning' => 'NÃO execute Importar Pedidos antes de Exportar Clientes concluir.',
    'after_export_check' => 'oc_customer.code deve ser preenchido (não vazio) nos 6 IDs',
    'cadastro_sql_fallback' => 'https://awamotos.com/sectra-cadastro-sql.php',
    'mysql_pin_test_url' => 'https://awamotos.com/sectra-mysql-pin.php',
    'export_ids_are_native_bridge' => $idsMatch,
    'expected_export_ids' => $expectedIds,
    'actual_export_ids' => $actualIds,
    'sectra_export_query_url' => 'https://awamotos.com/sectra-export-query.php',
    'mysql_check_hint' => 'No PC Sectra: abra sectra_export_query_url — deve listar exatamente os 6 IDs nativos acima',
];

// #region agent log
$logPath = dirname(__DIR__) . '/.cursor/debug-fc1127.log';
$payload = [
    'sessionId' => 'fc1127',
    'runId' => 'export-status',
    'hypothesisId' => 'H',
    'location' => 'sectra-export-status.php',
    'message' => 'Export status probe',
    'data' => [
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
        'magento_ready' => $allMagentoReady,
        'export_ids' => array_map('intval', $exportIds),
        'codes_empty' => array_column($clients, 'code'),
        'export_completed' => $exportCompleted,
        'blocker' => $response['blocker'] ?? null,
    ],
    'timestamp' => (int) (microtime(true) * 1000),
];
@file_put_contents($logPath, json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
// #endregion

// #region agent log
$agentLogPath = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-608fd0.log';
@file_put_contents($agentLogPath, json_encode([
    'sessionId' => '608fd0',
    'runId' => 'sectra-pc-export-status',
    'hypothesisId' => 'H8',
    'location' => 'sectra-export-status.php',
    'message' => 'Sectra export status endpoint accessed',
    'data' => [
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
        'export_count' => count($exportIds),
        'actual_export_ids' => $actualIds,
        'oc_order' => $ocOrder,
        'export_completed' => $exportCompleted,
    ],
    'timestamp' => (int) (microtime(true) * 1000),
], JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
// #endregion

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
