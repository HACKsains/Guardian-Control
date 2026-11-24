<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — DEDICATED DATABASE SINGLETON & AUTO-MIGRATION (9 TABEL gc_*)
 * ==============================================================================
 * Database 100% terpisah: uofruaju_guardian_control
 * InnoDB · utf8mb4_unicode_ci · Timezone +07:00 · PHP 7.4 - 8.3 Native PDO
 * ==============================================================================
 */

if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Mengembalikan koneksi PDO singleton ke uofruaju_guardian_control
 *
 * @return PDO
 */
function getDBConnection()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        GC_DB_HOST,
        GC_DB_PORT,
        GC_DB_NAME
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, GC_DB_USER, GC_DB_PASS, $options);
    } catch (PDOException $e) {
        // Di lingkungan lokal Laragon, otomatis buat database dedicated jika belum ada
        if (GC_IS_LOCAL && (strpos($e->getMessage(), 'Unknown database') !== false || (int)$e->getCode() === 1049)) {
            $dsnNoDb = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', GC_DB_HOST, GC_DB_PORT);
            $tmpPdo = new PDO($dsnNoDb, GC_DB_USER, GC_DB_PASS, $options);
            $safeDb = str_replace('`', '', GC_DB_NAME);
            $tmpPdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $tmpPdo = null;
            $pdo = new PDO($dsn, GC_DB_USER, GC_DB_PASS, $options);
        } else {
            throw $e;
        }
    }

    $pdo->exec("SET time_zone = '+07:00'");
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    gc_run_auto_migrations($pdo);

    return $pdo;
}

/**
 * Auto-migration idempoten untuk seluruh 9 tabel gc_*
 */
