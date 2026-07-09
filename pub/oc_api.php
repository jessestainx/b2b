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
 * OpenCart API Compatibility Layer for Sectra ERP
 * Handles HTTP requests that Sectra makes to what it thinks is an OpenCart instance.
 * Routes: /oc_api.php?route=api/login, api/order, etc.
 */

// Log all requests for debugging
$logFile = '/tmp/sectra_http_requests.log';
$logEntry = date('Y-m-d H:i:s') . ' | ' . $_SERVER['REMOTE_ADDR'] . ' | ' .
    $_SERVER['REQUEST_METHOD'] . ' | ' . $_SERVER['REQUEST_URI'] . ' | ' .
    json_encode($_GET) . ' | ' . file_get_contents('php://input') . "\n";
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

header('Content-Type: application/json; charset=utf-8');

$route = $_GET['route'] ?? $_REQUEST['route'] ?? '';

switch ($route) {
    case 'api/login':
        // OpenCart API login - return a token
        echo json_encode([
            'success' => 'Success: API session started!',
            'api_token' => bin2hex(random_bytes(16))
        ]);
        break;

    case 'api/order/add':
    case 'api/order/edit':
        // Order operations
        echo json_encode([
            'success' => 'Success: Order has been modified!',
            'order_id' => $_REQUEST['order_id'] ?? 0
        ]);
        break;

    case 'api/order/info':
        // Order info
        $orderId = $_REQUEST['order_id'] ?? 0;
        echo json_encode([
            'order_id' => $orderId,
            'success' => true
        ]);
        break;

    case 'api/order/history':
        // Order status update
        echo json_encode([
            'success' => 'Success: Order history has been added!'
        ]);
        break;

    default:
        // Return success for any unknown route
        echo json_encode([
            'success' => true,
            'message' => 'OK'
        ]);
        break;
}
