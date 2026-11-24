<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — ZERO-TRUST ADMIN AUTHENTICATION & CSRF GUARD
 * ==============================================================================
 * - Tidak ada halaman publik; seluruh dashboard & /api/* wajib role `admin`.
 * - Mendukung Margo One SSO (OAuth2 + PKCE S256 + RS256 + Global SLO)
 *   dan login admin lokal terproteksi bcrypt (config.local.php).
 * - Semua POST/PUT/DELETE API wajib header `X-CSRF-Token` valid.
 * ==============================================================================
 */

if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/config.php';
}
require_once BASE_PATH . '/database.php';
require_once BASE_PATH . '/sdk/ilmuna-sso-client.php';

function gc_start_session()
{
    if (php_sapi_name() === 'cli') {
        return;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    session_name('GC_SECOPS_SESS');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $isHttps, true);
    }
    session_start();
}

gc_start_session();

/**
 * Inisialisasi klien Margo One SSO (OAuth2 + PKCE + RS256)
 */
function gc_sso_is_configured()
{
    return GC_SSO_ENABLED && GC_SSO_PUBLIC_KEY !== '' && GC_SSO_PRIVATE_KEY !== '';
}

function gc_get_sso_client()
{
    static $client = null;
    if ($client instanceof MargoOneSsoClient) {
        return $client;
    }
    $client = new MargoOneSsoClient([
        'sso_url'         => GC_SSO_URL,
        'public_key'      => GC_SSO_PUBLIC_KEY,
        'private_key'     => GC_SSO_PRIVATE_KEY,
        'redirect_uri'    => GC_SSO_REDIRECT_URI,
        'site_host'       => APP_DOMAIN,
        'ssl_verify'      => GC_SSO_SSL_VERIFY,
        'verify_response' => true,
        'jwks_cache_file' => GC_LOCAL_CACHE_DIR . '/margo_sso_jwks.json',
    ]);
    return $client;
}

/**
 * CSRF Token Management
 */
function gc_get_csrf_token()
{
    if (empty($_SESSION['gc_csrf_token']) || !is_string($_SESSION['gc_csrf_token'])) {
        $_SESSION['gc_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['gc_csrf_token'];
}

function gc_verify_csrf_token($token = null)
{
    if ($token === null) {
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
        } elseif (!empty($_POST['_csrf_token'])) {
            $token = (string)$_POST['_csrf_token'];
        } else {
            $token = '';
        }
    }
    if (empty($_SESSION['gc_csrf_token']) || $token === '') {
        return false;
    }
    return hash_equals((string)$_SESSION['gc_csrf_token'], (string)$token);
}

/**
 * Cek apakah sesi SSO pengguna telah dicabut oleh Back-Channel Global SLO
 */
function gc_is_sso_session_revoked(array $admin)
{
    if (($admin['auth_provider'] ?? '') !== 'margo_one_sso') {
        return false;
    }
    $sub = (string)($admin['sso_sub'] ?? '');
    $loginAt = (int)($admin['logged_in_at'] ?? 0);
    if ($sub === '' || $loginAt <= 0) {
        return false;
    }
    try {
        $pdo = getDBConnection();
        $revokedMap = gc_get_setting($pdo, 'slo_revoked_subs', []);
        if (is_array($revokedMap) && isset($revokedMap[$sub])) {
            $revokedAt = (int)$revokedMap[$sub];
            if ($revokedAt >= $loginAt) {
                return true;
            }
        }
    } catch (Throwable $e) {
        // Non-fatal
    }
    return false;
}

/**
 * Ambil data admin aktif (null jika belum login atau bukan role admin)
 */
function getCurrentUser()
{
    if (empty($_SESSION['gc_admin']) || !is_array($_SESSION['gc_admin'])) {
        return null;
    }
    $admin = $_SESSION['gc_admin'];
    $role = strtolower((string)($admin['role'] ?? ''));
    if ($role !== 'admin') {
        return null;
    }
    // Cek idle timeout 8 jam
    $lastActive = (int)($admin['last_active_at'] ?? 0);
    if ($lastActive > 0 && (time() - $lastActive) > 28800) {
        gc_destroy_local_session();
        return null;
    }
    // Cek Global SLO revocation
    if (gc_is_sso_session_revoked($admin)) {
        gc_destroy_local_session();
        return null;
    }
    $_SESSION['gc_admin']['last_active_at'] = time();
    return $_SESSION['gc_admin'];
}

function getActiveRole()
{
    $u = getCurrentUser();
    return $u ? (string)($u['role'] ?? 'admin') : '';
}

function hasRole($role)
{
    $u = getCurrentUser();
    return $u !== null && strtolower((string)($u['role'] ?? '')) === strtolower((string)$role);
}

function isAdminLoggedIn()
{
    return getCurrentUser() !== null;
}

/**
 * Buat sesi login admin terverifikasi
 */
function gc_create_admin_session(array $userData, $provider = 'local_admin')
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['gc_admin'] = [
        'username'       => (string)($userData['username'] ?? 'admin'),
        'full_name'      => (string)($userData['full_name'] ?? 'Administrator SEC-OPS'),
        'email'          => (string)($userData['email'] ?? 'admin@ilmuna.my.id'),
        'avatar'         => (string)($userData['avatar'] ?? ''),
        'role'           => 'admin',
        'auth_provider'  => (string)$provider,
        'sso_sub'        => (string)($userData['sso_sub'] ?? ''),
        'sso_sid'        => (string)($userData['sso_sid'] ?? ''),
        'logged_in_at'   => time(),
        'last_active_at' => time(),
        'ip_address'     => gc_get_client_ip(),
    ];
    $_SESSION['gc_csrf_token'] = bin2hex(random_bytes(32));
}

function gc_destroy_local_session()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Guard untuk halaman web (redirect ke /login jika belum login)
 */
function requireAdminAuth()
{
    if (!isAdminLoggedIn()) {
        $next = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '/dashboard';
        header('Location: ' . BASE_URL . '/login?next=' . rawurlencode($next));
        exit;
    }
}

/**
 * Guard untuk endpoint JSON API (/api/*.php)
 * Wajib autentikasi admin + verifikasi X-CSRF-Token untuk semua request mutasi (POST/PUT/DELETE)
 */
function requireAdminApiAuth($requireCsrfOnWrite = true)
{
    $user = getCurrentUser();
    if ($user === null) {
        gc_json_response([
            'success' => false,
            'error'   => 'Akses ditolak. Sesi Administrator SEC-OPS diperlukan.',
            'code'    => 'UNAUTHORIZED',
        ], 401);
    }

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($requireCsrfOnWrite && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        if (!gc_verify_csrf_token()) {
            gc_json_response([
                'success' => false,
                'error'   => 'Token CSRF tidak valid atau kedaluwarsa. Muat ulang halaman.',
                'code'    => 'INVALID_CSRF',
            ], 403);
        }
    }

    return $user;
}

/**
 * Ambil JSON body untuk API POST
 */
function gc_get_json_input()
{
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

/**
 * Flash Alert helper untuk window.Ilmuna (includes/alert.php)
 */
function gc_set_flash_alert($type, $title, $message)
{
    if (!isset($_SESSION['ilmuna_alerts']) || !is_array($_SESSION['ilmuna_alerts'])) {
        $_SESSION['ilmuna_alerts'] = [];
    }
    $_SESSION['ilmuna_alerts'][] = [
        'type'    => (string)$type,
        'title'   => (string)$title,
        'message' => (string)$message,
    ];
}
