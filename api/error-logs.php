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
    $status   = strtolower(trim((string)($_GET['status'] ?? 'active')));
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

    // Hitung jumlah aktif vs selesai untuk filter source+level saat ini
    $baseWhereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));
    $cntStmt = $pdo->prepare(
        "SELECT
            SUM(CASE WHEN is_resolved = 0 THEN 1 ELSE 0 END) AS active_cnt,
            SUM(CASE WHEN is_resolved = 1 THEN 1 ELSE 0 END) AS resolved_cnt,
            COUNT(*) AS total_cnt
         FROM gc_error_snapshots {$baseWhereSql}"
    );
    $cntStmt->execute($params);
    $cntRow = $cntStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if ($status === 'active') {
        $where[] = "is_resolved = 0";
    } elseif ($status === 'resolved') {
        $where[] = "is_resolved = 1";
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
        'status'  => $status,
        'counts'  => [
            'active'   => (int)($cntRow['active_cnt'] ?? 0),
            'resolved' => (int)($cntRow['resolved_cnt'] ?? 0),
            'all'      => (int)($cntRow['total_cnt'] ?? 0),
        ],
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
        if ($id <= 0) {
            gc_json_response(['success' => false, 'error' => 'ID error tidak valid.'], 422);
        }
        $stmt = $pdo->prepare("UPDATE gc_error_snapshots SET is_resolved = :r WHERE id = :id");
        $stmt->execute([':r' => $resolved, ':id' => $id]);
        gc_audit_log('error_log_resolve', (string)$id, ['resolved' => $resolved], $admin['username']);
        gc_json_response([
            'success'  => true,
            'resolved' => $resolved,
            'message'  => $resolved ? 'Error ditandai selesai & dipindahkan ke arsip Selesai.' : 'Error dibuka kembali ke daftar Aktif.',
        ]);
    }

    if (in_array($action, ['bulk_resolve', 'bulk_reopen', 'bulk_delete'], true)) {
        $rawIds = isset($input['ids']) && is_array($input['ids']) ? $input['ids'] : [];
        $ids = [];
        foreach ($rawIds as $v) {
            $iv = (int)$v;
            if ($iv > 0) {
                $ids[] = $iv;
            }
        }
        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            gc_json_response(['success' => false, 'error' => 'Pilih minimal 1 baris error terlebih dahulu.'], 422);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($action === 'bulk_resolve') {
            $st = $pdo->prepare("UPDATE gc_error_snapshots SET is_resolved = 1 WHERE id IN ({$placeholders})");
            $st->execute($ids);
            $affected = $st->rowCount();
            gc_audit_log('error_log_bulk_resolve', count($ids) . ' items', ['ids' => $ids], $admin['username']);
            gc_json_response(['success' => true, 'affected' => $affected, 'message' => count($ids) . ' error berhasil ditandai selesai.']);
        } elseif ($action === 'bulk_reopen') {
            $st = $pdo->prepare("UPDATE gc_error_snapshots SET is_resolved = 0 WHERE id IN ({$placeholders})");
            $st->execute($ids);
            $affected = $st->rowCount();
            gc_audit_log('error_log_bulk_reopen', count($ids) . ' items', ['ids' => $ids], $admin['username']);
            gc_json_response(['success' => true, 'affected' => $affected, 'message' => count($ids) . ' error dibuka kembali ke daftar Aktif.']);
        } else {
            $st = $pdo->prepare("DELETE FROM gc_error_snapshots WHERE id IN ({$placeholders})");
            $st->execute($ids);
            $affected = $st->rowCount();
            gc_audit_log('error_log_bulk_delete', count($ids) . ' items', ['ids' => $ids], $admin['username']);
            gc_json_response(['success' => true, 'affected' => $affected, 'message' => "{$affected} snapshot error berhasil dihapus dari daftar."]);
        }
    }

    if ($action === 'resolve_all') {
        $source = strtolower(trim((string)($input['source'] ?? 'all')));
        $level  = strtolower(trim((string)($input['level'] ?? 'all')));
        $where  = ["is_resolved = 0"];
        $params = [];
        if ($source !== 'all' && isset($sources[$source])) {
            $where[] = "app_source = :src";
            $params[':src'] = $source;
        }
        if ($level !== '' && $level !== 'all') {
            $where[] = "level = :lvl";
            $params[':lvl'] = $level;
        }
        $st = $pdo->prepare("UPDATE gc_error_snapshots SET is_resolved = 1 WHERE " . implode(' AND ', $where));
        $st->execute($params);
        $affected = $st->rowCount();
        gc_audit_log('error_log_resolve_all', $source, ['level' => $level, 'affected' => $affected], $admin['username']);
        gc_json_response(['success' => true, 'affected' => $affected, 'message' => "{$affected} error aktif berhasil ditandai selesai semua."]);
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
        $pdo->prepare("UPDATE gc_error_snapshots SET is_resolved = 1 WHERE app_source = :src")->execute([':src' => $source]);
        gc_audit_log('error_log_truncate', $source, $res['truncated'], $admin['username']);
        gc_json_response([
            'success'   => true,
            'message'   => "Log {$sources[$source]['label']} berhasil diarsipkan, dikosongkan & seluruh error ditandai selesai.",
            'truncated' => $res['truncated'],
        ]);
    }

    gc_json_response(['success' => false, 'error' => 'Aksi error log tidak dikenal.'], 400);
}

gc_json_response(['success' => false, 'error' => 'Method Not Allowed'], 405);
