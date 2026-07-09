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
 * Verifica se o MySQL do Sectra OpenCardB2B aponta para a nuvem Magento.
 * Abra no browser do PC Sectra e compare o PIN com o configurado no Sectra.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$envFile = dirname(__DIR__) . '/app/etc/env.php';
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

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS sectra_bridge_ping (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        pin VARCHAR(16) NOT NULL,
        updated_at DATETIME NOT NULL,
        last_remote VARCHAR(45) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$pin = strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
$remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
$forwardedFor = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
$cfConnectingIp = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
$runtimeNonce = bin2hex(random_bytes(4));

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
    ':endpoint' => 'sectra-mysql-pin',
    ':remote' => $remote,
    ':ua' => mb_substr($userAgent, 0, 255),
    ':qs' => mb_substr($queryString, 0, 255),
]);
// #endregion

$stmt = $pdo->prepare(
    'INSERT INTO sectra_bridge_ping (id, pin, updated_at, last_remote)
     VALUES (1, :pin, NOW(), :remote)
     ON DUPLICATE KEY UPDATE pin = VALUES(pin), updated_at = VALUES(updated_at), last_remote = VALUES(last_remote)'
);
$stmt->execute([':pin' => $pin, ':remote' => $remote]);

$exportCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM oc_customer WHERE code = '' AND status = 1"
)->fetchColumn();
$ocOrder = (int) $pdo->query('SELECT COUNT(*) FROM oc_order')->fetchColumn();

$response = [
    'code_version' => 'sectra-mysql-pin-v2',
    'status' => 'ok',
    'runtime_nonce' => $runtimeNonce,
    'cloud_mysql_pin' => $pin,
    'pin_updated_at' => date('c'),
    'your_ip' => $remote,
    'forwarded_for' => $forwardedFor,
    'cf_connecting_ip' => $cfConnectingIp,
    'magento_db_host' => $db['host'] ?? 'socket',
    'magento_db_name' => $db['dbname'] ?? '',
    'sectra_must_use_host' => '72.61.94.22',
    'sectra_must_use_port' => 3306,
    'sectra_must_use_database' => 'magento',
    'verify_in_sectra_mysql' => "SELECT pin, updated_at FROM sectra_bridge_ping WHERE id = 1",
    'expected_pin' => $pin,
    'export_queue_count' => $exportCount,
    'oc_order_count' => $ocOrder,
    'diagnosis' => $exportCount > 0
        ? "Nuvem OK: fila export = {$exportCount}. Se Sectra mostra 0 de 0, MySQL do Sectra NAO aponta para esta nuvem."
        : 'Nuvem fila export = 0. Verifique bridge/cron.',
    'next_urls' => [
        'export_query' => 'https://awamotos.com/sectra-export-query.php',
        'export_status' => 'https://awamotos.com/sectra-export-status.php',
    ],
];

// #region agent log
$logPath = dirname(__DIR__) . '/.cursor/debug-fc1127.log';
@file_put_contents($logPath, json_encode([
    'sessionId' => 'fc1127',
    'runId' => 'mysql-pin',
    'hypothesisId' => 'AK',
    'location' => 'sectra-mysql-pin.php',
    'message' => 'MySQL pin generated',
    'data' => [
        'remote_addr' => $remote,
        'pin' => $pin,
        'export_count' => $exportCount,
        'oc_order' => $ocOrder,
    ],
    'timestamp' => (int) (microtime(true) * 1000),
], JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
// #endregion

// #region agent log
$agentLogPath = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-608fd0.log';
@file_put_contents($agentLogPath, json_encode([
    'sessionId' => '608fd0',
    'runId' => 'sectra-pc-mysql-pin',
    'hypothesisId' => 'H8',
    'location' => 'sectra-mysql-pin.php',
    'message' => 'Sectra mysql pin endpoint accessed',
    'data' => [
        'remote_addr' => $remote,
        'http_host' => $_SERVER['HTTP_HOST'] ?? '',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'forwarded_for' => $forwardedFor,
        'cf_connecting_ip' => $cfConnectingIp,
        'runtime_nonce' => $runtimeNonce,
        'export_queue_count' => $exportCount,
        'oc_order_count' => $ocOrder,
        'expected_pin' => $pin,
        'code_version' => 'sectra-mysql-pin-v2',
    ],
    'timestamp' => (int) (microtime(true) * 1000),
], JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
// #endregion

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
