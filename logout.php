<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — GLOBAL SINGLE LOGOUT (SLO)
 * ==============================================================================
 */
require_once __DIR__ . '/auth.php';

$user = getCurrentUser();
$isSso = $user !== null && (($user['auth_provider'] ?? '') === 'margo_one_sso');
if ($user !== null) {
    gc_audit_log('admin_logout', $user['username'], ['provider' => $user['auth_provider'] ?? 'local'], $user['username']);
}

gc_destroy_local_session();

if ($isSso && gc_sso_is_configured()) {
    $postLogout = BASE_URL . '/login?logged_out=1';
    $sloUrl = GC_SSO_URL . '/logout?post_logout_redirect_uri=' . rawurlencode($postLogout);
    header('Location: ' . $sloUrl);
    exit;
}

header('Location: ' . BASE_URL . '/login?logged_out=1');
exit;
