<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — UNIFIED SEC-OPS & FULL ECOSYSTEM COMMAND DASHBOARD
 * ==============================================================================
 * Filosofi:
 * - Data-First (Zero Hero/Welcome Banner -> langsung Segmented Tabs + Counter)
 * - Zero Page Reload (Lazy Load On-Demand + In-Place DOM + .row-flash/.row-smooth-out)
 * - 100% Lumina Gold & Nusantara · Iconify Solar Icons · window.Ilmuna · Mobile 360px+
 * ==============================================================================
 */
require_once __DIR__ . '/auth.php';
requireAdminAuth();

$validTabs = ['waf', 'firewall', 'incidents', 'maintenance', 'server', 'errors', 'visitors', 'fim', 'settings'];
$activeTab = isset($_GET['tab']) ? strtolower(trim((string)$_GET['tab'])) : 'waf';
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'waf';
}

$pageTitle = 'Pusat Komando SEC-OPS';
$pageIcon  = 'solar:shield-star-bold';

require_once BASE_PATH . '/includes/header.php';
?>

<!-- DATA-FIRST SEGMENTED TABS (TANPA HERO BANNER) -->
<div class="curriculum-main-tabs" role="tablist" aria-label="Modul Guardian Control">
    <button type="button" class="curriculum-tab-btn <?= $activeTab === 'waf' ? 'is-active' : '' ?>" data-gc-tab="waf">
        <iconify-icon icon="solar:shield-star-bold" width="17"></iconify-icon>
        <span>WAF &amp; Policy</span>
        <span class="curriculum-tab-counter" id="cntTabWaf">11.0</span>
    </button>
    <button type="button" class="curriculum-tab-btn <?= $activeTab === 'firewall' ? 'is-active' : '' ?>" data-gc-tab="firewall">
        <iconify-icon icon="solar:forbidden-circle-bold" width="17"></iconify-icon>
        <span>Firewall IP</span>
        <span class="curriculum-tab-counter" id="cntTabBans">0</span>
    </button>
    <button type="button" class="curriculum-tab-btn <?= $activeTab === 'incidents' ? 'is-active' : '' ?>" data-gc-tab="incidents">
        <iconify-icon icon="solar:danger-triangle-bold" width="17"></iconify-icon>
        <span>Insiden</span>
        <span class="curriculum-tab-counter" id="cntTabIncidents">0</span>
    </button>
    <button type="button" class="curriculum-tab-btn <?= $activeTab === 'maintenance' ? 'is-active' : '' ?>" data-gc-tab="maintenance">
        <iconify-icon icon="solar:tuning-square-2-bold" width="17"></iconify-icon>
        <span>Maintenance Web</span>
        <span class="curriculum-tab-counter" id="cntTabMaint">0</span>
    </button>
    <button type="button" class="curriculum-tab-btn <?= $activeTab === 'server' ? 'is-active' : '' ?>" data-gc-tab="server">
        <iconify-icon icon="solar:server-square-bold" width="17"></iconify-icon>
        <span>Server &amp; Uptime</span>
        <span class="curriculum-tab-counter" id="cntTabServer">--</span>
    </button>
    <button type="button" class="curriculum-tab-btn <?= $activeTab === 'errors' ? 'is-active' : '' ?>" data-gc-tab="errors">
        <iconify-icon icon="solar:bug-bold" width="17"></iconify-icon>
        <span>Error Log</span>
        <span class="curriculum-tab-counter" id="cntTabErrors">0</span>
    </button>
    <button type="button" class="curriculum-tab-btn <?= $activeTab === 'visitors' ? 'is-active' : '' ?>" data-gc-tab="visitors">
        <iconify-icon icon="solar:users-group-two-rounded-bold" width="17"></iconify-icon>
        <span>1-Device &amp; AI/SEO</span>
        <span class="curriculum-tab-counter" id="cntTabVisitors">0</span>
    </button>
    <button type="button" class="curriculum-tab-btn <?= $activeTab === 'fim' ? 'is-active' : '' ?>" data-gc-tab="fim">
        <iconify-icon icon="solar:file-check-bold" width="17"></iconify-icon>
        <span>FIM &amp; Webshell</span>
        <span class="curriculum-tab-counter" id="cntTabFim">0</span>
    </button>
    <button type="button" class="curriculum-tab-btn <?= $activeTab === 'settings' ? 'is-active' : '' ?>" data-gc-tab="settings">
        <iconify-icon icon="solar:settings-bold" width="17"></iconify-icon>
        <span>Sentinel &amp; Audit</span>
    </button>
</div>

