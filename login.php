<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — SPLIT-PANEL LUMINA GOLD & NUSANTARA ADMIN LOGIN
 * ==============================================================================
 * Paritas Visual & Animasi 100% 1:1 dengan Margo One SSO & Ilmuna Learn:
 *   - Viewport Lock (100dvh, zero vertical scroll)
 *   - .page-transition Radial Wave Curtain (3-layer sweep-out & sweep-in)
 *   - #particleCanvas interactive network + .glow-orb floating ambient orbs
 *   - Split-Panel Overlap (.slide-panel z:1 slideInFromBehind 1.2s + .form-panel z:20)
 *   - .pixel-pattern pulse + 4-layer Parallax SVG Wave + .logo-wrap Shine & Glow
 *   - Eksklusif Iconify Solar Icons + window.Ilmuna + AJAX Smooth Login
 * ==============================================================================
 */
require_once __DIR__ . '/auth.php';

if (isAdminLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard');
    exit;
}

$ssoConfigured = gc_sso_is_configured();
$localEnabled  = GC_LOCAL_ADMIN_ENABLED && GC_LOCAL_ADMIN_PASS_HASH !== '';
$errorMsg      = '';
$infoMsg       = '';

if (isset($_GET['logged_out'])) {
    $infoMsg = 'Sesi Administrator SEC-OPS telah diakhiri dengan aman.';
} elseif (isset($_GET['sso_error'])) {
    $errorMsg = trim((string)$_GET['sso_error']);
}

// Mulai alur redirect Margo One SSO
if (isset($_GET['action']) && $_GET['action'] === 'sso') {
    if (!$ssoConfigured) {
        $errorMsg = 'Integrasi Margo One SSO belum diaktifkan di config.local.php. Gunakan login admin lokal.';
    } else {
        try {
            $client = gc_get_sso_client();
            $authUrl = $client->buildAuthorizeUrl(['scope' => 'openid profile email roles']);
            header('Location: ' . $authUrl);
            exit;
        } catch (Throwable $e) {
            $errorMsg = 'Gagal menginisiasi Margo One SSO: ' . $e->getMessage();
        }
    }
}

// Proses Login Admin Lokal / Darurat (POST + AJAX Support + CSRF + Rate Limit + bcrypt)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['local_login'])) {
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
           || (isset($_POST['ajax']) && $_POST['ajax'] === '1');

    if (!gc_verify_csrf_token($_POST['_csrf_token'] ?? '')) {
        $errorMsg = 'Token keamanan CSRF tidak valid. Silakan muat ulang halaman.';
    } elseif (!empty($_POST['website'])) {
        $errorMsg = 'Permintaan ditolak oleh pelindung honeypot.';
    } elseif (!$localEnabled) {
        $errorMsg = 'Login admin lokal dinonaktifkan. Silakan gunakan Margo One SSO.';
    } else {
        $attempts  = (int)($_SESSION['gc_login_attempts'] ?? 0);
        $lockUntil = (int)($_SESSION['gc_login_lock_until'] ?? 0);
        if ($lockUntil > time()) {
            $waitSec = $lockUntil - time();
            $errorMsg = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . $waitSec . ' detik.';
        } else {
            $inputUser = trim((string)($_POST['username'] ?? ''));
            $inputPass = (string)($_POST['password'] ?? '');

            $userMatch = hash_equals(strtolower(GC_LOCAL_ADMIN_USER), strtolower($inputUser));
            $passMatch = $userMatch && password_verify($inputPass, GC_LOCAL_ADMIN_PASS_HASH);

            if ($passMatch) {
                unset($_SESSION['gc_login_attempts'], $_SESSION['gc_login_lock_until']);
                gc_create_admin_session([
                    'username'  => GC_LOCAL_ADMIN_USER,
                    'full_name' => GC_LOCAL_ADMIN_NAME,
                    'email'     => GC_LOCAL_ADMIN_EMAIL,
                    'avatar'    => '',
                ], 'local_admin');
                gc_audit_log('admin_login', 'local_admin', ['method' => 'bcrypt', 'user' => GC_LOCAL_ADMIN_USER], GC_LOCAL_ADMIN_USER);

                $next = isset($_POST['next']) ? trim((string)$_POST['next']) : '';
                $targetUrl = ($next !== '' && strpos($next, '/') === 0 && strpos($next, '//') !== 0)
                    ? $next
                    : (BASE_URL . '/dashboard');

                if ($isAjax) {
                    gc_json_response(['success' => true, 'redirect' => $targetUrl]);
                }
                header('Location: ' . $targetUrl);
                exit;
            } else {
                $attempts++;
                $_SESSION['gc_login_attempts'] = $attempts;
                if ($attempts >= 5) {
                    $_SESSION['gc_login_lock_until'] = time() + 600;
                    $_SESSION['gc_login_attempts'] = 0;
                }
                gc_audit_log('admin_login_failed', $inputUser !== '' ? $inputUser : 'unknown', ['method' => 'local_admin', 'attempts' => $attempts], 'anonymous');
                $errorMsg = 'Username atau kata sandi Administrator tidak valid.';
            }
        }
    }

    if ($isAjax) {
        gc_json_response(['success' => false, 'message' => $errorMsg], 401);
    }
}

