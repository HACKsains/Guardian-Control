<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — CONTOH KONFIGURASI LOKAL / PRODUKSI (config.local.php)
 * ==============================================================================
 * Salin file ini menjadi `config.local.php` lalu sesuaikan kredensial DB,
 * kunci klien Margo One SSO, dan hash bcrypt akun admin darurat.
 * JANGAN PERNAH meng-commit `config.local.php` ke repositori Git!
 * ==============================================================================
 */

return [
    // 1. Database Dedicated (WAJIB TERPISAH dari SSO & Learn)
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'uofruaju_guardian_control',
    'db_user' => 'uofruaju_guardian_control',
    'db_pass' => 'GANTI_PASSWORD_DB_PRODUKSI',

    // 2. Domain & Base URL (kosongkan untuk auto-detect)
    // 'app_domain' => 'guardian-dome.ilmuna.my.id',
    // 'base_url'   => 'https://guardian-dome.ilmuna.my.id',

    // 3. Margo One SSO (OAuth 2.0 + PKCE S256 + RS256 + Global SLO)
    'sso_enabled'      => true,
    'sso_url'          => 'https://margo-one.ilmuna.my.id',
    'sso_client_slug'  => 'guardian-control',
    'sso_public_key'   => 'ISI_PUBLIC_KEY_DARI_MARGO_ONE_ADMIN',
    'sso_private_key'  => 'ISI_PRIVATE_KEY_DARI_MARGO_ONE_ADMIN',
    'sso_redirect_uri' => 'https://guardian-dome.ilmuna.my.id/sso-callback',
    'sso_ssl_verify'   => true,

    // 4. Jalur Login Admin Lokal / Darurat (bcrypt hash dari password_hash(..., PASSWORD_BCRYPT))
    'local_admin_enabled'   => true,
    'local_admin_user'      => 'secops_admin',
    'local_admin_name'      => 'Administrator SEC-OPS',
    'local_admin_email'     => 'admin@ilmuna.my.id',
    'local_admin_pass_hash' => '$2y$12$GANTI_DENGAN_HASIL_PASSWORD_HASH_BCRYPT',

    // 5. Cron Secret (opsional bila cron dipanggil via CLI tidak perlu)
    'cron_secret' => 'GANTI_DENGAN_TOKEN_ACAK_PANJANG',
];
