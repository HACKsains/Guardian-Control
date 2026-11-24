<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL API — IP FIREWALL, SUBNET /24 ISOLATION & WHITELIST MANAGER
 * ==============================================================================
 */
require_once dirname(__DIR__) . '/auth.php';
require_once BASE_PATH . '/includes/waf-bridge.php';

$admin  = requireAdminApiAuth(true);
$pdo    = getDBConnection();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

function gc_validate_ip_or_cidr($val)
{
    $val = trim((string)$val);
    if ($val === '') {
        return false;
    }
    if (filter_var($val, FILTER_VALIDATE_IP)) {
        return true;
    }
    if (strpos($val, '/') !== false) {
        list($ip, $mask) = explode('/', $val, 2);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && ctype_digit($mask)) {
            $m = (int)$mask;
            return ($m >= 8 && $m <= 32);
        }
    }
    return false;
}

if ($method === 'GET') {
    $q = strtolower(trim((string)($_GET['q'] ?? '')));

    // Gabungkan banned_ips.json (live WAF) + gc_ip_rules (DB)
    $liveBans = gc_waf_read_banned_ips();
    $dbRules = $pdo->query(
        "SELECT id, ip_or_cidr, rule_type, reason, source, expires_at, is_active, created_by, created_at
         FROM gc_ip_rules
         WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())
         ORDER BY created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $blacklistMap = [];
    $whitelist = [];

    foreach ($dbRules as $r) {
        if ($r['rule_type'] === 'whitelist') {
            $whitelist[] = $r;
        } else {
            $blacklistMap[$r['ip_or_cidr']] = [
                'id'         => (int)$r['id'],
                'ip'         => $r['ip_or_cidr'],
                'subnet'     => (strpos($r['ip_or_cidr'], '.') !== false && strpos($r['ip_or_cidr'], '/') === false)
                    ? (substr($r['ip_or_cidr'], 0, strrpos($r['ip_or_cidr'], '.')) . '.0/24')
                    : $r['ip_or_cidr'],
                'ray_id'     => 'DB-RULE-' . $r['id'],
                'reason'     => $r['reason'],
                'source'     => $r['source'],
                'expires_at' => $r['expires_at'] ?: 'Permanen',
                'created_by' => $r['created_by'] ?: 'system',
                'banned_at'  => $r['created_at'],
            ];
        }
    }

    foreach ($liveBans as $ip => $entry) {
        if (!isset($blacklistMap[$ip])) {
            $blacklistMap[$ip] = [
                'id'         => 0,
                'ip'         => $ip,
                'subnet'     => (strpos($ip, '.') !== false && strpos($ip, '/') === false)
                    ? (substr($ip, 0, strrpos($ip, '.')) . '.0/24')
                    : $ip,
                'ray_id'     => (string)($entry['ray_id'] ?? 'WAF-AUTO'),
                'reason'     => (string)($entry['reason'] ?? 'WAF Auto-Ban'),
                'source'     => (string)($entry['source'] ?? 'waf_auto'),
                'expires_at' => !empty($entry['until']) ? date('Y-m-d H:i:s', (int)$entry['until']) : 'Permanen',
                'created_by' => 'Guardian Dome WAF',
                'banned_at'  => !empty($entry['banned_at']) ? date('Y-m-d H:i:s', (int)$entry['banned_at']) : date('Y-m-d H:i:s'),
            ];
        } else {
            if (!empty($entry['ray_id'])) {
                $blacklistMap[$ip]['ray_id'] = (string)$entry['ray_id'];
            }
        }
    }

    $blacklist = array_values($blacklistMap);
    if ($q !== '') {
        $blacklist = array_values(array_filter($blacklist, function ($row) use ($q) {
            return strpos(strtolower($row['ip']), $q) !== false
                || strpos(strtolower($row['ray_id']), $q) !== false
                || strpos(strtolower($row['reason']), $q) !== false
                || strpos(strtolower($row['subnet']), $q) !== false;
        }));
    }

    gc_json_response([
        'success'    => true,
        'my_ip'      => gc_get_client_ip(),
        'blacklist'  => $blacklist,
        'whitelist'  => $whitelist,
    ]);
}

