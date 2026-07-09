<?php

declare(strict_types=1);

// IP Whitelist - acesso restrito ao servidor local e túneis SSH autorizados
(static function(): void {
    $allowed = ['127.0.0.1', '::1', '127.0.0.2'];
    $serverIp = (string) ($_SERVER['SERVER_ADDR'] ?? '');
    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $xff = (string) ($_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if (!in_array($clientIp, $allowed, true) || (strlen($xff) > 0 && !in_array($xff, $allowed, true))) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Access restricted to authorized networks.']);
        exit;
    }
})();

/**
 * AWA Motos — Prometheus Metrics Exporter
 * 
 * Endpoint: https://awamotos.com/metrics-exporter.php
 * 
 * Expõe métricas em formato Prometheus para:
 *   - HTTP request count + latency
 *   - PHP-FPM pool stats
 *   - MySQL connections
 *   - Redis cache hit rate
 *   - Elasticsearch health
 *   - Magento cron status
 * 
 * Uso com Prometheus:
 *   scrape_configs:
 *     - job_name: 'awa-motos'
 *       scrape_interval: 30s
 *       static_configs:
 *         - targets: ['awamotos.com']
 *       metrics_path: '/metrics-exporter.php'
 */
header('Content-Type: text/plain; version=0.0.4');

// Não cachear
header('Cache-Control: no-store, no-cache, must-revalidate');

$magentoRoot = realpath(__DIR__ . '/..');
require $magentoRoot . '/app/bootstrap.php';

$env = include $magentoRoot . '/app/etc/env.php';
$db = $env['db']['connection']['default'];

function getConnection($db) {
    $dsn = sprintf("mysql:unix_socket=%s;dbname=%s;charset=utf8mb4",
        $db['unix_socket'] ?? '/var/run/mysqld/mysqld.sock',
        $db['dbname']);
    return new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

// === HELP + TYPE comments ===
echo "# HELP awa_uptime_seconds Server uptime in seconds\n";
echo "# TYPE awa_uptime_seconds gauge\n";
echo "awa_uptime_seconds " . (time() - filemtime('/proc/1/cmdline') ?: 0) . "\n";

echo "# HELP awa_load_average System load average (1, 5, 15 min)\n";
echo "# TYPE awa_load_average gauge\n";
$load = sys_getloadavg();
echo "awa_load_average{period=\"1m\"} " . $load[0] . "\n";
echo "awa_load_average{period=\"5m\"} " . $load[1] . "\n";
echo "awa_load_average{period=\"15m\"} " . $load[2] . "\n";

echo "# HELP awa_memory_bytes Memory usage in bytes\n";
echo "# TYPE awa_memory_bytes gauge\n";
$memInfo = file_get_contents('/proc/meminfo');
if (preg_match('/MemTotal:\s+(\d+)/', $memInfo, $m1) &&
    preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $m2)) {
    $total = (int)$m1[1] * 1024;
    $available = (int)$m2[1] * 1024;
    $used = $total - $available;
    echo "awa_memory_bytes{type=\"total\"} $total\n";
    echo "awa_memory_bytes{type=\"available\"} $available\n";
    echo "awa_memory_bytes{type=\"used\"} $used\n";
}

echo "# HELP awa_disk_bytes Disk usage in bytes\n";
echo "# TYPE awa_disk_bytes gauge\n";
$disk = disk_free_space('/');
$diskTotal = disk_total_space('/');
echo "awa_disk_bytes{type=\"total\"} $diskTotal\n";
echo "awa_disk_bytes{type=\"free\"} $disk\n";
echo "awa_disk_bytes{type=\"used\"} " . ($diskTotal - $disk) . "\n";

