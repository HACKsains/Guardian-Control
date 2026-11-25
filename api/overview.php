<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL API — OVERVIEW & SEGMENTED TAB COUNTERS
 * ==============================================================================
 */
require_once dirname(__DIR__) . '/auth.php';
require_once BASE_PATH . '/includes/waf-bridge.php';
require_once BASE_PATH . '/includes/server-collector.php';

requireAdminApiAuth(false);
$pdo = getDBConnection();

// Sinkronisasi ringan inkremental saat Segarkan
$syncInc = gc_waf_sync_incidents_to_db($pdo);
$syncVis = gc_waf_sync_visitors_to_db($pdo);

$policy = gc_waf_read_policy();
$engine = gc_waf_get_engine_info();
$bannedLive = gc_waf_read_banned_ips();

$dbBans = (int)$pdo->query(
    "SELECT COUNT(*) FROM gc_ip_rules WHERE rule_type = 'blacklist' AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())"
)->fetchColumn();

$incidents24h = (int)$pdo->query(
    "SELECT COUNT(*) FROM gc_incidents WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
)->fetchColumn();

$aiBlocked24h = (int)$pdo->query(
    "SELECT COUNT(*) FROM gc_incidents WHERE category = 'ai_scraper' AND occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
)->fetchColumn();

$uniqueToday = (int)$pdo->query(
    "SELECT COUNT(DISTINCT device_hash) FROM gc_unique_visitors WHERE visit_date = CURDATE()"
)->fetchColumn();

$online5m = (int)$pdo->query(
    "SELECT COUNT(DISTINCT device_hash) FROM gc_unique_visitors WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
)->fetchColumn();

$unresolvedErrors = (int)$pdo->query(
    "SELECT COUNT(*) FROM gc_error_snapshots WHERE is_resolved = 0"
)->fetchColumn();

$fimAlerts = (int)$pdo->query(
    "SELECT COUNT(*) FROM gc_fim_hashes WHERE status IN ('modified','missing')"
)->fetchColumn();

$ram  = gc_collect_ram_stats();
$cpu  = gc_collect_cpu_stats();
$disk = gc_collect_disk_stats();

gc_json_response([
    'success' => true,
    'synced'  => ['incidents' => $syncInc, 'visitors' => $syncVis],
    'engine'  => $engine,
    'policy'  => $policy,
    'counters' => [
        'active_bans'       => max(count($bannedLive), $dbBans),
        'incidents_24h'     => $incidents24h,
        'ai_blocked_24h'    => $aiBlocked24h,
        'unique_today'      => $uniqueToday,
        'online_5m'         => $online5m,
        'unresolved_errors'  => $unresolvedErrors,
        'fim_alerts'         => $fimAlerts,
        'maintenance_active' => gc_waf_count_maintenance_active(),
    ],
    'health_quick' => [
        'ram_pct'  => $ram['used_pct'],
        'cpu_1m'   => $cpu['load_1m'],
        'cpu_sat'  => $cpu['saturation_pct'],
        'disk_pct' => $disk['used_pct'],
    ],
    'server_time' => date('Y-m-d H:i:s'),
]);
