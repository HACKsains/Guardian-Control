<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — UNIFIED SHELL FOOTER & RUNTIME TRANSITION ENGINE
 * ==============================================================================
 */
if (!defined('BASE_PATH')) {
    exit;
}
$gcJsVer = @filemtime(BASE_PATH . '/assets/js/guardian-control.js') ?: '1';
?>
            </div><!-- /.margo-main-inner -->

            <button type="button" id="scrollTopBtn" class="margo-scroll-top" aria-label="Kembali ke atas" title="Kembali ke atas">
                <iconify-icon icon="solar:arrow-up-bold"></iconify-icon>
            </button>
            <div class="margo-version-pill">Guardian Control v<?= gc_e(APP_VERSION) ?> · DB Terisolasi</div>
        </main>
    </div><!-- /.margo-shell -->

    <?php
    if (is_file(BASE_PATH . '/includes/alert.php')) {
        require_once BASE_PATH . '/includes/alert.php';
        if (function_exists('ilmuna_render_alerts')) {
            ilmuna_render_alerts();
        }
    }
    ?>

    <script src="<?= gc_e(BASE_URL . '/assets/js/guardian-control.js?v=' . $gcJsVer) ?>" defer></script>
    <script>
    function gcTriggerLogout() {
        var logoutUrl = <?= json_encode(BASE_URL . '/logout') ?>;
        var doWaveLogout = function () {
            var entry = document.getElementById('entrySlide');
            if (entry) entry.classList.remove('is-done');
            setTimeout(function () { window.location.href = logoutUrl; }, 440);
        };
        if (window.Ilmuna && typeof window.Ilmuna.logout === 'function') {
            window.Ilmuna.logout({
                url: logoutUrl,
                redirect: false,
                title: 'Keluar dari Guardian Control?',
                message: 'Sesi Administrator SEC-OPS akan diakhiri secara aman (Global SLO bila terhubung Margo One).',
                confirmText: 'Ya, Keluar',
                cancelText: 'Batal',
                type: 'logout',
                onConfirm: doWaveLogout
            });
        } else {
            doWaveLogout();
        }
    }
    </script>
</body>
</html>
