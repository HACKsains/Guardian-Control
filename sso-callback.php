<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — MARGO ONE SSO CALLBACK (OAUTH 2.0 + PKCE S256 + RS256)
 * ==============================================================================
 * Wajib role `admin`. Pengguna non-admin ditolak keras 403 Forbidden + audit log.
 * ==============================================================================
 */
require_once __DIR__ . '/auth.php';

if (!gc_sso_is_configured()) {
    http_response_code(503);
    header('Location: ' . BASE_URL . '/login?sso_error=' . rawurlencode('Margo One SSO belum dikonfigurasi.'));
    exit;
}

if (!empty($_GET['error'])) {
    $desc = trim((string)($_GET['error_description'] ?? $_GET['error']));
    gc_audit_log('sso_callback_error', 'margo_one', ['error' => $desc], 'anonymous');
    header('Location: ' . BASE_URL . '/login?sso_error=' . rawurlencode('Otorisasi SSO dibatalkan: ' . $desc));
    exit;
}

$code  = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
$state = isset($_GET['state']) ? trim((string)$_GET['state']) : '';

if ($code === '' || $state === '') {
    header('Location: ' . BASE_URL . '/login?sso_error=' . rawurlencode('Parameter OAuth2 (code/state) tidak lengkap.'));
    exit;
}

try {
    $client = gc_get_sso_client();
    $bundle = $client->exchangeCode($code, $state);
    $claims = isset($bundle['id_token_claims']) && is_array($bundle['id_token_claims'])
        ? $bundle['id_token_claims']
        : [];

    // Ekstrak role dari klaim JWT RS256
    $roles = [];
    if (!empty($claims['role'])) {
        $roles[] = strtolower(trim((string)$claims['role']));
    }
    if (!empty($claims['roles']) && is_array($claims['roles'])) {
        foreach ($claims['roles'] as $r) {
            $roles[] = strtolower(trim((string)$r));
        }
    }
    $roles = array_values(array_unique($roles));

    $username = (string)($claims['preferred_username'] ?? $claims['username'] ?? $claims['email'] ?? 'unknown');
    $fullName = (string)($claims['name'] ?? $claims['full_name'] ?? $username);
    $email    = (string)($claims['email'] ?? '');
    $avatar   = (string)($claims['picture'] ?? $claims['avatar'] ?? '');
    $sub      = (string)($claims['sub'] ?? '');
    $sid      = (string)($claims['sid'] ?? '');

    // WAJIB ROLE ADMIN — Tolak 403 Forbidden bagi Guru / Ketua Kelas / Siswa
    if (!in_array('admin', $roles, true)) {
        gc_audit_log('sso_forbidden_non_admin', $username, [
            'roles' => $roles,
            'sub'   => $sub,
            'email' => $email,
        ], $username);

        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 Forbidden — Guardian Control</title>
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@700;800&family=Plus+Jakarta+Sans:wght@400;600&display=swap" rel="stylesheet">
        </head>
        <body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#F8FAFC;font-family:'Plus Jakarta Sans',sans-serif;padding:20px;">
            <div style="max-width:460px;width:100%;background:#FFFFFF;border:1px solid #FECACA;border-radius:24px;padding:32px;text-align:center;box-shadow:0 16px 40px -12px rgba(239,68,68,0.18);">
                <div style="font-size:2.4rem;margin-bottom:8px;">🛡️</div>
                <h1 style="font-family:'Outfit',sans-serif;color:#991B1B;margin:0 0 10px;font-size:1.5rem;">403 — Akses Ditolak</h1>
                <p style="color:#475569;font-size:0.9rem;line-height:1.6;margin:0 0 20px;">
                    Akun <strong><?= gc_e($username) ?></strong> tidak memiliki peran <code>admin</code> yang diwajibkan untuk mengakses <strong>Guardian Control SEC-OPS</strong>. Percobaan ini telah dicatat ke audit log keamanan.
                </p>
                <a href="<?= gc_e(BASE_URL . '/login') ?>" style="display:inline-block;padding:11px 20px;border-radius:12px;background:#1D3B5E;color:#FFFFFF;text-decoration:none;font-weight:700;font-size:0.88rem;">Kembali ke Halaman Login</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    gc_create_admin_session([
        'username'  => $username,
        'full_name' => $fullName,
        'email'     => $email,
        'avatar'    => $avatar,
        'sso_sub'   => $sub,
        'sso_sid'   => $sid,
    ], 'margo_one_sso');

    gc_audit_log('admin_login', 'margo_one_sso', ['sub' => $sub, 'roles' => $roles], $username);
    gc_set_flash_alert('success', 'Selamat Datang, ' . $fullName, 'Sesi Administrator SEC-OPS terverifikasi melalui Margo One SSO (RS256).');

    header('Location: ' . BASE_URL . '/dashboard');
    exit;
} catch (Throwable $e) {
    gc_audit_log('sso_callback_exception', 'margo_one', ['message' => $e->getMessage()], 'anonymous');
    header('Location: ' . BASE_URL . '/login?sso_error=' . rawurlencode('Verifikasi Margo One SSO gagal: ' . $e->getMessage()));
    exit;
}
