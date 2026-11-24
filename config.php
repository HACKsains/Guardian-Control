<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — CORE CONFIGURATION (PHP 7.4 - 8.3 NATIVE)
 * ==============================================================================
 * Pusat Kendali Guardian Dome WAF, Keamanan Siber Zero-Trust, Telemetry Server,
 * dan Analitik Pengunjung Unik (1 Device = 1 Pengunjung) untuk *.ilmuna.my.id.
 * ==============================================================================
 */

if (!defined('GC_BOOTSTRAPPED')) {
    define('GC_BOOTSTRAPPED', true);
}

date_default_timezone_set('Asia/Jakarta');

if (!defined('BASE_PATH')) {
    define('BASE_PATH', rtrim(str_replace('\\', '/', __DIR__), '/'));
}

// Deteksi lingkungan lokal vs produksi
$gcHost = isset($_SERVER['HTTP_HOST']) ? strtolower(trim((string)$_SERVER['HTTP_HOST'])) : 'localhost';
$gcHostClean = explode(':', $gcHost)[0];
$gcIsLocal = (
    php_sapi_name() === 'cli' && (DIRECTORY_SEPARATOR === '\\' || !is_dir('/home/uofruaju'))
) || in_array($gcHostClean, ['localhost', '127.0.0.1', '::1'], true)
  || substr($gcHostClean, -5) === '.test'
  || substr($gcHostClean, -6) === '.local';

define('GC_IS_LOCAL', $gcIsLocal);

// Muat konfigurasi lokal/rahasia (tidak di-commit ke Git)
$gcLocalConfig = [];
$gcLocalFile = BASE_PATH . '/config.local.php';
if (is_file($gcLocalFile)) {
    $loaded = require $gcLocalFile;
    if (is_array($loaded)) {
        $gcLocalConfig = $loaded;
    }
}

// Helper baca config dengan fallback
function gc_cfg($key, $default = null)
{
    global $gcLocalConfig;
    if (isset($gcLocalConfig[$key]) && $gcLocalConfig[$key] !== '') {
        return $gcLocalConfig[$key];
    }
    $env = getenv('GC_' . strtoupper($key));
    if ($env !== false && $env !== '') {
        return $env;
    }
    return $default;
}

// ==============================================================================
// 1. IDENTITAS APLIKASI & BASE URL
// ==============================================================================
define('APP_NAME', 'Guardian Control');
define('APP_SUBTITLE', 'Cyber SEC-OPS & Full Ecosystem Monitoring Center');
define('APP_VERSION', '1.0.0');
define('APP_DOMAIN', gc_cfg('app_domain', $gcIsLocal ? $gcHostClean : 'guardian-dome.ilmuna.my.id'));

if (!defined('BASE_URL')) {
    $customBaseUrl = gc_cfg('base_url', '');
    if ($customBaseUrl !== '') {
        define('BASE_URL', rtrim((string)$customBaseUrl, '/'));
    } elseif (php_sapi_name() === 'cli') {
        define('BASE_URL', $gcIsLocal ? 'http://localhost/guardian%20control' : 'https://guardian-dome.ilmuna.my.id');
    } else {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        $scheme = $isHttps ? 'https' : 'http';
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'])), '/') : '';
        $relPath = '';
        if ($docRoot !== '' && strpos(BASE_PATH, $docRoot) === 0) {
            $relPath = substr(BASE_PATH, strlen($docRoot));
            $segments = array_map('rawurlencode', array_filter(explode('/', $relPath), 'strlen'));
            $relPath = empty($segments) ? '' : '/' . implode('/', $segments);
        }
        define('BASE_URL', $scheme . '://' . $gcHost . $relPath);
    }
}

define('APP_LOGO_URL', BASE_URL . '/assets/images/Guardian_Dome.png');

