<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — UNIFIED LUMINA GOLD INNER SHELL HEADER (FULL ANIMATIONS)
 * ==============================================================================
 */
if (!defined('BASE_PATH')) {
    exit;
}

$currentUser = getCurrentUser();
$user_name   = $currentUser ? ($currentUser['full_name'] ?: $currentUser['username']) : 'Administrator';
$user_foto   = $currentUser['avatar'] ?? '';
$pageTitle   = isset($pageTitle) ? (string)$pageTitle : 'Pusat Komando SEC-OPS';
$pageIcon    = isset($pageIcon) ? (string)$pageIcon : 'solar:shield-star-bold';

$parts = preg_split('/\s+/', trim($user_name));
$inisial = '';
if (!empty($parts[0])) {
    $inisial .= strtoupper(substr($parts[0], 0, 1));
    if (!empty($parts[1])) {
        $inisial .= strtoupper(substr($parts[1], 0, 1));
    }
}
if ($inisial === '') {
    $inisial = 'AD';
}

$shellCssVer = @filemtime(BASE_PATH . '/assets/css/margo-shell.css') ?: '1';
$respCssVer  = @filemtime(BASE_PATH . '/assets/css/ilmuna-unified-responsive.css') ?: '1';
$gcCssVer    = @filemtime(BASE_PATH . '/assets/css/guardian-control.css') ?: '1';
$csrfToken   = gc_get_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?= gc_e($csrfToken) ?>">
    <title><?= gc_e($pageTitle) ?> — <?= gc_e(APP_NAME) ?></title>
<?= renderFavicon() ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<?= renderIconifyScript() ?>

    <link rel="stylesheet" href="<?= gc_e(BASE_URL . '/assets/css/lumina-lms.css') ?>">
    <link rel="stylesheet" href="<?= gc_e(BASE_URL . '/assets/css/ilmuna-unified-responsive.css?v=' . $respCssVer) ?>">
    <link rel="stylesheet" href="<?= gc_e(BASE_URL . '/assets/css/margo-shell.css?v=' . $shellCssVer) ?>">
    <link rel="stylesheet" href="<?= gc_e(BASE_URL . '/assets/css/guardian-control.css?v=' . $gcCssVer) ?>">

    <script>
        window.ILMUNA_BASE_URL = <?= json_encode(BASE_URL) ?>;
        window.GC_CSRF_TOKEN   = <?= json_encode($csrfToken) ?>;
        window.GC_ADMIN_USER   = <?= json_encode($currentUser ? $currentUser['username'] : 'admin') ?>;
        (function(){
            try {
                if (localStorage.getItem('gc_theme_mode') === 'dark') {
                    document.documentElement.classList.add('gc-dark');
                }
            } catch (e) {}
        })();
    </script>
    <script src="<?= gc_e(BASE_URL . '/assets/js/alert.js') ?>" defer></script>

<?php
loadCentralTemplates(['modal', 'table', 'calendar']);
?>
</head>
<body class="margo-shell-body gc-shell-body">
    <!-- RADIAL WAVE ENTRY/EXIT CURTAIN (1:1 LEARN & SSO) -->
    <div id="entrySlide" class="margo-entry-slide" aria-hidden="true"></div>
    <canvas id="gcParticleCanvas" class="gc-ambient-canvas" aria-hidden="true"></canvas>
    <div class="gc-ambient-orb gc-ambient-orb-1" aria-hidden="true"></div>
    <div class="gc-ambient-orb gc-ambient-orb-2" aria-hidden="true"></div>
    <div id="sidebarOverlay" class="margo-sidebar-overlay" aria-hidden="true"></div>

    <div class="margo-shell gc-shell-2col">
        <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

        <main id="mainContent" class="margo-main">
            <header id="topHeader" class="margo-top-header">
                <div class="margo-header-left">
                    <button type="button" id="leftSidebarToggle" class="margo-icon-btn" aria-label="Buka/Tutup Menu Navigasi" title="Menu Navigasi">
                        <iconify-icon icon="solar:hamburger-menu-bold"></iconify-icon>
                    </button>
                </div>

                <div class="margo-header-center">
                    <div class="margo-header-title">
                        <iconify-icon icon="<?= gc_e($pageIcon) ?>" width="18"></iconify-icon>
                        <span>Guardian Control</span>
                    </div>
                    <span class="margo-header-role-chip">Admin SEC-OPS</span>
                </div>

                <div class="margo-header-right">
                    <button type="button" id="gcGlobalRefreshBtn" class="margo-icon-btn" title="Segarkan Data Tab Aktif" aria-label="Segarkan Data">
                        <iconify-icon icon="solar:refresh-bold" id="gcRefreshIcon"></iconify-icon>
                    </button>
                    <button type="button" id="gcThemeToggleBtn" class="margo-icon-btn" title="Mode Terang / Gelap" aria-label="Ubah Tema">
                        <iconify-icon icon="solar:moon-stars-bold" id="gcThemeIcon"></iconify-icon>
                    </button>
                    <div class="margo-header-avatar-btn" title="<?= gc_e($user_name) ?> (<?= gc_e($currentUser['auth_provider'] ?? 'local') ?>)">
                        <span class="margo-mini-avatar">
                            <?php if ($user_foto !== ''): ?>
                                <img src="<?= gc_e($user_foto) ?>" alt="Avatar">
                            <?php else: ?>
                                <?= gc_e($inisial) ?>
                            <?php endif; ?>
                        </span>
                        <span class="margo-header-avatar-name"><?= gc_e($user_name) ?></span>
                    </div>
                </div>
            </header>

            <div class="margo-main-inner gc-main-inner">
