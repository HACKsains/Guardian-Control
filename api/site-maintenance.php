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
 * Render HTML Halaman Maintenance 1-Tema Lumina Gold & Nusantara (Live Preview)
 */
function gc_render_maintenance_preview_html($host, array $site, array $global)
{
    $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $isGlobal = ($host === '__global__');
    $title    = $isGlobal ? (string)($global['global_title'] ?? 'Pemeliharaan Ekosistem') : (string)($site['title'] ?? 'Sedang Pemeliharaan Sistem');
    $message  = $isGlobal ? (string)($global['global_message'] ?? '') : (string)($site['message'] ?? '');
    $eta      = $isGlobal ? (string)($global['global_eta'] ?? '') : (string)($site['eta'] ?? '');
    $progress = $isGlobal ? (int)($global['global_progress'] ?? 70) : (int)($site['progress'] ?? 65);
    $progress = max(5, min(100, $progress));
    $email    = (string)($site['contact_email'] ?? $global['contact_email'] ?? 'admin@ilmuna.my.id');
    $wa       = preg_replace('/[^0-9]/', '', (string)($global['contact_whatsapp'] ?? '6285117100227'));
    $domain   = $isGlobal ? '*.ilmuna.my.id' : (string)$host;
    $label    = $isGlobal ? 'Seluruh Ekosistem Ilmuna' : (string)($site['label'] ?? $host);
    $logoUrl  = BASE_URL . '/assets/images/Guardian_Dome.png';
    $etaIso   = ($eta !== '' && strtotime($eta) !== false) ? date('c', strtotime($eta)) : '';
    $etaHuman = ($eta !== '' && strtotime($eta) !== false) ? date('d M Y · H:i', strtotime($eta)) . ' WIB' : 'Segera Selesai';
    $gununganSvg = '<svg class="gunungan-svg" viewBox="0 0 220 300" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
        . '<path d="M110 8 C132 58 186 112 198 186 C204 226 184 268 158 286 L62 286 C36 268 16 226 22 186 C34 112 88 58 110 8 Z" stroke="rgba(245,158,11,0.52)" stroke-width="2.5" fill="rgba(255,255,255,0.06)"/>'
        . '<path d="M110 26 C128 70 172 118 182 184 C187 218 171 254 148 272 L72 272 C49 254 33 218 38 184 C48 118 92 70 110 26 Z" stroke="rgba(82,178,220,0.45)" stroke-width="1.6" fill="none"/>'
        . '<path d="M110 54 L110 272 M64 214 Q110 176 156 214 M54 176 Q110 132 166 176 M74 134 Q110 100 146 134 M84 246 L136 246 M92 224 L128 224" stroke="rgba(255,255,255,0.34)" stroke-width="1.5" stroke-linecap="round"/>'
        . '<circle cx="110" cy="96" r="14" stroke="rgba(245,158,11,0.55)" stroke-width="1.8" fill="rgba(245,158,11,0.12)"/>'
        . '<path d="M110 86 L114 96 L110 106 L106 96 Z" fill="rgba(245,158,11,0.55)"/>'
        . '<rect x="52" y="284" width="116" height="8" rx="4" fill="rgba(245,158,11,0.32)"/>'
        . '</svg>';

    return '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>' . $e($title) . ' — ' . $e($domain) . '</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js" defer></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--navy:#1D3B5E;--teal:#0D7A87;--sky:#52B2DC;--gold:#F59E0B;--emerald:#10B981}
html,body{min-height:100dvh;font-family:"Plus Jakarta Sans",-apple-system,BlinkMacSystemFont,sans-serif;background:#F8FAFC;color:#0F1D2F;overflow-x:hidden}
body{display:flex;align-items:center;justify-content:center;padding:24px 16px;position:relative}
#maintCanvas{position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:1}
.orb{position:fixed;border-radius:50%;filter:blur(110px);pointer-events:none;z-index:0;width:480px;height:480px;animation:orbFloat 12s ease-in-out infinite alternate}
.orb-1{top:-120px;left:-80px;background:rgba(13,122,135,0.16)}
.orb-2{bottom:-140px;right:-80px;background:rgba(82,178,220,0.18);animation-delay:-6s}
@keyframes orbFloat{0%{transform:translate3d(0,0,0) scale(1)}100%{transform:translate3d(30px,-24px,0) scale(1.09)}}

.maint-shell{position:relative;z-index:10;width:100%;max-width:960px;display:grid;grid-template-columns:1fr 1.08fr;border-radius:32px;overflow:hidden;box-shadow:0 28px 70px -18px rgba(29,59,94,0.24),0 0 0 1px rgba(226,232,240,0.85);animation:shellIn 0.85s cubic-bezier(0.22,1,0.36,1) both}
@keyframes shellIn{from{opacity:0;transform:translateY(22px) scale(0.97)}to{opacity:1;transform:translateY(0) scale(1)}}

.maint-left{position:relative;background:linear-gradient(135deg,#1D3B5E 0%,#0D7A87 52%,#13273F 100%);color:#FFF;padding:38px 34px 72px;display:flex;flex-direction:column;justify-content:space-between;overflow:hidden}
.gunungan-svg{position:absolute;right:-14px;bottom:14px;width:210px;height:280px;opacity:0.42;pointer-events:none;z-index:1;animation:gunFloat 8s ease-in-out infinite}
@keyframes gunFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
.pixel-bg{position:absolute;inset:0;background-image:radial-gradient(circle,rgba(82,178,220,0.32) 1.5px,transparent 1.5px);background-size:20px 20px;opacity:0.5;animation:pixPulse 7s ease-in-out infinite;pointer-events:none}
@keyframes pixPulse{0%,100%{opacity:0.35}50%{opacity:0.65}}
.wave-wrap{position:absolute;bottom:0;left:0;width:100%;overflow:hidden;line-height:0;z-index:4;opacity:0.42;pointer-events:none}
.wave-wrap svg{display:block;width:calc(100% + 700px);height:54px}
.parallax>use{animation:waveMove 22s cubic-bezier(.55,.5,.45,.5) infinite}
.parallax>use:nth-child(1){animation-delay:-2s;animation-duration:7s}
.parallax>use:nth-child(2){animation-delay:-3s;animation-duration:10s}
.parallax>use:nth-child(3){animation-delay:-4s;animation-duration:14s}
@keyframes waveMove{0%{transform:translate3d(-90px,0,0)}100%{transform:translate3d(85px,0,0)}}

.left-glass{position:relative;z-index:5;background:rgba(255,255,255,0.10);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.22);border-radius:20px;padding:22px}
.logo-box{position:relative;display:inline-flex;align-items:center;gap:11px;padding:9px 15px;border-radius:15px;background:rgba(255,255,255,0.96);box-shadow:0 10px 24px rgba(0,0,0,0.2);overflow:hidden;margin-bottom:16px}
.logo-box::after{content:"";position:absolute;top:0;left:0;width:50%;height:100%;background:linear-gradient(to right,transparent,rgba(255,255,255,0.82),transparent);transform:translateX(-160%) skewX(-24deg);animation:logoShine 5.5s infinite ease-in-out}
@keyframes logoShine{0%{transform:translateX(-160%) skewX(-24deg)}100%{transform:translateX(260%) skewX(-24deg)}}
.logo-box img{width:42px;height:42px;object-fit:contain;display:block}
.logo-box strong{display:block;font-family:"Outfit",sans-serif;font-size:0.95rem;font-weight:800;color:#1D3B5E;line-height:1.1}
.logo-box small{font-size:0.66rem;font-weight:700;color:#0D7A87;letter-spacing:0.08em;text-transform:uppercase}

.status-pill{display:inline-flex;align-items:center;gap:8px;padding:6px 13px;border-radius:999px;background:rgba(245,158,11,0.22);border:1px solid rgba(245,158,11,0.55);font-size:0.72rem;font-weight:800;letter-spacing:0.09em;text-transform:uppercase;color:#FEF3C7;margin-bottom:14px}
.status-pill .dot{width:8px;height:8px;border-radius:50%;background:#F59E0B;box-shadow:0 0 10px #F59E0B;animation:pulseDot 1.8s ease-in-out infinite}
@keyframes pulseDot{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(0.75);opacity:0.45}}
.left-h1{font-family:"Outfit",sans-serif;font-size:1.75rem;font-weight:900;line-height:1.15;margin-bottom:10px}
.left-h1 span{background:linear-gradient(to right,#FFF 0%,#52B2DC 55%,#F59E0B 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.domain-chip{display:inline-flex;align-items:center;gap:7px;padding:7px 12px;border-radius:11px;background:rgba(15,29,47,0.4);border:1px solid rgba(255,255,255,0.2);font-family:"JetBrains Mono",monospace;font-size:0.76rem;color:#E0F2FE;margin-top:8px}

.maint-right{background:rgba(255,255,255,0.95);backdrop-filter:blur(16px);padding:38px 34px;display:flex;flex-direction:column;justify-content:center;position:relative}
.right-kicker{font-size:0.74rem;font-weight:800;text-transform:uppercase;letter-spacing:0.09em;color:#0D7A87;margin-bottom:6px;display:flex;align-items:center;gap:6px}
.right-title{font-family:"Outfit",sans-serif;font-size:1.55rem;font-weight:800;color:#0F1D2F;line-height:1.2;margin-bottom:10px}
.right-msg{font-size:0.9rem;color:#475569;line-height:1.65;margin-bottom:22px}

.prog-card{background:#F8FAFC;border:1px solid #E2E8F0;border-radius:16px;padding:15px 16px;margin-bottom:18px}
.prog-head{display:flex;justify-content:space-between;align-items:center;font-size:0.78rem;font-weight:700;color:#334155;margin-bottom:8px}
.prog-bar{height:11px;border-radius:999px;background:#E2E8F0;overflow:hidden}
.prog-fill{height:100%;width:' . $progress . '%;border-radius:999px;background:linear-gradient(90deg,#1D3B5E,#0D7A87,#52B2DC);position:relative;overflow:hidden}
.prog-fill::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.55),transparent);animation:shimmer 2.4s infinite}
@keyframes shimmer{0%{transform:translateX(-100%)}100%{transform:translateX(100%)}}

.countdown-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:20px}
.cd-box{background:#FFFFFF;border:1.5px solid #E2E8F0;border-radius:14px;padding:10px 6px;text-align:center;box-shadow:0 4px 12px -6px rgba(29,59,94,0.12)}
.cd-num{font-family:"Outfit",sans-serif;font-size:1.35rem;font-weight:900;color:#1D3B5E;line-height:1.1}
.cd-lbl{font-size:0.66rem;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:0.06em;margin-top:3px}

.action-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}
.btn-primary{flex:1;min-height:46px;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 16px;border-radius:13px;border:none;background:linear-gradient(135deg,#1D3B5E 0%,#0D7A87 60%,#52B2DC 100%);color:#FFF;font-family:"Outfit",sans-serif;font-weight:700;font-size:0.88rem;cursor:pointer;position:relative;overflow:hidden;box-shadow:0 10px 24px -8px rgba(13,122,135,0.5);text-decoration:none;transition:transform 0.2s}
.btn-primary:hover{transform:translateY(-2px)}
.btn-primary::after{content:"";position:absolute;top:0;left:-100%;width:50%;height:100%;background:linear-gradient(to right,transparent,rgba(255,255,255,0.5),transparent);transform:skewX(-20deg);animation:sweep 3.2s infinite}
@keyframes sweep{0%{left:-100%}22%{left:210%}100%{left:210%}}
.btn-ghost{min-height:46px;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:10px 15px;border-radius:13px;border:1.5px solid #E2E8F0;background:#FFF;color:#1D3B5E;font-family:"Outfit",sans-serif;font-weight:700;font-size:0.85rem;text-decoration:none;transition:border-color 0.2s,transform 0.2s}
.btn-ghost:hover{border-color:#0D7A87;transform:translateY(-1px)}

.foot-note{font-size:0.74rem;color:#64748B;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;border-top:1px solid #EEF2F6;padding-top:14px}
@media(max-width:820px){
  .maint-shell{grid-template-columns:1fr;border-radius:24px}
  .maint-left{padding:24px 20px 58px}
  .maint-right{padding:24px 20px}
  .left-h1{font-size:1.38rem}
  .right-title{font-size:1.3rem}
}
</style>
</head>
<body>
<canvas id="maintCanvas"></canvas>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<main class="maint-shell">
  <section class="maint-left">
    <div class="pixel-bg"></div>
    ' . $gununganSvg . '
    <div class="wave-wrap">
      <svg viewBox="0 24 150 28" preserveAspectRatio="none">
        <defs><path id="w" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z"/></defs>
        <g class="parallax">
          <use href="#w" x="48" y="0" fill="rgba(255,255,255,0.28)"/>
          <use href="#w" x="48" y="3" fill="rgba(255,255,255,0.18)"/>
          <use href="#w" x="48" y="6" fill="rgba(255,255,255,0.36)"/>
        </g>
      </svg>
    </div>
    <div class="left-glass">
      <div class="logo-box">
        <img src="' . $e($logoUrl) . '" alt="Guardian Dome">
        <div><strong>Guardian Dome</strong><small>Ilmuna Digital Ecosystem</small></div>
      </div>
      <div class="status-pill"><span class="dot"></span><span>Pemeliharaan Terjadwal · HTTP 503</span></div>
      <h1 class="left-h1">Kami Sedang<br><span>Meningkatkan Layanan</span></h1>
      <div class="domain-chip"><iconify-icon icon="solar:global-bold" width="15" style="color:#F59E0B"></iconify-icon><span>' . $e($domain) . '</span></div>
    </div>
  </section>

  <section class="maint-right">
    <div class="right-kicker"><iconify-icon icon="solar:shield-star-bold" width="16"></iconify-icon><span>' . $e($label) . '</span></div>
    <h2 class="right-title">' . $e($title) . '</h2>
    <p class="right-msg">' . nl2br($e($message)) . '</p>

    <div class="prog-card">
      <div class="prog-head"><span>Progres Pemeliharaan</span><span style="color:#0D7A87;font-family:\'JetBrains Mono\',monospace">' . $progress . '% · Estimasi: ' . $e($etaHuman) . '</span></div>
      <div class="prog-bar"><div class="prog-fill"></div></div>
    </div>

    ' . ($etaIso !== '' ? '<div class="countdown-grid" id="cdBox" data-eta="' . $e($etaIso) . '">
      <div class="cd-box"><div class="cd-num" id="cdD">00</div><div class="cd-lbl">Hari</div></div>
      <div class="cd-box"><div class="cd-num" id="cdH">00</div><div class="cd-lbl">Jam</div></div>
      <div class="cd-box"><div class="cd-num" id="cdM">00</div><div class="cd-lbl">Menit</div></div>
      <div class="cd-box"><div class="cd-num" id="cdS">00</div><div class="cd-lbl">Detik</div></div>
    </div>' : '') . '

    <div class="action-row">
      <button type="button" class="btn-primary" onclick="window.location.reload()">
        <iconify-icon icon="solar:refresh-bold" width="18"></iconify-icon><span>Periksa Status Sekarang</span>
      </button>
      ' . ($wa !== '' ? '<a class="btn-ghost" href="https://wa.me/' . $e($wa) . '" target="_blank" rel="noopener"><iconify-icon icon="solar:chat-round-dots-bold" width="17" style="color:#10B981"></iconify-icon><span>Hubungi Admin</span></a>' : '') . '
    </div>

    <div class="foot-note">
      <span><iconify-icon icon="solar:shield-check-bold" style="color:#0D7A87;vertical-align:-2px"></iconify-icon> Dilindungi Guardian Dome · Data Anda 100% Aman</span>
      <span style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <span>' . $e($email) . '</span>
        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;background:rgba(29,59,94,0.08);color:#1D3B5E;font-weight:700;font-size:0.7rem;"><iconify-icon icon="solar:lock-keyhole-bold" width="12"></iconify-icon> Akses Khusus Admin</span>
      </span>
    </div>
  </section>
</main>

<script>
(function(){
  var c=document.getElementById("maintCanvas");
  if(c&&c.getContext){
    var x=c.getContext("2d"),cols=["#1D3B5E","#0D7A87","#52B2DC","#F59E0B","#10B981"],pts=[];
    function rs(){c.width=innerWidth;c.height=innerHeight}rs();addEventListener("resize",rs);
    for(var i=0;i<36;i++)pts.push({x:Math.random()*c.width,y:Math.random()*c.height,vx:(Math.random()-.5)*.45,vy:(Math.random()-.5)*.45,r:Math.random()*2.5+1,c:cols[i%cols.length],o:Math.random()*.4+.2});
    (function loop(){x.clearRect(0,0,c.width,c.height);for(var a=0;a<pts.length;a++){var p=pts[a];p.x+=p.vx;p.y+=p.vy;if(p.x<0||p.x>c.width)p.vx*=-1;if(p.y<0||p.y>c.height)p.vy*=-1;x.beginPath();x.arc(p.x,p.y,p.r,0,Math.PI*2);x.fillStyle=p.c;x.globalAlpha=p.o;x.fill();for(var b=a+1;b<pts.length;b++){var q=pts[b],d=Math.hypot(p.x-q.x,p.y-q.y);if(d<105){x.beginPath();x.moveTo(p.x,p.y);x.lineTo(q.x,q.y);x.strokeStyle=p.c;x.globalAlpha=(1-d/105)*.16;x.stroke()}}}x.globalAlpha=1;requestAnimationFrame(loop)})();
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
