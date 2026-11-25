<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — ATOMIC WAF POLICY BRIDGE & VISITOR/INCIDENT SYNC ENGINE
 * ==============================================================================
 * Jembatan zero-overhead antara Guardian Control dan Guardian Dome WAF (Patch 11.0).
 * Semua penulisan JSON memakai pola atomic write (.tmp + LOCK_EX + rename()).
 * 100% Kompatibel PHP 7.4 - 8.3 Native.
 * ==============================================================================
 */

if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__) . '/config.php';
}

function gc_waf_ensure_cache_dir()
{
    $dir = GC_WAF_CACHE_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        $dir = GC_LOCAL_CACHE_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
    return $dir;
}

function gc_atomic_write_json($filePath, array $data)
{
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $tmpFile = $filePath . '.tmp.' . uniqid('', true);
    $written = @file_put_contents($tmpFile, $json, LOCK_EX);
    if ($written === false) {
        return false;
    }
    if (!@rename($tmpFile, $filePath)) {
        // Fallback Windows jika file target sedang di-lock singkat
        @unlink($filePath);
        if (!@rename($tmpFile, $filePath)) {
            @unlink($tmpFile);
            return false;
        }
    }
    return true;
}

function gc_waf_get_secret_salt()
{
    static $salt = null;
    if ($salt !== null) {
        return $salt;
    }
    $salt = 'GD_ILMUNA_SECOPS_SHIELD_2026_x8b2ec91a';
    if (is_file(GC_WAF_FILE)) {
        $head = @file_get_contents(GC_WAF_FILE, false, null, 0, 8192);
        if (is_string($head) && preg_match("/const\s+SECRET_SALT\s*=\s*'([^']+)'/", $head, $m)) {
            $salt = $m[1];
        }
    }
    return $salt;
}

function gc_waf_default_maintenance()
{
    return [
        'global_enabled'            => false,
        'global_title'              => 'Pemeliharaan Ekosistem Digital Ilmuna',
        'global_message'            => 'Seluruh layanan ekosistem Ilmuna sedang menjalani pemeliharaan terjadwal & peningkatan keamanan server. Kami akan segera kembali aktif.',
        'global_eta'                => '',
        'global_progress'           => 70,
        'allow_admin_bypass'        => true,
        'allow_ip_whitelist_bypass' => false,
        'bypass_key'                => 'ilmuna2026',
        'contact_email'             => 'admin@ilmuna.my.id',
        'contact_whatsapp'          => '6285117100227',
        'sites'              => [
            'ilmuna.my.id' => [
                'label'         => 'Portal Utama (WordPress)',
                'icon'          => 'solar:global-bold',
                'enabled'       => false,
                'title'         => 'Portal Ilmuna Sedang Diperbarui',
                'message'       => 'Kami sedang mengoptimalkan performa & keamanan portal utama ilmuna.my.id agar pengalaman belajar semakin nyaman.',
                'eta'           => '',
                'auto_end'      => true,
                'progress'      => 65,
                'retry_after'   => 1800,
                'contact_email' => 'admin@ilmuna.my.id',
            ],
            'margo-one.ilmuna.my.id' => [
                'label'         => 'Margo One SSO (Identity Provider)',
                'icon'          => 'solar:shield-keyhole-bold',
                'enabled'       => false,
                'title'         => 'Pemeliharaan Margo One SSO',
                'message'       => 'Sistem autentikasi terpadu Margo One SSO sedang menjalani pembaruan keamanan enkripsi RS256. Sesi aktif Anda tetap aman.',
                'eta'           => '',
                'auto_end'      => true,
                'progress'      => 75,
                'retry_after'   => 900,
                'contact_email' => 'admin@ilmuna.my.id',
            ],
            'learn.ilmuna.my.id' => [
                'label'         => 'Ilmuna Learn (LMS & SIAKAD)',
                'icon'          => 'solar:square-academic-cap-2-bold',
                'enabled'       => false,
                'title'         => 'Ilmuna Learn Sedang Pemeliharaan',
                'message'       => 'Platform pembelajaran & SIAKAD sedang kami tingkatkan agar materi, tugas, dan ujian berjalan lebih cepat dan stabil.',
                'eta'           => '',
                'auto_end'      => true,
                'progress'      => 60,
                'retry_after'   => 1800,
                'contact_email' => 'admin@ilmuna.my.id',
            ],
            'about.ilmuna.my.id' => [
                'label'         => 'Portofolio & Profil (about.ilmuna.my.id)',
                'icon'          => 'solar:user-id-bold',
                'enabled'       => false,
                'title'         => 'Halaman Sedang Pembaruan Tampilan',
                'message'       => 'Kami sedang memperbarui konten dan tampilan halaman portofolio about.ilmuna.my.id. Silakan kembali beberapa saat lagi.',
                'eta'           => '',
                'auto_end'      => true,
                'progress'      => 80,
                'retry_after'   => 1800,
                'contact_email' => 'admin@ilmuna.my.id',
            ],
        ],
    ];
}

