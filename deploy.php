<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — WEB AUTO-DEPLOYER & GITHUB WEBHOOK SYNC
 * ==============================================================================
 * Cara 1 (Manual 1-Klik Browser / Cron):
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
$rawPayload   = (string)file_get_contents('php://input');
$ghSig256     = isset($_SERVER['HTTP_X_HUB_SIGNATURE_256']) ? trim((string)$_SERVER['HTTP_X_HUB_SIGNATURE_256']) : '';
$querySecret  = isset($_GET['secret']) ? (string)$_GET['secret'] : '';

$isWebhook = false;
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

$repoPath = __DIR__;
$disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
$logs     = [];

if (in_array('shell_exec', $disabled, true) || !function_exists('shell_exec')) {
    $logs[] = "[WARN] shell_exec dinonaktifkan di konfigurasi PHP server.\nGunakan cPanel Git Version Control (Update from Remote -> Deploy HEAD Commit) atau cPanel Cron Job.";
} else {
    $commands = [
        'Git Fetch'      => 'git fetch origin main 2>&1',
        'Git Reset Main' => 'git reset --hard origin/main 2>&1',
        'Git Status'     => 'git status -sb 2>&1',
        'Last Commit'    => 'git log -1 --stat 2>&1',
    ];
    foreach ($commands as $label => $cmd) {
        $out = @shell_exec('cd ' . escapeshellarg($repoPath) . ' && ' . $cmd);
        $logs[] = "=== {$label} ===\n" . trim((string)$out);
    }
}

// Verifikasi Auto-Migration 9 tabel gc_*
try {
    require_once __DIR__ . '/database.php';
    $pdo = getDBConnection();
    $tables = $pdo->query("SHOW TABLES LIKE 'gc_%'")->fetchAll(PDO::FETCH_COLUMN);
    $logs[] = "=== Verifikasi Database (" . GC_DB_NAME . ") ===\nOK — " . count($tables) . "/9 tabel aktif: " . implode(', ', $tables);
    gc_audit_log('system_git_deploy', $isWebhook ? 'github_webhook' : 'manual_web', ['tables' => count($tables)], 'deployer');
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
