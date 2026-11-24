<?php
/**
 * GUARDIAN CONTROL — ENTRYPOINT
 * Tidak ada halaman publik: redirect otomatis ke /dashboard (jika admin) atau /login.
 */
require_once __DIR__ . '/auth.php';

if (isAdminLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard');
    exit;
}

header('Location: ' . BASE_URL . '/login');
exit;