// ==============================================================================
// 2. DATABASE DEDICATED (100% TERPISAH: uofruaju_guardian-dome)
// ==============================================================================
define('GC_DB_HOST', gc_cfg('db_host', $gcIsLocal ? '127.0.0.1' : 'localhost'));
define('GC_DB_PORT', (int)gc_cfg('db_port', 3306));
define('GC_DB_NAME', gc_cfg('db_name', 'uofruaju_guardian-dome'));
define('GC_DB_USER', gc_cfg('db_user', $gcIsLocal ? 'root' : 'uofruaju_guardian-dome'));
define('GC_DB_PASS', gc_cfg('db_pass', $gcIsLocal ? '' : 'Guardian28@'));

// ==============================================================================
// 3. LOKASI ENGINE GUARDIAN DOME WAF (RELATIVE ../guardian-dome + FALLBACK)
// ==============================================================================
function gc_resolve_waf_dir()
{
    $custom = gc_cfg('waf_dir', '');
    $candidates = [
        $custom,
        dirname(BASE_PATH) . '/guardian-dome',
        '/home/uofruaju/guardian-dome',
        'C:/laragon/Guardian Dome',
        dirname(dirname(BASE_PATH)) . '/Guardian Dome',
    ];
    foreach ($candidates as $dir) {
        if (is_string($dir) && $dir !== '' && is_dir($dir)) {
            $real = realpath($dir);
            if ($real !== false) {
                return rtrim(str_replace('\\', '/', $real), '/');
            }
        }
    }
    return rtrim(str_replace('\\', '/', dirname(BASE_PATH) . '/guardian-dome'), '/');
}

define('GC_WAF_DIR', gc_resolve_waf_dir());
define('GC_WAF_FILE', GC_WAF_DIR . '/guardian-dome-waf.php');
define('GC_WAF_CACHE_DIR', GC_WAF_DIR . '/cache');
define('GC_LOCAL_CACHE_DIR', BASE_PATH . '/cache');

// ==============================================================================
// 4. INTEGRASI MARGO ONE SSO (OAUTH 2.0 + PKCE S256 + RS256 + GLOBAL SLO)
// ==============================================================================
define('GC_SSO_ENABLED', (bool)gc_cfg('sso_enabled', true));
define('GC_SSO_URL', rtrim((string)gc_cfg('sso_url', 'https://margo-one.ilmuna.my.id'), '/'));
define('GC_SSO_CLIENT_SLUG', gc_cfg('sso_client_slug', 'guardian-control'));
define('GC_SSO_PUBLIC_KEY', gc_cfg('sso_public_key', 'pk_sso_9c4e2a81f0b73d5e6a19c84f2b70e31a'));
define('GC_SSO_PRIVATE_KEY', gc_cfg('sso_private_key', 'sk_sso_4f8b1c72e90a53d6b21f84e0a7c59d31e6b40a28f7c319d5e2a86b40c1f973a2'));
define('GC_SSO_REDIRECT_URI', gc_cfg('sso_redirect_uri', $gcIsLocal ? (BASE_URL . '/sso-callback.php') : (BASE_URL . '/sso-callback')));
define('GC_SSO_ALLOWED_ROLES', ['admin']);
define('GC_SSO_SSL_VERIFY', (bool)gc_cfg('sso_ssl_verify', !$gcIsLocal));

// Jalur Login Admin Lokal / Darurat (bcrypt hash di config.local.php)
define('GC_LOCAL_ADMIN_ENABLED', (bool)gc_cfg('local_admin_enabled', true));
define('GC_LOCAL_ADMIN_USER', (string)gc_cfg('local_admin_user', 'secops_admin'));
define('GC_LOCAL_ADMIN_NAME', (string)gc_cfg('local_admin_name', 'Administrator SEC-OPS'));
define('GC_LOCAL_ADMIN_EMAIL', (string)gc_cfg('local_admin_email', 'admin@ilmuna.my.id'));
define('GC_LOCAL_ADMIN_PASS_HASH', (string)gc_cfg('local_admin_pass_hash', ''));
define('GC_CRON_SECRET', (string)gc_cfg('cron_secret', hash('sha256', 'gc_cron_' . BASE_PATH)));

