<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — NATIVE PHP GRASSROOTS SERVER TELEMETRY COLLECTOR
 * ==============================================================================
 * 100% Native PHP TANPA exec() / shell_exec() (Aman di Shared Hosting cPanel).
 * Mengumpulkan: RAM/Swap, CPU Load + Core Saturation + Process Queue, Disk +
 * Directory Size Audit + Giant Log Alert, PHP Runtime + php.ini Security Audit +
 * OPcache Detail, MySQL Deep Health (SHOW GLOBAL STATUS + SHOW PROCESSLIST +
 * INFORMATION_SCHEMA Per-DB & Top-Tables + Fragmentation), Subdomain Uptime +
 * TTFB + SSL Issuer/Expiry + HTTP Security Headers Audit, dan Cron Heartbeat.
 * ==============================================================================
 */

if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__) . '/config.php';
}

function gc_parse_php_bytes($val)
{
    $val = trim((string)$val);
    if ($val === '' || $val === '-1') {
        return 0;
    }
    $last = strtolower(substr($val, -1));
    $num = (float)$val;
    if ($last === 'g') {
        $num *= 1024 * 1024 * 1024;
    } elseif ($last === 'm') {
        $num *= 1024 * 1024;
    } elseif ($last === 'k') {
        $num *= 1024;
    }
    return (int)$num;
}

/**
 * 1. RAM, Swap & PHP Memory Peak
 */
function gc_collect_ram_stats()
{
    $phpLimitBytes = gc_parse_php_bytes(ini_get('memory_limit'));
    $phpUsedBytes  = memory_get_usage(true);
    $phpPeakBytes  = memory_get_peak_usage(true);

    $res = [
        'source'          => 'php_memory',
        'total_mb'        => $phpLimitBytes > 0 ? (int)round($phpLimitBytes / 1048576) : 256,
        'used_mb'         => (int)round($phpUsedBytes / 1048576),
        'free_mb'         => 0,
        'available_mb'    => 0,
        'buffers_mb'      => 0,
        'cached_mb'       => 0,
        'used_pct'        => 0.0,
        'swap_total_mb'   => 0,
        'swap_used_mb'    => 0,
        'swap_pct'        => 0.0,
        'php_used_mb'     => round($phpUsedBytes / 1048576, 2),
        'php_peak_mb'     => round($phpPeakBytes / 1048576, 2),
        'php_limit'       => (string)ini_get('memory_limit'),
    ];

    if (is_readable('/proc/meminfo')) {
        $raw = @file_get_contents('/proc/meminfo');
        if (is_string($raw) && $raw !== '') {
            $map = [];
            foreach (explode("\n", $raw) as $line) {
                if (preg_match('/^([A-Za-z0-9_()]+):\s+(\d+)\s*kB/i', trim($line), $m)) {
                    $map[$m[1]] = (int)$m[2]; // kB
                }
            }
            if (!empty($map['MemTotal'])) {
                $totalKb = $map['MemTotal'];
                $availKb = isset($map['MemAvailable'])
                    ? $map['MemAvailable']
                    : (($map['MemFree'] ?? 0) + ($map['Buffers'] ?? 0) + ($map['Cached'] ?? 0));
                $usedKb = max(0, $totalKb - $availKb);

                $res['source']       = '/proc/meminfo';
                $res['total_mb']     = (int)round($totalKb / 1024);
                $res['available_mb'] = (int)round($availKb / 1024);
                $res['free_mb']      = (int)round(($map['MemFree'] ?? 0) / 1024);
                $res['buffers_mb']   = (int)round(($map['Buffers'] ?? 0) / 1024);
                $res['cached_mb']    = (int)round(($map['Cached'] ?? 0) / 1024);
                $res['used_mb']      = (int)round($usedKb / 1024);
                $res['used_pct']     = $totalKb > 0 ? round(($usedKb / $totalKb) * 100, 2) : 0.0;

                $swapTotal = (int)($map['SwapTotal'] ?? 0);
                $swapFree  = (int)($map['SwapFree'] ?? 0);
                $swapUsed  = max(0, $swapTotal - $swapFree);
                $res['swap_total_mb'] = (int)round($swapTotal / 1024);
                $res['swap_used_mb']  = (int)round($swapUsed / 1024);
                $res['swap_pct']      = $swapTotal > 0 ? round(($swapUsed / $swapTotal) * 100, 2) : 0.0;
                return $res;
            }
        }
    }

    // Fallback Windows / non-proc
    $res['free_mb']      = max(0, $res['total_mb'] - $res['used_mb']);
    $res['available_mb'] = $res['free_mb'];
    $res['used_pct']     = $res['total_mb'] > 0 ? round(($phpPeakBytes / max(1, $phpLimitBytes)) * 100, 2) : 0.0;
    return $res;
}

