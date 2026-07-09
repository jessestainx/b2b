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
 * Diagnóstico rápido OpenCardB2B — use no PC do Sectra (browser ou curl).
 * Retorna contagens que o desktop deve ver ao exportar/importar pedidos.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Surrogate-Control: no-store');

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
    ':endpoint' => 'sectra-bridge-probe',
    ':remote' => $remoteAddr,
    ':ua' => mb_substr($userAgent, 0, 255),
    ':qs' => mb_substr($queryString, 0, 255),
]);
// #endregion

$preReg = (int) $pdo->query('SELECT COUNT(*) FROM oc_pre_registration')->fetchColumn();
$ocOrder = (int) $pdo->query('SELECT COUNT(*) FROM oc_order')->fetchColumn();
$exportQueue = $pdo->query(
    "SELECT customer_id FROM oc_customer WHERE code = '' AND status = 1 ORDER BY customer_id"
)->fetchAll(PDO::FETCH_COLUMN);
$orderSample = $pdo->query(
    'SELECT order_id, customer_id, total FROM oc_order ORDER BY order_id LIMIT 5'
)->fetchAll(PDO::FETCH_ASSOC);

$exportB2bConfirmed = [];
foreach ($exportQueue as $exportId) {
    $stmt = $pdo->prepare('SELECT 1 FROM oc_customer_b2b_confirmed WHERE customer_id = ?');
    $stmt->execute([(int) $exportId]);
    $exportB2bConfirmed[(int) $exportId] = (bool) $stmt->fetchColumn();
}

$response = [
    'status' => 'ok',
    'runtime_nonce' => $runtimeNonce,
    'server' => 'magento-cloud',
    'remote_addr' => $remoteAddr,
    'forwarded_for' => $forwardedFor,
    'cf_connecting_ip' => $cfConnectingIp,
    'oc_pre_registration' => $preReg,
    'oc_order' => $ocOrder,
    'exportar_clientes_queue' => array_map('intval', $exportQueue),
    'export_b2b_confirmed' => $exportB2bConfirmed,
    'export_b2b_confirmed_all' => !in_array(false, $exportB2bConfirmed, true) && $exportB2bConfirmed !== [],
    'exportar_clientes_expected' => count($exportQueue) . ' de ' . count($exportQueue),
    'importar_pedidos_expected' => $ocOrder . ' de ' . $ocOrder,
    'oc_order_sample' => $orderSample,
    'cadastro_sql_url' => 'https://awamotos.com/sectra-cadastro-sql.php',
    'cadastro_sql_available' => is_readable(dirname(__DIR__) . '/var/log/sectra_export_order_clients_latest.sql')
        || is_readable(dirname(__DIR__) . '/var/log/sectra_register_clients_latest.sql'),
    'next_step' => count($exportQueue) > 0
        ? 'Sectra desktop: Exportar Clientes / Cadastro de Cliente (' . count($exportQueue) . ' clientes)'
        : 'Fila export vazia — rode cron Magento ou verifique MySQL OpenCardB2B',
    'next_step_alt' => 'Se Exportar Clientes falhar: baixar cadastro_sql_url e executar no INDUSTRIAL (usuário JESS, rede local)',
    'hint' => 'Exportar Clientes ANTES de Importar Pedidos AWA',
];

// #region agent log
$logPath = dirname(__DIR__) . '/.cursor/debug-fc1127.log';
$payload = [
    'sessionId' => 'fc1127',
    'runId' => 'probe-access',
    'hypothesisId' => 'R',
    'location' => 'sectra-bridge-probe.php',
    'message' => 'Probe accessed',
    'data' => [
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
        'export_queue_count' => count($exportQueue),
        'export_b2b_confirmed_all' => !in_array(false, $exportB2bConfirmed, true) && $exportB2bConfirmed !== [],
        'oc_order' => $ocOrder,
    ],
    'timestamp' => (int) (microtime(true) * 1000),
];
@file_put_contents($logPath, json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
// #endregion

// #region agent log
$agentLogPath = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-608fd0.log';
@file_put_contents($agentLogPath, json_encode([
    'sessionId' => '608fd0',
    'runId' => 'sectra-pc-bridge-probe',
    'hypothesisId' => 'H8',
    'location' => 'sectra-bridge-probe.php',
    'message' => 'Sectra bridge probe endpoint accessed',
    'data' => [
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
        'http_host' => $_SERVER['HTTP_HOST'] ?? '',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'forwarded_for' => $forwardedFor,
        'cf_connecting_ip' => $cfConnectingIp,
        'runtime_nonce' => $runtimeNonce,
        'oc_order' => $ocOrder,
        'oc_pre_registration' => $preReg,
        'export_queue_count' => count($exportQueue),
    ],
    'timestamp' => (int) (microtime(true) * 1000),
], JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
// #endregion

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