$csrfToken      = gc_get_csrf_token();
$nextParam      = isset($_GET['next']) ? (string)$_GET['next'] : '';
$showEmergency  = isset($_GET['emergency_admin']) || GC_IS_LOCAL || !$ssoConfigured;
$loginActionUrl = GC_IS_LOCAL ? (BASE_URL . '/login.php') : (BASE_URL . '/login');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title>Masuk SEC-OPS — <?= gc_e(APP_NAME) ?></title>
<?= renderFavicon() ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<?= renderIconifyScript() ?>

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy: #1D3B5E;
            --navy-dark: #13273F;
            --teal: #0D7A87;
            --sky: #52B2DC;
            --gold: #F59E0B;
            --emerald: #10B981;
            --rose: #EF4444;
            --font-h: 'Outfit', sans-serif;
            --font-b: 'Plus Jakarta Sans', sans-serif;
            --font-m: 'JetBrains Mono', monospace;
        }

        iconify-icon {
            display: inline-block;
            width: 1em;
            height: 1em;
            vertical-align: -0.125em;
        }

        html { height: 100%; overflow: hidden; }

        body {
            font-family: var(--font-b);
            background: #F8FAFC;
            color: #334155;
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
            overscroll-behavior: none;
            -webkit-font-smoothing: antialiased;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px 0;
            position: relative;
            user-select: none;
            -webkit-user-select: none;
        }
        @media (min-width: 768px) { body { padding: 24px 0; } }

        input, textarea { user-select: text !important; -webkit-user-select: text !important; }
        img { -webkit-user-drag: none; user-drag: none; pointer-events: none; }

        /* 1. PAGE TRANSITION (RADIAL WAVE — 1:1 LEARN & SSO LOGIN) */
        .page-transition {
            position: fixed;
            top: 50%; left: 50%;
            width: 300vmax; height: 300vmax;
            margin-top: -150vmax; margin-left: -150vmax;
            background: #E8EFF7;
            z-index: 9999;
            transform-origin: center;
            transform: rotate(-225deg) translateY(85%);
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
            box-shadow: 0 -20px 60px rgba(0, 0, 0, 0.1);
            will-change: transform;
        }
        .page-transition::before {
            content: ''; position: absolute; top: 10vh; left: 0;
            width: 100%; height: 100%; background: #52B2DC;
            box-shadow: 0 -15px 35px rgba(0, 0, 0, 0.08);
        }
        .page-transition::after {
            content: ''; position: absolute; top: 20vh; left: 0;
            width: 100%; height: 100%; background: #0D7A87;
            box-shadow: 0 -15px 35px rgba(0, 0, 0, 0.1);
        }
        .page-transition.active { transform: rotate(135deg) translateY(-10%); }

        /* 2. PARTICLE CANVAS + FLOATING GLOW ORBS */
        #particleCanvas { position: fixed; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 2; }
        .glow-orb {
            position: absolute; border-radius: 50%;
            background: rgba(13, 122, 135, 0.12);
            filter: blur(100px); pointer-events: none; z-index: 1;
            width: 300px; height: 300px;
            animation: orbDrift 12s ease-in-out infinite alternate;
        }
        @media (min-width: 768px) { .glow-orb { width: 600px; height: 600px; } }
        .glow-orb-1 { top: -10%; left: 10%; }
        .glow-orb-2 { bottom: -10%; right: 10%; background: rgba(82, 178, 220, 0.14); animation-delay: -6s; }
        @keyframes orbDrift {
            0%   { transform: translate3d(0, 0, 0) scale(1); }
            100% { transform: translate3d(28px, -24px, 0) scale(1.08); }
        }

        /* 3. SPLIT-PANEL OVERLAP WRAPPER */
        .login-wrapper {
            position: relative; z-index: 10;
            width: 92%; max-width: 1200px; max-height: 100%; min-height: 0;
            margin: 0 auto; display: flex; flex-direction: column; overflow: visible;
        }
        @media (min-width: 768px) {
            .login-wrapper { flex-direction: row; align-items: stretch; justify-content: center; gap: 0; width: 86%; }
        }
        @media (min-width: 1024px) { .login-wrapper { width: 76%; } }
        @media (min-width: 1280px) { .login-wrapper { width: 70%; } }

        /* LEFT SLIDE PANEL (slideInFromBehind 1.2s + Parallax SVG Wave + Pixel Pulse + Gunungan) */
        .slide-panel {
            width: 100%; height: auto;
            background: linear-gradient(135deg, #1D3B5E 0%, #0D7A87 50%, #152C47 100%);
            border-radius: 28px; border-bottom-right-radius: 60px;
            display: flex; flex-direction: column; justify-content: flex-start;
            padding: 10px; position: relative; z-index: 1; overflow: hidden;
            transform: translate(-100%, 100%);
            animation: slideInFromBehind 1.2s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            will-change: transform; flex-shrink: 0;
        }
        .slide-panel::after {
            content: '';
            position: absolute;
            right: -18px;
            bottom: 18px;
            width: 240px;
            height: 290px;
            background: url('<?= gc_e(BASE_URL . '/assets/img/gunungan.png') ?>') no-repeat right bottom / contain;
            opacity: 0.13;
            pointer-events: none;
            z-index: 1;
            animation: gununganFloat 9s ease-in-out infinite;
        }
        @keyframes gununganFloat {
            0%, 100% { transform: translateY(0) scale(1); opacity: 0.12; }
            50%      { transform: translateY(-6px) scale(1.02); opacity: 0.17; }
        }
        @media (min-width: 768px) {
            .slide-panel {
                width: 46%; padding: 32px; border-radius: 0;
                border-top-left-radius: 32px; border-bottom-left-radius: 32px;
                border-bottom-right-radius: 100px;
            }
        }
        @media (min-width: 1024px) { .slide-panel { width: 48%; } }
        @keyframes slideInFromBehind {
            0% { transform: translate(-100%, 100%); opacity: 0; }
            100% { transform: translate(0, 0); opacity: 1; }
        }
        .slide-out-behind { animation: slideOutBehind 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards !important; }
        @keyframes slideOutBehind {
            0% { transform: translate(0, 0); opacity: 1; }
            100% { transform: translate(-100%, 100%); opacity: 0; }
        }

        .pixel-pattern {
            position: absolute; inset: 0; z-index: 0; pointer-events: none;
            background-image: radial-gradient(circle, rgba(82, 178, 220, 0.32) 1.5px, transparent 1.5px);
            background-size: 20px 20px;
            animation: pixelPulse 8s ease-in-out infinite; opacity: 0.55;
        }
        @keyframes pixelPulse { 0%, 100% { opacity: 0.40; transform: scale(1); } 50% { opacity: 0.72; transform: scale(1.02); } }

        .wave-wrap {
            position: absolute; bottom: 0; left: 0; width: 100%;
            overflow: hidden; line-height: 0; z-index: 10; pointer-events: none; opacity: 0.42;
        }
        .wave-wrap svg { position: relative; display: block; width: calc(100% + 800px); height: 30px; }
        @media (min-width: 768px) { .wave-wrap svg { height: 60px; } }
        .parallax > use { animation: wave-move 25s cubic-bezier(.55,.5,.45,.5) infinite; will-change: transform; }
        .parallax > use:nth-child(1) { animation-delay: -2s; animation-duration: 7s; }
        .parallax > use:nth-child(2) { animation-delay: -3s; animation-duration: 10s; }
        .parallax > use:nth-child(3) { animation-delay: -4s; animation-duration: 13s; }
        .parallax > use:nth-child(4) { animation-delay: -5s; animation-duration: 20s; }
        @keyframes wave-move { 0% { transform: translate3d(-90px, 0, 0); } 100% { transform: translate3d(85px, 0, 0); } }

        .slide-inner {
            position: relative; z-index: 10; display: flex; flex-direction: column;
            align-items: flex-start; justify-content: center; text-align: left;
            width: 100%; max-width: 370px; margin: 0 auto; padding: 4px 0 8px;
        }
        @media (min-width: 768px) { .slide-inner { margin: 0; padding: 14px 0 0; } }

        .slide-glass-card {
            background: rgba(255, 255, 255, 0.10);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 18px; padding: 10px 12px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.16); width: 100%;
            animation: cardFloatIn 0.9s cubic-bezier(0.22, 1, 0.36, 1) 0.3s both;
        }
        @keyframes cardFloatIn {
            from { opacity: 0; transform: translateY(14px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        @media (min-width: 768px) { .slide-glass-card { padding: 26px; } }

        .logo-line { display: flex; align-items: center; justify-content: flex-start; gap: 12px; margin-bottom: 8px; }
        @media (min-width: 768px) { .logo-line { margin-bottom: 18px; } }

        .logo-wrap {
            position: relative; width: fit-content; padding: 7px 12px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.95) 100%);
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.22), 0 0 28px rgba(82, 178, 220, 0.30), inset 0 1px 0 #FFFFFF;
            border: 1px solid rgba(255, 255, 255, 0.85); overflow: hidden;
            display: inline-flex; align-items: center; gap: 10px;
        }
        @media (min-width: 768px) { .logo-wrap { padding: 10px 16px; border-radius: 16px; } }
        .logo-wrap::after {
            content: ''; position: absolute; top: 0; left: 0; width: 50%; height: 100%;
            background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.78) 50%, rgba(255,255,255,0) 100%);
            transform: translateX(-150%) skewX(-25deg); animation: logoShine 5.5s infinite ease-in-out;
            pointer-events: none; z-index: 3;
        }
        @keyframes logoShine { 0% { transform: translateX(-150%) skewX(-25deg); } 100% { transform: translateX(260%) skewX(-25deg); } }
        .logo-wrap img {
            width: 38px; height: 38px; object-fit: contain;
            filter: drop-shadow(0 2px 6px rgba(29, 59, 94, 0.18));
            animation: logoGlow 7s ease-in-out infinite; display: block;
        }
        @media (min-width: 768px) { .logo-wrap img { width: 52px; height: 52px; } }
        .logo-brand-txt { display: none; }
        @media (min-width: 768px) {
            .logo-brand-txt { display: block; line-height: 1.15; }
            .logo-brand-txt strong { display: block; font-family: var(--font-h); font-size: 1rem; font-weight: 800; color: #1D3B5E; }
            .logo-brand-txt small { font-size: 0.68rem; font-weight: 700; color: #0D7A87; letter-spacing: 0.08em; text-transform: uppercase; }
        }
        @keyframes logoGlow {
            0%, 100% { filter: drop-shadow(0 2px 6px rgba(29,59,94,0.18)); transform: translate3d(0,0,0) scale(1); }
            50%      { filter: drop-shadow(0 4px 10px rgba(29,59,94,0.22)) drop-shadow(0 0 18px rgba(82,178,220,0.52)); transform: translate3d(0,-1.5px,0) scale(1.03); }
        }

        .mobile-side-text { flex: 1; text-align: left; }
        .mobile-side-text h2 { color: #FFFFFF; font-family: var(--font-h); font-size: 13px; font-weight: 800; line-height: 1.2; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
        .mobile-side-text p { color: rgba(255,255,255,0.86); font-size: 10px; font-weight: 500; margin-top: 2px; }
        @media (min-width: 768px) { .mobile-side-text { display: none; } }

        .badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 3px 10px; border-radius: 999px;
            background: rgba(255, 255, 255, 0.20); border: 1px solid rgba(255, 255, 255, 0.30);
            backdrop-filter: blur(8px); margin-bottom: 6px; width: fit-content;
        }
        @media (min-width: 768px) { .badge { padding: 6px 14px; gap: 10px; margin-bottom: 14px; } }
        .badge .dot {
            width: 7px; height: 7px; border-radius: 50%; background: var(--emerald);
            animation: pulseDot 2s ease-in-out infinite; box-shadow: 0 0 10px var(--emerald);
        }
        @keyframes pulseDot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.45; transform: scale(0.82); } }
        .badge span { color: #FFFFFF; font-size: 8.5px; font-weight: 700; letter-spacing: 0.11em; text-transform: uppercase; }
        @media (min-width: 768px) { .badge span { font-size: 10.5px; } }

        .slide-h1 {
            display: none; color: #FFFFFF; font-family: var(--font-h); font-weight: 900;
            font-size: 1.5rem; letter-spacing: -0.02em; line-height: 1.15; margin-bottom: 10px;
            text-shadow: 0 4px 12px rgba(0,0,0,0.30);
        }
        @media (min-width: 768px) { .slide-h1 { display: block; font-size: 1.85rem; } }
        @media (min-width: 1024px) { .slide-h1 { font-size: 2.1rem; } }
        .slide-h1 .gradient-text {
            background: linear-gradient(to right, #FFFFFF 0%, #52B2DC 55%, #F59E0B 100%);
            -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
        }

        .slide-desc {
            display: none; color: rgba(255, 255, 255, 0.90); font-size: 12.8px;
            line-height: 1.58; font-weight: 500; max-width: 330px; text-shadow: 0 1px 4px rgba(0,0,0,0.2);
        }
        @media (min-width: 768px) { .slide-desc { display: block; } }

        .client-ctx-pill {
            display: none; margin-top: 14px; padding: 8px 12px; border-radius: 12px;
            background: rgba(15, 29, 47, 0.36); border: 1px solid rgba(255, 255, 255, 0.22);
            color: #E0F2FE; font-size: 11px; font-weight: 600; align-items: center; gap: 8px;
        }
        @media (min-width: 768px) { .client-ctx-pill { display: inline-flex; } }

        /* RIGHT FORM PANEL (Overlapping Front Glass Card — 1:1 Learn & SSO) */
        .form-panel {
            width: 100%; height: auto;
            border-radius: 28px; border-top-left-radius: 60px;
            padding: 18px; padding-top: 32px; padding-bottom: 18px; margin-top: -24px;
            position: relative; z-index: 20;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            background: rgba(255, 255, 255, 0.93);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.75);
            box-shadow: 0 8px 32px 0 rgba(29, 59, 94, 0.15), -20px 12px 40px -12px rgba(29, 59, 94, 0.20);
            overflow-y: auto; overflow-x: hidden; -webkit-overflow-scrolling: touch;
            scrollbar-width: none; max-height: 100%;
            opacity: 0; animation: fadeInPanel 0.8s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            animation-delay: 0.38s;
        }
        .form-panel::-webkit-scrollbar { display: none; }
        @media (min-width: 768px) {
            .form-panel {
                width: 54%; border-radius: 0; border-top-right-radius: 40px; border-bottom-right-radius: 40px;
                border-top-left-radius: 100px; padding: 32px; margin-top: 0; margin-left: -28px;
            }
        }
        @media (min-width: 1024px) { .form-panel { width: 52%; } }
        @keyframes fadeInPanel {
            0%   { opacity: 0; transform: translateY(10px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .form-inner { width: 100%; max-width: 380px; }
        .form-head-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 4px; }
        .form-heading { color: var(--navy); font-family: var(--font-h); font-size: 1.35rem; font-weight: 800; letter-spacing: -0.02em; }
        @media (min-width: 768px) { .form-heading { font-size: 1.55rem; } }

        .secops-chip {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 11px; border-radius: 999px;
            background: rgba(13, 122, 135, 0.10); border: 1px solid rgba(13, 122, 135, 0.26);
            color: var(--teal); font-family: var(--font-h); font-size: 0.72rem; font-weight: 700;
        }

        .form-subheading { color: #64748B; font-size: 0.8rem; margin-bottom: 16px; font-weight: 500; line-height: 1.45; }
        @media (min-width: 768px) { .form-subheading { font-size: 0.84rem; margin-bottom: 18px; } }

        .alert-box {
            display: flex; align-items: flex-start; gap: 8px;
            padding: 10px 12px; border-radius: 12px; margin-bottom: 14px;
            font-size: 0.79rem; line-height: 1.45; font-weight: 600;
            animation: alertSlide 0.35s cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        .alert-error { background: #FEF2F2; border: 1px solid #FECACA; border-left: 4px solid #EF4444; color: #991B1B; }
        .alert-info  { background: rgba(16, 185, 129, 0.10); border: 1px solid rgba(16, 185, 129, 0.30); border-left: 4px solid #10B981; color: #065F46; }
        @keyframes alertSlide { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        .alert-close { background: none; border: none; color: inherit; cursor: pointer; margin-left: auto; padding: 0; display: inline-flex; }

        .btn-submit {
            width: 100%; padding: 12px 16px; min-height: 46px; border-radius: 13px; border: none;
            background: linear-gradient(135deg, #1D3B5E 0%, #0D7A87 52%, #52B2DC 100%);
            color: #FFFFFF; font-family: var(--font-h); font-size: 0.94rem; font-weight: 700;
            letter-spacing: 0.02em; cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; justify-content: center; gap: 9px;
            box-shadow: 0 10px 24px rgba(13, 122, 135, 0.32);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden;
        }
        @media (min-width: 768px) { .btn-submit { padding: 13px 18px; font-size: 0.96rem; border-radius: 14px; } }
        .btn-submit::after {
            content: ''; position: absolute; top: 0; left: -100%; width: 50%; height: 100%;
            background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.55) 50%, rgba(255,255,255,0) 100%);
            transform: skewX(-20deg); animation: sweepShine 3s cubic-bezier(0.4, 0, 0.2, 1) infinite;
        }
        @keyframes sweepShine { 0% { left: -100%; } 22% { left: 200%; } 100% { left: 200%; } }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(13, 122, 135, 0.44); }
        .btn-submit.is-disabled { opacity: 0.58; pointer-events: none; }
        .btn-submit:disabled { opacity: 0.72; cursor: not-allowed; }

        .btn-local-submit {
            background: linear-gradient(135deg, #13273F 0%, #1D3B5E 65%, #0D7A87 100%);
            box-shadow: 0 8px 20px rgba(29, 59, 94, 0.28);
            margin-top: 6px;
        }

        .sso-divider {
            display: flex; align-items: center; gap: 10px; margin: 14px 0;
            color: #94A3B8; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;
        }
        .sso-divider::before, .sso-divider::after { content: ''; flex: 1; height: 1px; background: linear-gradient(to right, transparent, #CBD5E1, transparent); }

        .field { margin-bottom: 11px; }
        .field-label { display: block; font-size: 0.76rem; font-weight: 700; color: #334155; margin-bottom: 5px; }
        .input-wrap { position: relative; display: flex; align-items: center; }
        .input-wrap > .input-icon {
            position: absolute; left: 14px; color: #94A3B8; font-size: 1.05rem;
            pointer-events: none; transition: color 0.2s ease, transform 0.2s ease;
        }
        .input-wrap:focus-within > .input-icon { color: var(--teal); transform: scale(1.08); }

        .form-input {
            width: 100%; padding: 11px 42px 11px 42px; min-height: 44px;
            border-radius: 12px; border: 1.5px solid #E2E8F0; background: #FFFFFF;
            color: #0F1D2F; font-family: var(--font-b); font-size: 0.9rem; font-weight: 500;
            transition: all 0.25s ease;
        }
        .form-input::placeholder { color: #94A3B8; font-weight: 400; }
        .form-input:focus { outline: none; border-color: var(--teal); box-shadow: 0 0 0 4px rgba(13, 122, 135, 0.12); }

        .toggle-password {
            position: absolute; right: 6px; background: none; border: none;
            color: #94A3B8; cursor: pointer; font-size: 1.05rem; width: 36px; height: 36px;
            display: inline-flex; align-items: center; justify-content: center; border-radius: 8px;
            transition: color 0.2s;
        }
        .toggle-password:hover { color: var(--navy); }
        .hp-field { position: absolute !important; left: -9999px !important; width: 1px !important; height: 1px !important; opacity: 0 !important; pointer-events: none !important; }

        .form-footer { margin-top: 16px; text-align: center; }
        .form-footer p { color: #64748B; font-size: 10px; font-weight: 700; letter-spacing: 0.09em; text-transform: uppercase; }
        .spin-icon { animation: gcSpin 0.9s linear infinite; }
        @keyframes gcSpin { to { transform: rotate(360deg); } }

        @media (max-width: 380px) {
            .form-subheading { display: none; }
            .slide-glass-card { padding: 8px 10px; }
            .form-panel { padding-top: 26px; }
        }
    </style>
</head>
<body>
    <!-- RADIAL WAVE CURTAIN (1:1 LEARN & SSO) -->
    <div id="pageTransition" class="page-transition active"></div>
    <canvas id="particleCanvas"></canvas>
    <div class="glow-orb glow-orb-1"></div>
    <div class="glow-orb glow-orb-2"></div>

    <div class="login-wrapper">
        <!-- LEFT SLIDE PANEL -->
        <div class="slide-panel" id="slidePanel">
            <div class="pixel-pattern"></div>
            <div class="wave-wrap">
                <svg viewBox="0 24 150 28" preserveAspectRatio="none" shape-rendering="auto">
                    <defs>
                        <path id="gentle-wave" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z"/>
                    </defs>
                    <g class="parallax">
                        <use href="#gentle-wave" x="48" y="0" fill="rgba(255,255,255,0.30)"/>
                        <use href="#gentle-wave" x="48" y="3" fill="rgba(255,255,255,0.20)"/>
                        <use href="#gentle-wave" x="48" y="5" fill="rgba(255,255,255,0.15)"/>
                        <use href="#gentle-wave" x="48" y="7" fill="rgba(255,255,255,0.40)"/>
                    </g>
                </svg>
            </div>

            <div class="slide-inner">
                <div class="slide-glass-card">
                    <div class="logo-line">
                        <div class="logo-wrap">
                            <img src="<?= gc_e(APP_LOGO_URL) ?>" alt="Guardian Dome" draggable="false">
                            <div class="logo-brand-txt">
                                <strong>Guardian Control</strong>
                                <small>Patch 11.0 · SEC-OPS</small>
                            </div>
                        </div>
                        <div class="mobile-side-text">
                            <h2>Guardian Control</h2>
                            <p>Zero-Trust SEC-OPS · *.ilmuna.my.id</p>
                        </div>
                    </div>

                    <div class="badge">
                        <span class="dot"></span>
                        <span>ZERO-TRUST · ADMIN ONLY</span>
                    </div>

                    <h1 class="slide-h1">
                        Pusat Kendali<br>
                        <span class="gradient-text">Guardian Dome</span>
                    </h1>

                    <p class="slide-desc">
                        Kendali WAF real-time, telemetry akar rumput cPanel, FIM SHA-256, dan analitik pengunjung unik 1-Device untuk seluruh ekosistem <strong>*.ilmuna.my.id</strong>.
                    </p>

                    <div class="client-ctx-pill">
                        <iconify-icon icon="solar:shield-keyhole-bold" width="16" style="color:#F59E0B;"></iconify-icon>
                        <span>DB Terisolasi: <?= gc_e(GC_DB_NAME) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT FORM PANEL -->
        <div class="form-panel" id="formPanel">
            <div class="form-inner">
                <div class="form-head-row">
                    <h2 class="form-heading">Masuk SEC-OPS</h2>
                    <span class="secops-chip">
                        <iconify-icon icon="solar:lock-keyhole-bold" width="13"></iconify-icon>
                        <span>Role: Admin</span>
                    </span>
                </div>
                <p class="form-subheading">
                    Akses terproteksi Margo One SSO (RS256 + PKCE) &amp; jalur darurat Admin Lokal.
                </p>

                <?php if ($errorMsg !== ''): ?>
                    <div class="alert-box alert-error" role="alert">
                        <iconify-icon icon="solar:danger-circle-bold" width="18" style="flex-shrink:0;margin-top:1px;"></iconify-icon>
                        <div><?= gc_e($errorMsg) ?></div>
                        <button type="button" class="alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">
                            <iconify-icon icon="solar:close-circle-bold" width="16"></iconify-icon>
                        </button>
                    </div>
                <?php elseif ($infoMsg !== ''): ?>
                    <div class="alert-box alert-info" role="status">
                        <iconify-icon icon="solar:check-circle-bold" width="18" style="flex-shrink:0;margin-top:1px;"></iconify-icon>
                        <div><?= gc_e($infoMsg) ?></div>
                        <button type="button" class="alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">
                            <iconify-icon icon="solar:close-circle-bold" width="16"></iconify-icon>
                        </button>
                    </div>
                <?php endif; ?>

                <a href="<?= gc_e($loginActionUrl . '?action=sso') ?>" id="btnSsoLogin" class="btn-submit">
                    <iconify-icon icon="solar:shield-keyhole-bold" width="19"></iconify-icon>
                    <span>Masuk dengan Margo One SSO</span>
                    <iconify-icon icon="solar:arrow-right-bold" width="17"></iconify-icon>
                </a>
                <?php if (!$ssoConfigured): ?>
                    <p style="font-size:0.72rem;color:#64748B;text-align:center;margin-top:6px;">
                        Lengkapi kunci klien Margo One SSO di <code>config.local.php</code>.
                    </p>
                <?php endif; ?>

                <?php if ($localEnabled && $showEmergency): ?>
                    <div class="sso-divider"><span>atau login admin lokal / darurat</span></div>

                    <form method="POST" action="<?= gc_e($loginActionUrl) ?>" id="loginForm" autocomplete="on" novalidate>
                        <input type="hidden" name="_csrf_token" value="<?= gc_e($csrfToken) ?>">
                        <input type="hidden" name="local_login" value="1">
                        <input type="hidden" name="next" value="<?= gc_e($nextParam) ?>">
                        <div class="hp-field" aria-hidden="true">
                            <input type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="field">
                            <label class="field-label" for="gcUsername">Username Administrator</label>
                            <div class="input-wrap">
                                <iconify-icon icon="solar:user-bold" class="input-icon"></iconify-icon>
                                <input type="text" id="gcUsername" name="username" class="form-input"
                                       placeholder="secops_admin" maxlength="80" required autofocus
                                       autocomplete="username" spellcheck="false">
                            </div>
                        </div>

                        <div class="field">
                            <label class="field-label" for="gcPassword">Kata Sandi</label>
                            <div class="input-wrap">
                                <iconify-icon icon="solar:lock-password-bold" class="input-icon"></iconify-icon>
                                <input type="password" id="gcPassword" name="password" class="form-input"
                                       placeholder="Masukkan kata sandi" maxlength="200" required
                                       autocomplete="current-password">
                                <button type="button" class="toggle-password" id="togglePasswordBtn" aria-label="Tampilkan sandi">
                                    <iconify-icon icon="solar:eye-linear" id="togglePasswordIcon"></iconify-icon>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit btn-local-submit" id="btnSubmit">
                            <span>Masuk Panel SEC-OPS</span>
                            <iconify-icon icon="solar:arrow-right-bold" width="18"></iconify-icon>
                        </button>
                    </form>
                <?php elseif ($localEnabled): ?>
                    <div style="text-align:center;margin-top:16px;">
                        <a href="<?= gc_e($loginActionUrl . '?emergency_admin=1') ?>" style="font-size:0.8rem;color:#0D7A87;font-weight:700;text-decoration:none;">
                            Jalur Darurat Admin Lokal →
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-footer">
                <p>Guardian Control · *.ilmuna.my.id © <?= date('Y') ?></p>
            </div>
        </div>
    </div>

    <script>window.ILMUNA_BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
    <script src="<?= gc_e(BASE_URL . '/assets/js/alert.js') ?>"></script>
    <script>
    (function () {
        'use strict';

        // 1. Page transition radial wave sweep-out (1:1 Learn & SSO)
        var pageTransition = document.getElementById('pageTransition');
        var slidePanel = document.getElementById('slidePanel');
        setTimeout(function () {
            if (pageTransition) pageTransition.classList.remove('active');
        }, 100);

        // 2. Canvas interactive particle network (1:1 Lumina Gold)
        var canvas = document.getElementById('particleCanvas');
        if (canvas && canvas.getContext) {
            var ctx = canvas.getContext('2d');
            function resizeCanvas() {
                canvas.width  = window.innerWidth;
                canvas.height = window.innerHeight;
            }
            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);

            var particleColors = ['#1D3B5E', '#0D7A87', '#52B2DC', '#F59E0B', '#10B981'];
            var particles = [];
            for (var i = 0; i < 48; i++) {
                particles.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    vx: (Math.random() - 0.5) * 0.5,
                    vy: (Math.random() - 0.5) * 0.5,
                    radius: Math.random() * 3 + 1,
                    color: particleColors[Math.floor(Math.random() * particleColors.length)],
                    opacity: Math.random() * 0.5 + 0.2
                });
            }
            function animateParticles() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                for (var a = 0; a < particles.length; a++) {
                    var p1 = particles[a];
                    p1.x += p1.vx;
                    p1.y += p1.vy;
                    if (p1.x < 0 || p1.x > canvas.width)  p1.vx *= -1;
                    if (p1.y < 0 || p1.y > canvas.height) p1.vy *= -1;
                    ctx.beginPath();
                    ctx.arc(p1.x, p1.y, p1.radius, 0, Math.PI * 2);
                    ctx.fillStyle = p1.color;
                    ctx.globalAlpha = p1.opacity;
                    ctx.fill();
                    for (var b = a + 1; b < particles.length; b++) {
                        var p2 = particles[b];
                        var dist = Math.hypot(p1.x - p2.x, p1.y - p2.y);
                        if (dist < 105) {
                            ctx.beginPath();
                            ctx.moveTo(p1.x, p1.y);
                            ctx.lineTo(p2.x, p2.y);
                            ctx.strokeStyle = p1.color;
                            ctx.globalAlpha = (1 - dist / 105) * 0.2;
                            ctx.stroke();
                        }
                    }
                }
                ctx.globalAlpha = 1;
                requestAnimationFrame(animateParticles);
            }
            animateParticles();
        }

        // 3. Toggle password visibility
        var btnToggle = document.getElementById('togglePasswordBtn');
        var passInput = document.getElementById('gcPassword');
        var passIcon  = document.getElementById('togglePasswordIcon');
        if (btnToggle && passInput && passIcon) {
            btnToggle.addEventListener('click', function () {
                if (passInput.type === 'password') {
                    passInput.type = 'text';
                    passIcon.setAttribute('icon', 'solar:eye-closed-linear');
                } else {
                    passInput.type = 'password';
                    passIcon.setAttribute('icon', 'solar:eye-linear');
                }
            });
        }

        // 4. Radial wave exit on SSO click
        var btnSso = document.getElementById('btnSsoLogin');
        if (btnSso && !btnSso.classList.contains('is-disabled')) {
            btnSso.addEventListener('click', function (e) {
                e.preventDefault();
                var href = btnSso.getAttribute('href');
                if (slidePanel) slidePanel.classList.add('slide-out-behind');
                if (pageTransition) pageTransition.classList.add('active');
                setTimeout(function () { window.location.href = href; }, 420);
            });
        }

        // 5. AJAX Smooth Login + Radial Wave Exit Transition
        var loginForm = document.getElementById('loginForm');
        var btnSubmit = document.getElementById('btnSubmit');
        if (loginForm && btnSubmit) {
            var defaultBtnHtml = btnSubmit.innerHTML;
            loginForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var userEl = document.getElementById('gcUsername');
                var passEl = document.getElementById('gcPassword');
                if (!userEl.value.trim() || !passEl.value) {
                    if (window.Ilmuna) window.Ilmuna.warning('Lengkapi Kredensial', 'Masukkan username dan kata sandi Administrator.');
                    return;
                }
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<iconify-icon icon="solar:refresh-bold" class="spin-icon" width="18"></iconify-icon><span>Memverifikasi...</span>';

                var fd = new FormData(loginForm);
                fd.append('ajax', '1');

                fetch(loginForm.action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.success && res.redirect) {
                        if (window.Ilmuna) window.Ilmuna.toast({ type: 'success', title: 'Autentikasi Berhasil', message: 'Memasuki Pusat Komando SEC-OPS...' });
                        if (slidePanel) slidePanel.classList.add('slide-out-behind');
                        if (pageTransition) pageTransition.classList.add('active');
                        setTimeout(function () { window.location.href = res.redirect; }, 440);
                        return;
                    }
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = defaultBtnHtml;
                    var msg = (res && res.message) ? res.message : 'Username atau kata sandi tidak valid.';
                    if (window.Ilmuna) window.Ilmuna.error('Login Ditolak', msg);
                })
                .catch(function () {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = defaultBtnHtml;
                    if (window.Ilmuna) window.Ilmuna.error('Kesalahan Jaringan', 'Gagal menghubungi server autentikasi.');
                });
            });
        }
    })();
    </script>
</body>
</html>