/**
 * 2. CPU Load Average 1m/5m/15m + Core Saturation + Process Queue
 */
function gc_collect_cpu_stats()
{
    $cores = 1;
    if (is_readable('/proc/cpuinfo')) {
        $cpuinfo = @file_get_contents('/proc/cpuinfo');
        if (is_string($cpuinfo)) {
            $cnt = preg_match_all('/^processor\s*:/m', $cpuinfo);
            if ($cnt > 0) {
                $cores = (int)$cnt;
            }
        }
    } elseif (getenv('NUMBER_OF_PROCESSORS')) {
        $cores = max(1, (int)getenv('NUMBER_OF_PROCESSORS'));
    }

    $load = [0.0, 0.0, 0.0];
    $loadSource = 'fallback';
    if (function_exists('sys_getloadavg')) {
        $l = @sys_getloadavg();
        if (is_array($l) && count($l) >= 3) {
            $load = [round((float)$l[0], 2), round((float)$l[1], 2), round((float)$l[2], 2)];
            $loadSource = 'sys_getloadavg';
        }
    }

    $runningProcs = null;
    $totalProcs   = null;
    if (is_readable('/proc/loadavg')) {
        $la = trim((string)@file_get_contents('/proc/loadavg'));
        $parts = preg_split('/\s+/', $la);
        if (isset($parts[3]) && strpos($parts[3], '/') !== false) {
            list($rP, $tP) = explode('/', $parts[3], 2);
            $runningProcs = (int)$rP;
            $totalProcs   = (int)$tP;
        }
    }

    $satPct = min(100.0, round(($load[0] / max(1, $cores)) * 100, 2));

    return [
        'source'          => $loadSource,
        'cores'           => $cores,
        'load_1m'         => $load[0],
        'load_5m'         => $load[1],
        'load_15m'        => $load[2],
        'saturation_pct'  => $satPct,
        'running_procs'   => $runningProcs,
        'total_procs'     => $totalProcs,
        'os_family'       => PHP_OS,
    ];
}

/**
 * Hitung ukuran direktori secara aman & dibatasi kedalaman agar cepat di shared hosting
 */
function gc_dir_size_bounded($dir, $maxFiles = 2000)
{
    if (!is_dir($dir) || !is_readable($dir)) {
        return ['bytes' => 0, 'files' => 0, 'truncated' => false];
    }
    $bytes = 0;
    $files = 0;
    $truncated = false;
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $file) {
            if ($files >= $maxFiles) {
                $truncated = true;
                break;
            }
            if ($file->isFile()) {
                $bytes += (int)$file->getSize();
                $files++;
            }
        }
    } catch (Throwable $e) {
        // Ignore unreadable subfolder
    }
    return ['bytes' => $bytes, 'files' => $files, 'truncated' => $truncated];
}

/**
 * 3. Disk Space, Audit Ukuran Folder & Deteksi File Log Raksasa (> 5 MB)
 */
