<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — BACK-CHANNEL GLOBAL SINGLE LOGOUT (SLO) WEBHOOK
 * ==============================================================================
 * Dipanggil oleh Margo One SSO saat admin logout di aplikasi lain.
 * Memverifikasi signature HMAC/RSA dari Margo One lalu mencabut sesi sub terkait.
 * ==============================================================================
 */
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gc_json_response(['success' => false, 'error' => 'Method Not Allowed'], 405);
}

$rawBody = (string)file_get_contents('php://input');
$sigHdr  = isset($_SERVER['HTTP_X_ILMUNA_SIGNATURE']) ? trim((string)$_SERVER['HTTP_X_ILMUNA_SIGNATURE']) : '';
$tsHdr   = isset($_SERVER['HTTP_X_ILMUNA_TIMESTAMP']) ? trim((string)$_SERVER['HTTP_X_ILMUNA_TIMESTAMP']) : '';

// Verifikasi signature Back-Channel SLO menggunakan private_key klien
if (GC_SSO_PRIVATE_KEY === '') {
    gc_json_response(['success' => false, 'error' => 'SSO private key belum dikonfigurasi'], 503);
}

if ($sigHdr === '' || $tsHdr === '' || abs(time() - (int)$tsHdr) > 300) {
    gc_audit_log('slo_webhook_rejected', 'invalid_timestamp', ['ts' => $tsHdr], 'slo_webhook');
    gc_json_response(['success' => false, 'error' => 'Timestamp atau signature SLO tidak valid'], 403);
}

$expected = hash_hmac('sha256', $tsHdr . "\n" . $rawBody, GC_SSO_PRIVATE_KEY);
if (!hash_equals($expected, strtolower($sigHdr))) {
    gc_audit_log('slo_webhook_rejected', 'bad_signature', [], 'slo_webhook');
    gc_json_response(['success' => false, 'error' => 'Signature SLO tidak cocok'], 403);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    gc_json_response(['success' => false, 'error' => 'Payload JSON tidak valid'], 400);
}

$sub = trim((string)($payload['sub'] ?? $payload['user_id'] ?? ''));
if ($sub === '') {
    gc_json_response(['success' => false, 'error' => 'Parameter sub wajib disertakan'], 400);
}

try {
    $pdo = getDBConnection();
    $revoked = gc_get_setting($pdo, 'slo_revoked_subs', []);
    if (!is_array($revoked)) {
        $revoked = [];
    }
    $now = time();
    $revoked[$sub] = $now;

    // Bersihkan entri > 24 jam agar tetap ringkas
    foreach ($revoked as $k => $ts) {
        if (($now - (int)$ts) > 86400) {
            unset($revoked[$k]);
        }
    }

    gc_set_setting($pdo, 'slo_revoked_subs', $revoked, 'slo_webhook');
    gc_audit_log('slo_webhook_revoked', $sub, ['sid' => $payload['sid'] ?? null], 'slo_webhook');

    gc_json_response(['success' => true, 'revoked_sub' => $sub, 'revoked_at' => $now], 200);
} catch (Throwable $e) {
    gc_json_response(['success' => false, 'error' => 'Gagal memproses pencabutan sesi'], 500);
}