// ==============================================================================
// 5. PRESET WHITELIST ERROR LOG LINTAS APLIKASI (ANTI-LFI MUTLAK)
// ==============================================================================
function gc_get_error_log_sources()
{
    $custom = gc_cfg('error_log_sources', null);
    if (is_array($custom) && !empty($custom)) {
        return $custom;
    }

    if (is_dir('/home/uofruaju')) {
        return [
            'wordpress' => [
                'label' => 'WordPress (ilmuna.my.id)',
                'icon'  => 'solar:global-bold',
                'paths' => [
                    '/home/uofruaju/public_html/error_log',
                    '/home/uofruaju/public_html/wp-content/debug.log',
                ],
            ],
            'margo_one' => [
                'label' => 'Margo One SSO',
                'icon'  => 'solar:shield-keyhole-bold',
                'paths' => [
                    '/home/uofruaju/margo-one.ilmuna.my.id/error_log',
                ],
            ],
            'learn' => [
                'label' => 'Ilmuna Learn (LMS)',
                'icon'  => 'solar:book-2-bold',
                'paths' => [
                    '/home/uofruaju/learn.ilmuna.my.id/error_log',
                ],
            ],
            'guardian_dome' => [
                'label' => 'Guardian Dome WAF',
                'icon'  => 'solar:shield-warning-bold',
                'paths' => [
                    GC_WAF_CACHE_DIR . '/php_errors.log',
                    '/home/uofruaju/guardian-dome/error_log',
                ],
            ],
            'guardian_control' => [
                'label' => 'Guardian Control',
                'icon'  => 'solar:server-square-bold',
                'paths' => [
                    BASE_PATH . '/error_log',
                    GC_LOCAL_CACHE_DIR . '/php_errors.log',
                ],
            ],
        ];
    }

    // Fallback Lokal Laragon
    $phpErrLog = ini_get('error_log');
    return [
        'wordpress' => [
            'label' => 'WordPress (ilmuna.my.id)',
            'icon'  => 'solar:global-bold',
            'paths' => [
                'C:/laragon/www/wordpress/error_log',
                'C:/laragon/www/wordpress/wp-content/debug.log',
            ],
        ],
        'margo_one' => [
            'label' => 'Margo One SSO',
            'icon'  => 'solar:shield-keyhole-bold',
            'paths' => [
                'C:/laragon/www/SSO/error_log',
            ],
        ],
        'learn' => [
            'label' => 'Ilmuna Learn (LMS)',
            'icon'  => 'solar:book-2-bold',
            'paths' => [
                'C:/laragon/www/learn/error_log',
                'C:/laragon/www/ilmuna-learn/error_log',
            ],
        ],
        'guardian_dome' => [
            'label' => 'Guardian Dome WAF',
            'icon'  => 'solar:shield-warning-bold',
            'paths' => [
                GC_WAF_CACHE_DIR . '/php_errors.log',
                GC_WAF_DIR . '/error_log',
            ],
        ],
        'guardian_control' => [
            'label' => 'Guardian Control',
            'icon'  => 'solar:server-square-bold',
            'paths' => array_values(array_filter([
                BASE_PATH . '/error_log',
                GC_LOCAL_CACHE_DIR . '/php_errors.log',
                is_string($phpErrLog) && $phpErrLog !== '' ? str_replace('\\', '/', $phpErrLog) : null,
            ])),
        ],
    ];
}