function gc_collect_disk_stats()
{
    $root = is_dir('/home/uofruaju') ? '/home/uofruaju' : BASE_PATH;
    $total = (float)@disk_total_space($root);
    $free  = (float)@disk_free_space($root);
    $used  = max(0.0, $total - $free);
    $pct   = $total > 0 ? round(($used / $total) * 100, 2) : 0.0;

    $auditDirs = [
        ['label' => 'Guardian Dome Cache', 'path' => GC_WAF_CACHE_DIR],
        ['label' => 'Guardian Control Cache', 'path' => GC_LOCAL_CACHE_DIR],
    ];
    foreach (gc_get_uploads_scan_dirs() as $app => $uDir) {
        $auditDirs[] = ['label' => 'Uploads (' . $app . ')', 'path' => $uDir];
    }

    $folders = [];
    foreach ($auditDirs as $ad) {
        if (!is_dir($ad['path'])) {
            continue;
        }
        $sz = gc_dir_size_bounded($ad['path'], 1500);
        $folders[] = [
            'label'     => $ad['label'],
            'path'      => $ad['path'],
            'bytes'     => $sz['bytes'],
            'human'     => gc_format_bytes($sz['bytes']),
            'files'     => $sz['files'],
            'truncated' => $sz['truncated'],
        ];
    }

    // Audit file error_log raksasa (> 5 MB)
    $giantLogs = [];
    foreach (gc_get_error_log_sources() as $key => $src) {
        foreach ($src['paths'] as $lp) {
            if (is_file($lp)) {
                $fSize = (int)@filesize($lp);
                if ($fSize >= 5242880) { // >= 5 MB
                    $giantLogs[] = [
                        'source' => $key,
                        'label'  => $src['label'],
                        'path'   => $lp,
                        'bytes'  => $fSize,
                        'human'  => gc_format_bytes($fSize),
                    ];
                }
            }
        }
    }

    return [
        'mount'       => $root,
        'total_gb'    => round($total / 1073741824, 2),
        'used_gb'     => round($used / 1073741824, 2),
        'free_gb'     => round($free / 1073741824, 2),
        'used_pct'    => $pct,
        'folders'     => $folders,
        'giant_logs'  => $giantLogs,
    ];
}

/**
 * 4. PHP Runtime, php.ini Security Flags Audit & OPcache Detail
 */
function gc_collect_php_opcache_stats()
{
    $requiredExts = ['pdo_mysql', 'openssl', 'curl', 'mbstring', 'zip', 'gd', 'json'];
    $extStatus = [];
    foreach ($requiredExts as $ext) {
        $extStatus[$ext] = extension_loaded($ext);
    }

    $displayErrors   = filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN);
    $exposePhp       = filter_var(ini_get('expose_php'), FILTER_VALIDATE_BOOLEAN);
    $allowUrlInclude = filter_var(ini_get('allow_url_include'), FILTER_VALIDATE_BOOLEAN);
    $openBasedir     = trim((string)ini_get('open_basedir'));
    $disableFuncs    = trim((string)ini_get('disable_functions'));

    $iniAudit = [
        [
            'flag'   => 'display_errors',
            'value'  => $displayErrors ? 'On' : 'Off',
            'status' => $displayErrors ? 'warn' : 'ok',
            'note'   => $displayErrors ? 'Sebaiknya Off di produksi agar path tidak bocor' : 'Aman (tidak menampilkan error ke publik)',
        ],
        [
            'flag'   => 'expose_php',
            'value'  => $exposePhp ? 'On' : 'Off',
            'status' => $exposePhp ? 'warn' : 'ok',
            'note'   => $exposePhp ? 'Header X-Powered-By mengekspos versi PHP' : 'Aman (versi PHP disembunyikan)',
        ],
        [
            'flag'   => 'allow_url_include',
            'value'  => $allowUrlInclude ? 'On' : 'Off',
            'status' => $allowUrlInclude ? 'critical' : 'ok',
            'note'   => $allowUrlInclude ? 'BERBAHAYA! Wajib Off untuk mencegah RFI' : 'Aman (RFI diblokir)',
        ],
        [
            'flag'   => 'open_basedir',
            'value'  => $openBasedir !== '' ? substr($openBasedir, 0, 60) : '(tidak dibatasi)',
            'status' => $openBasedir !== '' ? 'ok' : 'info',
            'note'   => $openBasedir !== '' ? 'Isolasi filesystem aktif' : 'Opsional di lokal; aktifkan di produksi',
        ],
        [
            'flag'   => 'upload_max_filesize / post_max_size',
            'value'  => ini_get('upload_max_filesize') . ' / ' . ini_get('post_max_size'),
            'status' => 'info',
            'note'   => 'max_execution_time: ' . ini_get('max_execution_time') . 's',
        ],
        [
            'flag'   => 'disable_functions',
            'value'  => $disableFuncs !== '' ? (count(explode(',', $disableFuncs)) . ' fungsi dibatasi') : '(kosong)',
            'status' => $disableFuncs !== '' ? 'ok' : 'info',
            'note'   => $disableFuncs !== '' ? substr($disableFuncs, 0, 80) : 'Semua fungsi aktif (normal di Laragon dev)',
        ],
    ];

    $opcache = [
        'enabled'          => false,
        'hit_rate'         => null,
        'used_mb'          => 0.0,
        'free_mb'          => 0.0,
        'wasted_mb'        => 0.0,
        'wasted_pct'       => 0.0,
        'cached_scripts'   => 0,
        'oom_restarts'     => 0,
        'hash_restarts'    => 0,
    ];

    if (function_exists('opcache_get_status')) {
        $st = @opcache_get_status(false);
        if (is_array($st) && !empty($st['opcache_enabled'])) {
            $mem   = isset($st['memory_usage']) ? $st['memory_usage'] : [];
            $stats = isset($st['opcache_statistics']) ? $st['opcache_statistics'] : [];
            $opcache = [
                'enabled'        => true,
                'hit_rate'       => isset($stats['opcache_hit_rate']) ? round((float)$stats['opcache_hit_rate'], 2) : 0.0,
                'used_mb'        => round(((float)($mem['used_memory'] ?? 0)) / 1048576, 2),
                'free_mb'        => round(((float)($mem['free_memory'] ?? 0)) / 1048576, 2),
                'wasted_mb'      => round(((float)($mem['wasted_memory'] ?? 0)) / 1048576, 2),
                'wasted_pct'     => round((float)($mem['current_wasted_percentage'] ?? 0), 2),
                'cached_scripts' => (int)($stats['num_cached_scripts'] ?? 0),
                'oom_restarts'   => (int)($stats['oom_restarts'] ?? 0),
                'hash_restarts'  => (int)($stats['hash_restarts'] ?? 0),
            ];
        }
    }

    return [
        'php_version' => PHP_VERSION,
        'sapi'        => php_sapi_name(),
        'extensions'  => $extStatus,
        'ini_audit'   => $iniAudit,
        'opcache'     => $opcache,
    ];
}

