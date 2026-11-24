<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — CRON TELEMETRY & SYNC WORKER (CLI-ONLY / SECRET KEY)
 * ==============================================================================
 * Jadwal cPanel Cron Job (setiap 5 menit):
 *   * /5 * * * * /usr/local/bin/php -q /home/uofruaju/guardian-dome.ilmuna.my.id/cron/collect-telemetry.php
 * ==============================================================================
 */

$isCli = (php_sapi_name() === 'cli');
require_once dirname(__DIR__) . '/config.php';

if (!$isCli) {
    $provided = isset($_GET['secret']) ? (string)$_GET['secret'] : '';
    if ($provided === '' || !hash_equals(GC_CRON_SECRET, $provided)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "403 Forbidden — Cron worker hanya dapat dijalankan via CLI.";
        exit;
    }
}

require_once BASE_PATH . '/database.php';
require_once BASE_PATH . '/includes/waf-bridge.php';
require_once BASE_PATH . '/includes/server-collector.php';
require_once BASE_PATH . '/includes/log-reader.php';

$pdo = getDBConnection();
$summary = [];

// 1. Rekam snapshot RAM, CPU, Disk, OPcache, MySQL -> gc_server_metrics
$ram   = gc_collect_ram_stats();
$cpu   = gc_collect_cpu_stats();
$disk  = gc_collect_disk_stats();
$php   = gc_collect_php_opcache_stats();
$mysql = gc_collect_mysql_stats($pdo);

$pdo->prepare(
    "INSERT INTO gc_server_metrics
        (ram_total_mb, ram_used_mb, ram_pct, cpu_load_1m, cpu_load_5m, cpu_load_15m, disk_total_gb, disk_used_gb, disk_pct, mysql_threads, mysql_slow_queries, opcache_hit_rate, recorded_at)
     VALUES
        (:rt, :ru, :rp, :c1, :c5, :c15, :dt, :du, :dp, :mt, :ms, :op, NOW())"
)->execute([
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

// Auto-prune metrik & uptime > 30 hari
$pdo->exec("DELETE FROM gc_server_metrics WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$pdo->exec("DELETE FROM gc_uptime_checks WHERE checked_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");

// 2. Sinkronkan incidents.jsonl, visitors_*.jsonl, & error_log
$incCount = gc_waf_sync_incidents_to_db($pdo);
$visCount = gc_waf_sync_visitors_to_db($pdo);
$errCount = gc_sync_error_logs_to_db($pdo, 'all');

// 3. Cek Uptime & SSL Subdomain
$uptime = gc_check_ecosystem_uptime($pdo, true);
$downTargets = [];
$expiringSsl = [];
$prefs = gc_get_setting($pdo, 'telegram_sentinel_prefs', ['notify_downtime' => true, 'notify_ssl_days' => 14, 'notify_fim' => true]);
$sslThresh = (int)($prefs['notify_ssl_days'] ?? 14);

foreach ($uptime as $u) {
    if (empty($u['is_up'])) {
        $downTargets[] = $u['target_name'] . ' (HTTP ' . $u['http_code'] . ')';
    }
    if ($u['ssl_days_left'] !== null && (int)$u['ssl_days_left'] <= $sslThresh) {
        $expiringSsl[] = $u['host'] . ' (' . $u['ssl_days_left'] . ' hari)';
    }
}

// 4. Kirim alert Telegram Sentinel jika ada downtime atau SSL hampir habis
$teleBoot = GC_WAF_DIR . '/guardian-dome-telegram/bootstrap.php';
if (is_file($teleBoot) && (!empty($downTargets) || !empty($expiringSsl))) {
    require_once $teleBoot;
    if (function_exists('guardian_dome_send_telegram') && !empty($prefs['notify_downtime'])) {
        $alertText = "🚨 <b>GUARDIAN CONTROL — PERINGATAN EKOSISTEM</b>\n";
        if (!empty($downTargets)) {
            $alertText .= "• <b>Subdomain Down:</b> " . htmlspecialchars(implode(', ', $downTargets), ENT_QUOTES, 'UTF-8') . "\n";
        }
        if (!empty($expiringSsl)) {
            $alertText .= "• <b>SSL Hampir Kedaluwarsa:</b> " . htmlspecialchars(implode(', ', $expiringSsl), ENT_QUOTES, 'UTF-8') . "\n";
        }
        $alertText .= "• Waktu: <code>" . date('Y-m-d H:i:s') . " WIB</code>";
        guardian_dome_send_telegram($alertText, 'gc_uptime_alert', '127.0.0.1');
    }
}

$summaryText = sprintf(
    'RAM %.1f%% | CPU %.2f | Disk %.1f%% | Sync: %d insiden, %d visitor, %d error',
    $ram['used_pct'],
    $cpu['load_1m'],
    $disk['used_pct'],
    $incCount,
    $visCount,
    $errCount
);

gc_set_setting($pdo, 'cron_last_heartbeat', [
    'ts'      => time(),
    'at'      => date('Y-m-d H:i:s'),
    'summary' => $summaryText,
], 'cron');

if ($isCli) {
    echo "[Guardian Control Cron] OK — {$summaryText}\n";
} else {
    gc_json_response(['success' => true, 'summary' => $summaryText]);
}
