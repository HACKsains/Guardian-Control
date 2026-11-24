<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — LEFT SIDEBAR (LUMINA GOLD & NUSANTARA 1:1 PARITY)
 * ==============================================================================
 */
if (!defined('BASE_PATH')) {
    exit;
}

$activeTab = isset($activeTab) ? (string)$activeTab : 'waf';
require __DIR__ . '/menu.php';
?>
<aside id="leftSidebar" class="margo-sidebar-left" aria-label="Navigasi Guardian Control">
    <div class="margo-left-brand">
        <a href="<?= gc_e(BASE_URL . '/dashboard') ?>" class="margo-left-brand-link" aria-label="Guardian Control" onclick="if(window.GC){event.preventDefault();GC.switchTab('waf');}">
            <img src="<?= gc_e(APP_LOGO_URL) ?>" alt="Guardian Dome" class="margo-left-logo" draggable="false" style="max-height:46px;width:auto;object-fit:contain;">
            <span class="margo-left-brand-tag">
                <span class="dot"></span>
                <span>Guardian Control · SEC-OPS</span>
            </span>
        </a>
    </div>

    <nav id="margoLeftNav" class="margo-left-nav">
        <?php foreach ($gcSidebarGroups as $grp): ?>
            <details open class="margo-nav-group <?= !empty($grp['is_admin']) ? 'is-admin-group' : '' ?>" data-sidebar-dropdown="<?= gc_e($grp['key']) ?>">
                <summary>
                    <iconify-icon icon="<?= gc_e($grp['icon']) ?>" width="16"></iconify-icon>
                    <span><?= gc_e($grp['title']) ?></span>
                    <iconify-icon icon="solar:alt-arrow-down-bold" width="13" class="margo-nav-group-chevron"></iconify-icon>
                </summary>
                <div class="margo-nav-items">
                    <?php foreach ($grp['items'] as $item):
                        $isAct = ($activeTab === $item['tab']);
                    ?>
                        <a href="<?= gc_e(BASE_URL . '/dashboard?tab=' . $item['tab']) ?>"
                           class="margo-nav-link <?= $isAct ? 'is-active' : '' ?>"
                           data-gc-tab="<?= gc_e($item['tab']) ?>">
                            <iconify-icon icon="<?= gc_e($item['icon']) ?>"></iconify-icon>
                            <span><?= gc_e($item['label']) ?></span>
                            <?php if (!empty($item['badge_id'])): ?>
                                <span class="margo-nav-badge" id="<?= gc_e($item['badge_id']) ?>" style="display:none;">0</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </nav>

    <div class="margo-left-footer">
        <button type="button" class="margo-left-logout-btn" onclick="gcTriggerLogout()">
            <iconify-icon icon="solar:logout-2-bold" width="16"></iconify-icon>
            <span>Keluar Global (SLO)</span>
        </button>
        <div class="margo-left-status-line">
            <span class="margo-left-status-dot">Patch 12.0</span>
            <span><?= gc_e(APP_DOMAIN) ?></span>
        </div>
    </div>
</aside>
