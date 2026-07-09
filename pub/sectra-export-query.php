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
 * Simula o SELECT do OpenCardB2B "Exportar Clientes" (code='' AND status=1).
 * Abra no PC do Sectra: se não mostrar 6 linhas com IDs nativos, o MySQL do Sectra
 * NÃO está apontando para a nuvem Magento.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Surrogate-Control: no-store');

$envFile = dirname(__DIR__) . '/app/etc/env.php';
$env = require $envFile;
$db = $env['db']['connection']['default'];
$dsn = isset($db['unix_socket'])
    ? "mysql:unix_socket={$db['unix_socket']};dbname={$db['dbname']};charset=utf8"
    : "mysql:host={$db['host']};dbname={$db['dbname']};charset=utf8";

$pdo = new PDO($dsn, $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

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
    ':endpoint' => 'sectra-export-query',
    ':remote' => $remoteAddr,
    ':ua' => mb_substr($userAgent, 0, 255),
    ':qs' => mb_substr($queryString, 0, 255),
]);
// #endregion

$rows = $pdo->query(
    "SELECT customer_id, firstname, lastname, email, telephone, code, status,
            customer_group_id, address_id,
            JSON_UNQUOTE(JSON_EXTRACT(custom_field, '$.\"6\"')) AS cnpj
     FROM oc_customer
     WHERE code = '' AND status = 1
     ORDER BY customer_id"
)->fetchAll(PDO::FETCH_ASSOC);

$expected = [2541, 11134, 16161, 17421, 18202];
$actual = array_map('intval', array_column($rows, 'customer_id'));
$match = ($actual === $expected);

$response = [
    'code_version' => 'sectra-export-query-v2',
    'status' => 'ok',
    'runtime_nonce' => $runtimeNonce,
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
    'forwarded_for' => $forwardedFor,
    'cf_connecting_ip' => $cfConnectingIp,
    'mysql_host_used_by_magento' => $db['host'] ?? 'socket',
    'sectra_export_query_rows' => count($rows),
    'expected_customer_ids' => $expected,
    'actual_customer_ids' => $actual,
    'ids_match_expected' => $match,
    'rows' => $rows,
    'diagnosis' => $match
        ? 'MySQL OK — Sectra deve ver 5 de 5. Execute Exportar Clientes no desktop.'
        : 'MySQL do Sectra provavelmente aponta para banco ERRADO. Configure host 72.61.94.22, DB magento.',
];

// #region agent log
$logPath = dirname(__DIR__) . '/.cursor/debug-fc1127.log';
@file_put_contents($logPath, json_encode([
    'sessionId' => 'fc1127',
    'runId' => 'export-query',
    'hypothesisId' => 'M',
    'location' => 'sectra-export-query.php',
    'message' => 'Export query simulation',
    'data' => [
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
        'actual_ids' => $actual,
        'match' => $match,
    ],
    'timestamp' => (int) (microtime(true) * 1000),
], JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
// #endregion

// #region agent log
$agentLogPath = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-608fd0.log';
@file_put_contents($agentLogPath, json_encode([
    'sessionId' => '608fd0',
    'runId' => 'sectra-pc-export-query',
    'hypothesisId' => 'H8',
    'location' => 'sectra-export-query.php',
    'message' => 'Sectra export query endpoint accessed',
    'data' => [
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
        'http_host' => $_SERVER['HTTP_HOST'] ?? '',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'forwarded_for' => $forwardedFor,
        'cf_connecting_ip' => $cfConnectingIp,
        'runtime_nonce' => $runtimeNonce,
        'rows_count' => count($rows),
        'actual_customer_ids' => $actual,
        'ids_match_expected' => $match,
        'code_version' => 'sectra-export-query-v2',
    ],
    'timestamp' => (int) (microtime(true) * 1000),
], JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
// #endregion

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
