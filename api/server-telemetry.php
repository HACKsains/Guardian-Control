<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL API — DEEP SERVER TELEMETRY, MYSQL, UPTIME & SSL AUDIT
 * ==============================================================================
 */
require_once dirname(__DIR__) . '/auth.php';
require_once BASE_PATH . '/includes/server-collector.php';

$admin  = requireAdminApiAuth(true);
$pdo    = getDBConnection();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

// Aksi POST: jalankan uji uptime & SSL sekarang atau rekam snapshot metrik
if ($method === 'POST') {
    $input  = gc_get_json_input();
    $action = trim((string)($input['action'] ?? 'check_uptime'));

    if ($action === 'check_uptime') {
        $checks = gc_check_ecosystem_uptime($pdo, true);
        gc_audit_log('server_uptime_check', 'ecosystem', ['targets' => count($checks)], $admin['username']);
        gc_json_response([
            'success' => true,
            'message' => 'Pengujian Uptime, TTFB, SSL & Security Headers selesai.',
            'uptime'  => $checks,
        ]);
    }

    if ($action === 'record_snapshot') {
        $ram   = gc_collect_ram_stats();
        $cpu   = gc_collect_cpu_stats();
        $disk  = gc_collect_disk_stats();
        $php   = gc_collect_php_opcache_stats();
        $mysql = gc_collect_mysql_stats($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO gc_server_metrics
                (ram_total_mb, ram_used_mb, ram_pct, cpu_load_1m, cpu_load_5m, cpu_load_15m, disk_total_gb, disk_used_gb, disk_pct, mysql_threads, mysql_slow_queries, opcache_hit_rate, recorded_at)
             VALUES
                (:rt, :ru, :rp, :c1, :c5, :c15, :dt, :du, :dp, :mt, :ms, :op, NOW())"
        );
        $stmt->execute([
            ':rt'  => $ram['total_mb'],
            ':ru'  => $ram['used_mb'],
            ':rp'  => $ram['used_pct'],
            ':c1'  => $cpu['load_1m'],
            ':c5'  => $cpu['load_5m'],
            ':c15' => $cpu['load_15m'],
            ':dt'  => $disk['total_gb'],
            ':du'  => $disk['used_gb'],
            ':dp'  => $disk['used_pct'],
            ':mt'  => $mysql['threads_connected'],
            ':ms'  => $mysql['slow_queries'],
            ':op'  => $php['opcache']['hit_rate'],
        ]);

        gc_json_response(['success' => true, 'message' => 'Snapshot metrik server berhasil direkam.']);
    }

    gc_json_response(['success' => false, 'error' => 'Aksi telemetry tidak dikenal.'], 400);
}

// GET: kumpulkan semua metrik live + riwayat 24 jam + uptime terakhir
$ram   = gc_collect_ram_stats();
$cpu   = gc_collect_cpu_stats();
$disk  = gc_collect_disk_stats();
$php   = gc_collect_php_opcache_stats();
$mysql = gc_collect_mysql_stats($pdo);
$cron  = gc_get_cron_heartbeat($pdo);

// Ambil histori 24 jam terakhir dari gc_server_metrics
$history = $pdo->query(
    "SELECT ram_pct, cpu_load_1m, disk_pct, mysql_threads, opcache_hit_rate, DATE_FORMAT(recorded_at, '%H:%i') AS label, recorded_at
     FROM gc_server_metrics
     WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
     ORDER BY recorded_at ASC
     LIMIT 120"
)->fetchAll(PDO::FETCH_ASSOC);

// Jika histori kosong, sisipkan 1 titik live agar grafik tetap langsung tampil
if (empty($history)) {
    $history[] = [
        'ram_pct'          => $ram['used_pct'],
        'cpu_load_1m'      => $cpu['load_1m'],
        'disk_pct'         => $disk['used_pct'],
        'mysql_threads'    => $mysql['threads_connected'],
        'opcache_hit_rate' => $php['opcache']['hit_rate'],
        'label'            => date('H:i'),
        'recorded_at'      => date('Y-m-d H:i:s'),
    ];
}

// Ambil hasil uptime terbaru per target
$uptimeRows = $pdo->query(
    "SELECT u1.*
     FROM gc_uptime_checks u1
     INNER JOIN (
         SELECT target_name, MAX(id) AS max_id
         FROM gc_uptime_checks
         GROUP BY target_name
     ) u2 ON u1.id = u2.max_id
     ORDER BY u1.target_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

gc_json_response([
    'success'       => true,
    'ram'           => $ram,
    'cpu'           => $cpu,
    'disk'          => $disk,
    'php_opcache'   => $php,
    'mysql'         => $mysql,
    'cron'          => $cron,
    'history_24h'   => $history,
    'uptime_latest' => $uptimeRows,
    'collected_at'  => date('Y-m-d H:i:s'),
]);