<!-- ========================================================================== -->
<!-- TAB 1: WAF COMMAND CENTER & ATOMIC POLICY SWITCHBOARD                      -->
<!-- ========================================================================== -->
<section id="panel-waf" class="gc-tab-panel <?= $activeTab === 'waf' ? 'is-active' : '' ?>">
    <div class="gc-kpi-grid">
        <div class="gc-kpi-card">
            <div>
                <div class="gc-kpi-label">Engine WAF Aktif</div>
                <div class="gc-kpi-val" id="wafPatchCode">New-Patch.11.0</div>
                <div class="gc-kpi-sub" id="wafPolicyStatus">Memuat status bridge...</div>
            </div>
            <div class="gc-kpi-icon navy"><iconify-icon icon="solar:shield-check-bold"></iconify-icon></div>
        </div>
        <div class="gc-kpi-card">
            <div>
                <div class="gc-kpi-label">IP Terblokir Aktif</div>
                <div class="gc-kpi-val" id="wafKpiBans">0</div>
                <div class="gc-kpi-sub">Live banned_ips.json + DB</div>
            </div>
            <div class="gc-kpi-icon rose"><iconify-icon icon="solar:forbidden-circle-bold"></iconify-icon></div>
        </div>
        <div class="gc-kpi-card">
            <div>
                <div class="gc-kpi-label">Unique Device Hari Ini</div>
                <div class="gc-kpi-val" id="wafKpiVisitors">0</div>
                <div class="gc-kpi-sub"><span id="wafKpiOnline">0</span> online (5 mnt terakhir)</div>
            </div>
            <div class="gc-kpi-icon teal"><iconify-icon icon="solar:users-group-two-rounded-bold"></iconify-icon></div>
        </div>
        <div class="gc-kpi-card">
            <div>
                <div class="gc-kpi-label">Insiden 24 Jam</div>
                <div class="gc-kpi-val" id="wafKpiIncidents">0</div>
                <div class="gc-kpi-sub" id="wafKpiHealth">RAM --% · CPU --</div>
            </div>
            <div class="gc-kpi-icon gold"><iconify-icon icon="solar:danger-triangle-bold"></iconify-icon></div>
        </div>
    </div>

    <div class="gc-card">
        <div class="gc-card-head">
            <h2 class="gc-card-title"><iconify-icon icon="solar:tuning-2-bold"></iconify-icon> Saklar Kebijakan Real-Time (Atomic Bridge: gc-runtime-rules.json)</h2>
            <div class="gc-toolbar" style="margin:0;">
                <button type="button" class="gc-btn gc-btn-ghost gc-btn-sm" onclick="GC.flushRdnsCache()">
                    <iconify-icon icon="solar:refresh-circle-bold" width="16"></iconify-icon> Flush FCrDNS
                </button>
                <button type="button" class="gc-btn gc-btn-danger gc-btn-sm" onclick="GC.emergencyUnbanAll()">
                    <iconify-icon icon="solar:shield-cross-bold" width="16"></iconify-icon> Emergency Unban Semua
                </button>
            </div>
        </div>

        <div class="gc-switch-grid" id="wafSwitchboard">
            <div class="gc-switch-item is-danger-mode">
                <div>
                    <div class="gc-switch-title">Under-Attack Mode (Darurat DDoS)</div>
                    <div class="gc-switch-desc">Memaksa Pre-Flight Scan &amp; pengetatan rate-limit seketika di seluruh *.ilmuna.my.id.</div>
                </div>
                <label class="gc-toggle is-danger"><input type="checkbox" id="sw_under_attack_mode" onchange="GC.saveWafPolicy()"><span class="gc-toggle-slider"></span></label>
            </div>
            <div class="gc-switch-item">
                <div>
                    <div class="gc-switch-title">Pre-Flight Scan Screen</div>
                    <div class="gc-switch-desc">Layar verifikasi browser interaktif untuk pengunjung baru non-crawler.</div>
                </div>
                <label class="gc-toggle"><input type="checkbox" id="sw_enable_preflight_scan" onchange="GC.saveWafPolicy()"><span class="gc-toggle-slider"></span></label>
            </div>
            <div class="gc-switch-item">
                <div>
                    <div class="gc-switch-title">Guardian Shield v3 (Invisible Human Score)</div>
                    <div class="gc-switch-desc">Skor perilaku manusia otonom tanpa puzzle CAPTCHA.</div>
                </div>
                <label class="gc-toggle"><input type="checkbox" id="sw_enable_shield_v3" onchange="GC.saveWafPolicy()"><span class="gc-toggle-slider"></span></label>
            </div>
            <div class="gc-switch-item">
                <div>
                    <div class="gc-switch-title">Anti-AI Scraper &amp; LLM Blocker</div>
                    <div class="gc-switch-desc">Blokir GPTBot, ClaudeBot, PerplexityBot, Bytespider, Google-Extended, dll.</div>
                </div>
                <label class="gc-toggle"><input type="checkbox" id="sw_enable_ai_scraper_block" onchange="GC.saveWafPolicy()"><span class="gc-toggle-slider"></span></label>
            </div>
            <div class="gc-switch-item">
                <div>
                    <div class="gc-switch-title">Invisible Form Honeypot (_gd_hp_contact)</div>
                    <div class="gc-switch-desc">Menangkap bot pengisi form otomatis dengan 0% false positive manusia.</div>
                </div>
                <label class="gc-toggle"><input type="checkbox" id="sw_enable_honeypot_form" onchange="GC.saveWafPolicy()"><span class="gc-toggle-slider"></span></label>
            </div>
            <div class="gc-switch-item">
                <div>
                    <div class="gc-switch-title">SEO &amp; AdSense FCrDNS Pass</div>
                    <div class="gc-switch-desc">Izinkan Googlebot, Site Kit, AdSense, Bingbot setelah verifikasi Reverse-DNS.</div>
                </div>
                <label class="gc-toggle"><input type="checkbox" id="sw_enable_seo_adsense_pass" onchange="GC.saveWafPolicy()"><span class="gc-toggle-slider"></span></label>
            </div>
        </div>

        <div class="gc-grid-2" style="margin-top:16px;margin-bottom:0;">
            <div class="gc-switch-item" style="flex-direction:column;align-items:stretch;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="gc-switch-title">Ambang Skor Minimum Shield v3</span>
                    <span class="gc-badge gc-badge-info gc-mono" id="valShieldScore">0.35</span>
                </div>
                <input type="range" id="rng_shield_v3_min_score" min="0.20" max="0.70" step="0.05" value="0.35" oninput="document.getElementById('valShieldScore').textContent=parseFloat(this.value).toFixed(2)" onchange="GC.saveWafPolicy()">
            </div>
            <div class="gc-switch-item" style="gap:10px;flex-wrap:wrap;">
                <div style="flex:1;min-width:120px;">
                    <label class="gc-switch-desc" style="display:block;font-weight:700;">Rate Burst (2 dtk)</label>
                    <input type="number" id="inp_rate_limit_burst" class="gc-input" style="width:100%;" min="3" max="200" value="15">
                </div>
                <div style="flex:1;min-width:120px;">
                    <label class="gc-switch-desc" style="display:block;font-weight:700;">Rate Normal (10 dtk)</label>
                    <input type="number" id="inp_rate_limit_normal" class="gc-input" style="width:100%;" min="10" max="500" value="35">
                </div>
                <button type="button" class="gc-btn gc-btn-primary" onclick="GC.saveWafPolicy()" style="align-self:flex-end;">
                    <iconify-icon icon="solar:diskette-bold" width="16"></iconify-icon> Terapkan
                </button>
            </div>
        </div>
    </div>
</section>

<!-- ========================================================================== -->
<!-- TAB 2: IP FIREWALL, SUBNET /24 & WHITELIST MANAGER                         -->
<!-- ========================================================================== -->
<section id="panel-firewall" class="gc-tab-panel <?= $activeTab === 'firewall' ? 'is-active' : '' ?>">
    <div class="gc-card">
        <div class="gc-card-head">
            <h2 class="gc-card-title"><iconify-icon icon="solar:forbidden-circle-bold"></iconify-icon> Daftar IP &amp; Subnet /24 Terblokir (Live WAF + DB)</h2>
            <div class="gc-toolbar" style="margin:0;">
                <input type="search" id="fwSearchInput" class="gc-input" placeholder="Cari IP, Subnet, Ray-ID, Alasan..." oninput="GC.loadFirewall()">
                <button type="button" class="gc-btn gc-btn-danger" onclick="GC.openBanModal()">
                    <iconify-icon icon="solar:shield-cross-bold" width="17"></iconify-icon> Ban IP / CIDR
                </button>
                <button type="button" class="gc-btn gc-btn-ghost gc-btn-sm" onclick="GC.bulkUnban()">Bulk Unban</button>
                <button type="button" class="gc-btn gc-btn-gold gc-btn-sm" onclick="GC.bulkPermanent()">Promote Permanen</button>
            </div>
        </div>
        <div class="ilm-data-container">
            <table class="lms-table ilmuna-table-stack" id="fwBlacklistTable">
                <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="fwCheckAll" onchange="GC.toggleAllFwChecks(this.checked)"></th>
                        <th>IP / CIDR</th>
                        <th>Ray-ID</th>
                        <th>Alasan Blokir</th>
                        <th>Kedaluwarsa</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="fwBlacklistBody">
                    <tr><td colspan="6" style="text-align:center;padding:24px;">Memuat daftar firewall...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="gc-card">
        <div class="gc-card-head">
            <h2 class="gc-card-title"><iconify-icon icon="solar:verified-check-bold"></iconify-icon> Whitelist IP Admin &amp; Sekolah (Bebas False-Positive)</h2>
            <div class="gc-toolbar" style="margin:0;">
                <button type="button" class="gc-btn gc-btn-teal gc-btn-sm" onclick="GC.whitelistMyIp()">
                    <iconify-icon icon="solar:user-check-bold" width="16"></iconify-icon> Tambahkan IP Saya (<span id="fwMyIp">...</span>)
                </button>
                <button type="button" class="gc-btn gc-btn-primary gc-btn-sm" onclick="GC.openWhitelistModal()">
                    <iconify-icon icon="solar:add-circle-bold" width="16"></iconify-icon> Tambah Whitelist
                </button>
            </div>
        </div>
        <div class="ilm-data-container">
            <table class="lms-table ilmuna-table-stack">
                <thead>
                    <tr>
                        <th>IP / CIDR</th>
                        <th>Label / Keterangan</th>
                        <th>Ditambahkan Oleh</th>
                        <th>Waktu</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="fwWhitelistBody">
                    <tr><td colspan="5" style="text-align:center;padding:20px;">Memuat whitelist...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ========================================================================== -->