// ==============================================================================
// 6. TARGET MONITORING UPTIME, SSL & SECURITY HEADERS EKOSISTEM *.ilmuna.my.id
// ==============================================================================
function gc_get_uptime_targets()
{
    $custom = gc_cfg('uptime_targets', null);
    if (is_array($custom) && !empty($custom)) {
        return $custom;
    }
    return [
        [
            'name' => 'ilmuna.my.id (Portal Utama)',
            'host' => 'ilmuna.my.id',
            'url'  => 'https://ilmuna.my.id/',
        ],
        [
            'name' => 'margo-one.ilmuna.my.id (SSO IdP)',
            'host' => 'margo-one.ilmuna.my.id',
            'url'  => 'https://margo-one.ilmuna.my.id/login',
        ],
        [
            'name' => 'Margo One JWKS Endpoint',
            'host' => 'margo-one.ilmuna.my.id',
            'url'  => 'https://margo-one.ilmuna.my.id/api/sso/jwks.php',
        ],
        [
            'name' => 'learn.ilmuna.my.id (LMS & SIAKAD)',
            'host' => 'learn.ilmuna.my.id',
            'url'  => 'https://learn.ilmuna.my.id/',
        ],
        [
            'name' => 'guardian-dome.ilmuna.my.id (SEC-OPS)',
            'host' => 'guardian-dome.ilmuna.my.id',
            'url'  => 'https://guardian-dome.ilmuna.my.id/login',
        ],
    ];
}

// ==============================================================================
// 7. TARGET FILE INTEGRITY MONITORING (FIM SHA-256) & UPLOADS SCANNER
// ==============================================================================
function gc_get_fim_watchlist()
{
    $custom = gc_cfg('fim_watchlist', null);
    if (is_array($custom) && !empty($custom)) {
        return $custom;
    }

    $list = [
        ['app' => 'guardian_dome', 'path' => GC_WAF_FILE],
        ['app' => 'guardian_dome', 'path' => GC_WAF_DIR . '/guardian-dome-purger.php'],
        ['app' => 'guardian_dome', 'path' => GC_WAF_DIR . '/guardian-dome-telegram/bootstrap.php'],
        ['app' => 'guardian_control', 'path' => BASE_PATH . '/config.php'],
        ['app' => 'guardian_control', 'path' => BASE_PATH . '/auth.php'],
        ['app' => 'guardian_control', 'path' => BASE_PATH . '/database.php'],
        ['app' => 'guardian_control', 'path' => BASE_PATH . '/.htaccess'],
        ['app' => 'guardian_control', 'path' => BASE_PATH . '/sso-callback.php'],
        ['app' => 'guardian_control', 'path' => BASE_PATH . '/includes/waf-bridge.php'],
    ];

    if (is_dir('/home/uofruaju')) {
        $extra = [
            ['app' => 'wordpress', 'path' => '/home/uofruaju/public_html/wp-config.php'],
            ['app' => 'wordpress', 'path' => '/home/uofruaju/public_html/.htaccess'],
            ['app' => 'margo_one', 'path' => '/home/uofruaju/margo-one.ilmuna.my.id/config.php'],
            ['app' => 'margo_one', 'path' => '/home/uofruaju/margo-one.ilmuna.my.id/auth.php'],
            ['app' => 'margo_one', 'path' => '/home/uofruaju/margo-one.ilmuna.my.id/.htaccess'],
            ['app' => 'learn', 'path' => '/home/uofruaju/learn.ilmuna.my.id/config.php'],
            ['app' => 'learn', 'path' => '/home/uofruaju/learn.ilmuna.my.id/auth.php'],
            ['app' => 'learn', 'path' => '/home/uofruaju/learn.ilmuna.my.id/.htaccess'],
        ];
    } else {
        $extra = [
            ['app' => 'margo_one', 'path' => 'C:/laragon/www/SSO/config.php'],
            ['app' => 'margo_one', 'path' => 'C:/laragon/www/SSO/auth.php'],
            ['app' => 'margo_one', 'path' => 'C:/laragon/www/SSO/.htaccess'],
        ];
    }

    foreach ($extra as $item) {
        if (is_file($item['path'])) {
            $list[] = $item;
        }
    }

    return $list;
}

