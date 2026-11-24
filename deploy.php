<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — WEB AUTO-DEPLOYER & GITHUB WEBHOOK SYNC (GIT + NATIVE PHP)
 * ==============================================================================
 * Mendukung 2 mesin update otomatis (100% cPanel-Safe):
 *   1. Native Git (`git fetch` + `git reset --hard origin/main`) jika shell_exec aktif
 *   2. Native PHP GitHub Zipball API (`cURL` + `ZipArchive`, tanpa shell_exec/SSH!)
 *      bila `github_pat` diisi di config.local.php.
 *
 * Cara 1 (Manual 1-Klik Browser):
 *   https://guardian-dome.ilmuna.my.id/deploy.php?secret=guardian_control_deploy_2026
 *
 * Cara 2 (Otomatis tiap git push via GitHub Webhook):
 *   Payload URL : https://guardian-dome.ilmuna.my.id/deploy.php
 *   Content type: application/json
 *   Secret      : guardian_control_deploy_2026
 * ==============================================================================
 */

require_once __DIR__ . '/config.php';

$deploySecret = (string)gc_cfg('deploy_secret', 'guardian_control_deploy_2026');
$githubRepo   = (string)gc_cfg('github_repo', 'HACKsains/Guardian-Control');
$githubBranch = (string)gc_cfg('github_branch', 'main');
$githubToken  = (string)gc_cfg('github_pat', '');

$rawPayload   = (string)file_get_contents('php://input');
$ghSig256     = isset($_SERVER['HTTP_X_HUB_SIGNATURE_256']) ? trim((string)$_SERVER['HTTP_X_HUB_SIGNATURE_256']) : '';
$querySecret  = isset($_GET['secret']) ? (string)$_GET['secret'] : '';

$isWebhook  = false;
$authorized = false;

if ($ghSig256 !== '') {
    $expected = 'sha256=' . hash_hmac('sha256', $rawPayload, $deploySecret);
    if (hash_equals($expected, $ghSig256)) {
        $authorized = true;
        $isWebhook  = true;
    }
} elseif ($querySecret !== '' && hash_equals($deploySecret, $querySecret)) {
    $authorized = true;
}

if (!$authorized) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="id"><body style="font-family:sans-serif;padding:40px;text-align:center;background:#F8FAFC;color:#1D3B5E;">';
    echo '<h2 style="color:#DC2626;">403 Forbidden — Akses Deploy Ditolak</h2>';
    echo '<p>Sertakan <code>?secret=...</code> yang valid atau tanda tangan GitHub Webhook (<code>X-Hub-Signature-256</code>).</p>';
    echo '</body></html>';
    exit;
}

/**
 * Fallback 100% Native PHP: unduh zipball private repo via GitHub API & ekstrak aman
 */
function gc_deploy_via_github_zip($repo, $branch, $token, $targetDir)
{
    if (!function_exists('curl_init') || !class_exists('ZipArchive')) {
        return "[ERROR] Ekstensi PHP cURL dan ZipArchive diperlukan untuk mode Native PHP Deploy.";
    }
    $url = "https://api.github.com/repos/{$repo}/zipball/{$branch}";
    $tmpZip = GC_LOCAL_CACHE_DIR . '/deploy_' . time() . '.zip';
    if (!is_dir(GC_LOCAL_CACHE_DIR)) {
        @mkdir(GC_LOCAL_CACHE_DIR, 0755, true);
    }

    $fp = @fopen($tmpZip, 'wb');
    if (!$fp) {
        return "[ERROR] Tidak dapat membuat file sementara di {$tmpZip}.";
    }

    $headers = [
        'User-Agent: GuardianControl-Deployer/1.0',
        'Accept: application/vnd.github+json',
    ];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $ok = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if (!$ok || $httpCode !== 200) {
        @unlink($tmpZip);
        return "[ERROR] Gagal mengunduh arsip GitHub (HTTP {$httpCode}): {$err}";
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpZip) !== true) {
        @unlink($tmpZip);
        return "[ERROR] Arsip ZIP dari GitHub rusak/tidak bisa dibuka.";
    }

    $skipFiles = ['config.local.php'];
    $updated = 0;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entryName = $zip->getNameIndex($i);
        $slashPos  = strpos($entryName, '/');
        if ($slashPos === false) {
            continue;
        }
        $relPath = substr($entryName, $slashPos + 1);
        if ($relPath === '' || substr($relPath, -1) === '/') {
            continue;
        }
        if (strpos($relPath, '..') !== false || strpos($relPath, '.git/') === 0 || in_array($relPath, $skipFiles, true)) {
            continue;
        }

        $destPath = rtrim($targetDir, '/\\') . '/' . $relPath;
        $destDir  = dirname($destPath);
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }
        $stream = $zip->getStream($entryName);
        if ($stream) {
            $contents = stream_get_contents($stream);
            fclose($stream);
            if ($contents !== false && @file_put_contents($destPath, $contents, LOCK_EX) !== false) {
                $updated++;
            }
        }
    }

    $zip->close();
    @unlink($tmpZip);
    return "[OK] Native PHP GitHub Deploy berhasil memperbarui {$updated} berkas dari {$repo}@{$branch} (config.local.php & cache/ tetap aman).";
}