<!-- TAB 3: SECURITY INCIDENT & ATTACK LOG EXPLORER                             -->
<!-- ========================================================================== -->
<section id="panel-incidents" class="gc-tab-panel <?= $activeTab === 'incidents' ? 'is-active' : '' ?>">
    <div class="gc-card">
        <div class="gc-card-head">
            <h2 class="gc-card-title"><iconify-icon icon="solar:danger-triangle-bold"></iconify-icon> Security Incident &amp; Attack Log Explorer</h2>
            <div class="gc-toolbar" style="margin:0;">
                <a id="incExportCsv" href="<?= gc_e(BASE_URL . '/api/incidents.php?export=csv') ?>" class="gc-btn gc-btn-ghost gc-btn-sm">
                    <iconify-icon icon="solar:download-minimalistic-bold" width="16"></iconify-icon> Export CSV
                </a>
                <a id="incExportJson" href="<?= gc_e(BASE_URL . '/api/incidents.php?export=json') ?>" class="gc-btn gc-btn-ghost gc-btn-sm">
                    <iconify-icon icon="solar:code-file-bold" width="16"></iconify-icon> Export JSON
                </a>
            </div>
        </div>

        <div class="gc-toolbar">
            <select id="incCatFilter" class="gc-select" onchange="GC.loadIncidents()">
                <option value="all">Semua Kategori</option>
                <option value="sqli_xss">SQLi / XSS / LFI</option>
                <option value="rce">RCE / Webshell</option>
                <option value="ai_scraper">Anti-AI Scraper</option>
                <option value="shield_hp">Shield v3 / Honeypot</option>
                <option value="oob_ssrf">OOB / SSRF / JWT / CSRF</option>
                <option value="bruteforce">Brute-Force / Stuffing</option>
                <option value="fake_bot">DNS / Fake Bot / IP Spoof</option>
                <option value="flood">Rate Limit / Flood</option>
            </select>
            <select id="incDomFilter" class="gc-select" onchange="GC.loadIncidents()">
                <option value="all">Semua Subdomain</option>
            </select>
            <input type="search" id="incSearchInput" class="gc-input" placeholder="Cari IP, Ray-ID, URI..." onkeydown="if(event.key==='Enter')GC.loadIncidents()">
            <input type="date" id="incDateFrom" class="gc-input" onchange="GC.loadIncidents()">
            <input type="date" id="incDateTo" class="gc-input" onchange="GC.loadIncidents()">
            <button type="button" class="gc-btn gc-btn-primary gc-btn-sm" onclick="GC.loadIncidents()">Filter</button>
        </div>

        <div class="ilm-data-container">
            <table class="lms-table ilmuna-table-stack">
                <thead>
                    <tr>
                        <th>Waktu &amp; Ray-ID</th>
                        <th>Subdomain &amp; IP</th>
                        <th>Kategori &amp; Severity</th>
                        <th>Target URI / Alasan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="incidentsBody">
                    <tr><td colspan="5" style="text-align:center;padding:24px;">Memuat log insiden...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ========================================================================== -->
<!-- TAB 3.5: PER-WEBSITE & GLOBAL MAINTENANCE CONTROL CENTER (1-TEMA 503)      -->
<!-- ========================================================================== -->
<section id="panel-maintenance" class="gc-tab-panel <?= $activeTab === 'maintenance' ? 'is-active' : '' ?>">
    <div class="gc-card gc-maint-command">
        <div class="gc-card-head">
            <div>
                <h2 class="gc-card-title">
                    <iconify-icon icon="solar:tuning-square-2-bold"></iconify-icon>
                    Kontrol Mode Maintenance Ekosistem (*.ilmuna.my.id)
                </h2>
                <div class="gc-maint-admin-banner" id="maintAdminPassBanner">
                    <span class="dot"></span>
                    <span id="maintAdminPassText">Sesi Admin aktif di <strong>.ilmuna.my.id</strong> — Anda tetap bisa mengakses &amp; mengerjakan website saat Maintenance, sedangkan selain Admin otomatis melihat halaman 503.</span>
                </div>
            </div>
            <div class="gc-toolbar" style="margin:0;">
                <button type="button" class="gc-btn gc-btn-ghost gc-btn-sm" onclick="GC.openMaintPreview('__global__')">
                    <iconify-icon icon="solar:eye-bold" width="16"></iconify-icon> Preview Global
                </button>
                <button type="button" class="gc-btn gc-btn-gold gc-btn-sm" onclick="GC.openMaintGlobalModal()">
                    <iconify-icon icon="solar:settings-minimalistic-bold" width="16"></iconify-icon> Pengaturan Global
                </button>
                <button type="button" class="gc-btn gc-btn-primary gc-btn-sm" onclick="GC.openAddMaintSiteModal()">
                    <iconify-icon icon="solar:add-circle-bold" width="16"></iconify-icon> Tambah Subdomain
                </button>
            </div>
        </div>

        <div class="gc-maint-master-grid">
            <div class="gc-switch-item gc-maint-global-box" id="maintGlobalBox">
                <div>
                    <div class="gc-switch-title" style="display:flex;align-items:center;gap:7px;">
                        <iconify-icon icon="solar:danger-circle-bold" width="18" style="color:#F59E0B"></iconify-icon>
                        <span>Global Maintenance (Semua Website)</span>
                    </div>
                    <div class="gc-switch-desc">Aktifkan mode 503 serentak di seluruh <code>*.ilmuna.my.id</code>. Panel <code>guardian-dome.ilmuna.my.id</code> selalu kebal terkunci.</div>
                </div>
                <label class="gc-toggle is-danger">
                    <input type="checkbox" id="sw_maint_global_enabled" onchange="GC.toggleGlobalMaintenance(this.checked)">
                    <span class="gc-toggle-slider"></span>
                </label>
            </div>

            <div class="gc-switch-item">
                <div>
                    <div class="gc-switch-title" style="display:flex;align-items:center;gap:7px;">
                        <iconify-icon icon="solar:shield-user-bold" width="18" style="color:#0D7A87"></iconify-icon>
                        <span>Bypass Otomatis Khusus Admin</span>
                    </div>
                    <div class="gc-switch-desc">Admin yang login di Guardian Control atau memakai Kunci Bypass dapat membuka situs secara penuh; selain Admin masuk Mode 503.</div>
                </div>
                <label class="gc-toggle">
                    <input type="checkbox" id="sw_maint_allow_admin" checked onchange="GC.quickSaveMaintAccess()">
                    <span class="gc-toggle-slider"></span>
                </label>
            </div>

            <div class="gc-switch-item">
                <div style="flex:1;min-width:0;">
                    <div class="gc-switch-title" style="display:flex;align-items:center;gap:7px;">
                        <iconify-icon icon="solar:key-minimalistic-square-bold" width="18" style="color:#1D3B5E"></iconify-icon>
                        <span>Kunci Bypass Perangkat Lain</span>
                    </div>
                    <div class="gc-switch-desc" style="margin-bottom:6px;">Untuk membuka web dari HP/browser lain tanpa login Guardian Control terlebih dahulu.</div>
                    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                        <code class="gc-badge gc-badge-navy gc-mono" id="maintBypassKeyBadge">?gd_maint_bypass=ilmuna2026</code>
                        <button type="button" class="gc-btn gc-btn-ghost gc-btn-sm" onclick="GC.copyBypassParam()" style="min-height:30px;padding:3px 9px;font-size:0.74rem;">
                            <iconify-icon icon="solar:copy-bold" width="14"></iconify-icon> Salin
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="gc-maint-grid" id="maintSitesGrid">
        <div class="gc-card" style="text-align:center;padding:32px;color:var(--gc-muted);">Memuat daftar website ekosistem...</div>
    </div>
