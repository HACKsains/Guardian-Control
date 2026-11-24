<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL API — CENTRALIZED ERROR LOG & CRASH MONITOR (ANTI-LFI)
 * ==============================================================================
 */
require_once dirname(__DIR__) . '/auth.php';
require_once BASE_PATH . '/includes/log-reader.php';

$admin  = requireAdminApiAuth(true);
$pdo    = getDBConnection();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

$sources = gc_get_error_log_sources();

if ($method === 'GET') {
    $source   = strtolower(trim((string)($_GET['source'] ?? 'all')));
    $level    = strtolower(trim((string)($_GET['level'] ?? 'all')));
    $download = !empty($_GET['download']);

    // STRICT ANTI-LFI WHITELIST CHECK
    if ($source !== 'all' && !isset($sources[$source])) {
        gc_json_response([
            'success' => false,
            'error'   => 'Parameter source ditolak (hanya menerima whitelist preset aplikasi).',
            'code'    => 'INVALID_SOURCE_WHITELIST',
        ], 400);
    }

    // Unduh mentah tail log (max 512KB) untuk 1 sumber terverifikasi
    if ($download) {
        if ($source === 'all') {
            gc_json_response(['success' => false, 'error' => 'Pilih satu aplikasi spesifik untuk mengunduh log.'], 400);
        }
        $files = gc_resolve_log_files_for_source($source);
        $content = '';
        foreach ($files as $fp) {
            $content .= "=== " . basename($fp) . " ===\n" . gc_read_tail_bytes($fp, 524288) . "\n\n";
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="error-log-' . $source . '-' . date('Ymd-His') . '.log"');
        echo $content !== '' ? $content : "(Tidak ada isi log untuk {$source})";
        exit;
    }

    // Sinkronkan error log terbaru ke DB
    gc_sync_error_logs_to_db($pdo, $source);

    $where  = [];
    $params = [];
    if ($source !== 'all') {
        $where[] = "app_source = :src";
        $params[':src'] = $source;
    }
    if ($level !== '' && $level !== 'all') {
        $where[] = "level = :lvl";
        $params[':lvl'] = $level;
    }
    $whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

    $stmt = $pdo->prepare(
        "SELECT id, app_source, level, fingerprint, message, file_path, line_num, hit_count, is_resolved, first_seen_at, last_seen_at
         FROM gc_error_snapshots
         {$whereSql}
         ORDER BY is_resolved ASC, last_seen_at DESC
         LIMIT 150"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Metadata file fisik tiap sumber
    $sourceMeta = [];
    foreach ($sources as $k => $cfg) {
        $files = gc_resolve_log_files_for_source($k);
        $totalBytes = 0;
        foreach ($files as $fp) {
            $totalBytes += (int)@filesize($fp);
        }
        $sourceMeta[$k] = [
            'key'        => $k,
            'label'      => $cfg['label'],
            'icon'       => $cfg['icon'],
            'file_count' => count($files),
            'size_bytes' => $totalBytes,
            'size_human' => gc_format_bytes($totalBytes),
        ];
    }

    gc_json_response([
        'success' => true,
        'sources' => $sourceMeta,
        'errors'  => $rows,
    ]);
}

if ($method === 'POST') {
    $input  = gc_get_json_input();
    $action = trim((string)($input['action'] ?? ''));

    if ($action === 'resolve') {
        $id = (int)($input['id'] ?? 0);
        $resolved = !empty($input['resolved']) ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE gc_error_snapshots SET is_resolved = :r WHERE id = :id");
        $stmt->execute([':r' => $resolved, ':id' => $id]);
        gc_audit_log('error_log_resolve', (string)$id, ['resolved' => $resolved], $admin['username']);
        gc_json_response(['success' => true, 'message' => $resolved ? 'Error ditandai selesai.' : 'Error dibuka kembali.']);
    }

    if ($action === 'truncate') {
        $source = strtolower(trim((string)($input['source'] ?? '')));
        if (!isset($sources[$source])) {
            gc_json_response(['success' => false, 'error' => 'Source key tidak valid (whitelist only).'], 400);
        }
        $res = gc_truncate_error_log($source, true);
        if (empty($res['success'])) {
            gc_json_response(['success' => false, 'error' => $res['error'] ?? 'Gagal mengosongkan log.'], 400);
        }
        gc_audit_log('error_log_truncate', $source, $res['truncated'], $admin['username']);
        gc_json_response([
            'success'   => true,
            'message'   => "Log {$sources[$source]['label']} berhasil diarsipkan & dikosongkan.",
            'truncated' => $res['truncated'],
        ]);
    }

    gc_json_response(['success' => false, 'error' => 'Aksi error log tidak dikenal.'], 400);
}

gc_json_response(['success' => false, 'error' => 'Method Not Allowed'], 405);