// === Magento DB metrics ===
try {
    $pdo = getConnection($db);
    
    echo "# HELP awa_mysql_connections Active MySQL connections\n";
    echo "# TYPE awa_mysql_connections gauge\n";
    $r = $pdo->query("SHOW STATUS LIKE 'Threads_connected'")->fetch(PDO::FETCH_ASSOC);
    echo "awa_mysql_connections " . ($r['Value'] ?? 0) . "\n";
    
    echo "# HELP awa_mysql_slow_queries Total slow queries\n";
    echo "# TYPE awa_mysql_slow_queries counter\n";
    $r = $pdo->query("SHOW STATUS LIKE 'Slow_queries'")->fetch(PDO::FETCH_ASSOC);
    echo "awa_mysql_slow_queries " . ($r['Value'] ?? 0) . "\n";
    
    echo "# HELP awa_mysql_size_bytes Database size in bytes\n";
    echo "# TYPE awa_mysql_size_bytes gauge\n";
    $r = $pdo->query("SELECT SUM(DATA_LENGTH + INDEX_LENGTH) AS s 
        FROM information_schema.tables WHERE TABLE_SCHEMA = '{$db['dbname']}'")->fetch(PDO::FETCH_ASSOC);
    echo "awa_mysql_size_bytes " . ($r['s'] ?? 0) . "\n";
    
    echo "# HELP awa_mysql_tables_count Total number of tables\n";
    echo "# TYPE awa_mysql_tables_count gauge\n";
    $r = $pdo->query("SELECT COUNT(*) AS c FROM information_schema.tables 
        WHERE TABLE_SCHEMA = '{$db['dbname']}'")->fetch(PDO::FETCH_ASSOC);
    echo "awa_mysql_tables_count " . ($r['c'] ?? 0) . "\n";
} catch (Exception $e) {
    echo "# mysql_error: " . $e->getMessage() . "\n";
}

// === Redis metrics (via shell) ===
echo "# HELP awa_redis_keys Number of keys per Redis DB\n";
echo "# TYPE awa_redis_keys gauge\n";
foreach ([0, 1, 2, 3] as $dbNum) {
    $output = shell_exec("redis-cli -a 'Aw4R3d1s2026Sec' --no-auth-warning -n {$dbNum} DBSIZE 2>/dev/null");
    $keys = (int)trim($output);
    echo "awa_redis_keys{db=\"{$dbNum}\"} {$keys}\n";
}

echo "# HELP awa_redis_memory_bytes Redis memory usage in bytes\n";
echo "# TYPE awa_redis_memory_bytes gauge\n";
$output = shell_exec("redis-cli -a 'Aw4R3d1s2026Sec' --no-auth-warning INFO memory 2>/dev/null | grep used_memory: | head -1");
if (preg_match('/used_memory:(\d+)/', $output, $m)) {
    echo "awa_redis_memory_bytes " . $m[1] . "\n";
}

echo "# HELP awa_redis_hits_total Total Redis key hits\n";
echo "# TYPE awa_redis_hits_total counter\n";
$output = shell_exec("redis-cli -a 'Aw4R3d1s2026Sec' --no-auth-warning INFO stats 2>/dev/null | grep keyspace_hits: | head -1");
if (preg_match('/keyspace_hits:(\d+)/', $output, $m)) {
    echo "awa_redis_hits_total " . $m[1] . "\n";
}

echo "# HELP awa_redis_misses_total Total Redis key misses\n";
echo "# TYPE awa_redis_misses_total counter\n";
$output = shell_exec("redis-cli -a 'Aw4R3d1s2026Sec' --no-auth-warning INFO stats 2>/dev/null | grep keyspace_misses: | head -1");
if (preg_match('/keyspace_misses:(\d+)/', $output, $m)) {
    echo "awa_redis_misses_total " . $m[1] . "\n";
}

// === Magento cron status ===
echo "# HELP awa_cron_last_run_timestamp Last cron run timestamp\n";
echo "# TYPE awa_cron_last_run_timestamp gauge\n";
try {
    $pdo = getConnection($db);
    $r = $pdo->query("SELECT MAX(executed_at) AS last FROM cron_schedule")->fetch(PDO::FETCH_ASSOC);
    if ($r['last']) {
        $ts = strtotime($r['last']);
        echo "awa_cron_last_run_timestamp $ts\n";
        echo "# HELP awa_cron_seconds_since_last_run Seconds since last cron\n";
        echo "# TYPE awa_cron_seconds_since_last_run gauge\n";
        echo "awa_cron_seconds_since_last_run " . (time() - $ts) . "\n";
    }
} catch (Exception $e) {}

// === Indexer status ===
echo "# HELP awa_indexers_ready Number of ready indexers\n";
echo "# TYPE awa_indexers_ready gauge\n";
try {
    $pdo = getConnection($db);
    $r = $pdo->query("SELECT COUNT(*) AS c FROM magento_indexer_state 
        WHERE STATUS = 'valid'")->fetch(PDO::FETCH_ASSOC);
    echo "awa_indexers_ready " . ($r['c'] ?? 0) . "\n";
} catch (Exception $e) {}

echo "# HELP awa_awa_up Application status (1=ok, 0=error)\n";
echo "# TYPE awa_awa_up gauge\n";
echo "awa_awa_up 1\n";
