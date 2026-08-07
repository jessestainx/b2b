<?php
declare(strict_types=1);
header('Content-Type: application/json');
header('Cache-Control: no-store');
$raw = file_get_contents('php://input') ?: '';
if (trim($raw) === '') { http_response_code(400); echo '{"ok":false}'; exit; }
@file_put_contents('/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-ca231d.log', trim($raw)."\n", FILE_APPEND|LOCK_EX);
echo '{"ok":true}';