function gc_waf_default_policy()
{
    return [
        'schema_version'          => 2,
        'under_attack_mode'       => false,
        'enable_preflight_scan'   => true,
        'enable_shield_v3'        => true,
        'shield_v3_min_score'     => 0.35,
        'enable_ai_scraper_block' => true,
        'enable_honeypot_form'    => true,
        'enable_seo_adsense_pass' => true,
        'rate_limit_burst'        => 15,
        'rate_limit_normal'       => 35,
        'rate_limit_flood_ban'    => 65,
        'whitelist_ips'           => ['127.0.0.1', '::1', '103.105.78.163'],
        'blacklist_ips'           => [],
        'maintenance'             => gc_waf_default_maintenance(),
        'updated_at'              => date('Y-m-d H:i:s'),
        'updated_by'              => 'system',
    ];
}

function gc_waf_read_maintenance()
{
    $policy   = gc_waf_read_policy();
    $defaults = gc_waf_default_maintenance();
    $stored   = isset($policy['maintenance']) && is_array($policy['maintenance']) ? $policy['maintenance'] : [];

    $merged = array_merge($defaults, $stored);
    $sites  = $defaults['sites'];
    if (isset($stored['sites']) && is_array($stored['sites'])) {
        foreach ($stored['sites'] as $host => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            $h = strtolower(trim((string)$host));
            if ($h === '' || $h === 'guardian-dome.ilmuna.my.id') {
                continue;
            }
            $base = isset($sites[$h]) ? $sites[$h] : [
                'label'         => $h,
                'icon'          => 'solar:global-bold',
                'enabled'       => false,
                'title'         => 'Sedang Pemeliharaan Sistem',
                'message'       => 'Website ini sedang menjalani pemeliharaan terjadwal untuk peningkatan layanan.',
                'eta'           => '',
                'auto_end'      => true,
                'progress'      => 60,
                'retry_after'   => 1800,
                'contact_email' => 'admin@ilmuna.my.id',
            ];
            $sites[$h] = array_merge($base, $cfg);
        }
    }
    $merged['sites'] = $sites;
    return $merged;
}

function gc_waf_write_maintenance(array $maint, $updatedBy = null)
{
    // Pastikan panel guardian-dome.ilmuna.my.id tidak pernah mengunci dirinya sendiri
    if (isset($maint['sites']['guardian-dome.ilmuna.my.id'])) {
        unset($maint['sites']['guardian-dome.ilmuna.my.id']);
    }
    $maint['global_enabled']            = !empty($maint['global_enabled']);
    $maint['allow_admin_bypass']        = !empty($maint['allow_admin_bypass']);
    $maint['allow_ip_whitelist_bypass'] = !empty($maint['allow_ip_whitelist_bypass']);
    $maint['global_progress']           = max(0, min(100, (int)($maint['global_progress'] ?? 70)));
    $maint['bypass_key']                = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($maint['bypass_key'] ?? 'ilmuna2026')) ?: 'ilmuna2026';

    return gc_waf_write_policy(['maintenance' => $maint], $updatedBy);
}

function gc_waf_count_maintenance_active()
{
    $m = gc_waf_read_maintenance();
    if (!empty($m['global_enabled'])) {
        return count($m['sites']);
    }
    $cnt = 0;
    $now = time();
    foreach ($m['sites'] as $s) {
        if (empty($s['enabled'])) {
            continue;
        }
        if (!empty($s['auto_end']) && !empty($s['eta'])) {
            $etaTs = strtotime((string)$s['eta']);
            if ($etaTs !== false && $etaTs <= $now) {
                continue;
            }
        }
        $cnt++;
    }
    return $cnt;
}

