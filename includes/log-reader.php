<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — CENTRALIZED ERROR LOG TAIL READER & PARSER (ANTI-LFI)
 * ==============================================================================
 * - Whitelist preset key KETAT (hanya menerima key dari gc_get_error_log_sources()).
 * - Membaca maksimal 200KB terakhir via fseek() (tidak pernah memuat file GB ke RAM).
 * - Mengklasifikasikan Fatal, Exception, Warning, Notice/Deprecated, MySQL/PDO,
 *   mengekstrak file, nomor baris, stack trace, dan deduplikasi berdasarkan fingerprint.
 * ==============================================================================
 */

if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__) . '/config.php';
}

/**
 * Validasi ketat source key (100% kebal LFI / Path Traversal)
 */
function gc_validate_log_source_key($sourceKey)
{
    $sourceKey = strtolower(trim((string)$sourceKey));
    $sources = gc_get_error_log_sources();
    if (!isset($sources[$sourceKey])) {
        return null;
    }
    return $sources[$sourceKey];
}

/**
 * Cari file fisik pertama yang tersedia untuk source key
 */
function gc_resolve_log_files_for_source($sourceKey)
{
    $src = gc_validate_log_source_key($sourceKey);
    if ($src === null) {
        return [];
    }
    $found = [];
    foreach ($src['paths'] as $p) {
        if (is_file($p) && is_readable($p)) {
            $found[] = $p;
        }
    }
    return $found;
}

/**
 * Tail-read aman dengan fseek()
 */
function gc_read_tail_bytes($filePath, $maxBytes = 204800)
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        return '';
    }
    $size = (int)@filesize($filePath);
    if ($size <= 0) {
        return '';
    }
    $fh = @fopen($filePath, 'rb');
    if (!$fh) {
        return '';
    }
    $readBytes = min($size, max(4096, (int)$maxBytes));
    if ($size > $readBytes) {
        fseek($fh, $size - $readBytes);
        fgets($fh); // Buang baris terpotong pertama
    }
    $data = fread($fh, $readBytes);
    fclose($fh);
    return is_string($data) ? $data : '';
}

/**
 * Klasifikasi level & ekstrak file, line, message dari 1 entri error PHP/MySQL
 */
function gc_parse_error_entry($rawEntry, $appSource)
{
    $rawEntry = trim((string)$rawEntry);
    if ($rawEntry === '') {
        return null;
    }

    $lines = preg_split('/\r\n|\r|\n/', $rawEntry);
    $firstLine = trim((string)$lines[0]);
    $stackTrace = count($lines) > 1 ? implode("\n", array_slice($lines, 1, 15)) : '';

    // Ekstrak timestamp [DD-Mon-YYYY HH:MM:SS ...]
    $ts = date('Y-m-d H:i:s');
    $msgBody = $firstLine;
    if (preg_match('/^\[([^\]]+)\]\s*(.*)$/', $firstLine, $m)) {
        $parsedTs = strtotime($m[1]);
        if ($parsedTs !== false) {
            $ts = date('Y-m-d H:i:s', $parsedTs);
        }
        $msgBody = trim($m[2]);
    }

    // Klasifikasi severity
    $lower = strtolower($msgBody);
    $level = 'other';
    if (strpos($lower, 'pdoexception') !== false || strpos($lower, 'sqlstate') !== false || strpos($lower, 'mysqli') !== false || strpos($lower, 'mysql') !== false) {
        $level = 'sql';
    } elseif (strpos($lower, 'fatal error') !== false || strpos($lower, 'parse error') !== false || strpos($lower, 'core error') !== false || strpos($lower, 'compile error') !== false) {
        $level = 'fatal';
    } elseif (strpos($lower, 'uncaught') !== false || strpos($lower, 'exception') !== false || strpos($lower, 'error:') !== false) {
        $level = 'exception';
    } elseif (strpos($lower, 'warning') !== false) {
        $level = 'warning';
    } elseif (strpos($lower, 'deprecated') !== false || strpos($lower, 'notice') !== false || strpos($lower, 'strict standards') !== false) {
        $level = 'notice';
    }

    // Ekstrak file & nomor baris
    $filePath = null;
    $lineNum  = null;
    if (preg_match('/\s+in\s+([^\s:]+(?:\s[^\s:]+)*?)\s+on\s+line\s+(\d+)/i', $msgBody, $fm)) {
        $filePath = substr(trim($fm[1]), 0, 255);
        $lineNum  = (int)$fm[2];
    } elseif (preg_match('/\s+in\s+([^\s:]+):(\d+)/i', $msgBody, $fm)) {
        $filePath = substr(trim($fm[1]), 0, 255);
        $lineNum  = (int)$fm[2];
    }

    // Normalisasi pesan untuk deduplikasi fingerprint
    $normMsg = preg_replace('/\b\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}\b/', '', $msgBody);
    $fingerprint = md5($appSource . '|' . $level . '|' . $filePath . '|' . $lineNum . '|' . substr(trim((string)$normMsg), 0, 200));

    return [
        'app_source'  => $appSource,
        'level'       => $level,
        'fingerprint' => $fingerprint,
        'message'     => substr($msgBody, 0, 1000),
        'stack_trace' => substr($stackTrace, 0, 2500),
        'file_path'   => $filePath,
        'line_num'    => $lineNum,
        'occurred_at' => $ts,
    ];
}