function gc_run_auto_migrations(PDO $pdo)
{
    static $migrated = false;
    if ($migrated) {
        return;
    }
    $migrated = true;

    $ddls = [
        // 1. Log Insiden Keamanan WAF
        "CREATE TABLE IF NOT EXISTS gc_incidents (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            ray_id VARCHAR(40) NOT NULL,
            domain VARCHAR(120) NOT NULL,
            ip_address VARCHAR(64) NOT NULL,
            category VARCHAR(60) NOT NULL,
            severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'high',
            action_taken ENUM('blocked','banned','challenged','alerted') NOT NULL DEFAULT 'blocked',
            http_method VARCHAR(12) NOT NULL DEFAULT 'GET',
            request_uri VARCHAR(500) NOT NULL,
            user_agent VARCHAR(300) NULL,
            reason TEXT NOT NULL,
            shield_v3_score DECIMAL(4,2) NULL,
            meta_json TEXT NULL,
            occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_gc_inc_time (occurred_at),
            INDEX idx_gc_inc_ip (ip_address),
            INDEX idx_gc_inc_cat (category, severity),
            INDEX idx_gc_inc_domain (domain),
            INDEX idx_gc_inc_ray (ray_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 2. Aturan Firewall IP & Whitelist Terpusat
        "CREATE TABLE IF NOT EXISTS gc_ip_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_or_cidr VARCHAR(64) NOT NULL,
            rule_type ENUM('blacklist','whitelist') NOT NULL,
            reason VARCHAR(255) NOT NULL,
            source VARCHAR(40) NOT NULL DEFAULT 'manual',
            expires_at DATETIME NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by VARCHAR(80) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_gc_ip_type (ip_or_cidr, rule_type),
            INDEX idx_gc_ip_active (rule_type, is_active, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 3. Riwayat Metrik Server (RAM, CPU Load, Disk, MySQL, OPcache)
        "CREATE TABLE IF NOT EXISTS gc_server_metrics (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            ram_total_mb INT UNSIGNED NOT NULL DEFAULT 0,
            ram_used_mb INT UNSIGNED NOT NULL DEFAULT 0,
            ram_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            cpu_load_1m DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            cpu_load_5m DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            cpu_load_15m DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            disk_total_gb DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            disk_used_gb DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            disk_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            mysql_threads INT UNSIGNED NOT NULL DEFAULT 0,
            mysql_slow_queries BIGINT UNSIGNED NOT NULL DEFAULT 0,
            opcache_hit_rate DECIMAL(5,2) NULL,
            recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_gc_met_time (recorded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 4. Monitoring Uptime, TTFB & Masa Berlaku SSL Subdomain
        "CREATE TABLE IF NOT EXISTS gc_uptime_checks (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            target_name VARCHAR(80) NOT NULL,
            target_url VARCHAR(255) NOT NULL,
            http_code SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            latency_ms INT UNSIGNED NOT NULL DEFAULT 0,
            ssl_days_left INT NULL,
            is_up TINYINT(1) NOT NULL DEFAULT 0,
            error_message VARCHAR(255) NULL,
            checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_gc_up_target (target_name, checked_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 5. Snapshot Error Log Lintas Aplikasi
        "CREATE TABLE IF NOT EXISTS gc_error_snapshots (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            app_source VARCHAR(50) NOT NULL,
            level ENUM('fatal','exception','warning','notice','sql','other') NOT NULL DEFAULT 'other',
            fingerprint CHAR(32) NOT NULL,
            message TEXT NOT NULL,
            file_path VARCHAR(255) NULL,
            line_num INT NULL,
            hit_count INT UNSIGNED NOT NULL DEFAULT 1,
            is_resolved TINYINT(1) NOT NULL DEFAULT 0,
            first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_gc_err_fp (app_source, fingerprint),
            INDEX idx_gc_err_res (is_resolved, last_seen_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 6. Baseline File Integrity Monitoring (FIM SHA-256)
        "CREATE TABLE IF NOT EXISTS gc_fim_hashes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            app_source VARCHAR(50) NOT NULL,
            file_path VARCHAR(300) NOT NULL UNIQUE,
            baseline_sha256 CHAR(64) NOT NULL,
            last_sha256 CHAR(64) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('ok','modified','missing') NOT NULL DEFAULT 'ok',
            last_checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 7. Pengaturan Policy WAF & Sentinel
        "CREATE TABLE IF NOT EXISTS gc_settings (
            setting_key VARCHAR(80) PRIMARY KEY,
            setting_value LONGTEXT NULL,
            updated_by VARCHAR(80) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 8. Audit Trail Tindakan Admin SEC-OPS
        "CREATE TABLE IF NOT EXISTS gc_admin_audit (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            admin_username VARCHAR(80) NOT NULL,
            action_type VARCHAR(60) NOT NULL,
            target_ref VARCHAR(120) NULL,
            detail_json TEXT NULL,
            ip_address VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_gc_aud_time (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 9. Analitik Pengunjung Unik Berbasis Perangkat (1 Device = 1 Pengunjung)
        "CREATE TABLE IF NOT EXISTS gc_unique_visitors (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            visit_date DATE NOT NULL,
            domain VARCHAR(120) NOT NULL,
            device_hash CHAR(64) NOT NULL,
            device_type ENUM('mobile','tablet','desktop','other') NOT NULL DEFAULT 'other',
            os_name VARCHAR(40) NOT NULL DEFAULT 'Unknown',
            browser_name VARCHAR(40) NOT NULL DEFAULT 'Unknown',
            ip_subnet VARCHAR(45) NULL,
            hits_count INT UNSIGNED NOT NULL DEFAULT 1,
            first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_gc_vis_day_dom_dev (visit_date, domain, device_hash),
            INDEX idx_gc_vis_date_dev (visit_date, device_hash),
            INDEX idx_gc_vis_last_seen (last_seen_at),
            INDEX idx_gc_vis_domain (domain, visit_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($ddls as $sql) {
        $pdo->exec($sql);
    }
}

/**
 * Helper baca / simpan setting dari gc_settings
 */
function gc_get_setting(PDO $pdo, $key, $default = null)
{
    $stmt = $pdo->prepare("SELECT setting_value FROM gc_settings WHERE setting_key = :k LIMIT 1");
    $stmt->execute([':k' => (string)$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $default;
    }
    $decoded = json_decode((string)$row['setting_value'], true);
    return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $row['setting_value'];
}

function gc_set_setting(PDO $pdo, $key, $value, $updatedBy = null)
{
    $encoded = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($updatedBy === null && isset($_SESSION['gc_admin']['username'])) {
        $updatedBy = (string)$_SESSION['gc_admin']['username'];
    }
    $stmt = $pdo->prepare(
        "INSERT INTO gc_settings (setting_key, setting_value, updated_by, updated_at)
         VALUES (:k, :v, :u, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()"
    );
    $stmt->execute([
        ':k' => substr((string)$key, 0, 80),
        ':v' => $encoded,
        ':u' => $updatedBy !== null ? substr((string)$updatedBy, 0, 80) : null,
    ]);
}