/**
 * 5. MySQL / MariaDB Deep Health (SHOW GLOBAL STATUS, SHOW PROCESSLIST, INFORMATION_SCHEMA)
 */
function gc_collect_mysql_stats(PDO $pdo)
{
    $statusVars = [
        'Threads_connected', 'Threads_running', 'Max_used_connections',
        'Uptime', 'Questions', 'Slow_queries', 'Aborted_connects', 'Table_locks_waited',
    ];
    $metrics = [];
    foreach ($statusVars as $v) {
        $metrics[$v] = 0;
    }

    try {
        $inList = "'" . implode("','", $statusVars) . "'";
        $rows = $pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN ({$inList})")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $metrics[$r['Variable_name']] = (int)$r['Value'];
        }
    } catch (Throwable $e) {
        // Fallback jika hak akses terbatas
    }

    $version = '';
    try {
        $version = (string)$pdo->query("SELECT VERSION()")->fetchColumn();
    } catch (Throwable $e) {
        $version = 'Unknown';
    }

    // Live Query Inspector (SHOW PROCESSLIST)
    $processes = [];
    try {
        $pl = $pdo->query("SHOW PROCESSLIST")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pl as $p) {
            $processes[] = [
                'id'      => (int)($p['Id'] ?? 0),
                'user'    => (string)($p['User'] ?? ''),
                'db'      => (string)($p['db'] ?? '-'),
                'command' => (string)($p['Command'] ?? ''),
                'time'    => (int)($p['Time'] ?? 0),
                'state'   => (string)($p['State'] ?? ''),
                'info'    => isset($p['Info']) && $p['Info'] !== null ? substr((string)$p['Info'], 0, 140) : '',
            ];
        }
    } catch (Throwable $e) {
        // Processlist may be restricted on some shared hosting
    }

    // Per-Database size, rows & overhead fragmentation
    $databases = [];
    $topTables = [];
    try {
        $dbSql = "SELECT table_schema AS db_name,
                         COUNT(*) AS table_count,
                         COALESCE(SUM(table_rows), 0) AS total_rows,
                         ROUND(COALESCE(SUM(data_length + index_length), 0) / 1048576, 2) AS size_mb,
                         ROUND(COALESCE(SUM(data_free), 0) / 1048576, 2) AS overhead_mb
                  FROM information_schema.TABLES
                  WHERE table_schema NOT IN ('information_schema','performance_schema','mysql','sys')
                  GROUP BY table_schema
                  ORDER BY size_mb DESC";
        $databases = $pdo->query($dbSql)->fetchAll(PDO::FETCH_ASSOC);

        $tblSql = "SELECT table_schema AS db_name,
                          table_name,
                          engine,
                          COALESCE(table_rows, 0) AS row_count,
                          ROUND(COALESCE(data_length + index_length, 0) / 1048576, 2) AS size_mb,
                          ROUND(COALESCE(data_free, 0) / 1048576, 2) AS overhead_mb
                   FROM information_schema.TABLES
                   WHERE table_schema NOT IN ('information_schema','performance_schema','mysql','sys')
                   ORDER BY (data_length + index_length) DESC
                   LIMIT 12";
        $topTables = $pdo->query($tblSql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Non-fatal
    }

    $uptimeSec = (int)$metrics['Uptime'];
    $days  = floor($uptimeSec / 86400);
    $hours = floor(($uptimeSec % 86400) / 3600);
    $mins  = floor(($uptimeSec % 3600) / 60);

    return [
        'version'               => $version,
        'threads_connected'     => (int)$metrics['Threads_connected'],
        'threads_running'       => (int)$metrics['Threads_running'],
        'max_used_connections'  => (int)$metrics['Max_used_connections'],
        'uptime_sec'            => $uptimeSec,
        'uptime_human'          => "{$days}h {$hours}j {$mins}m",
        'questions'             => (int)$metrics['Questions'],
        'slow_queries'          => (int)$metrics['Slow_queries'],
        'aborted_connects'      => (int)$metrics['Aborted_connects'],
        'table_locks_waited'    => (int)$metrics['Table_locks_waited'],
        'processlist'           => $processes,
        'databases'             => $databases,
        'top_tables'            => $topTables,
    ];
}