function gc_waf_policy_file()
{
    return gc_waf_ensure_cache_dir() . '/gc-runtime-rules.json';
}

function gc_waf_read_policy()
{
    $defaults = gc_waf_default_policy();
    $file = gc_waf_policy_file();
    if (!is_file($file)) {
        return $defaults;
    }
    $raw = @file_get_contents($file);
    if (!is_string($raw) || $raw === '') {
        return $defaults;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $defaults;
    }
    return array_merge($defaults, $decoded);
}

function gc_waf_write_policy(array $updates, $updatedBy = null)
{
    $current = gc_waf_read_policy();
    $merged  = array_merge($current, $updates);

    // Sanitasi tipe data ketat
    $merged['under_attack_mode']       = !empty($merged['under_attack_mode']);
    $merged['enable_preflight_scan']   = !empty($merged['enable_preflight_scan']);
    $merged['enable_shield_v3']        = !empty($merged['enable_shield_v3']);
    $merged['shield_v3_min_score']     = max(0.15, min(0.85, round((float)$merged['shield_v3_min_score'], 2)));
    $merged['enable_ai_scraper_block'] = !empty($merged['enable_ai_scraper_block']);
    $merged['enable_honeypot_form']    = !empty($merged['enable_honeypot_form']);
    $merged['enable_seo_adsense_pass'] = !empty($merged['enable_seo_adsense_pass']);
    $merged['rate_limit_burst']        = max(3, min(200, (int)$merged['rate_limit_burst']));
    $merged['rate_limit_normal']       = max(10, min(500, (int)$merged['rate_limit_normal']));
    $merged['rate_limit_flood_ban']    = max(20, min(1000, (int)$merged['rate_limit_flood_ban']));
    $merged['updated_at']              = date('Y-m-d H:i:s');
    $merged['updated_by']              = $updatedBy !== null ? (string)$updatedBy : (isset($_SESSION['gc_admin']['username']) ? (string)$_SESSION['gc_admin']['username'] : 'system');

    $ok = gc_atomic_write_json(gc_waf_policy_file(), $merged);
    return $ok ? $merged : false;
}

/**
 * Deteksi informasi versi engine Guardian Dome WAF dari file fisik
 */
function gc_waf_get_engine_info()
{
    $info = [
        'installed'   => is_file(GC_WAF_FILE),
        'waf_dir'     => GC_WAF_DIR,
        'waf_file'    => GC_WAF_FILE,
        'cache_dir'   => gc_waf_ensure_cache_dir(),
        'patch_code'  => 'New-Patch.11.0',
        'version'     => 'New-Patch.11.0 (Apex Citadel)',
        'patch_date'  => '23 November 2026',
        'policy_file' => gc_waf_policy_file(),
        'policy_live' => is_file(gc_waf_policy_file()),
        'file_size'   => is_file(GC_WAF_FILE) ? (int)@filesize(GC_WAF_FILE) : 0,
        'file_mtime'  => is_file(GC_WAF_FILE) ? date('Y-m-d H:i:s', (int)@filemtime(GC_WAF_FILE)) : null,
    ];

    if ($info['installed']) {
        $head = @file_get_contents(GC_WAF_FILE, false, null, 0, 8192);
        if (is_string($head)) {
            if (preg_match("/const\s+PATCH_CODE\s*=\s*'([^']+)'/", $head, $m)) {
                $info['patch_code'] = $m[1];
            }
            if (preg_match("/const\s+VERSION\s*=\s*'([^']+)'/", $head, $m)) {
                $info['version'] = $m[1];
            }
            if (preg_match("/const\s+PATCH_DATE\s*=\s*'([^']+)'/", $head, $m)) {
                $info['patch_date'] = $m[1];
            }
        }
    }
    return $info;
}

/**
 * Daftar path banned_ips.json yang mungkin digunakan WAF
 */
function gc_waf_ban_files()
{
    $files = [
        gc_waf_ensure_cache_dir() . '/banned_ips.json',
        GC_WAF_DIR . '/banned_ips.json',
    ];
    return array_values(array_unique($files));
}