$repoPath = __DIR__;
$disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
$canShell = !in_array('shell_exec', $disabled, true) && function_exists('shell_exec');
$logs     = [];
$gitOk    = false;

if ($canShell && is_dir($repoPath . '/.git')) {
    $fetchOut = trim((string)@shell_exec('cd ' . escapeshellarg($repoPath) . ' && git fetch origin ' . escapeshellarg($githubBranch) . ' 2>&1'));
    $logs[] = "=== Git Fetch ===\n" . ($fetchOut !== '' ? $fetchOut : '(up to date)');
    if (stripos($fetchOut, 'fatal:') === false && stripos($fetchOut, 'error:') === false) {
        $resetOut = trim((string)@shell_exec('cd ' . escapeshellarg($repoPath) . ' && git reset --hard origin/' . escapeshellarg($githubBranch) . ' 2>&1'));
        $statOut  = trim((string)@shell_exec('cd ' . escapeshellarg($repoPath) . ' && git log -1 --stat 2>&1'));
        $logs[] = "=== Git Reset Hard ===\n" . $resetOut;
        $logs[] = "=== Last Commit ===\n" . $statOut;
        $gitOk = true;
    }
}

if (!$gitOk) {
    if ($githubToken !== '') {
        $logs[] = "=== Native PHP GitHub Zipball Fallback ===\n" . gc_deploy_via_github_zip($githubRepo, $githubBranch, $githubToken, $repoPath);
    } else {
        $logs[] = "[INFO] Untuk mengaktifkan Native PHP Auto-Update tanpa SSH/Git, tambahkan 'github_pat' => 'github_pat_...' di config.local.php.";
    }
}

// Verifikasi Auto-Migration 9 tabel gc_*
try {
    require_once __DIR__ . '/database.php';
    $pdo = getDBConnection();
    $tables = $pdo->query("SHOW TABLES LIKE 'gc_%'")->fetchAll(PDO::FETCH_COLUMN);
    $logs[] = "=== Verifikasi Database (" . GC_DB_NAME . ") ===\nOK — " . count($tables) . "/9 tabel aktif: " . implode(', ', $tables);
    gc_audit_log('system_git_deploy', $isWebhook ? 'github_webhook' : 'manual_web', ['git_ok' => $gitOk, 'tables' => count($tables)], 'deployer');
} catch (Throwable $e) {
    $logs[] = "=== Verifikasi Database ===\n[ERROR] " . $e->getMessage();
}

$outputText = implode("\n\n", $logs);

if ($isWebhook) {
    gc_json_response([
        'success' => true,
        'mode'    => 'github_webhook',
        'time'    => date('Y-m-d H:i:s'),
        'log'     => $outputText,
    ]);
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guardian Control — Auto Deployer</title>
    <style>
        body { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif; background: #0B1523; color: #F8FAFC; padding: 28px 16px; margin: 0; }
        .card { max-width: 860px; margin: 0 auto; background: #102035; border: 1px solid rgba(82,178,220,.28); border-radius: 18px; padding: 24px; box-shadow: 0 20px 45px rgba(0,0,0,.45); }
        h1 { margin: 0 0 8px; font-size: 20px; color: #52B2DC; }
        p { color: #94A3B8; font-size: 13.5px; margin: 0 0 18px; }
        pre { background: #070E18; color: #A5F3FC; padding: 16px; border-radius: 12px; overflow-x: auto; font-size: 12.5px; line-height: 1.55; border: 1px solid rgba(82,178,220,.18); }
        .btn { display: inline-block; padding: 10px 18px; background: linear-gradient(135deg, #1D3B5E, #0D7A87); color: #fff; text-decoration: none; border-radius: 10px; font-weight: 700; margin-top: 14px; font-size: 13px; }
    </style>
</head>
<body>
<div class="card">
    <h1>🛡️ Guardian Control — Auto Deployer</h1>
    <p>Sinkronisasi Git private repo &amp; verifikasi skema database <code><?= gc_e(GC_DB_NAME) ?></code>.</p>
    <pre><?= gc_e($outputText) ?></pre>
    <a class="btn" href="?secret=<?= gc_e($querySecret) ?>">Jalankan Sinkronisasi Ulang</a>
</div>
</body>
</html>