/**
 * Baca & parse error log untuk 1 source key terverifikasi
 */
function gc_tail_error_log($sourceKey, $maxBytes = 204800)
{
    $src = gc_validate_log_source_key($sourceKey);
    if ($src === null) {
        return ['error' => 'Sumber error_log tidak dikenal atau tidak diizinkan (whitelist only).'];
    }

    $files = gc_resolve_log_files_for_source($sourceKey);
    $grouped = [];
    $fileMeta = [];

    foreach ($files as $fp) {
        $fSize = (int)@filesize($fp);
        $fileMeta[] = [
            'path'       => $fp,
            'size_bytes' => $fSize,
            'size_human' => gc_format_bytes($fSize),
            'mtime'      => date('Y-m-d H:i:s', (int)@filemtime($fp)),
        ];

        $content = gc_read_tail_bytes($fp, $maxBytes);
        if ($content === '') {
            continue;
        }

        // Pisahkan entri log berdasarkan awalan timestamp [DD-Mon-YYYY ... atau baris baru non-stack
        $rawLines = preg_split('/\r\n|\r|\n/', $content);
        $entries = [];
        $current = '';
        foreach ($rawLines as $ln) {
            if (preg_match('/^\[[0-9]{2}-[A-Za-z]{3}-[0-9]{4}|^\[[0-9]{4}-[0-9]{2}-[0-9]{2}/', $ln)) {
                if ($current !== '') {
                    $entries[] = $current;
                }
                $current = $ln;
            } elseif (preg_match('/^(Stack trace:|#\d+\s|\s+thrown in\s)/', $ln)) {
                $current .= "\n" . $ln;
            } else {
                if ($current !== '') {
                    $entries[] = $current;
                }
                $current = $ln;
            }
        }
        if ($current !== '') {
            $entries[] = $current;
        }

        foreach ($entries as $rawE) {
            $parsed = gc_parse_error_entry($rawE, $sourceKey);
            if ($parsed === null) {
                continue;
            }
            $fpKey = $parsed['fingerprint'];
            if (!isset($grouped[$fpKey])) {
                $parsed['hit_count'] = 1;
                $parsed['first_seen_at'] = $parsed['occurred_at'];
                $parsed['last_seen_at']  = $parsed['occurred_at'];
                $grouped[$fpKey] = $parsed;
            } else {
                $grouped[$fpKey]['hit_count']++;
                if ($parsed['occurred_at'] > $grouped[$fpKey]['last_seen_at']) {
                    $grouped[$fpKey]['last_seen_at'] = $parsed['occurred_at'];
                }
                if ($parsed['stack_trace'] !== '' && $grouped[$fpKey]['stack_trace'] === '') {
                    $grouped[$fpKey]['stack_trace'] = $parsed['stack_trace'];
                }
            }
        }
    }

    $items = array_values($grouped);
    usort($items, function ($a, $b) {
        return strcmp($b['last_seen_at'], $a['last_seen_at']);
    });

    return [
        'source' => $sourceKey,
        'label'  => $src['label'],
        'files'  => $fileMeta,
        'items'  => $items,
    ];
}