if ($method === 'POST') {
    $input  = gc_get_json_input();
    $action = trim((string)($input['action'] ?? ''));

    if ($action === 'ban') {
        $ip     = trim((string)($input['ip'] ?? ''));
        $reason = trim((string)($input['reason'] ?? 'Manual Ban SEC-OPS'));
        $dur    = trim((string)($input['duration'] ?? 'permanent'));

        if (!gc_validate_ip_or_cidr($ip)) {
            gc_json_response(['success' => false, 'error' => 'Format IP atau CIDR /24 tidak valid.'], 422);
        }
        if ($ip === '127.0.0.1' || $ip === '::1') {
            gc_json_response(['success' => false, 'error' => 'IP loopback lokal tidak boleh diblokir.'], 422);
        }

        $durSec = 0;
        $expiresAt = null;
        if ($dur === '1h') { $durSec = 3600; $expiresAt = date('Y-m-d H:i:s', time() + 3600); }
        elseif ($dur === '24h') { $durSec = 86400; $expiresAt = date('Y-m-d H:i:s', time() + 86400); }
        elseif ($dur === '7d') { $durSec = 604800; $expiresAt = date('Y-m-d H:i:s', time() + 604800); }

        $stmt = $pdo->prepare(
            "INSERT INTO gc_ip_rules (ip_or_cidr, rule_type, reason, source, expires_at, is_active, created_by, created_at)
             VALUES (:ip, 'blacklist', :rsn, 'manual', :exp, 1, :by, NOW())
             ON DUPLICATE KEY UPDATE reason = VALUES(reason), expires_at = VALUES(expires_at), is_active = 1, created_by = VALUES(created_by)"
        );
        $stmt->execute([':ip' => $ip, ':rsn' => substr($reason, 0, 255), ':exp' => $expiresAt, ':by' => $admin['username']]);

        $wafEntry = gc_waf_ban_ip($ip, $reason, $durSec);
        gc_waf_sync_policy_ip_lists($pdo);
        gc_audit_log('firewall_ban_ip', $ip, ['reason' => $reason, 'duration' => $dur], $admin['username']);

        gc_json_response([
            'success' => true,
            'message' => "IP/CIDR {$ip} berhasil diblokir dan disinkronkan ke Guardian Dome WAF.",
            'entry'   => $wafEntry,
        ]);
    }

    if ($action === 'unban') {
        $ip = trim((string)($input['ip'] ?? ''));
        if ($ip === '') {
            gc_json_response(['success' => false, 'error' => 'Parameter IP wajib diisi.'], 422);
        }
        $stmt = $pdo->prepare("UPDATE gc_ip_rules SET is_active = 0 WHERE ip_or_cidr = :ip AND rule_type = 'blacklist'");
        $stmt->execute([':ip' => $ip]);
        gc_waf_unban_ip($ip);
        gc_waf_sync_policy_ip_lists($pdo);
        gc_audit_log('firewall_unban_ip', $ip, [], $admin['username']);

        gc_json_response([
            'success' => true,
            'message' => "IP/CIDR {$ip} berhasil dibuka blokirnya (Unban).",
        ]);
    }

    if ($action === 'bulk_unban') {
        $ips = isset($input['ips']) && is_array($input['ips']) ? $input['ips'] : [];
        $count = 0;
        $stmt = $pdo->prepare("UPDATE gc_ip_rules SET is_active = 0 WHERE ip_or_cidr = :ip AND rule_type = 'blacklist'");
        foreach ($ips as $ip) {
            $ip = trim((string)$ip);
            if ($ip === '') continue;
            $stmt->execute([':ip' => $ip]);
            gc_waf_unban_ip($ip);
            $count++;
        }
        gc_waf_sync_policy_ip_lists($pdo);
        gc_audit_log('firewall_bulk_unban', $count . ' IPs', ['ips' => $ips], $admin['username']);
        gc_json_response(['success' => true, 'message' => "{$count} IP/CIDR berhasil di-unban."]);
    }

    if ($action === 'bulk_permanent') {
        $ips = isset($input['ips']) && is_array($input['ips']) ? $input['ips'] : [];
        $count = 0;
        $stmt = $pdo->prepare(
            "INSERT INTO gc_ip_rules (ip_or_cidr, rule_type, reason, source, expires_at, is_active, created_by, created_at)
             VALUES (:ip, 'blacklist', 'Promoted to Permanent Ban', 'manual', NULL, 1, :by, NOW())
             ON DUPLICATE KEY UPDATE expires_at = NULL, is_active = 1"
        );
        foreach ($ips as $ip) {
            $ip = trim((string)$ip);
            if (!gc_validate_ip_or_cidr($ip)) continue;
            $stmt->execute([':ip' => $ip, ':by' => $admin['username']]);
            gc_waf_ban_ip($ip, 'Promoted to Permanent Ban', 0);
            $count++;
        }
        gc_waf_sync_policy_ip_lists($pdo);
        gc_audit_log('firewall_bulk_permanent', $count . ' IPs', ['ips' => $ips], $admin['username']);
        gc_json_response(['success' => true, 'message' => "{$count} IP dipromosikan menjadi Ban Permanen."]);
    }

    if ($action === 'whitelist_add') {
        $ip    = trim((string)($input['ip'] ?? ''));
        $label = trim((string)($input['reason'] ?? 'IP Admin / Sekolah Terpercaya'));
        if (!gc_validate_ip_or_cidr($ip)) {
            gc_json_response(['success' => false, 'error' => 'Format IP Whitelist tidak valid.'], 422);
        }
        $stmt = $pdo->prepare(
            "INSERT INTO gc_ip_rules (ip_or_cidr, rule_type, reason, source, expires_at, is_active, created_by, created_at)
             VALUES (:ip, 'whitelist', :rsn, 'manual', NULL, 1, :by, NOW())
             ON DUPLICATE KEY UPDATE reason = VALUES(reason), is_active = 1, created_by = VALUES(created_by)"
        );
        $stmt->execute([':ip' => $ip, ':rsn' => substr($label, 0, 255), ':by' => $admin['username']]);
        // Pastikan tidak ada di blacklist
        $pdo->prepare("UPDATE gc_ip_rules SET is_active = 0 WHERE ip_or_cidr = :ip AND rule_type = 'blacklist'")->execute([':ip' => $ip]);
        gc_waf_unban_ip($ip);
        gc_waf_sync_policy_ip_lists($pdo);
        gc_audit_log('firewall_whitelist_add', $ip, ['label' => $label], $admin['username']);

        gc_json_response(['success' => true, 'message' => "IP {$ip} ditambahkan ke Whitelist & aktif di WAF."]);
    }

    if ($action === 'whitelist_remove') {
        $ip = trim((string)($input['ip'] ?? ''));
        $stmt = $pdo->prepare("UPDATE gc_ip_rules SET is_active = 0 WHERE ip_or_cidr = :ip AND rule_type = 'whitelist'");
        $stmt->execute([':ip' => $ip]);
        gc_waf_sync_policy_ip_lists($pdo);
        gc_audit_log('firewall_whitelist_remove', $ip, [], $admin['username']);
        gc_json_response(['success' => true, 'message' => "IP {$ip} dihapus dari Whitelist."]);
    }

    gc_json_response(['success' => false, 'error' => 'Aksi Firewall tidak dikenal.'], 400);
}

gc_json_response(['success' => false, 'error' => 'Method Not Allowed'], 405);
