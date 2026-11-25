<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL API — PER-WEBSITE MAINTENANCE CONTROL & LIVE 1-TEMA PREVIEW
 * ==============================================================================
 * - Mengendalikan mode maintenance per-subdomain maupun global via Atomic Bridge
 *   (../guardian-dome/cache/gc-runtime-rules.json) tanpa membebani DB pengunjung.
 * - Live preview 1:1 halaman maintenance bertema Lumina Gold & Nusantara (?preview=1).
 * ==============================================================================
 */
require_once dirname(__DIR__) . '/auth.php';
require_once BASE_PATH . '/includes/waf-bridge.php';

$admin  = requireAdminApiAuth(true);
$pdo    = getDBConnection();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

/**
 * Render HTML Halaman Maintenance FULL-SCREEN (100vw x 100dvh) 1-Tema Lumina Gold & Nusantara
 */
function gc_render_maintenance_preview_html($host, array $site, array $global)
{
    $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $isGlobal = ($host === '__global__');
    $title    = $isGlobal ? (string)($global['global_title'] ?? 'Pemeliharaan Ekosistem Digital Ilmuna') : (string)($site['title'] ?? 'Sedang Pemeliharaan Sistem');
    $message  = $isGlobal ? (string)($global['global_message'] ?? '') : (string)($site['message'] ?? '');
    $eta      = $isGlobal ? (string)($global['global_eta'] ?? '') : (string)($site['eta'] ?? '');
    $progress = $isGlobal ? (int)($global['global_progress'] ?? 70) : (int)($site['progress'] ?? 65);
    $progress = max(5, min(100, $progress));
    $email    = (string)($site['contact_email'] ?? $global['contact_email'] ?? 'admin@ilmuna.my.id');
    $wa       = preg_replace('/[^0-9]/', '', (string)($global['contact_whatsapp'] ?? '6285117100227'));
    $domain   = $isGlobal ? '*.ilmuna.my.id' : (string)$host;
    $label    = $isGlobal ? 'Seluruh Ekosistem Ilmuna' : (string)($site['label'] ?? $host);
    $logoUrl  = BASE_URL . '/assets/images/Guardian_Dome.png';
    $etaTs    = ($eta !== '' && strtotime($eta) !== false) ? strtotime($eta) : false;
    $etaIso   = $etaTs !== false ? date('c', $etaTs) : '';
    $etaHuman = $etaTs !== false ? (date('d M Y · H:i', $etaTs) . ' WIB') : 'Segera Selesai';

    // Pecah judul agar kata terakhir berkilau gradasi Lumina Gold & Sky
    $words = preg_split('/\s+/', trim($title));
    if (is_array($words) && count($words) >= 3) {
        $splitAt = (int)ceil(count($words) / 2);
        $titleTop = implode(' ', array_slice($words, 0, $splitAt));
        $titleBot = implode(' ', array_slice($words, $splitAt));
        $titleHtml = $e($titleTop) . '<br><span class="hero-grad">' . $e($titleBot) . '</span>';
    } else {
        $titleHtml = '<span class="hero-grad">' . $e($title) . '</span>';
    }

    $gununganSvg = '<svg class="gunungan-bg" viewBox="0 0 280 380" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
        . '<path d="M140 10 C172 78 242 148 258 242 C266 294 238 348 204 370 L76 370 C42 348 14 294 22 242 C38 148 108 78 140 10 Z" stroke="url(#gGold)" stroke-width="2.4" fill="rgba(255,255,255,0.035)"/>'
        . '<path d="M140 34 C166 94 224 156 238 238 C244 282 222 330 192 352 L88 352 C58 330 36 282 42 238 C56 156 114 94 140 34 Z" stroke="rgba(82,178,220,0.42)" stroke-width="1.5" fill="none"/>'
        . '<path d="M140 70 L140 352 M78 274 Q140 222 202 274 M66 226 Q140 168 214 226 M92 172 Q140 128 188 172 M104 316 L176 316 M114 288 L166 288" stroke="rgba(255,255,255,0.28)" stroke-width="1.5" stroke-linecap="round"/>'
        . '<circle cx="140" cy="122" r="18" stroke="rgba(245,158,11,0.62)" stroke-width="1.8" fill="rgba(245,158,11,0.10)"/>'
        . '<path d="M140 108 L146 122 L140 136 L134 122 Z" fill="rgba(245,158,11,0.65)"/>'
        . '<rect x="64" y="366" width="152" height="9" rx="4.5" fill="rgba(245,158,11,0.28)"/>'
        . '<defs><linearGradient id="gGold" x1="140" y1="10" x2="140" y2="370" gradientUnits="userSpaceOnUse"><stop stop-color="#F59E0B" stop-opacity="0.72"/><stop offset="0.55" stop-color="#52B2DC" stop-opacity="0.45"/><stop offset="1" stop-color="#FFFFFF" stop-opacity="0.22"/></linearGradient></defs>'
        . '</svg>';

    $countdownBlock = $etaIso !== ''
        ? '<div class="cd-grid" id="cdBox" data-eta="' . $e($etaIso) . '">'
            . '<div class="cd-tile"><div class="cd-val" id="cdD">00</div><div class="cd-lbl">Hari</div></div>'
            . '<div class="cd-tile"><div class="cd-val" id="cdH">00</div><div class="cd-lbl">Jam</div></div>'
            . '<div class="cd-tile"><div class="cd-val" id="cdM">00</div><div class="cd-lbl">Menit</div></div>'
            . '<div class="cd-tile"><div class="cd-val" id="cdS">00</div><div class="cd-lbl">Detik</div></div>'
            . '</div>'
        : '<div class="pulse-status-box">'
            . '<div class="pulse-ring"><iconify-icon icon="solar:settings-bold" width="26" class="spin-slow"></iconify-icon></div>'
            . '<div><strong>Optimasi Sistem Berlangsung</strong><span>Layanan akan kembali aktif secara otomatis begitu pemeliharaan tuntas.</span></div>'
            . '</div>';

    return '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>' . $e($title) . ' — ' . $e($domain) . '</title>
<link rel="icon" href="' . $e($logoUrl) . '">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js" defer></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--navy:#1D3B5E;--navy-deep:#0B192C;--teal:#0D7A87;--sky:#52B2DC;--gold:#F59E0B;--emerald:#10B981}
html,body{width:100%;min-height:100dvh;font-family:"Plus Jakarta Sans",-apple-system,BlinkMacSystemFont,sans-serif;color:#FFFFFF;background:#0B192C;overflow-x:hidden;-webkit-font-smoothing:antialiased}
body{position:relative;display:flex;flex-direction:column;justify-content:space-between;background:radial-gradient(ellipse 85% 75% at 15% 15%,rgba(13,122,135,0.58) 0%,transparent 60%),radial-gradient(ellipse 70% 65% at 88% 22%,rgba(245,158,11,0.20) 0%,transparent 55%),radial-gradient(ellipse 90% 80% at 50% 100%,rgba(82,178,220,0.24) 0%,transparent 62%),linear-gradient(135deg,#0B192C 0%,#142C49 42%,#0D5F6E 85%,#10253E 100%)}

/* Radial Wave Curtain (1:1 Login DNA) */
.page-curtain{position:fixed;top:50%;left:50%;width:300vmax;height:300vmax;margin-top:-150vmax;margin-left:-150vmax;background:#E8EFF7;z-index:999;transform:rotate(135deg) translateY(-10%);transition:transform 0.7s cubic-bezier(0.4,0,0.2,1);pointer-events:none}
.page-curtain::before{content:"";position:absolute;top:10vh;left:0;width:100%;height:100%;background:#52B2DC}
.page-curtain::after{content:"";position:absolute;top:20vh;left:0;width:100%;height:100%;background:#0D7A87}
.page-curtain.is-done{transform:rotate(-225deg) translateY(85%)}

/* Fullscreen Interactive Backdrop Layers */
#maintCanvas{position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:1}
.pixel-mesh{position:fixed;inset:0;background-image:radial-gradient(circle,rgba(82,178,220,0.24) 1.3px,transparent 1.3px);background-size:26px 26px;opacity:0.42;pointer-events:none;z-index:1;animation:meshPulse 8s ease-in-out infinite}
@keyframes meshPulse{0%,100%{opacity:0.32}50%{opacity:0.55}}
.orb{position:fixed;border-radius:50%;filter:blur(120px);pointer-events:none;z-index:0;animation:orbFloat 14s ease-in-out infinite alternate}
.orb-1{width:540px;height:540px;top:-160px;left:-120px;background:rgba(13,122,135,0.42)}
.orb-2{width:460px;height:460px;bottom:-150px;right:-90px;background:rgba(82,178,220,0.34);animation-delay:-5s}
.orb-3{width:340px;height:340px;top:30%;right:28%;background:rgba(245,158,11,0.18);animation-delay:-9s}
@keyframes orbFloat{0%{transform:translate3d(0,0,0) scale(1)}100%{transform:translate3d(36px,-28px,0) scale(1.12)}}

.gunungan-bg{position:fixed;right:2vw;bottom:34px;height:min(76vh,620px);width:auto;opacity:0.36;pointer-events:none;z-index:2;animation:gunFloat 10s ease-in-out infinite}
@keyframes gunFloat{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-10px) scale(1.015)}}

/* Full-Width 4-Layer Parallax Waves */
.fs-waves{position:fixed;bottom:0;left:0;width:100%;overflow:hidden;line-height:0;z-index:3;opacity:0.38;pointer-events:none}
.fs-waves svg{display:block;width:calc(100% + 900px);height:68px}
.parallax>use{animation:waveMove 24s cubic-bezier(.55,.5,.45,.5) infinite}
.parallax>use:nth-child(1){animation-delay:-2s;animation-duration:7s}
.parallax>use:nth-child(2){animation-delay:-3s;animation-duration:10s}
.parallax>use:nth-child(3){animation-delay:-4s;animation-duration:14s}
.parallax>use:nth-child(4){animation-delay:-5s;animation-duration:20s}
@keyframes waveMove{0%{transform:translate3d(-90px,0,0)}100%{transform:translate3d(85px,0,0)}}

/* 1. TOP FULL-WIDTH BAR */
.fs-header{position:relative;z-index:10;width:100%;padding:24px clamp(18px,4.5vw,56px) 12px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;animation:fadeDown 0.75s cubic-bezier(0.22,1,0.36,1) both}
@keyframes fadeDown{from{opacity:0;transform:translateY(-14px)}to{opacity:1;transform:translateY(0)}}
.brand-pill{position:relative;display:inline-flex;align-items:center;gap:12px;padding:9px 18px 9px 11px;border-radius:18px;background:rgba(255,255,255,0.96);box-shadow:0 12px 30px rgba(0,0,0,0.28),0 0 28px rgba(82,178,220,0.28);overflow:hidden}
.brand-pill::after{content:"";position:absolute;top:0;left:0;width:48%;height:100%;background:linear-gradient(to right,transparent,rgba(255,255,255,0.85),transparent);transform:translateX(-160%) skewX(-24deg);animation:shine 5.5s infinite ease-in-out}
@keyframes shine{0%{transform:translateX(-160%) skewX(-24deg)}100%{transform:translateX(270%) skewX(-24deg)}}
.brand-pill img{width:40px;height:40px;object-fit:contain;display:block}
.brand-pill strong{display:block;font-family:"Outfit",sans-serif;font-size:1rem;font-weight:800;color:#1D3B5E;line-height:1.1}
.brand-pill small{display:block;font-size:0.66rem;font-weight:700;color:#0D7A87;letter-spacing:0.09em;text-transform:uppercase}

.header-badges{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.status-pill{display:inline-flex;align-items:center;gap:8px;padding:8px 15px;border-radius:999px;background:rgba(245,158,11,0.18);border:1px solid rgba(245,158,11,0.58);backdrop-filter:blur(12px);font-size:0.74rem;font-weight:800;letter-spacing:0.09em;text-transform:uppercase;color:#FEF3C7}
.status-pill .dot{width:8px;height:8px;border-radius:50%;background:#F59E0B;box-shadow:0 0 12px #F59E0B;animation:pulseDot 1.8s ease-in-out infinite}
@keyframes pulseDot{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(0.75);opacity:0.45}}
.domain-chip{display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:999px;background:rgba(11,25,44,0.52);border:1px solid rgba(255,255,255,0.22);backdrop-filter:blur(12px);font-family:"JetBrains Mono",monospace;font-size:0.78rem;font-weight:600;color:#E0F2FE}

/* 2. FULL-SCREEN MAIN STAGE */
.fs-main{position:relative;z-index:10;flex:1;width:100%;max-width:1360px;margin:0 auto;padding:24px clamp(18px,4.5vw,56px) 32px;display:grid;grid-template-columns:1.14fr 0.86fr;gap:clamp(28px,5vw,68px);align-items:center}

.hero-col{animation:fadeUp 0.85s cubic-bezier(0.22,1,0.36,1) 0.12s both}
@keyframes fadeUp{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}
.service-kicker{display:inline-flex;align-items:center;gap:8px;padding:7px 14px;border-radius:12px;background:rgba(255,255,255,0.10);border:1px solid rgba(255,255,255,0.20);backdrop-filter:blur(10px);font-size:0.8rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#BAE6FD;margin-bottom:18px}
.hero-title{font-family:"Outfit",sans-serif;font-size:clamp(2.1rem,4.3vw,3.75rem);font-weight:900;line-height:1.09;letter-spacing:-0.025em;color:#FFFFFF;margin-bottom:18px;text-shadow:0 10px 32px rgba(0,0,0,0.28)}
.hero-grad{background:linear-gradient(95deg,#FFFFFF 0%,#7DD3FC 45%,#FBBF24 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.hero-msg{font-size:clamp(0.95rem,1.25vw,1.1rem);color:rgba(241,245,249,0.88);line-height:1.72;max-width:600px;margin-bottom:28px}

.hero-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.btn-gold{min-height:52px;display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:12px 26px;border-radius:15px;border:none;background:linear-gradient(135deg,#F59E0B 0%,#D97706 100%);color:#0B192C;font-family:"Outfit",sans-serif;font-weight:800;font-size:0.95rem;cursor:pointer;position:relative;overflow:hidden;box-shadow:0 14px 32px -8px rgba(245,158,11,0.58);transition:transform 0.22s,box-shadow 0.22s;text-decoration:none}
.btn-gold:hover{transform:translateY(-2px);box-shadow:0 18px 36px -6px rgba(245,158,11,0.72)}
.btn-gold::after{content:"";position:absolute;top:0;left:-100%;width:50%;height:100%;background:linear-gradient(to right,transparent,rgba(255,255,255,0.6),transparent);transform:skewX(-20deg);animation:sweep 3.2s infinite}
@keyframes sweep{0%{left:-100%}22%{left:210%}100%{left:210%}}
.btn-glass{min-height:52px;display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:12px 22px;border-radius:15px;border:1.5px solid rgba(255,255,255,0.28);background:rgba(255,255,255,0.10);backdrop-filter:blur(12px);color:#FFFFFF;font-family:"Outfit",sans-serif;font-weight:700;font-size:0.92rem;text-decoration:none;transition:transform 0.22s,background 0.22s,border-color 0.22s}
.btn-glass:hover{transform:translateY(-2px);background:rgba(255,255,255,0.18);border-color:rgba(255,255,255,0.5)}

/* RIGHT FROSTED TELEMETRY HUD */
.hud-card{background:rgba(11,25,44,0.48);backdrop-filter:blur(22px);-webkit-backdrop-filter:blur(22px);border:1px solid rgba(255,255,255,0.20);border-radius:28px;padding:28px;box-shadow:0 28px 64px -16px rgba(0,0,0,0.45),inset 0 1px 0 rgba(255,255,255,0.18);animation:fadeUp 0.9s cubic-bezier(0.22,1,0.36,1) 0.24s both}
.hud-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:18px;flex-wrap:wrap}
.hud-title{font-family:"Outfit",sans-serif;font-size:0.86rem;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:#BAE6FD;display:flex;align-items:center;gap:7px}
.hud-eta{font-family:"JetBrains Mono",monospace;font-size:0.76rem;font-weight:700;color:#FDE68A;padding:4px 10px;border-radius:999px;background:rgba(245,158,11,0.16);border:1px solid rgba(245,158,11,0.35)}

.cd-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:22px}
.cd-tile{background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.16);border-radius:18px;padding:15px 6px;text-align:center;transition:transform 0.22s}
.cd-tile:hover{transform:translateY(-2px);border-color:rgba(245,158,11,0.5)}
.cd-val{font-family:"Outfit",sans-serif;font-size:clamp(1.5rem,2.4vw,2.15rem);font-weight:900;line-height:1.05;background:linear-gradient(180deg,#FFFFFF 20%,#7DD3FC 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.cd-lbl{font-size:0.68rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:rgba(226,232,240,0.75);margin-top:5px}

.pulse-status-box{display:flex;align-items:center;gap:14px;padding:16px;border-radius:18px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.16);margin-bottom:22px}
.pulse-ring{width:52px;height:52px;border-radius:16px;background:linear-gradient(135deg,rgba(245,158,11,0.28),rgba(82,178,220,0.28));border:1px solid rgba(245,158,11,0.45);display:flex;align-items:center;justify-content:center;color:#FDE68A;flex-shrink:0}
.spin-slow{animation:spinSlow 7s linear infinite}
@keyframes spinSlow{to{transform:rotate(360deg)}}
.pulse-status-box strong{display:block;font-family:"Outfit",sans-serif;font-size:0.96rem;color:#FFF;margin-bottom:2px}
.pulse-status-box span{font-size:0.78rem;color:rgba(226,232,240,0.8);line-height:1.45;display:block}

.prog-wrap{margin-bottom:20px}
.prog-meta{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px;font-size:0.8rem;font-weight:700;color:rgba(241,245,249,0.9)}
.prog-pct{font-family:"JetBrains Mono",monospace;font-size:1.05rem;font-weight:800;color:#FBBF24}
.prog-track{height:12px;border-radius:999px;background:rgba(255,255,255,0.12);overflow:hidden;border:1px solid rgba(255,255,255,0.12)}
.prog-fill{height:100%;width:' . $progress . '%;border-radius:999px;background:linear-gradient(90deg,#0D7A87 0%,#52B2DC 55%,#F59E0B 100%);position:relative;overflow:hidden;box-shadow:0 0 18px rgba(82,178,220,0.55)}
.prog-fill::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.62),transparent);animation:shimmer 2.2s infinite}
@keyframes shimmer{0%{transform:translateX(-100%)}100%{transform:translateX(100%)}}

.check-list{display:grid;gap:9px}
.check-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:13px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.10);font-size:0.8rem;color:rgba(241,245,249,0.9)}
.check-item strong{color:#FFFFFF}

/* 3. BOTTOM FULL-WIDTH BAR */
.fs-footer{position:relative;z-index:10;width:100%;padding:14px clamp(18px,4.5vw,56px) 22px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:0.78rem;color:rgba(226,232,240,0.82);border-top:1px solid rgba(255,255,255,0.12);background:rgba(11,25,44,0.28);backdrop-filter:blur(10px)}
.adm-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 13px;border-radius:999px;border:1px solid rgba(255,255,255,0.24);background:rgba(255,255,255,0.10);color:#FFFFFF;font-family:"Outfit",sans-serif;font-size:0.75rem;font-weight:700}

@media(max-width:960px){
  .fs-main{grid-template-columns:1fr;gap:26px;padding-top:12px}
  .gunungan-bg{height:44vh;opacity:0.2;right:-24px}
  .hud-card{padding:20px;border-radius:22px}
}
@media(max-width:520px){
  .fs-header{padding:16px 16px 8px}
  .fs-main{padding:12px 16px 24px}
  .fs-footer{padding:14px 16px 20px}
  .hero-actions>*{width:100%}
  .cd-grid{gap:7px}
  .cd-tile{padding:11px 4px;border-radius:14px}
}
</style>
</head>
<body>
<div class="page-curtain" id="pageCurtain"></div>
<canvas id="maintCanvas"></canvas>
<div class="pixel-mesh"></div>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>
' . $gununganSvg . '
<div class="fs-waves">
  <svg viewBox="0 24 150 28" preserveAspectRatio="none">
    <defs><path id="w" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z"/></defs>
    <g class="parallax">
      <use href="#w" x="48" y="0" fill="rgba(255,255,255,0.24)"/>
      <use href="#w" x="48" y="3" fill="rgba(82,178,220,0.20)"/>
      <use href="#w" x="48" y="5" fill="rgba(255,255,255,0.14)"/>
      <use href="#w" x="48" y="7" fill="rgba(255,255,255,0.34)"/>
    </g>
  </svg>
</div>

<header class="fs-header">
  <div class="brand-pill">
    <img src="' . $e($logoUrl) . '" alt="Guardian Dome">
    <div><strong>Guardian Dome</strong><small>Ilmuna Digital Ecosystem</small></div>
  </div>
  <div class="header-badges">
    <span class="status-pill"><span class="dot"></span><span>Pemeliharaan Terjadwal · HTTP 503</span></span>
    <span class="domain-chip"><iconify-icon icon="solar:global-bold" width="15" style="color:#F59E0B"></iconify-icon><span>' . $e($domain) . '</span></span>
  </div>
</header>

<main class="fs-main">
  <section class="hero-col">
    <div class="service-kicker"><iconify-icon icon="solar:shield-star-bold" width="16" style="color:#F59E0B"></iconify-icon><span>' . $e($label) . '</span></div>
    <h1 class="hero-title">' . $titleHtml . '</h1>
    <p class="hero-msg">' . nl2br($e($message)) . '</p>
    <div class="hero-actions">
      <button type="button" class="btn-gold" onclick="window.location.reload()">
        <iconify-icon icon="solar:refresh-bold" width="19"></iconify-icon><span>Periksa Status Sekarang</span>
      </button>
      ' . ($wa !== '' ? '<a class="btn-glass" href="https://wa.me/' . $e($wa) . '" target="_blank" rel="noopener"><iconify-icon icon="solar:chat-round-dots-bold" width="18" style="color:#34D399"></iconify-icon><span>Hubungi Admin</span></a>' : '') . '
    </div>
  </section>

  <aside class="hud-card">
    <div class="hud-head">
      <span class="hud-title"><iconify-icon icon="solar:clock-circle-bold" width="17" style="color:#F59E0B"></iconify-icon> Status Pemeliharaan Live</span>
      <span class="hud-eta">Estimasi: ' . $e($etaHuman) . '</span>
    </div>
    ' . $countdownBlock . '
    <div class="prog-wrap">
      <div class="prog-meta"><span>Progres Peningkatan Layanan</span><span class="prog-pct">' . $progress . '%</span></div>
      <div class="prog-track"><div class="prog-fill"></div></div>
    </div>
    <div class="check-list">
      <div class="check-item"><iconify-icon icon="solar:verified-check-bold" width="18" style="color:#34D399;flex-shrink:0"></iconify-icon><span><strong>Data &amp; Akun 100% Aman</strong> — Enkripsi penuh terjaga</span></div>
      <div class="check-item"><iconify-icon icon="solar:cpu-bolt-bold" width="18" style="color:#FBBF24;flex-shrink:0"></iconify-icon><span><strong>Optimasi &amp; Patch Server</strong> — Sedang diproses real-time</span></div>
      <div class="check-item"><iconify-icon icon="solar:shield-check-bold" width="18" style="color:#7DD3FC;flex-shrink:0"></iconify-icon><span><strong>Guardian Dome WAF Aktif</strong> — Zero-Trust 24/7</span></div>
    </div>
  </aside>
</main>

<footer class="fs-footer">
  <span><iconify-icon icon="solar:shield-check-bold" width="15" style="color:#34D399;vertical-align:-2px"></iconify-icon> Dilindungi <strong>Guardian Dome</strong> · SEC-OPS ilmuna.my.id</span>
  <span style="display:inline-flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <span>' . $e($email) . '</span>
    <span class="adm-chip"><iconify-icon icon="solar:lock-keyhole-bold" width="13" style="color:#FBBF24"></iconify-icon> Akses Khusus Admin</span>
  </span>
</footer>

<script>
(function(){
  requestAnimationFrame(function(){setTimeout(function(){var ct=document.getElementById("pageCurtain");if(ct)ct.classList.add("is-done")},60)});
  var c=document.getElementById("maintCanvas");
  if(c&&c.getContext){
    var x=c.getContext("2d"),cols=["#52B2DC","#F59E0B","#10B981","#BAE6FD","#FFFFFF"],pts=[];
    function rs(){c.width=innerWidth;c.height=innerHeight}rs();addEventListener("resize",rs);
    for(var i=0;i<46;i++)pts.push({x:Math.random()*c.width,y:Math.random()*c.height,vx:(Math.random()-.5)*.48,vy:(Math.random()-.5)*.48,r:Math.random()*2.4+1,c:cols[i%cols.length],o:Math.random()*.45+.2});
    (function loop(){x.clearRect(0,0,c.width,c.height);for(var a=0;a<pts.length;a++){var p=pts[a];p.x+=p.vx;p.y+=p.vy;if(p.x<0||p.x>c.width)p.vx*=-1;if(p.y<0||p.y>c.height)p.vy*=-1;x.beginPath();x.arc(p.x,p.y,p.r,0,Math.PI*2);x.fillStyle=p.c;x.globalAlpha=p.o;x.fill();for(var b=a+1;b<pts.length;b++){var q=pts[b],d=Math.hypot(p.x-q.x,p.y-q.y);if(d<125){x.beginPath();x.moveTo(p.x,p.y);x.lineTo(q.x,q.y);x.strokeStyle=p.c;x.globalAlpha=(1-d/125)*.18;x.stroke()}}}x.globalAlpha=1;requestAnimationFrame(loop)})();
  }
  var cd=document.getElementById("cdBox");
  if(cd){
    var target=Date.parse(cd.getAttribute("data-eta"));
    function pad(n){return n<10?"0"+n:String(n)}
    function tick(){
      var diff=Math.max(0,Math.floor((target-Date.now())/1000));
      document.getElementById("cdD").textContent=pad(Math.floor(diff/86400));
      document.getElementById("cdH").textContent=pad(Math.floor((diff%86400)/3600));
      document.getElementById("cdM").textContent=pad(Math.floor((diff%3600)/60));
      document.getElementById("cdS").textContent=pad(diff%60);
    }
    tick();setInterval(tick,1000);
  }
})();
</script>
</body>
</html>';
}

if ($method === 'GET') {
    $maint = gc_waf_read_maintenance();

    // Live Preview 1:1 Halaman Maintenance Lumina Gold & Nusantara
    if (!empty($_GET['preview'])) {
        $host = strtolower(trim((string)($_GET['host'] ?? 'ilmuna.my.id')));
        $site = isset($maint['sites'][$host]) ? $maint['sites'][$host] : [];
        header('Content-Type: text/html; charset=utf-8');
        header('X-Frame-Options: SAMEORIGIN');
        echo gc_render_maintenance_preview_html($host, $site, $maint);
        exit;
    }

    gc_json_response([
        'success'             => true,
        'maintenance'         => $maint,
        'active_count'        => gc_waf_count_maintenance_active(),
        'admin_cookie_active' => !empty($_COOKIE['gd_admin_maint_pass']),
        'admin_username'      => (string)($admin['username'] ?? 'admin'),
        'my_ip'               => gc_get_client_ip(),
    ]);
}

if ($method === 'POST') {
    $input  = gc_get_json_input();
    $action = trim((string)($input['action'] ?? ''));
    $maint  = gc_waf_read_maintenance();

    if ($action === 'toggle_global') {
        $enabled = !empty($input['enabled']);
        $maint['global_enabled'] = $enabled;
        if (isset($input['global_title']) && trim((string)$input['global_title']) !== '') {
            $maint['global_title'] = substr(trim((string)$input['global_title']), 0, 140);
        }
        if (isset($input['global_message']) && trim((string)$input['global_message']) !== '') {
            $maint['global_message'] = substr(trim((string)$input['global_message']), 0, 600);
        }
        if (isset($input['global_eta'])) {
            $maint['global_eta'] = trim((string)$input['global_eta']);
        }
        if (isset($input['global_progress'])) {
            $maint['global_progress'] = max(0, min(100, (int)$input['global_progress']));
        }
        $saved = gc_waf_write_maintenance($maint, $admin['username']);
        if ($saved === false) {
            gc_json_response(['success' => false, 'error' => 'Gagal menyimpan status Global Maintenance ke gc-runtime-rules.json.'], 500);
        }
        gc_audit_log('site_maintenance_global', $enabled ? 'ENABLED' : 'DISABLED', ['eta' => $maint['global_eta']], $admin['username']);
        gc_json_response([
            'success'      => true,
            'message'      => $enabled
                ? 'Global Maintenance Mode AKTIF untuk seluruh web *.ilmuna.my.id (Guardian Control & Admin Whitelist tetap dapat mengakses).'
                : 'Global Maintenance Mode DINONAKTIFKAN. Seluruh ekosistem kembali normal.',
            'maintenance'  => gc_waf_read_maintenance(),
            'active_count' => gc_waf_count_maintenance_active(),
        ]);
    }

    if ($action === 'toggle_site') {
        $host = strtolower(trim((string)($input['host'] ?? '')));
        if ($host === '' || $host === 'guardian-dome.ilmuna.my.id' || !isset($maint['sites'][$host])) {
            gc_json_response(['success' => false, 'error' => 'Domain target tidak valid atau dilindungi sistem.'], 422);
        }
        $enabled = !empty($input['enabled']);
        $maint['sites'][$host]['enabled'] = $enabled;
        $saved = gc_waf_write_maintenance($maint, $admin['username']);
        if ($saved === false) {
            gc_json_response(['success' => false, 'error' => 'Gagal menulis status maintenance ke gc-runtime-rules.json.'], 500);
        }
        gc_audit_log('site_maintenance_toggle', $host, ['enabled' => $enabled], $admin['username']);
        gc_json_response([
            'success'      => true,
            'message'      => $enabled ? "Mode Maintenance AKTIF untuk {$host} (HTTP 503 SEO-Safe)." : "{$host} kembali ONLINE secara penuh.",
            'maintenance'  => gc_waf_read_maintenance(),
            'active_count' => gc_waf_count_maintenance_active(),
        ]);
    }

    if ($action === 'save_site_config') {
        $host = strtolower(trim((string)($input['host'] ?? '')));
        if ($host === '' || $host === 'guardian-dome.ilmuna.my.id' || !preg_match('/^([a-z0-9-]+\.)*ilmuna\.my\.id$/i', $host)) {
            gc_json_response(['success' => false, 'error' => 'Domain harus berada dalam ekosistem *.ilmuna.my.id (selain guardian-dome).'], 422);
        }
        $existing = isset($maint['sites'][$host]) ? $maint['sites'][$host] : ['enabled' => false, 'icon' => 'solar:global-bold'];
        $maint['sites'][$host] = [
            'label'         => substr(trim((string)($input['label'] ?? ($existing['label'] ?? $host))), 0, 80),
            'icon'          => (string)($existing['icon'] ?? 'solar:global-bold'),
            'enabled'       => isset($input['enabled']) ? !empty($input['enabled']) : !empty($existing['enabled']),
            'title'         => substr(trim((string)($input['title'] ?? 'Sedang Pemeliharaan Sistem')), 0, 140),
            'message'       => substr(trim((string)($input['message'] ?? 'Website sedang menjalani pemeliharaan terjadwal.')), 0, 600),
            'eta'           => trim((string)($input['eta'] ?? '')),
            'auto_end'      => !empty($input['auto_end']),
            'progress'      => max(0, min(100, (int)($input['progress'] ?? 65))),
            'retry_after'   => max(60, min(86400, (int)($input['retry_after'] ?? 1800))),
            'contact_email' => substr(trim((string)($input['contact_email'] ?? 'admin@ilmuna.my.id')), 0, 120),
        ];
        $saved = gc_waf_write_maintenance($maint, $admin['username']);
        if ($saved === false) {
            gc_json_response(['success' => false, 'error' => 'Gagal menyimpan konfigurasi maintenance.'], 500);
        }
        gc_audit_log('site_maintenance_config', $host, ['eta' => $maint['sites'][$host]['eta'], 'progress' => $maint['sites'][$host]['progress']], $admin['username']);
        gc_json_response([
            'success'      => true,
            'message'      => "Konfigurasi halaman maintenance untuk {$host} berhasil disimpan & aktif instan.",
            'maintenance'  => gc_waf_read_maintenance(),
            'active_count' => gc_waf_count_maintenance_active(),
        ]);
    }

    if ($action === 'save_global_opts') {
        $maint['allow_admin_bypass']        = !empty($input['allow_admin_bypass']);
        $maint['allow_ip_whitelist_bypass'] = !empty($input['allow_ip_whitelist_bypass']);
        $maint['bypass_key']                = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($input['bypass_key'] ?? 'ilmuna2026')) ?: 'ilmuna2026';
        $maint['global_title']              = substr(trim((string)($input['global_title'] ?? $maint['global_title'])), 0, 140);
        $maint['global_message']            = substr(trim((string)($input['global_message'] ?? $maint['global_message'])), 0, 600);
        $maint['global_eta']                = trim((string)($input['global_eta'] ?? ''));
        $maint['global_progress']           = max(0, min(100, (int)($input['global_progress'] ?? 70)));
        $maint['contact_whatsapp']          = preg_replace('/[^0-9]/', '', (string)($input['contact_whatsapp'] ?? '6285117100227'));

        $saved = gc_waf_write_maintenance($maint, $admin['username']);
        if ($saved === false) {
            gc_json_response(['success' => false, 'error' => 'Gagal menyimpan pengaturan global maintenance.'], 500);
        }
        gc_audit_log('site_maintenance_opts', 'global_options', [
            'allow_admin_bypass' => $maint['allow_admin_bypass'],
            'allow_ip_whitelist_bypass' => $maint['allow_ip_whitelist_bypass'],
        ], $admin['username']);
        gc_json_response([
            'success'      => true,
            'message'      => 'Pengaturan Global Maintenance & Akses Khusus Admin berhasil diperbarui.',
            'maintenance'  => gc_waf_read_maintenance(),
            'active_count' => gc_waf_count_maintenance_active(),
        ]);
    }

    if ($action === 'remove_site') {
        $host = strtolower(trim((string)($input['host'] ?? '')));
        $core = ['ilmuna.my.id', 'margo-one.ilmuna.my.id', 'learn.ilmuna.my.id', 'about.ilmuna.my.id'];
        if (in_array($host, $core, true)) {
            gc_json_response(['success' => false, 'error' => 'Website inti ekosistem tidak dapat dihapus (cukup matikan saklarnya).'], 422);
        }
        if (isset($maint['sites'][$host])) {
            unset($maint['sites'][$host]);
            gc_waf_write_maintenance($maint, $admin['username']);
            gc_audit_log('site_maintenance_remove', $host, [], $admin['username']);
        }
        gc_json_response([
            'success'      => true,
            'message'      => "Subdomain {$host} dihapus dari daftar maintenance.",
            'maintenance'  => gc_waf_read_maintenance(),
            'active_count' => gc_waf_count_maintenance_active(),
        ]);
    }

    gc_json_response(['success' => false, 'error' => 'Aksi maintenance website tidak dikenal.'], 400);
}

gc_json_response(['success' => false, 'error' => 'Method Not Allowed'], 405);