</section>

<!-- ========================================================================== -->
<!-- TAB 4: DEEP SERVER HEALTH, MYSQL, UPTIME, SSL & SECURITY HEADERS           -->
<!-- ========================================================================== -->
<section id="panel-server" class="gc-tab-panel <?= $activeTab === 'server' ? 'is-active' : '' ?>">
    <div class="gc-kpi-grid">
        <div class="gc-kpi-card">
            <div style="width:100%;">
                <div class="gc-kpi-label">RAM &amp; Swap (<span id="srvRamSrc">--</span>)</div>
                <div class="gc-kpi-val" id="srvRamVal">--%</div>
                <div class="gc-meter-bar"><div class="gc-meter-fill" id="srvRamBar" style="width:0%"></div></div>
                <div class="gc-kpi-sub" id="srvRamSub">PHP Peak: -- MB</div>
            </div>
        </div>
        <div class="gc-kpi-card">
            <div style="width:100%;">
                <div class="gc-kpi-label">CPU Load (1m / 5m / 15m)</div>
                <div class="gc-kpi-val" id="srvCpuVal">--</div>
                <div class="gc-meter-bar"><div class="gc-meter-fill" id="srvCpuBar" style="width:0%"></div></div>
                <div class="gc-kpi-sub" id="srvCpuSub">-- Core · Queue: --</div>
            </div>
        </div>
        <div class="gc-kpi-card">
            <div style="width:100%;">
                <div class="gc-kpi-label">Disk Storage</div>
                <div class="gc-kpi-val" id="srvDiskVal">--%</div>
                <div class="gc-meter-bar"><div class="gc-meter-fill" id="srvDiskBar" style="width:0%"></div></div>
                <div class="gc-kpi-sub" id="srvDiskSub">-- / -- GB</div>
            </div>
        </div>
        <div class="gc-kpi-card">
            <div style="width:100%;">
                <div class="gc-kpi-label">OPcache &amp; MySQL</div>
                <div class="gc-kpi-val" id="srvOpVal">--%</div>
                <div class="gc-kpi-sub" id="srvMysqlSub">Threads: -- · Slow: --</div>
                <div class="gc-kpi-sub" id="srvCronSub">Cron: --</div>
            </div>
        </div>
    </div>

    <div class="gc-card">
        <div class="gc-card-head">
            <h2 class="gc-card-title"><iconify-icon icon="solar:graph-up-bold"></iconify-icon> Tren Historis Server 24 Jam (RAM % vs Disk % vs CPU)</h2>
            <div class="gc-toolbar" style="margin:0;">
                <button type="button" class="gc-btn gc-btn-ghost gc-btn-sm" onclick="GC.recordServerSnapshot()">
                    <iconify-icon icon="solar:camera-bold" width="16"></iconify-icon> Rekam Snapshot
                </button>
                <button type="button" class="gc-btn gc-btn-primary gc-btn-sm" onclick="GC.runUptimeCheckNow()">
                    <iconify-icon icon="solar:bolt-bold" width="16"></iconify-icon> Uji Uptime &amp; SSL Sekarang
                </button>
            </div>
        </div>
        <div class="gc-canvas-wrap"><canvas id="srvHistoryCanvas"></canvas></div>
    </div>

    <div class="gc-card">
        <div class="gc-card-head">
            <h2 class="gc-card-title"><iconify-icon icon="solar:global-bold"></iconify-icon> Uptime, TTFB, SSL Expiry &amp; Audit HTTP Security Headers (*.ilmuna.my.id)</h2>
        </div>
        <div class="ilm-data-container">
            <table class="lms-table ilmuna-table-stack">
                <thead>
                    <tr>
                        <th>Target Ekosistem</th>
                        <th>Status &amp; HTTP</th>
                        <th>TTFB / Total</th>
                        <th>SSL Issuer &amp; Sisa Hari</th>
                        <th>Security Headers (HSTS/CSP/XFO/XCTO/RP)</th>
                    </tr>
                </thead>
                <tbody id="srvUptimeBody">
                    <tr><td colspan="5" style="text-align:center;padding:20px;">Klik "Uji Uptime &amp; SSL Sekarang" atau tunggu cron...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="gc-grid-2">
        <div class="gc-card">
            <div class="gc-card-head"><h2 class="gc-card-title"><iconify-icon icon="solar:database-bold"></iconify-icon> MySQL Per-Database &amp; Fragmentasi</h2></div>
            <div class="ilm-data-container">
                <table class="lms-table ilmuna-table-stack">
                    <thead><tr><th>Database</th><th>Tabel</th><th>Baris</th><th>Ukuran</th><th>Overhead</th></tr></thead>
                    <tbody id="srvDbBody"><tr><td colspan="5">Memuat...</td></tr></tbody>
                </table>
            </div>
        </div>
        <div class="gc-card">
            <div class="gc-card-head"><h2 class="gc-card-title"><iconify-icon icon="solar:shield-warning-bold"></iconify-icon> Audit php.ini Security Flags &amp; Folder Disk</h2></div>
            <div id="srvIniAuditList" style="display:flex;flex-direction:column;gap:8px;"></div>
            <div id="srvGiantLogsBox" style="margin-top:12px;"></div>
        </div>
    </div>

    <div class="gc-card">
        <div class="gc-card-head"><h2 class="gc-card-title"><iconify-icon icon="solar:programming-bold"></iconify-icon> MySQL Live Query Inspector (SHOW PROCESSLIST)</h2></div>
        <div class="ilm-data-container">
            <table class="lms-table ilmuna-table-stack">
                <thead><tr><th>ID</th><th>User / DB</th><th>Command</th><th>Durasi (dtk)</th><th>State / Query</th></tr></thead>
                <tbody id="srvProcBody"><tr><td colspan="5">Memuat...</td></tr></tbody>
            </table>
        </div>
    </div>
</section>