function gc_get_uploads_scan_dirs()
{
    $custom = gc_cfg('uploads_scan_dirs', null);
    if (is_array($custom) && !empty($custom)) {
        return $custom;
    }
    $candidates = is_dir('/home/uofruaju')
        ? [
            'wordpress'        => '/home/uofruaju/public_html/wp-content/uploads',
            'margo_one'        => '/home/uofruaju/margo-one.ilmuna.my.id/uploads',
            'learn'            => '/home/uofruaju/learn.ilmuna.my.id/uploads',
            'guardian_control' => BASE_PATH . '/uploads',
        ]
        : [
            'margo_one'        => 'C:/laragon/www/SSO/uploads',
            'guardian_control' => BASE_PATH . '/uploads',
        ];
    $valid = [];
    foreach ($candidates as $app => $dir) {
        if (is_dir($dir)) {
            $valid[$app] = rtrim(str_replace('\\', '/', $dir), '/');
        }
    }
    return $valid;
}

// ==============================================================================
// 8. HELPER UI & UTILITAS UMUM (PARITAS 100% SSO & LEARN)
// ==============================================================================
function gc_e($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function renderFavicon()
{
    $ico = gc_e(BASE_URL . '/assets/images/logo.ico');
    $png = gc_e(BASE_URL . '/assets/images/Guardian_Dome.png');
    return "    <link rel=\"icon\" type=\"image/x-icon\" href=\"{$ico}\">\n"
         . "    <link rel=\"apple-touch-icon\" href=\"{$png}\">\n";
}

function renderIconifyScript()
{
    return "    <script src=\"https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js\" defer></script>\n";
}

function loadCentralTemplates(array $templates = ['modal', 'table'])
{
    $map = [
        'modal'    => BASE_PATH . '/includes/modal-template.php',
        'table'    => BASE_PATH . '/includes/table-template.php',
        'calendar' => BASE_PATH . '/includes/calendar-template.php',
    ];
    foreach ($templates as $tpl) {
        if (isset($map[$tpl]) && is_file($map[$tpl])) {
            require_once $map[$tpl];
        }
    }
}

function gc_get_client_ip()
{
    $remote = isset($_SERVER['REMOTE_ADDR']) ? trim((string)$_SERVER['REMOTE_ADDR']) : '127.0.0.1';
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
        return trim((string)$_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP']) && filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)) {
        return trim((string)$_SERVER['HTTP_X_REAL_IP']);
    }
    return $remote !== '' ? $remote : '127.0.0.1';
}

function gc_json_response(array $payload, $statusCode = 200)
{
    if (!headers_sent()) {
        http_response_code((int)$statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function gc_format_bytes($bytes, $precision = 2)
{
    $bytes = max(0, (float)$bytes);
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

function gc_audit_log($actionType, $targetRef = null, $detail = null, $adminUsername = null)
{
    try {
        if (!function_exists('getDBConnection')) {
            require_once BASE_PATH . '/database.php';
        }
        $pdo = getDBConnection();
        if ($adminUsername === null) {
            $adminUsername = isset($_SESSION['gc_admin']['username'])
                ? (string)$_SESSION['gc_admin']['username']
                : 'system';
        }
        $detailJson = is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $pdo->prepare(
            "INSERT INTO gc_admin_audit (admin_username, action_type, target_ref, detail_json, ip_address, created_at)
             VALUES (:u, :a, :t, :d, :ip, NOW())"
        );
        $stmt->execute([
            ':u'  => substr((string)$adminUsername, 0, 80),
            ':a'  => substr((string)$actionType, 0, 60),
            ':t'  => $targetRef !== null ? substr((string)$targetRef, 0, 120) : null,
            ':d'  => $detailJson,
            ':ip' => substr(gc_get_client_ip(), 0, 64),
        ]);
    } catch (Throwable $e) {
        // Non-fatal agar tidak mengganggu operasi utama
    }
}