function gc_waf_read_banned_ips()
{
    $merged = [];
    foreach (gc_waf_ban_files() as $file) {
        if (!is_file($file)) {
            continue;
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            continue;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            continue;
        }
        foreach ($decoded as $ip => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $until = (int)($entry['until'] ?? 0);
            if ($until > 0 && $until < time()) {
                continue;
            }
            $entry['ip'] = (string)($entry['ip'] ?? $ip);
            $merged[$entry['ip']] = $entry;
        }
    }
    return $merged;
}

function gc_waf_ban_ip($ipOrCidr, $reason, $durationSeconds = 0)
{
    $ipOrCidr = trim((string)$ipOrCidr);
    $rayId = 'GC-' . strtoupper(substr(md5($ipOrCidr . microtime(true)), 0, 10));
    $isPerm = ((int)$durationSeconds <= 0);
    $entry = [
        'ip'        => $ipOrCidr,
        'reason'    => trim((string)$reason) !== '' ? trim((string)$reason) : 'Manual Ban via Guardian Control',
        'ray_id'    => $rayId,
        'banned_at' => time(),
        'until'     => $isPerm ? 0 : (time() + (int)$durationSeconds),
        'duration'  => $isPerm ? 'Permanen' : (int)$durationSeconds,
        'uri'       => 'Guardian Control Firewall',
        'ua'        => 'SEC-OPS Policy',
        'source'    => 'guardian_control',
    ];

    $primaryFile = gc_waf_ensure_cache_dir() . '/banned_ips.json';
    $list = gc_waf_read_banned_ips();
    $list[$ipOrCidr] = $entry;
    gc_atomic_write_json($primaryFile, $list);
    return $entry;
}

function gc_waf_unban_ip($ipOrCidr)
{
    $ipOrCidr = trim((string)$ipOrCidr);
    $removed = false;
    foreach (gc_waf_ban_files() as $file) {
        if (!is_file($file)) {
            continue;
        }
        $raw = @file_get_contents($file);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded) && isset($decoded[$ipOrCidr])) {
            unset($decoded[$ipOrCidr]);
            gc_atomic_write_json($file, $decoded);
            $removed = true;
        }
    }
    return $removed;
}

function gc_waf_emergency_unban_all(PDO $pdo)
{
    $count = 0;
    foreach (gc_waf_ban_files() as $file) {
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $count += count($decoded);
            }
            gc_atomic_write_json($file, []);
        }
    }
    $stmt = $pdo->exec("UPDATE gc_ip_rules SET is_active = 0 WHERE rule_type = 'blacklist' AND is_active = 1");
    gc_waf_sync_policy_ip_lists($pdo);
    return $count + (int)$stmt;
}

/**
 * Sinkronkan daftar aktif whitelist & blacklist dari DB ke gc-runtime-rules.json
 */
function gc_waf_sync_policy_ip_lists(PDO $pdo)
{
    $wl = ['127.0.0.1', '::1', '103.105.78.163'];
    $bl = [];

    $stmt = $pdo->query(
        "SELECT ip_or_cidr, rule_type FROM gc_ip_rules
         WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())"
    );
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['rule_type'] === 'whitelist') {
            $wl[] = $row['ip_or_cidr'];
        } elseif ($row['rule_type'] === 'blacklist') {
            $bl[] = $row['ip_or_cidr'];
        }
    }

    return gc_waf_write_policy([
        'whitelist_ips' => array_values(array_unique($wl)),
        'blacklist_ips' => array_values(array_unique($bl)),
    ]);
}

/**
 * Baca cache FCrDNS Verified Bots (rdns_bot_*.json)
 */
function gc_waf_read_rdns_cache()
{
    $dir = gc_waf_ensure_cache_dir();
    $files = @glob($dir . '/rdns_bot_*.json');
    if (!is_array($files)) {
        return [];
    }
    $entries = [];
    foreach ($files as $f) {
        $raw = @file_get_contents($f);
        $d = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($d)) {
            continue;
        }
        $expTs = isset($d['exp']) ? (int)$d['exp'] : (isset($d['expires']) ? (int)$d['expires'] : ((int)@filemtime($f) + 86400));
        $entries[] = [
            'file'        => basename($f),
            'ip'          => (string)($d['ip'] ?? 'Verified Bot'),
            'ptr'         => (string)($d['ptr'] ?? $d['hostname'] ?? '-'),
            'bot_name'    => (string)($d['bot'] ?? $d['ua'] ?? 'Official Crawler'),
            'verified'    => !empty($d['verified']) || !empty($d['valid']) || !empty($d['is_official']),
            'expires_at'  => date('Y-m-d H:i:s', $expTs),
            'updated_at'  => date('Y-m-d H:i:s', (int)@filemtime($f)),
        ];
    }
    usort($entries, function ($a, $b) {
        return strcmp($b['updated_at'], $a['updated_at']);
    });
    return $entries;
}