<!-- ========================================================================== -->
<!-- TAB 5: CENTRALIZED ERROR LOG & CRASH MONITOR                               -->
<!-- ========================================================================== -->
<section id="panel-errors" class="gc-tab-panel <?= $activeTab === 'errors' ? 'is-active' : '' ?>">
    <div class="gc-card">
        <div class="gc-card-head">
            <h2 class="gc-card-title"><iconify-icon icon="solar:bug-bold"></iconify-icon> Centralized Error Log Drill-Down (Lintas Aplikasi)</h2>
            <div class="gc-toolbar" style="margin:0;">
                <select id="errStatusFilter" class="gc-select" onchange="GC.loadErrorLogs()">
                    <option value="active" selected>Belum Selesai (Aktif)</option>
                    <option value="resolved">Sudah Selesai</option>
                    <option value="all">Semua Status</option>
                </select>
                <select id="errLevelFilter" class="gc-select" onchange="GC.loadErrorLogs()">
                    <option value="all">Semua Level</option>
                    <option value="fatal">FATAL / PARSE</option>
                    <option value="exception">EXCEPTION</option>
                    <option value="sql">MYSQL / PDO</option>
                    <option value="warning">WARNING</option>
                    <option value="notice">NOTICE / DEPRECATED</option>
                </select>
                <button type="button" class="gc-btn gc-btn-ghost gc-btn-sm" id="btnErrDownload" onclick="GC.downloadActiveErrorLog()">
                    <iconify-icon icon="solar:download-minimalistic-bold" width="16"></iconify-icon> Unduh Log
                </button>
                <button type="button" class="gc-btn gc-btn-danger gc-btn-sm" id="btnErrTruncate" onclick="GC.truncateActiveErrorLog()">
                    <iconify-icon icon="solar:trash-bin-trash-bold" width="16"></iconify-icon> Arsip &amp; Bersihkan
                </button>
            </div>
        </div>

        <div class="gc-pill-group" id="errSourcePills" style="margin-bottom:12px;">
            <button type="button" class="gc-pill-btn is-active" data-err-src="all" onclick="GC.selectErrorSource('all')">Semua Aplikasi</button>
        </div>

        <div class="gc-toolbar" id="errBulkToolbar" style="padding:10px 12px;border-radius:14px;background:rgba(29,59,94,0.04);border:1px solid var(--gc-border);margin-bottom:14px;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <span class="gc-badge gc-badge-navy" id="errSelectedBadge">0 dipilih</span>
                <span class="gc-badge gc-badge-crit" id="errCountActive">Aktif: 0</span>
                <span class="gc-badge gc-badge-ok" id="errCountResolved">Selesai: 0</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <button type="button" class="gc-btn gc-btn-teal gc-btn-sm" onclick="GC.bulkErrorAction('bulk_resolve')">
                    <iconify-icon icon="solar:check-read-bold" width="15"></iconify-icon> Selesai Terpilih
                </button>
                <button type="button" class="gc-btn gc-btn-ghost gc-btn-sm" onclick="GC.bulkErrorAction('bulk_reopen')">
                    <iconify-icon icon="solar:restart-bold" width="15"></iconify-icon> Buka Kembali
                </button>
                <button type="button" class="gc-btn gc-btn-danger gc-btn-sm" onclick="GC.bulkErrorAction('bulk_delete')">
                    <iconify-icon icon="solar:trash-bin-2-bold" width="15"></iconify-icon> Hapus Terpilih
                </button>
                <button type="button" class="gc-btn gc-btn-primary gc-btn-sm" onclick="GC.resolveAllErrors()">
                    <iconify-icon icon="solar:verified-check-bold" width="15"></iconify-icon> Tandai Semua Selesai
                </button>
            </div>
        </div>

        <div class="ilm-data-container">
            <table class="lms-table ilmuna-table-stack">
                <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="errCheckAll" onchange="GC.toggleAllErrChecks(this.checked)"></th>
                        <th>Sumber &amp; Level</th>
                        <th>Pesan Error, File &amp; Nomor Baris</th>
                        <th>Frekuensi</th>
                        <th>Terakhir Terjadi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="errorLogsBody">
                    <tr><td colspan="6" style="text-align:center;padding:24px;">Memuat error log...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ========================================================================== -->
<!-- TAB 6: UNIQUE DEVICE VISITORS (1 DEVICE = 1) & AI/SEO TRAFFIC INSPECTOR    -->
<!-- ========================================================================== -->
<section id="panel-visitors" class="gc-tab-panel <?= $activeTab === 'visitors' ? 'is-active' : '' ?>">
    <div class="gc-kpi-grid">
        <div class="gc-kpi-card">
            <div>
                <div class="gc-kpi-label">Unique Device Hari Ini</div>
                <div class="gc-kpi-val" id="visTodayUniq">0</div>
                <div class="gc-kpi-sub"><span id="visTodayHits">0</span> total pageviews</div>
            </div>
            <div class="gc-kpi-icon teal"><iconify-icon icon="solar:smartphone-2-bold"></iconify-icon></div>
        </div>
        <div class="gc-kpi-card">
            <div>
                <div class="gc-kpi-label">Online Aktif (5 Menit)</div>
                <div class="gc-kpi-val" id="visOnline5m">0</div>
                <div class="gc-kpi-sub">100% bebas bot/crawler</div>
            </div>
            <div class="gc-kpi-icon emerald"><iconify-icon icon="solar:pulse-2-bold"></iconify-icon></div>
        </div>
        <div class="gc-kpi-card">
            <div>
                <div class="gc-kpi-label">Unique Device 7 / 30 Hari</div>
                <div class="gc-kpi-val"><span id="vis7dUniq">0</span> / <span id="vis30dUniq">0</span></div>
                <div class="gc-kpi-sub">Deduplikasi cookie gd_dev_id</div>
            </div>
            <div class="gc-kpi-icon navy"><iconify-icon icon="solar:users-group-rounded-bold"></iconify-icon></div>
        </div>
        <div class="gc-kpi-card">
            <div>
                <div class="gc-kpi-label">AI Scraper Diblokir vs SEO</div>
                <div class="gc-kpi-val"><span id="botAiCnt">0</span> / <span id="botSeoCnt">0</span></div>
                <div class="gc-kpi-sub"><span id="botFakeCnt">0</span> fake bot ditolak</div>
            </div>
            <div class="gc-kpi-icon gold"><iconify-icon icon="solar:shield-warning-bold"></iconify-icon></div>
        </div>
    </div>

    <div class="gc-card">
        <div class="gc-card-head">
            <h2 class="gc-card-title"><iconify-icon icon="solar:chart-2-bold"></iconify-icon> Tren Pengunjung Unik (1 Device = 1) vs Pageviews</h2>
            <div class="gc-pill-group">
                <button type="button" class="gc-pill-btn" data-vis-range="1d" onclick="GC.setVisitorRange('1d')">Hari Ini</button>
                <button type="button" class="gc-pill-btn is-active" data-vis-range="7d" onclick="GC.setVisitorRange('7d')">7 Hari</button>
                <button type="button" class="gc-pill-btn" data-vis-range="30d" onclick="GC.setVisitorRange('30d')">30 Hari</button>
            </div>
        </div>
        <div class="gc-canvas-wrap"><canvas id="visTrendCanvas"></canvas></div>
    </div>

    <div class="gc-grid-2">
        <div class="gc-card">
            <div class="gc-card-head"><h2 class="gc-card-title"><iconify-icon icon="solar:global-bold"></iconify-icon> Rincian Per Subdomain (*.ilmuna.my.id Auto-Discover)</h2></div>
            <div class="ilm-data-container">
                <table class="lms-table ilmuna-table-stack">
                    <thead><tr><th>Subdomain</th><th>Unique Devices</th><th>Pageviews</th><th>Online 5m</th></tr></thead>
                    <tbody id="visSubdomainBody"><tr><td colspan="4">Memuat...</td></tr></tbody>
                </table>
            </div>
        </div>
        <div class="gc-card">
            <div class="gc-card-head"><h2 class="gc-card-title"><iconify-icon icon="solar:devices-bold"></iconify-icon> OS, Perangkat, Browser &amp; Halaman Terpopuler</h2></div>
            <div id="visBreakdownBox" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;"></div>
        </div>
    </div>

    <div class="gc-grid-2">
        <div class="gc-card">
            <div class="gc-card-head"><h2 class="gc-card-title"><iconify-icon icon="solar:robot-bold"></iconify-icon> AI / LLM Scrapers Diblokir (30 Hari)</h2></div>
            <div id="botAiList" class="ilm-data-container" style="padding:12px;">Memuat...</div>
        </div>
        <div class="gc-card">
            <div class="gc-card-head"><h2 class="gc-card-title"><iconify-icon icon="solar:verified-check-bold"></iconify-icon> Verified SEO / AdSense Crawlers (FCrDNS Cache)</h2></div>
            <div class="ilm-data-container">
                <table class="lms-table ilmuna-table-stack">
                    <thead><tr><th>IP</th><th>PTR Hostname</th><th>Status</th><th>Kedaluwarsa</th></tr></thead>
                    <tbody id="botSeoBody"><tr><td colspan="4">Memuat...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- ========================================================================== -->