/**
 * Sinkronkan hasil tail error log ke tabel gc_error_snapshots
 */
function gc_sync_error_logs_to_db(PDO $pdo, $sourceKey = 'all')
{
    $sources = gc_get_error_log_sources();
    $keys = ($sourceKey === 'all') ? array_keys($sources) : [$sourceKey];
    $stmt = $pdo->prepare(
        "INSERT INTO gc_error_snapshots
            (app_source, level, fingerprint, message, file_path, line_num, hit_count, is_resolved, first_seen_at, last_seen_at)
         VALUES
            (:src, :lvl, :fp, :msg, :fpath, :lnum, :hits, 0, :first, :last)
         ON DUPLICATE KEY UPDATE
            hit_count = GREATEST(hit_count, VALUES(hit_count)),
            last_seen_at = GREATEST(last_seen_at, VALUES(last_seen_at)),
            message = VALUES(message)"
    );

    $synced = 0;
    foreach ($keys as $k) {
        if (!isset($sources[$k])) {
            continue;
        }
        $res = gc_tail_error_log($k);
        if (empty($res['items'])) {
            continue;
        }
        foreach ($res['items'] as $it) {
            $fullMsg = $it['message'];
            if (!empty($it['stack_trace'])) {
                $fullMsg .= "\n--- STACK TRACE ---\n" . $it['stack_trace'];
            }
            $stmt->execute([
                ':src'   => $k,
                ':lvl'   => $it['level'],
                ':fp'    => $it['fingerprint'],
                ':msg'   => $fullMsg,
                ':fpath' => $it['file_path'],
                ':lnum'  => $it['line_num'],
                ':hits'  => (int)$it['hit_count'],
                ':first' => $it['first_seen_at'],
                ':last'  => $it['last_seen_at'],
            ]);
            $synced++;
        }
    }
    return $synced;
}

/**
 * Arsipkan & kosongkan error_log secara aman (whitelist only)
 */
function gc_truncate_error_log($sourceKey, $archive = true)
{
    $src = gc_validate_log_source_key($sourceKey);
    if ($src === null) {
        return ['success' => false, 'error' => 'Source key tidak valid (whitelist only).'];
    }
    $files = gc_resolve_log_files_for_source($sourceKey);
    if (empty($files)) {
        return ['success' => false, 'error' => 'Tidak ada berkas error_log yang ditemukan untuk sumber ini.'];
    }

    $truncated = [];
    foreach ($files as $fp) {
        if (!is_writable($fp)) {
            continue;
        }
        $origSize = (int)@filesize($fp);
        if ($archive && $origSize > 0) {
            $archDir = GC_LOCAL_CACHE_DIR . '/log-archives';
            if (!is_dir($archDir)) {
                @mkdir($archDir, 0755, true);
            }
            $archFile = $archDir . '/' . $sourceKey . '_' . date('Ymd_His') . '.log';
            @file_put_contents($archFile, gc_read_tail_bytes($fp, 524288));
        }
        if (@file_put_contents($fp, '', LOCK_EX) !== false) {
            $truncated[] = ['path' => $fp, 'freed' => gc_format_bytes($origSize)];
        }
    }

    return ['success' => true, 'truncated' => $truncated];
}
