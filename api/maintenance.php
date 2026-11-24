<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL API — TELEGRAM SENTINEL, CACHE PURGER & ADMIN AUDIT LOG
 * ==============================================================================
 */
require_once dirname(__DIR__) . '/auth.php';
require_once BASE_PATH . '/includes/waf-bridge.php';

$admin  = requireAdminApiAuth(true);
$pdo    = getDBConnection();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

function gc_load_telegram_sentinel()
{
    $boot = GC_WAF_DIR . '/guardian-dome-telegram/bootstrap.php';
    if (is_file($boot)) {
        require_once $boot;
        return function_exists('guardian_dome_send_telegram');
    }
    return false;
}

if ($method === 'POST') {
    $input  = gc_get_json_input();
    $action = trim((string)($input['action'] ?? ''));

    if ($action === 'save_sentinel_prefs') {
        $prefs = [
            'alert_level'     => in_array(($input['alert_level'] ?? ''), ['critical_only', 'all_blocks'], true) ? $input['alert_level'] : 'critical_only',
            'notify_fim'      => !empty($input['notify_fim']),
            'notify_downtime' => !empty($input['notify_downtime']),
            'notify_ssl_days' => max(3, min(60, (int)($input['notify_ssl_days'] ?? 14))),
        ];
        gc_set_setting($pdo, 'telegram_sentinel_prefs', $prefs, $admin['username']);
        gc_audit_log('sentinel_prefs_updated', 'telegram', $prefs, $admin['username']);
        gc_json_response(['success' => true, 'message' => 'Preferensi Telegram Sentinel disimpan.', 'prefs' => $prefs]);
    }

    if ($action === 'test_telegram') {
        $loaded = gc_load_telegram_sentinel();
        if (!$loaded) {
            gc_json_response([
                'success' => false,
                'error'   => 'Modul Telegram Sentinel tidak ditemukan di ' . GC_WAF_DIR . '/guardian-dome-telegram/bootstrap.php.',
            ], 404);
        }
        $msg = "🛡️ <b>GUARDIAN CONTROL — UJI KONEKSI SENTINEL</b>\n"
             . "• Admin: <code>" . htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8') . "</code>\n"
             . "• Node: <code>" . htmlspecialchars(APP_DOMAIN, ENT_QUOTES, 'UTF-8') . "</code>\n"
             . "• Waktu: <code>" . date('Y-m-d H:i:s') . " WIB</code>\n"
             . "• Status: <b>Aktif &amp; Terhubung</b>";
        $sent = guardian_dome_send_telegram($msg, 'gc_test_alert', gc_get_client_ip());
        gc_audit_log('sentinel_test_alert', 'telegram', ['sent' => (bool)$sent], $admin['username']);
        gc_json_response([
            'success' => (bool)$sent,
            'message' => $sent ? 'Pesan uji Telegram Sentinel berhasil dikirim!' : 'Telegram Sentinel menolak pengiriman (periksa Bot Token / Chat ID di config Telegram Sentinel).',
        ], $sent ? 200 : 502);
    }

    if ($action === 'purge_cache') {
        $forceAll = !empty($input['force_all']);
        $res = gc_waf_purge_cache(7, $forceAll);
        $summary = 'Purge ' . ($forceAll ? 'Penuh' : '>7 Hari') . ': ' . ($res['purged_files'] ?? 0) . ' file dibersihkan.';
        gc_set_setting($pdo, 'purger_last_run', ['at' => date('Y-m-d H:i:s'), 'summary' => $summary], $admin['username']);
        gc_audit_log('waf_cache_purge', $forceAll ? 'force_all' : '7d', $res, $admin['username']);
        gc_json_response([
            'success' => true,
            'message' => $summary,
            'result'  => $res,
            'cache'   => gc_waf_cache_stats(),
        ]);
    }

    gc_json_response(['success' => false, 'error' => 'Aksi maintenance tidak dikenal.'], 400);
}

// GET: status Sentinel, statistik cache, dan riwayat Audit Admin SEC-OPS
$sentinelInstalled = is_file(GC_WAF_DIR . '/guardian-dome-telegram/bootstrap.php');
$prefs = gc_get_setting($pdo, 'telegram_sentinel_prefs', [
    'alert_level'     => 'critical_only',
    'notify_fim'      => true,
    'notify_downtime' => true,
    'notify_ssl_days' => 14,
]);
$cacheStats = gc_waf_cache_stats();
$purgerLast = gc_get_setting($pdo, 'purger_last_run', null);

$auditLogs = $pdo->query(
    "SELECT id, admin_username, action_type, target_ref, detail_json, ip_address, created_at
     FROM gc_admin_audit
     ORDER BY created_at DESC, id DESC
     LIMIT 100"
)->fetchAll(PDO::FETCH_ASSOC);

gc_json_response([
    'success'   => true,
    'sentinel'  => [
        'installed' => $sentinelInstalled,
        'path'      => GC_WAF_DIR . '/guardian-dome-telegram/bootstrap.php',
        'prefs'     => $prefs,
    ],
    'cache'     => $cacheStats,
    'purger'    => $purgerLast,
    'audit_log' => $auditLogs,
]);