<!-- TAB 7: FILE INTEGRITY MONITOR (FIM SHA-256), HARDENING & WEBSHELL SCANNER  -->
<!-- ========================================================================== -->
<section id="panel-fim" class="gc-tab-panel <?= $activeTab === 'fim' ? 'is-active' : '' ?>">
    <div class="gc-card">
        <div class="gc-card-head">
            <h2 class="gc-card-title"><iconify-icon icon="solar:file-check-bold"></iconify-icon> File Integrity Monitoring (Baseline SHA-256)</h2>
            <div class="gc-toolbar" style="margin:0;">
                <button type="button" class="gc-btn gc-btn-primary gc-btn-sm" onclick="GC.runFimScan()">
                    <iconify-icon icon="solar:scanner-bold" width="16"></iconify-icon> Pindai Sekarang
                </button>
                <button type="button" class="gc-btn gc-btn-gold gc-btn-sm" onclick="GC.resetFimBaseline()">
                    <iconify-icon icon="solar:refresh-bold" width="16"></iconify-icon> Perbarui Baseline SHA-256
                </button>
            </div>
        </div>
        <div class="ilm-data-container">
            <table class="lms-table ilmuna-table-stack">
                <thead>
                    <tr>
                        <th>Aplikasi</th>
                        <th>Berkas Kritis</th>
                        <th>Hash SHA-256 Aktif</th>
                        <th>Status</th>
                        <th>Cek Terakhir</th>
                    </tr>
                </thead>
                <tbody id="fimTableBody">
                    <tr><td colspan="5" style="text-align:center;padding:24px;">Memuat baseline FIM...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="gc-grid-2">
        <div class="gc-card">
            <div class="gc-card-head"><h2 class="gc-card-title"><iconify-icon icon="solar:lock-password-bold"></iconify-icon> Audit Hak Akses (0777) &amp; Eksposur File Sensitif</h2></div>
            <div id="fimHardeningBox">Memuat hasil audit...</div>
        </div>
        <div class="gc-card">
            <div class="gc-card-head"><h2 class="gc-card-title"><iconify-icon icon="solar:shield-warning-bold"></iconify-icon> Deep Webshell &amp; Polyglot Scanner (uploads/)</h2></div>
            <div id="fimUploadsBox">Memuat hasil scan uploads...</div>
        </div>
    </div>
</section>

<!-- ========================================================================== -->
<!-- TAB 8: TELEGRAM SENTINEL, CACHE PURGER & ADMIN AUDIT LOG                   -->
<!-- ========================================================================== -->
<section id="panel-settings" class="gc-tab-panel <?= $activeTab === 'settings' ? 'is-active' : '' ?>">
    <div class="gc-grid-2">
        <div class="gc-card">
            <div class="gc-card-head">
                <h2 class="gc-card-title"><iconify-icon icon="solar:plain-bold"></iconify-icon> Telegram Sentinel Control</h2>
                <button type="button" class="gc-btn gc-btn-teal gc-btn-sm" onclick="GC.testTelegram()">
                    <iconify-icon icon="solar:bell-bing-bold" width="16"></iconify-icon> Kirim Uji Alert
                </button>
            </div>
            <div id="sentinelStatusBox" style="margin-bottom:14px;">Memuat status Telegram Sentinel...</div>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <label class="gc-switch-desc" style="font-weight:700;">Level Notifikasi Alert</label>
                <select id="sentAlertLevel" class="gc-select">
                    <option value="critical_only">Critical Only (Disarankan)</option>
                    <option value="all_blocks">Semua Blokir WAF</option>
                </select>
                <label style="display:flex;align-items:center;gap:8px;font-size:0.86rem;font-weight:600;">
                    <input type="checkbox" id="sentNotifyFim"> Kirim alert jika FIM mendeteksi perubahan file
                </label>
                <label style="display:flex;align-items:center;gap:8px;font-size:0.86rem;font-weight:600;">
                    <input type="checkbox" id="sentNotifyDown"> Kirim alert jika subdomain down atau SSL hampir habis
                </label>
                <div style="display:flex;align-items:center;gap:10px;">
                    <label style="font-size:0.84rem;font-weight:600;">Ambang Peringatan SSL (hari):</label>
                    <input type="number" id="sentSslDays" class="gc-input" style="width:90px;" min="3" max="60" value="14">
                </div>
                <button type="button" class="gc-btn gc-btn-primary" onclick="GC.saveSentinelPrefs()">Simpan Preferensi Sentinel</button>
            </div>
        </div>

        <div class="gc-card">
            <div class="gc-card-head">
                <h2 class="gc-card-title"><iconify-icon icon="solar:trash-bin-minimalistic-bold"></iconify-icon> Guardian Dome Cache Purger (Rotasi 7 Hari)</h2>
            </div>
            <div id="cacheStatsBox" style="margin-bottom:16px;">Memuat statistik cache...</div>
            <div class="gc-toolbar" style="margin:0;">
                <button type="button" class="gc-btn gc-btn-primary" onclick="GC.purgeCache(false)">
                    <iconify-icon icon="solar:broom-bold" width="17"></iconify-icon> Purge Cache &gt; 7 Hari
                </button>
                <button type="button" class="gc-btn gc-btn-danger" onclick="GC.purgeCache(true)">
                    <iconify-icon icon="solar:fire-bold" width="17"></iconify-icon> Purge Semua Cache Sementara
                </button>
            </div>
        </div>
    </div>

    <div class="gc-card">
        <div class="gc-card-head">
            <h2 class="gc-card-title"><iconify-icon icon="solar:history-bold"></iconify-icon> Audit Trail Tindakan Admin SEC-OPS</h2>
        </div>
        <div class="ilm-data-container">
            <table class="lms-table ilmuna-table-stack">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Admin</th>
                        <th>Aksi</th>
                        <th>Target</th>
                        <th>IP Admin</th>
                    </tr>
                </thead>
                <tbody id="auditLogBody">
                    <tr><td colspan="5" style="text-align:center;padding:20px;">Memuat audit log...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ========================================================================== -->
