<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL API — WAF COMMAND CENTER & ATOMIC POLICY SWITCHBOARD
 * ==============================================================================
 */
require_once dirname(__DIR__) . '/auth.php';
require_once BASE_PATH . '/includes/waf-bridge.php';

$admin = requireAdminApiAuth(true);
$pdo   = getDBConnection();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    gc_json_response([
        'success'     => true,
        'engine'      => gc_waf_get_engine_info(),
        'policy'      => gc_waf_read_policy(),
        'banned_cnt'  => count(gc_waf_read_banned_ips()),
        'rdns_cnt'    => count(gc_waf_read_rdns_cache()),
        'subdomains'  => ['ilmuna.my.id', 'margo-one.ilmuna.my.id', 'learn.ilmuna.my.id', 'guardian-dome.ilmuna.my.id'],
    ]);
}

if ($method === 'POST') {
    $input  = gc_get_json_input();
    $action = trim((string)($input['action'] ?? 'update_policy'));

    if ($action === 'emergency_unban_all') {
        $cleared = gc_waf_emergency_unban_all($pdo);
        gc_audit_log('waf_emergency_unban_all', 'all_bans', ['cleared' => $cleared], $admin['username']);
        gc_json_response([
            'success' => true,
            'message' => "Emergency Unban berhasil dijalankan. {$cleared} entri blokir dibersihkan.",
            'cleared' => $cleared,
        ]);
    }

    if ($action === 'flush_rdns_cache') {
        $deleted = gc_waf_flush_rdns_cache();
        gc_audit_log('waf_flush_rdns', 'rdns_cache', ['deleted' => $deleted], $admin['username']);
        gc_json_response([
            'success' => true,
            'message' => "Cache FCrDNS berhasil dibersihkan ({$deleted} berkas).",
            'deleted' => $deleted,
        ]);
    }

    // Update policy secara atomik ke ../guardian-dome/cache/gc-runtime-rules.json
    $allowedKeys = [
        'under_attack_mode',
        'enable_preflight_scan',
        'enable_shield_v3',
        'shield_v3_min_score',
        'enable_ai_scraper_block',
        'enable_honeypot_form',
        'enable_seo_adsense_pass',
        'rate_limit_burst',
        'rate_limit_normal',
        'rate_limit_flood_ban',
    ];
    $updates = [];
    foreach ($allowedKeys as $k) {
        if (array_key_exists($k, $input)) {
            $updates[$k] = $input[$k];
        }
    }

    $saved = gc_waf_write_policy($updates, $admin['username']);
    if ($saved === false) {
        gc_json_response([
            'success' => false,
            'error'   => 'Gagal menulis kebijakan atomik ke gc-runtime-rules.json. Periksa izin tulis folder cache.',
        ], 500);
    }

    gc_set_setting($pdo, 'waf_policy_snapshot', $saved, $admin['username']);
    gc_audit_log('waf_policy_updated', 'gc-runtime-rules.json', $updates, $admin['username']);

    gc_json_response([
        'success' => true,
        'message' => 'Kebijakan WAF berhasil ditulis secara atomik dan aktif seketika di seluruh *.ilmuna.my.id.',
        'policy'  => $saved,
    ]);
}

gc_json_response(['success' => false, 'error' => 'Method Not Allowed'], 405);