/**
 * Inspect SSL certificate issuer & expiry days (native PHP stream_socket_client)
 */
function gc_inspect_ssl_cert($host, $timeout = 5)
{
    $result = ['days_left' => null, 'issuer' => null, 'valid_to' => null];
    if ($host === '' || !function_exists('stream_socket_client')) {
        return $result;
    }
    $ctx = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'verify_peer'       => false,
            'verify_peer_name'  => false,
        ],
    ]);
    $fp = @stream_socket_client("ssl://{$host}:443", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return $result;
    }
    $params = stream_context_get_params($fp);
    fclose($fp);
    if (empty($params['options']['ssl']['peer_certificate'])) {
        return $result;
    }
    $cert = @openssl_x509_parse($params['options']['ssl']['peer_certificate']);
    if (!is_array($cert)) {
        return $result;
    }
    if (!empty($cert['validTo_time_t'])) {
        $validTo = (int)$cert['validTo_time_t'];
        $result['valid_to']  = date('Y-m-d', $validTo);
        $result['days_left'] = (int)floor(($validTo - time()) / 86400);
    }
    if (!empty($cert['issuer']['O'])) {
        $result['issuer'] = (string)$cert['issuer']['O'];
    } elseif (!empty($cert['issuer']['CN'])) {
        $result['issuer'] = (string)$cert['issuer']['CN'];
    }
    return $result;
}

/**
 * 6. Subdomain HTTP Uptime, TTFB Latency, SSL & Security Headers Audit
 */