<!-- UNIVERSAL NUSANTARA MODALS (BAN IP, WHITELIST, DETAIL INSIDEN/ERROR)       -->
<!-- ========================================================================== -->
<div class="ilm-modal-backdrop" id="gcBanModal">
    <div class="ilm-modal-dialog" style="max-width:500px;">
        <div class="ilm-modal-header">
            <div class="ilm-modal-header__brand">
                <div class="ilm-modal-header__icon"><iconify-icon icon="solar:forbidden-circle-bold"></iconify-icon></div>
                <div>
                    <h3 class="ilm-modal-header__title">Ban IP / Subnet /24</h3>
                    <p class="ilm-modal-header__subtitle">Blokir langsung di Guardian Dome WAF &amp; DB</p>
                </div>
            </div>
            <button type="button" class="ilm-modal-header__close" onclick="GC.closeModal('gcBanModal')"><iconify-icon icon="solar:close-circle-bold"></iconify-icon></button>
        </div>
        <div class="ilm-modal-body">
            <div style="display:flex;flex-direction:column;gap:12px;">
                <div>
                    <label style="display:block;font-size:0.82rem;font-weight:700;margin-bottom:5px;">Alamat IP atau CIDR /24</label>
                    <input type="text" id="banIpInput" class="gc-input" style="width:100%;" placeholder="Contoh: 45.132.227.129 atau 45.132.227.0/24">
                </div>
                <div>
                    <label style="display:block;font-size:0.82rem;font-weight:700;margin-bottom:5px;">Alasan Pemblokiran</label>
                    <input type="text" id="banReasonInput" class="gc-input" style="width:100%;" placeholder="SQLi / Brute-force / Scraper agresif">
                </div>
                <div>
                    <label style="display:block;font-size:0.82rem;font-weight:700;margin-bottom:5px;">Durasi Blokir</label>
                    <select id="banDurationInput" class="gc-select" style="width:100%;">
                        <option value="permanent">Permanen</option>
                        <option value="1h">1 Jam</option>
                        <option value="24h">24 Jam</option>
                        <option value="7d">7 Hari</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="ilm-modal-footer">
            <button type="button" class="ilm-modal-btn ilm-modal-btn--ghost" onclick="GC.closeModal('gcBanModal')">Batal</button>
            <button type="button" class="ilm-modal-btn ilm-modal-btn--danger" onclick="GC.submitBanIp()">Blokir Sekarang</button>
        </div>
    </div>
</div>

<div class="ilm-modal-backdrop" id="gcWhitelistModal">
    <div class="ilm-modal-dialog" style="max-width:480px;">
        <div class="ilm-modal-header">
            <div class="ilm-modal-header__brand">
                <div class="ilm-modal-header__icon"><iconify-icon icon="solar:verified-check-bold"></iconify-icon></div>
                <div>
                    <h3 class="ilm-modal-header__title">Tambah IP Whitelist</h3>
                    <p class="ilm-modal-header__subtitle">IP bebas false-positive di seluruh *.ilmuna.my.id</p>
                </div>
            </div>
            <button type="button" class="ilm-modal-header__close" onclick="GC.closeModal('gcWhitelistModal')"><iconify-icon icon="solar:close-circle-bold"></iconify-icon></button>
        </div>
        <div class="ilm-modal-body">
            <div style="display:flex;flex-direction:column;gap:12px;">
                <div>
                    <label style="display:block;font-size:0.82rem;font-weight:700;margin-bottom:5px;">Alamat IP</label>
                    <input type="text" id="wlIpInput" class="gc-input" style="width:100%;" placeholder="Contoh: 103.105.78.163">
                </div>
                <div>
                    <label style="display:block;font-size:0.82rem;font-weight:700;margin-bottom:5px;">Label / Keterangan</label>
                    <input type="text" id="wlLabelInput" class="gc-input" style="width:100%;" placeholder="IP Wi-Fi Sekolah / Admin Rumah">
                </div>
            </div>
        </div>
        <div class="ilm-modal-footer">
            <button type="button" class="ilm-modal-btn ilm-modal-btn--ghost" onclick="GC.closeModal('gcWhitelistModal')">Batal</button>
            <button type="button" class="ilm-modal-btn ilm-modal-btn--primary" onclick="GC.submitWhitelist()">Simpan Whitelist</button>
        </div>
    </div>
</div>

<div class="ilm-modal-backdrop" id="gcDetailModal">
    <div class="ilm-modal-dialog" style="max-width:660px;">
        <div class="ilm-modal-header">
            <div class="ilm-modal-header__brand">
                <div class="ilm-modal-header__icon"><iconify-icon icon="solar:document-text-bold"></iconify-icon></div>
                <div>
                    <h3 class="ilm-modal-header__title" id="detailModalTitle">Detail</h3>
                    <p class="ilm-modal-header__subtitle" id="detailModalSub">Inspeksi payload &amp; metadata</p>
                </div>
            </div>
            <button type="button" class="ilm-modal-header__close" onclick="GC.closeModal('gcDetailModal')"><iconify-icon icon="solar:close-circle-bold"></iconify-icon></button>
        </div>
        <div class="ilm-modal-body" id="detailModalBody"></div>
        <div class="ilm-modal-footer" id="detailModalFooter">
            <button type="button" class="ilm-modal-btn ilm-modal-btn--ghost" onclick="GC.closeModal('gcDetailModal')">Tutup</button>
        </div>
    </div>
</div>

<!-- MODAL EDIT / TAMBAH KONFIGURASI MAINTENANCE WEBSITE -->
<div class="ilm-modal-backdrop" id="gcMaintSiteModal">
    <div class="ilm-modal-dialog" style="max-width:580px;">
        <div class="ilm-modal-header">
            <div class="ilm-modal-header__brand">
                <div class="ilm-modal-header__icon"><iconify-icon icon="solar:tuning-square-2-bold"></iconify-icon></div>
                <div>
                    <h3 class="ilm-modal-header__title" id="maintSiteModalTitle">Atur Halaman Maintenance</h3>
                    <p class="ilm-modal-header__subtitle" id="maintSiteModalSub">Kustomisasi pesan, jadwal ETA, &amp; progres 1-Tema</p>
                </div>
            </div>
            <button type="button" class="ilm-modal-header__close" onclick="GC.closeModal('gcMaintSiteModal')"><iconify-icon icon="solar:close-circle-bold"></iconify-icon></button>
        </div>
        <div class="ilm-modal-body">
            <div style="display:grid;gap:12px;">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px;">
                    <div>
                        <label style="display:block;font-size:0.8rem;font-weight:700;margin-bottom:4px;">Domain (*.ilmuna.my.id)</label>
                        <input type="text" id="msHost" class="gc-input gc-mono" style="width:100%;" placeholder="contoh: ppdb.ilmuna.my.id">
                    </div>
                    <div>
                        <label style="display:block;font-size:0.8rem;font-weight:700;margin-bottom:4px;">Nama / Label Layanan</label>
                        <input type="text" id="msLabel" class="gc-input" style="width:100%;" placeholder="Portal PPDB Ilmuna">
                    </div>
                </div>
                <div>
                    <label style="display:block;font-size:0.8rem;font-weight:700;margin-bottom:4px;">Judul Halaman Maintenance</label>
                    <input type="text" id="msTitle" class="gc-input" style="width:100%;" maxlength="140" placeholder="Sedang Pemeliharaan Sistem">
                </div>
                <div>
                    <label style="display:block;font-size:0.8rem;font-weight:700;margin-bottom:4px;">Pesan untuk Pengunjung</label>
                    <textarea id="msMessage" class="gc-input" style="width:100%;min-height:84px;resize:vertical;padding-top:10px;" maxlength="600"></textarea>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px;">
                    <div>
                        <label style="display:block;font-size:0.8rem;font-weight:700;margin-bottom:4px;">Estimasi Selesai (ETA · Opsional)</label>
                        <input type="datetime-local" id="msEta" class="gc-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block;font-size:0.8rem;font-weight:700;margin-bottom:4px;">Progres (<span id="msProgVal">65</span>%)</label>
                        <input type="range" id="msProgress" min="5" max="100" step="5" value="65" style="width:100%;margin-top:12px;" oninput="document.getElementById('msProgVal').textContent=this.value">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px;">
                    <div>
                        <label style="display:block;font-size:0.8rem;font-weight:700;margin-bottom:4px;">HTTP Retry-After (detik)</label>
                        <input type="number" id="msRetryAfter" class="gc-input" style="width:100%;" min="60" max="86400" value="1800">
                    </div>
                    <div>
                        <label style="display:block;font-size:0.8rem;font-weight:700;margin-bottom:4px;">Email Kontak Layanan</label>
                        <input type="email" id="msEmail" class="gc-input" style="width:100%;" value="admin@ilmuna.my.id">
                    </div>
                </div>
                <label style="display:flex;align-items:center;gap:8px;font-size:0.83rem;font-weight:600;cursor:pointer;">
                    <input type="checkbox" id="msAutoEnd" checked>
                    <span>Otomatis selesai &amp; buka akses publik saat waktu ETA tercapai</span>
                </label>
            </div>
        </div>
        <div class="ilm-modal-footer">
            <button type="button" class="ilm-modal-btn ilm-modal-btn--ghost" onclick="GC.closeModal('gcMaintSiteModal')">Batal</button>
            <button type="button" class="ilm-modal-btn ilm-modal-btn--primary" onclick="GC.submitMaintSiteConfig()">Simpan Konfigurasi</button>
        </div>
    </div>
