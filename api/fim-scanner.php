<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL API — FILE INTEGRITY (FIM SHA-256), PERMISSION AUDIT & WEBSHELL SCAN
 * ==============================================================================
 */
require_once dirname(__DIR__) . '/auth.php';
require_once BASE_PATH . '/includes/waf-bridge.php';

$admin  = requireAdminApiAuth(true);
$pdo    = getDBConnection();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

function gc_fim_run_scan(PDO $pdo, $resetBaseline = false)
{
    $watchlist = gc_get_fim_watchlist();
    $results = [];

    $existingStmt = $pdo->query("SELECT file_path, baseline_sha256 FROM gc_fim_hashes");
    $existingMap = $existingStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $upsert = $pdo->prepare(
        "INSERT INTO gc_fim_hashes (app_source, file_path, baseline_sha256, last_sha256, file_size, status, last_checked_at, updated_at)
         VALUES (:app, :fp, :base, :last, :sz, :st, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            app_source = VALUES(app_source),
            baseline_sha256 = VALUES(baseline_sha256),
            last_sha256 = VALUES(last_sha256),
            file_size = VALUES(file_size),
            status = VALUES(status),
            last_checked_at = NOW()"
    );

    foreach ($watchlist as $item) {
        $app  = (string)$item['app'];
        $path = str_replace('\\', '/', (string)$item['path']);

        if (!is_file($path)) {
            $baseHash = isset($existingMap[$path]) ? (string)$existingMap[$path] : str_repeat('0', 64);
            $status = 'missing';
            $upsert->execute([
                ':app'  => $app,
                ':fp'   => substr($path, 0, 300),
                ':base' => $baseHash,
                ':last' => str_repeat('0', 64),
                ':sz'   => 0,
                ':st'   => $status,
            ]);
            $results[] = [
                'app_source'      => $app,
                'file_path'       => $path,
                'baseline_sha256' => $baseHash,
                'last_sha256'     => str_repeat('0', 64),
                'file_size'       => 0,
                'status'          => $status,
                'last_checked_at' => date('Y-m-d H:i:s'),
            ];
            continue;
        }

        $currHash = hash_file('sha256', $path);
        $fSize    = (int)@filesize($path);
        if ($resetBaseline || !isset($existingMap[$path]) || $existingMap[$path] === str_repeat('0', 64)) {
            $baseHash = $currHash;
        } else {
            $baseHash = (string)$existingMap[$path];
        }

        $status = hash_equals($baseHash, $currHash) ? 'ok' : 'modified';
        $upsert->execute([
            ':app'  => $app,
            ':fp'   => substr($path, 0, 300),
            ':base' => $baseHash,
            ':last' => $currHash,
            ':sz'   => $fSize,
            ':st'   => $status,
        ]);

        $results[] = [
            'app_source'      => $app,
            'file_path'       => $path,
            'baseline_sha256' => $baseHash,
            'last_sha256'     => $currHash,
            'file_size'       => $fSize,
            'status'          => $status,
            'last_checked_at' => date('Y-m-d H:i:s'),
        ];
    }

    return $results;
}

/**
 * Audit Hak Akses File (0777) & Eksposur Berkas Sensitif (.env, .git, .sql, .zip)
 */
function gc_run_hardening_audit()
{
    $findings = [];
    $roots = [
        'guardian_control' => BASE_PATH,
        'guardian_dome'    => GC_WAF_DIR,
    ];
    if (is_dir('/home/uofruaju/public_html')) {
        $roots['wordpress'] = '/home/uofruaju/public_html';
    }
    if (is_dir('/home/uofruaju/margo-one.ilmuna.my.id')) {
        $roots['margo_one'] = '/home/uofruaju/margo-one.ilmuna.my.id';
    }
    if (is_dir('/home/uofruaju/learn.ilmuna.my.id')) {
        $roots['learn'] = '/home/uofruaju/learn.ilmuna.my.id';
    }

    $sensitiveExposed = ['.env', 'backup.sql', 'dump.sql', 'database.sql', 'backup.zip', 'site.zip', 'phpinfo.php'];

    foreach ($roots as $app => $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        // Cek permission folder utama (khusus Linux/Unix)
        if (DIRECTORY_SEPARATOR === '/') {
            $perms = @fileperms($dir);
            if ($perms !== false && (($perms & 0777) === 0777)) {
                $findings[] = [
                    'severity' => 'critical',
                    'app'      => $app,
                    'type'     => 'WORLD_WRITABLE_0777',
                    'path'     => $dir,
                    'detail'   => 'Direktori memiliki hak akses 0777 (world-writable). Ubah ke 0755.',
                ];
            }
            foreach (['config.php', 'config.local.php', 'wp-config.php'] as $cf) {
                $cfp = $dir . '/' . $cf;
                if (is_file($cfp)) {
                    $cp = @fileperms($cfp) & 0777;
                    if (($cp & 0004) !== 0 && ($cp & 0002) !== 0) {
                        $findings[] = [
                            'severity' => 'high',
                            'app'      => $app,
                            'type'     => 'CONFIG_PERM_OPEN',
                            'path'     => $cfp,
                            'detail'   => sprintf('File konfigurasi memiliki izin %04o. Disarankan 0640 atau 0600.', $cp),
                        ];
                    }
                }
            }
        }

        // Cek eksposur file sensitif di document root
        foreach ($sensitiveExposed as $sf) {
            $sfp = $dir . '/' . $sf;
            if (is_file($sfp)) {
                $findings[] = [
                    'severity' => 'high',
                    'app'      => $app,
                    'type'     => 'SENSITIVE_FILE_EXPOSED',
                    'path'     => $sfp,
                    'detail'   => "File sensitif {$sf} terdeteksi di root aplikasi.",
                ];
            }
        }
        if (is_dir($dir . '/.git') && !is_file($dir . '/.htaccess')) {
            $findings[] = [
                'severity' => 'high',
                'app'      => $app,
                'type'     => 'GIT_EXPOSURE_RISK',
                'path'     => $dir . '/.git',
                'detail'   => 'Folder .git terdeteksi tanpa proteksi .htaccess.',
            ];
        }
    }

    return $findings;
}

/**
 * Deep Webshell & Polyglot Scanner di direktori uploads/
 */
function gc_scan_uploads_webshells()
{
    $dirs = gc_get_uploads_scan_dirs();
    $threats = [];
    $scannedCount = 0;
    $dangerExts = ['php', 'phtml', 'php5', 'php7', 'phar', 'pht', 'shtml', 'cgi', 'pl', 'asp', 'aspx', 'jsp'];
    $polyglotRegex = '/(<\?php|\beval\s*\(|\bbase64_decode\s*\(|\bgzinflate\s*\(|\bshell_exec\s*\(|\bsystem\s*\(|\bpassthru\s*\(|\bexec\s*\()/i';

    foreach ($dirs as $app => $dir) {
        if (!is_dir($dir) || !is_readable($dir)) {
            continue;
        }
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($it as $file) {
                if ($scannedCount >= 3000) {
                    break 2;
                }
                if (!$file->isFile()) {
                    continue;
                }
                $scannedCount++;
                $path = str_replace('\\', '/', $file->getPathname());
                $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $base = strtolower($file->getFilename());

                if (in_array($ext, $dangerExts, true)) {
                    $threats[] = [
                        'app'      => $app,
                        'severity' => 'critical',
                        'type'     => 'DANGEROUS_EXECUTABLE_EXT',
                        'path'     => $path,
                        'detail'   => "File berekstensi berbahaya (.{$ext}) di dalam direktori uploads.",
                    ];
                    continue;
                }

                if ($base === '.htaccess') {
                    $raw = @file_get_contents($path, false, null, 0, 4096);
                    if (is_string($raw) && preg_match('/(AddType|SetHandler|php_value|auto_prepend)/i', $raw)) {
                        $threats[] = [
                            'app'      => $app,
                            'severity' => 'high',
                            'type'     => 'SUSPICIOUS_HTACCESS',
                            'path'     => $path,
                            'detail'   => '.htaccess di uploads mengandung handler eksekusi PHP mencurigakan.',
                        ];
                    }
                    continue;
                }

                // Cek polyglot head + tail pada gambar/dokumen (<= 5MB)
                $fSize = (int)$file->getSize();
                if ($fSize > 0 && $fSize <= 5242880) {
                    $head = @file_get_contents($path, false, null, 0, 2048);
                    $tail = $fSize > 2048 ? @file_get_contents($path, false, null, $fSize - 2048, 2048) : '';
                    $sample = (is_string($head) ? $head : '') . "\n" . (is_string($tail) ? $tail : '');
                    if (preg_match($polyglotRegex, $sample, $pm)) {
                        $threats[] = [
                            'app'      => $app,
                            'severity' => 'critical',
                            'type'     => 'POLYGLOT_WEBSHELL_SIGNATURE',
                            'path'     => $path,
                            'detail'   => 'Ditemukan signature kode berbahaya (' . trim($pm[1]) . ') di dalam file upload.',
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            // Skip unreadable dir
        }
    }

    return [
        'scanned_dirs'  => $dirs,
        'scanned_files' => $scannedCount,
        'threats'       => $threats,
    ];
}

if ($method === 'POST') {
    $input  = gc_get_json_input();
    $action = trim((string)($input['action'] ?? 'scan'));

    if ($action === 'reset_baseline') {
        $rows = gc_fim_run_scan($pdo, true);
        gc_audit_log('fim_reset_baseline', count($rows) . ' files', [], $admin['username']);
        gc_json_response([
            'success' => true,
            'message' => 'Baseline SHA-256 berhasil diperbarui untuk ' . count($rows) . ' berkas kritis.',
            'fim'     => $rows,
        ]);
    }

    if ($action === 'scan_now') {
        $rows = gc_fim_run_scan($pdo, false);
        $hardening = gc_run_hardening_audit();
        $uploads = gc_scan_uploads_webshells();
        gc_audit_log('fim_manual_scan', 'full', ['fim' => count($rows), 'uploads_threats' => count($uploads['threats'])], $admin['username']);
        gc_json_response([
            'success'   => true,
            'message'   => 'Pemindaian FIM SHA-256, Permission Audit & Deep Webshell selesai.',
            'fim'       => $rows,
            'hardening' => $hardening,
            'uploads'   => $uploads,
        ]);
    }

    gc_json_response(['success' => false, 'error' => 'Aksi FIM tidak dikenal.'], 400);
}

// GET: tampilkan status FIM (auto-init baseline bila kosong), hardening audit, & uploads scan
$fimRows = $pdo->query("SELECT app_source, file_path, baseline_sha256, last_sha256, file_size, status, last_checked_at FROM gc_fim_hashes ORDER BY FIELD(status,'modified','missing','ok'), app_source, file_path")->fetchAll(PDO::FETCH_ASSOC);
if (empty($fimRows)) {
    $fimRows = gc_fim_run_scan($pdo, true);
}

$hardening = gc_run_hardening_audit();
$uploads   = gc_scan_uploads_webshells();

gc_json_response([
    'success'   => true,
    'fim'       => $fimRows,
    'hardening' => $hardening,
    'uploads'   => $uploads,
]);