function gc_check_ecosystem_uptime(PDO $pdo = null, $saveToDb = false)
{
    $targets = gc_get_uptime_targets();
    $results = [];
    $sslCache = [];

    foreach ($targets as $t) {
        $url  = (string)$t['url'];
        $host = (string)($t['host'] ?? parse_url($url, PHP_URL_HOST));
        $respHeaders = [];

        $httpCode = 0;
        $ttfbMs   = 0;
        $totalMs  = 0;
        $errMsg   = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY         => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT      => 'GuardianControl-Monitor/1.0 (+https://guardian-dome.ilmuna.my.id)',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_HEADERFUNCTION => function ($chObj, $line) use (&$respHeaders) {
                    $len = strlen($line);
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                    }
                    return $len;
                },
            ]);
            $body = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $ttfbMs   = (int)round((float)curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME) * 1000);
            $totalMs  = (int)round((float)curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
            if ($body === false) {
                $errMsg = curl_error($ch);
            }
            curl_close($ch);
        } else {
            $errMsg = 'Ekstensi PHP cURL tidak tersedia';
        }

        if (!isset($sslCache[$host])) {
            $sslCache[$host] = (strpos($url, 'https://') === 0) ? gc_inspect_ssl_cert($host, 4) : ['days_left' => null, 'issuer' => null, 'valid_to' => null];
        }
        $ssl = $sslCache[$host];

        $secHeaders = [
            'hsts'                   => isset($respHeaders['strict-transport-security']),
            'csp'                    => isset($respHeaders['content-security-policy']),
            'x_frame_options'        => isset($respHeaders['x-frame-options']),
            'x_content_type_options' => isset($respHeaders['x-content-type-options']),
            'referrer_policy'        => isset($respHeaders['referrer-policy']),
        ];
        $secScore = 0;
        foreach ($secHeaders as $present) {
            if ($present) {
                $secScore++;
            }
        }

        $isUp = ($httpCode >= 200 && $httpCode < 400);
        $item = [
            'target_name'      => (string)$t['name'],
            'host'             => $host,
            'target_url'       => $url,
            'http_code'        => $httpCode,
            'ttfb_ms'          => $ttfbMs,
            'latency_ms'       => $totalMs,
            'ssl_days_left'    => $ssl['days_left'],
            'ssl_issuer'       => $ssl['issuer'],
            'ssl_valid_to'     => $ssl['valid_to'],
            'security_headers' => $secHeaders,
            'security_score'   => $secScore,
            'is_up'            => $isUp ? 1 : 0,
            'error_message'    => $errMsg,
            'checked_at'       => date('Y-m-d H:i:s'),
        ];
        $results[] = $item;

        if ($saveToDb && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO gc_uptime_checks (target_name, target_url, http_code, latency_ms, ssl_days_left, is_up, error_message, checked_at)
                     VALUES (:n, :u, :c, :l, :s, :up, :e, NOW())"
                );
                $stmt->execute([
                    ':n'  => substr($item['target_name'], 0, 80),
                    ':u'  => substr($item['target_url'], 0, 255),
                    ':c'  => $item['http_code'],
                    ':l'  => $item['latency_ms'],
                    ':s'  => $item['ssl_days_left'],
                    ':up' => $item['is_up'],
                    ':e'  => $item['error_message'] !== null ? substr((string)$item['error_message'], 0, 255) : null,
                ]);
            } catch (Throwable $e) {
                // Non-fatal
            }
        }
    }

    return $results;
}

/**
 * 7. Cron & Background Job Heartbeat Monitor
 */
function gc_get_cron_heartbeat(PDO $pdo)
{
    $lastCron = gc_get_setting($pdo, 'cron_last_heartbeat', null);
    $lastPurge = gc_get_setting($pdo, 'purger_last_run', null);

    $cronTs = is_array($lastCron) && !empty($lastCron['ts']) ? (int)$lastCron['ts'] : 0;
    $ageSec = $cronTs > 0 ? (time() - $cronTs) : null;
    $status = 'never';
    if ($ageSec !== null) {
        $status = ($ageSec <= 600) ? 'healthy' : (($ageSec <= 1800) ? 'delayed' : 'stalled');
    }

    return [
        'collect_telemetry' => [
            'status'      => $status,
            'last_run'    => $cronTs > 0 ? date('Y-m-d H:i:s', $cronTs) : null,
            'age_seconds' => $ageSec,
            'note'        => is_array($lastCron) ? (string)($lastCron['summary'] ?? '') : 'Belum pernah berjalan via Cron',
        ],
        'cache_purger' => [
            'last_run' => is_array($lastPurge) && !empty($lastPurge['at']) ? (string)$lastPurge['at'] : null,
            'summary'  => is_array($lastPurge) ? (string)($lastPurge['summary'] ?? '') : 'Belum ada riwayat purge manual',
        ],
    ];
}