</div>

<!-- MODAL PENGATURAN GLOBAL MAINTENANCE & AKSES ADMIN -->
<div class="ilm-modal-backdrop" id="gcMaintGlobalModal">
    <div class="ilm-modal-dialog" style="max-width:560px;">
        <div class="ilm-modal-header">
            <div class="ilm-modal-header__brand">
                <div class="ilm-modal-header__icon"><iconify-icon icon="solar:shield-keyhole-bold"></iconify-icon></div>
                <div>
                    <h3 class="ilm-modal-header__title">Pengaturan Global &amp; Akses Admin</h3>
                    <p class="ilm-modal-header__subtitle">Pesan global, kunci bypass, &amp; kontak WhatsApp</p>
                </div>
            </div>
            <button type="button" class="ilm-modal-header__close" onclick="GC.closeModal('gcMaintGlobalModal')"><iconify-icon icon="solar:close-circle-bold"></iconify-icon></button>
        </div>
        <div class="ilm-modal-body">
            <div style="display:grid;gap:12px;">
                <div>
                    <label style="display:block;font-size:0.8rem;font-weight:700;margin-bottom:4px;">Judul Global Maintenance</label>
                    <input type="text" id="mgTitle" class="gc-input" style="width:100%;" maxlength="140">
                </div>
                <div>
                    <label style="display:block;font-size:0.8rem;font-weight:700;margin-bottom:4px;">Pesan Global untuk Seluruh Ekosistem</label>
                    <textarea id="mgMessage" class="gc-input" style="width:100%;min-height:80px;resize:vertical;padding-top:10px;" maxlength="600"></textarea>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px;">
                    <div>
                        <label style="display:block;font-size:0.8rem;font-weight:700;margin-bottom:4px;">ETA Global (Opsional)</label>
                        <input type="datetime-local" id="mgEta" class="gc-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block;font-size:0.8rem;font-weight:700;margin-bottom:4px;">Progres Global (<span id="mgProgVal">70</span>%)</label>
                        <input type="range" id="mgProgress" min="5" max="100" step="5" value="70" style="width:100%;margin-top:12px;" oninput="document.getElementById('mgProgVal').textContent=this.value">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px;">
                    <div>
                        <label style="display:block;font-size:0.8rem;font-weight:700;margin-bottom:4px;">Kunci Bypass Admin</label>
                        <input type="text" id="mgBypassKey" class="gc-input gc-mono" style="width:100%;" placeholder="ilmuna2026">
                    </div>
                    <div>
                        <label style="display:block;font-size:0.8rem;font-weight:700;margin-bottom:4px;">Nomor WhatsApp Admin (628...)</label>
                        <input type="text" id="mgWhatsapp" class="gc-input gc-mono" style="width:100%;" placeholder="6285117100227">
                    </div>
                </div>
                <label style="display:flex;align-items:center;gap:8px;font-size:0.83rem;font-weight:600;cursor:pointer;">
                    <input type="checkbox" id="mgAllowAdmin" checked>
                    <span>Izinkan Admin (Sesi Guardian Control &amp; Kunci Bypass) mengakses web saat maintenance</span>
                </label>
                <label style="display:flex;align-items:center;gap:8px;font-size:0.83rem;font-weight:600;cursor:pointer;">
                    <input type="checkbox" id="mgAllowIpWl">
                    <span>Izinkan juga IP Whitelist Firewall mengakses tanpa login (matikan jika ingin tes 503 di Incognito)</span>
                </label>
            </div>
        </div>
        <div class="ilm-modal-footer">
            <button type="button" class="ilm-modal-btn ilm-modal-btn--ghost" onclick="GC.closeModal('gcMaintGlobalModal')">Batal</button>
            <button type="button" class="ilm-modal-btn ilm-modal-btn--primary" onclick="GC.submitMaintGlobalOpts()">Simpan Pengaturan</button>
        </div>
    </div>
</div>

<!-- MODAL LIVE PREVIEW 1-TEMA HALAMAN MAINTENANCE (FULL-SCREEN / MOBILE) -->
<div class="ilm-modal-backdrop" id="gcMaintPreviewModal">
    <div class="ilm-modal-dialog" style="width:96vw;max-width:1440px;">
        <div class="ilm-modal-header">
            <div class="ilm-modal-header__brand">
                <div class="ilm-modal-header__icon"><iconify-icon icon="solar:eye-bold"></iconify-icon></div>
                <div>
                    <h3 class="ilm-modal-header__title" id="maintPreviewTitle">Live Preview Full-Screen 1-Tema</h3>
                    <p class="ilm-modal-header__subtitle" id="maintPreviewSub">Tampilan layar penuh yang dilihat pengunjung non-admin (HTTP 503)</p>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
                <button type="button" class="gc-pill-btn is-active" data-prev-vp="desktop" onclick="GC.setMaintPreviewViewport('desktop')">Desktop Full</button>
                <button type="button" class="gc-pill-btn" data-prev-vp="mobile" onclick="GC.setMaintPreviewViewport('mobile')">Mobile 375px</button>
                <button type="button" class="ilm-modal-header__close" onclick="GC.closeModal('gcMaintPreviewModal')"><iconify-icon icon="solar:close-circle-bold"></iconify-icon></button>
            </div>
        </div>
        <div class="ilm-modal-body" style="padding:0;background:#0B192C;">
            <div class="gc-preview-stage" id="maintPreviewStage">
                <iframe id="maintPreviewIframe" class="gc-preview-iframe" title="Preview Halaman Maintenance 1-Tema"></iframe>
            </div>
        </div>
        <div class="ilm-modal-footer">
            <a id="maintPreviewNewTab" href="#" target="_blank" rel="noopener" class="ilm-modal-btn ilm-modal-btn--ghost" style="text-decoration:none;">Buka Layar Penuh di Tab Baru ↗</a>
            <button type="button" class="ilm-modal-btn ilm-modal-btn--primary" onclick="GC.closeModal('gcMaintPreviewModal')">Tutup Preview</button>
        </div>
    </div>
</div>

<script>window.GC_INITIAL_TAB = <?= json_encode($activeTab) ?>;</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