function gc_waf_flush_rdns_cache()
{
    $dir = gc_waf_ensure_cache_dir();
    $files = @glob($dir . '/rdns_bot_*.json');
    $deleted = 0;
    if (is_array($files)) {
        foreach ($files as $f) {
            if (@unlink($f)) {
                $deleted++;
            }
        }
    }
    return $deleted;
}

/**
 * Sinkronkan incidents.jsonl -> tabel gc_incidents secara inkremental
 */
function gc_waf_sync_incidents_to_db(PDO $pdo)
{
    $file = gc_waf_ensure_cache_dir() . '/incidents.jsonl';
    if (!is_file($file)) {
        return 0;
    }

    $size = (int)@filesize($file);
    $offset = (int)gc_get_setting($pdo, 'incidents_jsonl_offset', 0);
    if ($size < $offset) {
        $offset = 0; // File telah dirotasi
    }
    if ($size === $offset) {
        return 0;
    }

    $fh = @fopen($file, 'rb');
    if (!$fh) {
        return 0;
    }
    if ($offset > 0) {
        fseek($fh, $offset);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO gc_incidents
            (ray_id, domain, ip_address, category, severity, action_taken, http_method, request_uri, user_agent, reason, shield_v3_score, meta_json, occurred_at)
         VALUES
            (:ray, :dom, :ip, :cat, :sev, :act, :meth, :uri, :ua, :rsn, :score, :meta, :occ)"
    );

    $inserted = 0;
    $maxPerSync = 500;
    while (!feof($fh) && $inserted < $maxPerSync) {
        $line = fgets($fh);
        if ($line === false) {
            break;
        }
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $row = json_decode($line, true);
        if (!is_array($row)) {
            continue;
        }

        $sev = strtolower((string)($row['severity'] ?? 'high'));
        if (!in_array($sev, ['low', 'medium', 'high', 'critical'], true)) {
            $sev = 'high';
        }
        $act = strtolower((string)($row['action_taken'] ?? 'blocked'));
        if (!in_array($act, ['blocked', 'banned', 'challenged', 'alerted'], true)) {
            $act = 'blocked';
        }

        try {
            $stmt->execute([
                ':ray'   => substr((string)($row['ray_id'] ?? ('GD-' . substr(md5($line), 0, 8))), 0, 40),
                ':dom'   => substr((string)($row['domain'] ?? 'ilmuna.my.id'), 0, 120),
                ':ip'    => substr((string)($row['ip'] ?? '0.0.0.0'), 0, 64),
                ':cat'   => substr((string)($row['category'] ?? 'waf_block'), 0, 60),
                ':sev'   => $sev,
                ':act'   => $act,
                ':meth'  => substr(strtoupper((string)($row['method'] ?? 'GET')), 0, 12),
                ':uri'   => substr((string)($row['uri'] ?? '/'), 0, 500),
                ':ua'    => isset($row['ua']) ? substr((string)$row['ua'], 0, 300) : null,
                ':rsn'   => (string)($row['reason'] ?? 'WAF Security Block'),
                ':score' => isset($row['shield_v3_score']) && $row['shield_v3_score'] !== null ? (float)$row['shield_v3_score'] : null,
                ':meta'  => isset($row['meta']) ? json_encode($row['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                ':occ'   => (string)($row['occurred_at'] ?? date('Y-m-d H:i:s')),
            ]);
            $inserted++;
        } catch (Throwable $e) {
            // Skip malformed row
        }
    }

    $newOffset = ftell($fh);
    fclose($fh);
    gc_set_setting($pdo, 'incidents_jsonl_offset', (int)$newOffset, 'sync');
    return $inserted;
}

/**
 * Sinkronkan visitors_YYYY-MM-DD.jsonl -> gc_unique_visitors (1 Device = 1 Visitor)
 * sekaligus simpan agregasi top pages, referer, & trafik per jam ke gc_settings.
 */
function gc_waf_sync_visitors_to_db(PDO $pdo)
{
    $dir = gc_waf_ensure_cache_dir();
    $files = @glob($dir . '/visitors_*.jsonl');
    if (!is_array($files) || empty($files)) {
        return 0;
    }

    $offsets = gc_get_setting($pdo, 'visitors_jsonl_offsets', []);
    if (!is_array($offsets)) {
        $offsets = [];
    }

    $extraStats = gc_get_setting($pdo, 'visitors_drilldown_today', [
        'date'     => date('Y-m-d'),
        'hourly'   => array_fill(0, 24, 0),
        'pages'    => [],
        'referers' => [],
    ]);
    if (($extraStats['date'] ?? '') !== date('Y-m-d')) {
        $extraStats = [
            'date'     => date('Y-m-d'),
            'hourly'   => array_fill(0, 24, 0),
            'pages'    => [],
            'referers' => [],
        ];
    }

    $stmt = $pdo->prepare(
        "INSERT INTO gc_unique_visitors
            (visit_date, domain, device_hash, device_type, os_name, browser_name, ip_subnet, hits_count, first_seen_at, last_seen_at)
         VALUES
            (:vdate, :dom, :dhash, :dtype, :os, :brw, :sub, 1, :ts, :ts)
         ON DUPLICATE KEY UPDATE
            hits_count = hits_count + 1,
            last_seen_at = GREATEST(last_seen_at, VALUES(last_seen_at)),
            device_type = VALUES(device_type),
            os_name = VALUES(os_name),
            browser_name = VALUES(browser_name)"
    );

    $synced = 0;
    foreach ($files as $file) {
        $base = basename($file);
        $size = (int)@filesize($file);
        $off  = isset($offsets[$base]) ? (int)$offsets[$base] : 0;
        if ($size < $off) {
            $off = 0;
        }
        if ($size === $off) {
            continue;
        }

        $fh = @fopen($file, 'rb');
        if (!$fh) {
            continue;
        }
        if ($off > 0) {
            fseek($fh, $off);
        }

        $countFile = 0;
        while (!feof($fh) && $countFile < 1000) {
            $line = fgets($fh);
            if ($line === false) {
                break;
            }
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (!is_array($row) || empty($row['device_hash'])) {
                continue;
            }

            $vDate = (string)($row['date'] ?? date('Y-m-d'));
            $dom   = strtolower(substr((string)($row['domain'] ?? 'ilmuna.my.id'), 0, 120));
            $dHash = substr((string)$row['device_hash'], 0, 64);
            $dType = strtolower((string)($row['device_type'] ?? 'other'));
            if (!in_array($dType, ['mobile', 'tablet', 'desktop', 'other'], true)) {
                $dType = 'other';
            }
            $os  = substr((string)($row['os'] ?? 'Unknown'), 0, 40);
            $brw = substr((string)($row['browser'] ?? 'Unknown'), 0, 40);
            $sub = isset($row['ip_subnet']) ? substr((string)$row['ip_subnet'], 0, 45) : null;
            $ts  = (string)($row['ts'] ?? date('Y-m-d H:i:s'));

            try {
                $stmt->execute([
                    ':vdate' => $vDate,
                    ':dom'   => $dom,
                    ':dhash' => $dHash,
                    ':dtype' => $dType,
                    ':os'    => $os,
                    ':brw'   => $brw,
                    ':sub'   => $sub,
                    ':ts'    => $ts,
                ]);
                $synced++;
                $countFile++;

                // Agregasi drill-down hari ini (jam, halaman terpopuler, referer)
                if ($vDate === date('Y-m-d')) {
                    $hour = (int)date('G', strtotime($ts) ?: time());
                    if (isset($extraStats['hourly'][$hour])) {
                        $extraStats['hourly'][$hour]++;
                    }
                    $pathKey = $dom . substr((string)($row['path'] ?? '/'), 0, 120);
                    $extraStats['pages'][$pathKey] = (int)($extraStats['pages'][$pathKey] ?? 0) + 1;
                    $ref = trim((string)($row['referer'] ?? ''));
                    if ($ref !== '') {
                        $refHost = parse_url($ref, PHP_URL_HOST);
                        $refKey = $refHost ? strtolower((string)$refHost) : 'Direct';
                    } else {
                        $refKey = 'Direct / Bookmark';
                    }
                    $extraStats['referers'][$refKey] = (int)($extraStats['referers'][$refKey] ?? 0) + 1;
                }
            } catch (Throwable $e) {
                // Skip row
            }
        }

        $offsets[$base] = (int)ftell($fh);
        fclose($fh);
    }

    // Batasi top 30 pages & 20 referers agar setting tetap ringan
    if (!empty($extraStats['pages'])) {
        arsort($extraStats['pages']);
        $extraStats['pages'] = array_slice($extraStats['pages'], 0, 30, true);
    }
    if (!empty($extraStats['referers'])) {
        arsort($extraStats['referers']);
        $extraStats['referers'] = array_slice($extraStats['referers'], 0, 20, true);
    }

    gc_set_setting($pdo, 'visitors_jsonl_offsets', $offsets, 'sync');
    gc_set_setting($pdo, 'visitors_drilldown_today', $extraStats, 'sync');

    return $synced;
}

/**
 * Statistik & Pembersihan Cache Guardian Dome
 */
function gc_waf_cache_stats()
{
    $dirs = array_values(array_unique([
        GC_WAF_CACHE_DIR,
        GC_WAF_DIR . '/guardian-dome-telegram/cache',
        GC_LOCAL_CACHE_DIR,
    ]));

    $totalFiles = 0;
    $totalBytes = 0;
    $dirDetails = [];

    foreach ($dirs as $d) {
        if (!is_dir($d)) {
            continue;
        }
        $fCount = 0;
        $bCount = 0;
        $items = @scandir($d);
        if (is_array($items)) {
            foreach ($items as $it) {
                if ($it === '.' || $it === '..' || $it === '.htaccess') {
                    continue;
                }
                $fp = $d . '/' . $it;
                if (is_file($fp)) {
                    $fCount++;
                    $bCount += (int)@filesize($fp);
                }
            }
        }
        $totalFiles += $fCount;
        $totalBytes += $bCount;
        $dirDetails[] = [
            'path'  => $d,
            'files' => $fCount,
            'bytes' => $bCount,
            'human' => gc_format_bytes($bCount),
        ];
    }

    return [
        'total_files' => $totalFiles,
        'total_bytes' => $totalBytes,
        'total_human' => gc_format_bytes($totalBytes),
        'directories' => $dirDetails,
    ];
}

function gc_waf_purge_cache($retentionDays = 7, $forceAll = false)
{
    $purgerFile = GC_WAF_DIR . '/guardian-dome-purger.php';
    if (is_file($purgerFile)) {
        require_once $purgerFile;
        if (class_exists('GuardianDomeCachePurger') && method_exists('GuardianDomeCachePurger', 'execute_purge')) {
            return GuardianDomeCachePurger::execute_purge((int)$retentionDays, (bool)$forceAll);
        }
    }

    // Fallback native PHP purger (tetap melindungi banned_ips.json & gc-runtime-rules.json)
    $protected = ['.', '..', '.htaccess', 'index.html', 'index.php', 'banned_ips.json', 'gc-runtime-rules.json', 'admin_known_ips.json'];
    $threshold = time() - ((int)$retentionDays * 86400);
    $purged = 0;
    $freed  = 0;

    $dir = gc_waf_ensure_cache_dir();
    $items = @scandir($dir);
    if (is_array($items)) {
        foreach ($items as $it) {
            if (in_array($it, $protected, true)) {
                continue;
            }
            $fp = $dir . '/' . $it;
            if (!is_file($fp)) {
                continue;
            }
            $mtime = (int)@filemtime($fp);
            if ($forceAll || $mtime < $threshold) {
                $sz = (int)@filesize($fp);
                if (@unlink($fp)) {
                    $purged++;
                    $freed += $sz;
                }
            }
        }
    }

    return [
        'purged_files' => $purged,
        'bytes_freed'  => $freed,
        'human_freed'  => gc_format_bytes($freed),
        'details'      => ["Native purge selesai: {$purged} berkas (" . gc_format_bytes($freed) . ")"],
    ];
}
