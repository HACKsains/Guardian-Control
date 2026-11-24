/**
 * ==============================================================================
 * GUARDIAN CONTROL — ZERO-PAGE-RELOAD SEC-OPS CONTROLLER (VANILLA ES6+)
 * ==============================================================================
 * - Eksklusif window.Ilmuna untuk seluruh toast & dialog konfirmasi (ZERO alert/confirm!)
 * - Lazy Load On-Demand + tombol Segarkan + visibilitychange (anti-polling boros)
 * - Grafik ringan HTML5 <canvas> native (tanpa library chart eksternal berat)
 * - Mutasi in-place DOM dengan animasi .row-flash / .row-smooth-out
 * ==============================================================================
 */
(function () {
    'use strict';

    var BASE = (window.ILMUNA_BASE_URL || '').replace(/\/$/, '');
    var CSRF = window.GC_CSRF_TOKEN || '';

    var state = {
        activeTab: window.GC_INITIAL_TAB || 'waf',
        loadedTabs: {},
        errSource: 'all',
        visRange: '7d',
        myIp: '',
        incidentsCache: [],
        errorsCache: []
    };

    function esc(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function toast(type, title, msg) {
        var t = (type === 'danger') ? 'error' : (type || 'info');
        if (window.Ilmuna && typeof window.Ilmuna.toast === 'function') {
            window.Ilmuna.toast(t, String(title || ''), String(msg || ''));
        }
    }

    function askConfirm(opts, onYes) {
        var t = (opts.type === 'danger') ? 'error' : (opts.type || 'warning');
        if (window.Ilmuna && typeof window.Ilmuna.confirm === 'function') {
            window.Ilmuna.confirm(
                t,
                opts.title || 'Konfirmasi Tindakan',
                opts.message || 'Apakah Anda yakin melanjutkan?',
                opts.confirmText || 'Ya, Lanjutkan',
                opts.cancelText || 'Batal'
            ).then(function (ok) {
                if (ok && typeof onYes === 'function') onYes();
            });
        } else if (typeof onYes === 'function') {
            onYes();
        }
    }

    async function apiFetch(endpoint, opts) {
        opts = opts || {};
        var method = (opts.method || 'GET').toUpperCase();
        var headers = { 'Accept': 'application/json' };
        if (method !== 'GET') {
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-Token'] = CSRF;
        }
        var res = await fetch(BASE + '/api/' + endpoint, {
            method: method,
            headers: headers,
            credentials: 'same-origin',
            body: opts.body ? JSON.stringify(opts.body) : undefined
        });
        var data = await res.json().catch(function () { return { success: false, error: 'Respon JSON tidak valid (HTTP ' + res.status + ')' }; });
        if (!res.ok || data.success === false) {
            if (res.status === 401) {
                window.location.href = BASE + '/login';
                return null;
            }
            throw new Error(data.error || ('Permintaan gagal (HTTP ' + res.status + ')'));
        }
        return data;
    }

    function openModal(id) {
        var el = document.getElementById(id);
        if (el) el.classList.add('is-open');
    }
    function closeModal(id) {
        var el = document.getElementById(id);
        if (el) el.classList.remove('is-open');
    }

    // ==========================================================================
    // OVERVIEW & TAB SWITCHING (ZERO PAGE RELOAD)
    // ==========================================================================
    async function loadOverview() {
        try {
            var d = await apiFetch('overview.php');
            if (!d) return;
            var c = d.counters || {};
            setText('cntTabBans', c.active_bans || 0);
            setText('cntTabIncidents', c.incidents_24h || 0);
            setText('cntTabErrors', c.unresolved_errors || 0);
            setText('cntTabVisitors', c.unique_today || 0);
            setText('cntTabFim', c.fim_alerts || 0);
            setText('cntTabServer', (d.health_quick ? d.health_quick.ram_pct + '%' : '--'));

            var fimEl = document.getElementById('cntTabFim');
            if (fimEl) fimEl.classList.toggle('is-danger', (c.fim_alerts || 0) > 0);
            var errEl = document.getElementById('cntTabErrors');
            if (errEl) errEl.classList.toggle('is-danger', (c.unresolved_errors || 0) > 0);

            updateSideBadge('sideBadgeBans', c.active_bans);
            updateSideBadge('sideBadgeIncidents', c.incidents_24h);
            updateSideBadge('sideBadgeErrors', c.unresolved_errors);
            updateSideBadge('sideBadgeVisitors', c.unique_today);
            updateSideBadge('sideBadgeFim', c.fim_alerts);

            // Update KPI Tab 1
            if (d.engine) {
                setText('wafPatchCode', d.engine.patch_code || 'New-Patch.11.0');
                setText('wafPolicyStatus', d.engine.policy_live ? 'Atomic Bridge Aktif (' + (d.policy.updated_at || '') + ')' : 'Menggunakan konstanta bawaan WAF');
            }
            setText('wafKpiBans', c.active_bans || 0);
            setText('wafKpiVisitors', c.unique_today || 0);
            setText('wafKpiOnline', c.online_5m || 0);
            setText('wafKpiIncidents', c.incidents_24h || 0);
            if (d.health_quick) {
                setText('wafKpiHealth', 'RAM ' + d.health_quick.ram_pct + '% · CPU ' + d.health_quick.cpu_1m + ' · Disk ' + d.health_quick.disk_pct + '%');
            }
            if (d.policy) applyPolicyToUI(d.policy);
        } catch (err) {
            toast('error', 'Gagal Memuat Ringkasan', err.message);
        }
    }

    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = String(val);
    }

    function updateSideBadge(id, num) {
        var el = document.getElementById(id);
        if (!el) return;
        num = parseInt(num, 10) || 0;
        if (num > 0) {
            el.textContent = String(num);
            el.style.display = 'inline-flex';
        } else {
            el.style.display = 'none';
        }
    }

    function switchTab(tab, forceReload) {
        state.activeTab = tab;
        document.querySelectorAll('.gc-tab-panel').forEach(function (p) {
            p.classList.toggle('is-active', p.id === 'panel-' + tab);
        });
        document.querySelectorAll('[data-gc-tab]').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-gc-tab') === tab);
        });
        if (history.replaceState) {
            history.replaceState(null, '', BASE + '/dashboard?tab=' + encodeURIComponent(tab));
        }
        // Tutup sidebar di mobile
        document.body.classList.remove('sidebar-mobile-open');

        if (forceReload || !state.loadedTabs[tab]) {
            state.loadedTabs[tab] = true;
            if (tab === 'waf') loadWafPolicy();
            else if (tab === 'firewall') loadFirewall();
            else if (tab === 'incidents') loadIncidents();
            else if (tab === 'server') loadServerTelemetry();
            else if (tab === 'errors') loadErrorLogs();
            else if (tab === 'visitors') loadVisitorsAndBots();
            else if (tab === 'fim') loadFimScanner();
            else if (tab === 'settings') loadMaintenance();
        }
    }

    // ==========================================================================
    // TAB 1: WAF POLICY SWITCHBOARD
    // ==========================================================================
    function applyPolicyToUI(p) {
        var bools = ['under_attack_mode', 'enable_preflight_scan', 'enable_shield_v3', 'enable_ai_scraper_block', 'enable_honeypot_form', 'enable_seo_adsense_pass'];
        bools.forEach(function (k) {
            var el = document.getElementById('sw_' + k);
            if (el) el.checked = !!p[k];
        });
        var rng = document.getElementById('rng_shield_v3_min_score');
        if (rng && p.shield_v3_min_score != null) {
            rng.value = p.shield_v3_min_score;
            setText('valShieldScore', parseFloat(p.shield_v3_min_score).toFixed(2));
        }
        var bEl = document.getElementById('inp_rate_limit_burst');
        if (bEl && p.rate_limit_burst != null) bEl.value = p.rate_limit_burst;
        var nEl = document.getElementById('inp_rate_limit_normal');
        if (nEl && p.rate_limit_normal != null) nEl.value = p.rate_limit_normal;
    }

    async function loadWafPolicy() {
        try {
            var d = await apiFetch('waf-policy.php');
            if (!d) return;
            applyPolicyToUI(d.policy || {});
            setText('wafKpiBans', d.banned_cnt || 0);
        } catch (err) {
            toast('error', 'Gagal Memuat Policy WAF', err.message);
        }
    }

    async function saveWafPolicy() {
        var payload = {
            action: 'update_policy',
            under_attack_mode: !!document.getElementById('sw_under_attack_mode').checked,
            enable_preflight_scan: !!document.getElementById('sw_enable_preflight_scan').checked,
            enable_shield_v3: !!document.getElementById('sw_enable_shield_v3').checked,
            enable_ai_scraper_block: !!document.getElementById('sw_enable_ai_scraper_block').checked,
            enable_honeypot_form: !!document.getElementById('sw_enable_honeypot_form').checked,
            enable_seo_adsense_pass: !!document.getElementById('sw_enable_seo_adsense_pass').checked,
            shield_v3_min_score: parseFloat(document.getElementById('rng_shield_v3_min_score').value || '0.35'),
            rate_limit_burst: parseInt(document.getElementById('inp_rate_limit_burst').value || '15', 10),
            rate_limit_normal: parseInt(document.getElementById('inp_rate_limit_normal').value || '35', 10)
        };
        try {
            var d = await apiFetch('waf-policy.php', { method: 'POST', body: payload });
            if (d) {
                toast('success', 'Policy WAF Diperbarui', d.message);
                setText('wafPolicyStatus', 'Atomic Bridge Aktif (' + (d.policy.updated_at || '') + ')');
            }
        } catch (err) {
            toast('error', 'Gagal Menyimpan Policy', err.message);
        }
    }

    function emergencyUnbanAll() {
        askConfirm({
            title: 'Emergency Unban Semua IP?',
            message: 'Tindakan ini akan membersihkan seluruh daftar banned_ips.json dan menonaktifkan semua rule blacklist aktif di database.',
            confirmText: 'Ya, Reset Semua Ban',
            type: 'danger'
        }, async function () {
            try {
                var d = await apiFetch('waf-policy.php', { method: 'POST', body: { action: 'emergency_unban_all' } });
                if (d) {
                    toast('success', 'Emergency Unban Selesai', d.message);
                    loadOverview();
                    if (state.loadedTabs.firewall) loadFirewall();
                }
            } catch (err) {
                toast('error', 'Gagal Emergency Unban', err.message);
            }
        });
    }

    function flushRdnsCache() {
        askConfirm({
            title: 'Flush Cache FCrDNS?',
            message: 'Seluruh cache verifikasi Reverse-DNS crawler (Googlebot/AdSense) akan dihapus dan diverifikasi ulang.',
            confirmText: 'Ya, Flush Cache',
            type: 'warning'
        }, async function () {
            try {
                var d = await apiFetch('waf-policy.php', { method: 'POST', body: { action: 'flush_rdns_cache' } });
                if (d) toast('success', 'Cache FCrDNS Dibersihkan', d.message);
            } catch (err) {
                toast('error', 'Gagal Flush FCrDNS', err.message);
            }
        });
    }

    // ==========================================================================
    // TAB 2: FIREWALL IP & WHITELIST
    // ==========================================================================
    async function loadFirewall() {
        var q = (document.getElementById('fwSearchInput') || {}).value || '';
        try {
            var d = await apiFetch('firewall.php?q=' + encodeURIComponent(q));
            if (!d) return;
            state.myIp = d.my_ip || '';
            setText('fwMyIp', state.myIp);

            var blBody = document.getElementById('fwBlacklistBody');
            if (!d.blacklist || d.blacklist.length === 0) {
                blBody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--gc-muted);">Tidak ada IP/CIDR terblokir aktif.</td></tr>';
            } else {
                blBody.innerHTML = d.blacklist.map(function (r) {
                    return '<tr data-fw-ip="' + esc(r.ip) + '">' +
                        '<td data-label="Pilih"><input type="checkbox" class="fw-row-check" value="' + esc(r.ip) + '"></td>' +
                        '<td data-label="IP / CIDR"><strong class="gc-mono">' + esc(r.ip) + '</strong><div style="font-size:0.74rem;color:var(--gc-muted);">Subnet: ' + esc(r.subnet) + '</div></td>' +
                        '<td data-label="Ray-ID"><span class="gc-badge gc-badge-navy gc-mono">' + esc(r.ray_id) + '</span></td>' +
                        '<td data-label="Alasan">' + esc(r.reason) + '</td>' +
                        '<td data-label="Kedaluwarsa"><span class="gc-badge ' + (r.expires_at === 'Permanen' ? 'gc-badge-crit' : 'gc-badge-warn') + '">' + esc(r.expires_at) + '</span></td>' +
                        '<td data-label="Aksi"><button type="button" class="gc-btn gc-btn-ghost gc-btn-sm" onclick="GC.unbanIp(\'' + esc(r.ip) + '\', this)"><iconify-icon icon="solar:lock-keyhole-unlocked-bold" width="15"></iconify-icon> Unban</button></td>' +
                        '</tr>';
                }).join('');
            }

            var wlBody = document.getElementById('fwWhitelistBody');
            if (!d.whitelist || d.whitelist.length === 0) {
                wlBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--gc-muted);">Belum ada IP Whitelist kustom di database.</td></tr>';
            } else {
                wlBody.innerHTML = d.whitelist.map(function (w) {
                    return '<tr data-wl-ip="' + esc(w.ip_or_cidr) + '">' +
                        '<td data-label="IP / CIDR"><strong class="gc-mono">' + esc(w.ip_or_cidr) + '</strong></td>' +
                        '<td data-label="Label">' + esc(w.reason) + '</td>' +
                        '<td data-label="Admin">' + esc(w.created_by || 'admin') + '</td>' +
                        '<td data-label="Waktu">' + esc(w.created_at) + '</td>' +
                        '<td data-label="Aksi"><button type="button" class="gc-btn gc-btn-danger gc-btn-sm" onclick="GC.removeWhitelist(\'' + esc(w.ip_or_cidr) + '\', this)">Hapus</button></td>' +
                        '</tr>';
                }).join('');
            }
        } catch (err) {
            toast('error', 'Gagal Memuat Firewall', err.message);
        }
    }

    function openBanModal(prefillIp) {
        document.getElementById('banIpInput').value = prefillIp || '';
        document.getElementById('banReasonInput').value = '';
        openModal('gcBanModal');
    }

    async function submitBanIp() {
        var ip = document.getElementById('banIpInput').value.trim();
        var reason = document.getElementById('banReasonInput').value.trim() || 'Manual Ban SEC-OPS';
        var duration = document.getElementById('banDurationInput').value;
        try {
            var d = await apiFetch('firewall.php', { method: 'POST', body: { action: 'ban', ip: ip, reason: reason, duration: duration } });
            if (d) {
                closeModal('gcBanModal');
                toast('success', 'IP Diblokir', d.message);
                await loadFirewall();
                loadOverview();
                var row = document.querySelector('tr[data-fw-ip="' + CSS.escape(ip) + '"]');
                if (row) row.classList.add('row-flash');
            }
        } catch (err) {
            toast('error', 'Gagal Ban IP', err.message);
        }
    }

    function unbanIp(ip, btn) {
        askConfirm({
            title: 'Buka Blokir IP ' + ip + '?',
            message: 'IP ini akan segera dihapus dari banned_ips.json dan aturan blacklist aktif.',
            confirmText: 'Ya, Unban',
            type: 'warning'
        }, async function () {
            try {
                var d = await apiFetch('firewall.php', { method: 'POST', body: { action: 'unban', ip: ip } });
                if (d) {
                    var tr = btn ? btn.closest('tr') : null;
                    if (tr) {
                        tr.classList.add('row-smooth-out');
                        setTimeout(function () { tr.remove(); }, 280);
                    }
                    toast('success', 'Unban Berhasil', d.message);
                    loadOverview();
                }
            } catch (err) {
                toast('error', 'Gagal Unban', err.message);
            }
        });
    }

    function getSelectedFwIps() {
        return Array.from(document.querySelectorAll('.fw-row-check:checked')).map(function (c) { return c.value; });
    }

    function bulkUnban() {
        var ips = getSelectedFwIps();
        if (!ips.length) { toast('warning', 'Pilih IP Terlebih Dahulu', 'Centang minimal 1 IP pada tabel blacklist.'); return; }
        askConfirm({ title: 'Bulk Unban ' + ips.length + ' IP?', message: 'Semua IP terpilih akan dibuka blokirnya.', confirmText: 'Ya, Unban Semua', type: 'warning' }, async function () {
            try {
                var d = await apiFetch('firewall.php', { method: 'POST', body: { action: 'bulk_unban', ips: ips } });
                if (d) { toast('success', 'Bulk Unban Selesai', d.message); loadFirewall(); loadOverview(); }
            } catch (err) { toast('error', 'Gagal Bulk Unban', err.message); }
        });
    }

    function bulkPermanent() {
        var ips = getSelectedFwIps();
        if (!ips.length) { toast('warning', 'Pilih IP Terlebih Dahulu', 'Centang minimal 1 IP untuk dipromosikan permanen.'); return; }
        askConfirm({ title: 'Promosikan Permanen?', message: ips.length + ' IP akan dijadikan blokir permanen.', confirmText: 'Ya, Permanenkan', type: 'danger' }, async function () {
            try {
                var d = await apiFetch('firewall.php', { method: 'POST', body: { action: 'bulk_permanent', ips: ips } });
                if (d) { toast('success', 'Berhasil', d.message); loadFirewall(); }
            } catch (err) { toast('error', 'Gagal', err.message); }
        });
    }

    function openWhitelistModal(prefillIp) {
        document.getElementById('wlIpInput').value = prefillIp || '';
        document.getElementById('wlLabelInput').value = '';
        openModal('gcWhitelistModal');
    }

    async function whitelistMyIp() {
        if (!state.myIp) return;
        try {
            var d = await apiFetch('firewall.php', { method: 'POST', body: { action: 'whitelist_add', ip: state.myIp, reason: 'IP Admin SEC-OPS (' + (window.GC_ADMIN_USER || 'admin') + ')' } });
            if (d) { toast('success', 'IP Anda Di-Whitelist', d.message); loadFirewall(); }
        } catch (err) { toast('error', 'Gagal Whitelist', err.message); }
    }

    async function submitWhitelist() {
        var ip = document.getElementById('wlIpInput').value.trim();
        var label = document.getElementById('wlLabelInput').value.trim() || 'IP Terpercaya';
        try {
            var d = await apiFetch('firewall.php', { method: 'POST', body: { action: 'whitelist_add', ip: ip, reason: label } });
            if (d) { closeModal('gcWhitelistModal'); toast('success', 'Whitelist Ditambahkan', d.message); loadFirewall(); }
        } catch (err) { toast('error', 'Gagal Menambah Whitelist', err.message); }
    }

    function removeWhitelist(ip, btn) {
        askConfirm({ title: 'Hapus Whitelist ' + ip + '?', message: 'IP ini akan kembali diperiksa oleh aturan WAF normal.', confirmText: 'Ya, Hapus', type: 'warning' }, async function () {
            try {
                var d = await apiFetch('firewall.php', { method: 'POST', body: { action: 'whitelist_remove', ip: ip } });
                if (d) {
                    var tr = btn ? btn.closest('tr') : null;
                    if (tr) { tr.classList.add('row-smooth-out'); setTimeout(function () { tr.remove(); }, 280); }
                    toast('success', 'Whitelist Dihapus', d.message);
                }
            } catch (err) { toast('error', 'Gagal Menghapus', err.message); }
        });
    }

    // ==========================================================================
    // TAB 3: INCIDENT EXPLORER
    // ==========================================================================
    async function loadIncidents() {
        var cat = document.getElementById('incCatFilter').value;
        var dom = document.getElementById('incDomFilter').value;
        var q   = document.getElementById('incSearchInput').value.trim();
        var df  = document.getElementById('incDateFrom').value;
        var dt  = document.getElementById('incDateTo').value;
        var qs  = 'category=' + encodeURIComponent(cat) + '&domain=' + encodeURIComponent(dom) + '&q=' + encodeURIComponent(q) + '&date_from=' + encodeURIComponent(df) + '&date_to=' + encodeURIComponent(dt);

        document.getElementById('incExportCsv').href = BASE + '/api/incidents.php?export=csv&' + qs;
        document.getElementById('incExportJson').href = BASE + '/api/incidents.php?export=json&' + qs;

        try {
            var d = await apiFetch('incidents.php?' + qs);
            if (!d) return;
            state.incidentsCache = d.incidents || [];

            var domSel = document.getElementById('incDomFilter');
            if (domSel.options.length <= 1 && d.domains && d.domains.length) {
                d.domains.forEach(function (dm) {
                    var opt = document.createElement('option');
                    opt.value = dm; opt.textContent = dm;
                    domSel.appendChild(opt);
                });
            }

            var tbody = document.getElementById('incidentsBody');
            if (!state.incidentsCache.length) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:var(--gc-muted);">Belum ada insiden yang cocok dengan filter.</td></tr>';
                return;
            }
            tbody.innerHTML = state.incidentsCache.map(function (inc, idx) {
                var sevCls = inc.severity === 'critical' ? 'gc-badge-crit' : (inc.severity === 'high' ? 'gc-badge-warn' : 'gc-badge-info');
                return '<tr>' +
                    '<td data-label="Waktu & Ray-ID"><div>' + esc(inc.occurred_at) + '</div><span class="gc-badge gc-badge-navy gc-mono">' + esc(inc.ray_id) + '</span></td>' +
                    '<td data-label="Subdomain & IP"><strong>' + esc(inc.domain) + '</strong><div class="gc-mono" style="font-size:0.78rem;">' + esc(inc.ip_address) + '</div></td>' +
                    '<td data-label="Kategori"><span class="gc-badge ' + sevCls + '">' + esc(inc.category) + ' · ' + esc(inc.severity) + '</span></td>' +
                    '<td data-label="URI / Alasan"><div class="gc-mono" style="font-size:0.76rem;">[' + esc(inc.http_method) + '] ' + esc(inc.request_uri).slice(0, 75) + '</div><div style="font-size:0.78rem;color:var(--gc-muted);">' + esc(inc.reason).slice(0, 100) + '</div></td>' +
                    '<td data-label="Aksi"><button type="button" class="gc-btn gc-btn-ghost gc-btn-sm" onclick="GC.showIncidentDetail(' + idx + ')">Detail</button></td>' +
                    '</tr>';
            }).join('');
        } catch (err) {
            toast('error', 'Gagal Memuat Insiden', err.message);
        }
    }

    function showIncidentDetail(idx) {
        var inc = state.incidentsCache[idx];
        if (!inc) return;
        setText('detailModalTitle', 'Insiden ' + inc.ray_id);
        setText('detailModalSub', inc.domain + ' · ' + inc.occurred_at);
        document.getElementById('detailModalBody').innerHTML =
            '<div style="display:grid;gap:10px;font-size:0.86rem;">' +
            '<div><strong>IP Penyerang:</strong> <code class="gc-mono">' + esc(inc.ip_address) + '</code></div>' +
            '<div><strong>Kategori / Aksi:</strong> ' + esc(inc.category) + ' (' + esc(inc.severity) + ') → ' + esc(inc.action_taken) + '</div>' +
            '<div><strong>Shield v3 Score:</strong> ' + (inc.shield_v3_score != null ? esc(inc.shield_v3_score) : 'N/A') + '</div>' +
            '<div><strong>Request:</strong> <div class="gc-mono" style="background:rgba(15,29,47,0.06);padding:8px;border-radius:8px;">' + esc(inc.http_method + ' ' + inc.request_uri) + '</div></div>' +
            '<div><strong>User-Agent:</strong> <div class="gc-mono" style="font-size:0.75rem;">' + esc(inc.user_agent || '-') + '</div></div>' +
            '<div><strong>Alasan Lengkap:</strong> <div style="padding:10px;border-radius:10px;background:#FEF2F2;color:#991B1B;">' + esc(inc.reason) + '</div></div>' +
            '</div>';
        document.getElementById('detailModalFooter').innerHTML =
            '<button type="button" class="ilm-modal-btn ilm-modal-btn--ghost" onclick="GC.closeModal(\'gcDetailModal\')">Tutup</button>' +
            '<button type="button" class="ilm-modal-btn ilm-modal-btn--primary" onclick="GC.closeModal(\'gcDetailModal\');GC.openWhitelistModal(\'' + esc(inc.ip_address) + '\')">Whitelist IP</button>' +
            '<button type="button" class="ilm-modal-btn ilm-modal-btn--danger" onclick="GC.closeModal(\'gcDetailModal\');GC.openBanModal(\'' + esc(inc.ip_address) + '\')">Ban IP</button>';
        openModal('gcDetailModal');
    }

    // ==========================================================================
    // TAB 4: SERVER TELEMETRY, MYSQL, UPTIME, SSL & CANVAS CHART
    // ==========================================================================
    function setMeter(barId, pct) {
        var el = document.getElementById(barId);
        if (!el) return;
        pct = Math.max(0, Math.min(100, parseFloat(pct) || 0));
        el.style.width = pct + '%';
        el.className = 'gc-meter-fill' + (pct >= 85 ? ' crit' : (pct >= 65 ? ' warn' : ''));
    }

    async function loadServerTelemetry() {
        try {
            var d = await apiFetch('server-telemetry.php');
            if (!d) return;

            var r = d.ram || {};
            setText('srvRamSrc', r.source || '--');
            setText('srvRamVal', (r.used_pct || 0) + '%');
            setMeter('srvRamBar', r.used_pct);
            setText('srvRamSub', (r.used_mb || 0) + ' / ' + (r.total_mb || 0) + ' MB · Swap ' + (r.swap_pct || 0) + '% · PHP Peak ' + (r.php_peak_mb || 0) + ' MB');

            var c = d.cpu || {};
            setText('srvCpuVal', (c.load_1m || 0) + ' / ' + (c.load_5m || 0) + ' / ' + (c.load_15m || 0));
            setMeter('srvCpuBar', c.saturation_pct);
            setText('srvCpuSub', (c.cores || 1) + ' Core · Saturasi ' + (c.saturation_pct || 0) + '% · Queue: ' + (c.running_procs != null ? c.running_procs + '/' + c.total_procs : 'N/A'));

            var dk = d.disk || {};
            setText('srvDiskVal', (dk.used_pct || 0) + '%');
            setMeter('srvDiskBar', dk.used_pct);
            setText('srvDiskSub', (dk.used_gb || 0) + ' / ' + (dk.total_gb || 0) + ' GB (Sisa ' + (dk.free_gb || 0) + ' GB)');

            var op = (d.php_opcache && d.php_opcache.opcache) ? d.php_opcache.opcache : {};
            var my = d.mysql || {};
            setText('srvOpVal', op.enabled ? (op.hit_rate + '%') : 'OFF');
            setText('srvMysqlSub', 'PHP ' + (d.php_opcache ? d.php_opcache.php_version : '') + ' · MySQL Threads: ' + (my.threads_connected || 0) + ' · Slow: ' + (my.slow_queries || 0));
            var cr = (d.cron && d.cron.collect_telemetry) ? d.cron.collect_telemetry : {};
            setText('srvCronSub', 'Cron Heartbeat: ' + (cr.status || 'never').toUpperCase() + (cr.last_run ? ' (' + cr.last_run + ')' : ''));

            // Render Uptime Table
            renderUptimeRows(d.uptime_latest || []);

            // Render MySQL DBs
            var dbBody = document.getElementById('srvDbBody');
            if (my.databases && my.databases.length) {
                dbBody.innerHTML = my.databases.map(function (db) {
                    return '<tr><td data-label="Database"><strong class="gc-mono">' + esc(db.db_name) + '</strong></td>' +
                        '<td data-label="Tabel">' + esc(db.table_count) + '</td>' +
                        '<td data-label="Baris">' + Number(db.total_rows || 0).toLocaleString('id-ID') + '</td>' +
                        '<td data-label="Ukuran">' + esc(db.size_mb) + ' MB</td>' +
                        '<td data-label="Overhead">' + esc(db.overhead_mb) + ' MB</td></tr>';
                }).join('');
            } else {
                dbBody.innerHTML = '<tr><td colspan="5">Tidak ada data INFORMATION_SCHEMA.</td></tr>';
            }

            // Render Processlist
            var pBody = document.getElementById('srvProcBody');
            if (my.processlist && my.processlist.length) {
                pBody.innerHTML = my.processlist.map(function (p) {
                    return '<tr><td data-label="ID">' + esc(p.id) + '</td>' +
                        '<td data-label="User/DB">' + esc(p.user) + ' / ' + esc(p.db) + '</td>' +
                        '<td data-label="Command">' + esc(p.command) + '</td>' +
                        '<td data-label="Durasi">' + esc(p.time) + 's</td>' +
                        '<td data-label="State/Query"><span class="gc-mono" style="font-size:0.74rem;">' + esc(p.info || p.state || '-') + '</span></td></tr>';
                }).join('');
            } else {
                pBody.innerHTML = '<tr><td colspan="5">Tidak ada proses aktif.</td></tr>';
            }

            // Render php.ini Security Audit + Giant Logs
            var iniBox = document.getElementById('srvIniAuditList');
            var iniList = (d.php_opcache && d.php_opcache.ini_audit) ? d.php_opcache.ini_audit : [];
            iniBox.innerHTML = iniList.map(function (it) {
                var bCls = it.status === 'ok' ? 'gc-badge-ok' : (it.status === 'critical' ? 'gc-badge-crit' : (it.status === 'warn' ? 'gc-badge-warn' : 'gc-badge-info'));
                return '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 10px;border-radius:10px;border:1px solid var(--gc-border);">' +
                    '<div><strong class="gc-mono">' + esc(it.flag) + '</strong><div style="font-size:0.74rem;color:var(--gc-muted);">' + esc(it.note) + '</div></div>' +
                    '<span class="gc-badge ' + bCls + '">' + esc(it.value) + '</span></div>';
            }).join('');

            var gBox = document.getElementById('srvGiantLogsBox');
            if (dk.giant_logs && dk.giant_logs.length) {
                gBox.innerHTML = '<div class="gc-badge gc-badge-crit" style="margin-bottom:6px;">Peringatan File Log Raksasa (&gt; 5 MB)</div>' +
                    dk.giant_logs.map(function (gl) { return '<div class="gc-mono" style="font-size:0.76rem;">• ' + esc(gl.label) + ': ' + esc(gl.human) + '</div>'; }).join('');
            } else {
                gBox.innerHTML = '<div style="font-size:0.78rem;color:#059669;font-weight:600;">✓ Tidak ada file error_log raksasa (&gt; 5 MB).</div>';
            }

            // Draw 24h History Native Canvas Chart
            drawLineChart('srvHistoryCanvas', d.history_24h || [], [
                { key: 'ram_pct', label: 'RAM %', color: '#0D7A87' },
                { key: 'disk_pct', label: 'Disk %', color: '#F59E0B' }
            ], 'label', 100);
        } catch (err) {
            toast('error', 'Gagal Memuat Telemetry Server', err.message);
        }
    }

    function renderUptimeRows(rows) {
        var tbody = document.getElementById('srvUptimeBody');
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--gc-muted);">Belum ada riwayat cek uptime. Klik "Uji Uptime &amp; SSL Sekarang".</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(function (u) {
            var upBadge = u.is_up ? '<span class="gc-badge gc-badge-ok">UP · ' + esc(u.http_code) + '</span>' : '<span class="gc-badge gc-badge-crit">DOWN · ' + esc(u.http_code) + '</span>';
            var sslTxt = u.ssl_days_left != null ? (esc(u.ssl_days_left) + ' hari' + (u.ssl_issuer ? ' (' + esc(u.ssl_issuer) + ')' : '')) : 'N/A';
            var hdrHtml = '--';
            if (u.security_headers) {
                var sh = u.security_headers;
                hdrHtml = ['hsts', 'csp', 'x_frame_options', 'x_content_type_options', 'referrer_policy'].map(function (k) {
                    var short = k === 'hsts' ? 'HSTS' : (k === 'csp' ? 'CSP' : (k === 'x_frame_options' ? 'XFO' : (k === 'x_content_type_options' ? 'XCTO' : 'RP')));
                    return '<span class="gc-badge ' + (sh[k] ? 'gc-badge-ok' : 'gc-badge-warn') + '" style="margin:2px;">' + short + '</span>';
                }).join('');
            }
            return '<tr>' +
                '<td data-label="Target"><strong>' + esc(u.target_name) + '</strong><div class="gc-mono" style="font-size:0.74rem;color:var(--gc-muted);">' + esc(u.target_url) + '</div></td>' +
                '<td data-label="Status">' + upBadge + '</td>' +
                '<td data-label="Latency">' + (u.ttfb_ms != null ? esc(u.ttfb_ms) + 'ms / ' : '') + esc(u.latency_ms) + 'ms</td>' +
                '<td data-label="SSL">' + sslTxt + '</td>' +
                '<td data-label="Security Headers">' + hdrHtml + '</td></tr>';
        }).join('');
    }

    async function runUptimeCheckNow() {
        toast('info', 'Menguji Ekosistem...', 'Memeriksa HTTP status, TTFB, SSL & Security Headers seluruh *.ilmuna.my.id.');
        try {
            var d = await apiFetch('server-telemetry.php', { method: 'POST', body: { action: 'check_uptime' } });
            if (d) {
                renderUptimeRows(d.uptime || []);
                toast('success', 'Uji Uptime Selesai', d.message);
            }
        } catch (err) { toast('error', 'Gagal Uji Uptime', err.message); }
    }

    async function recordServerSnapshot() {
        try {
            var d = await apiFetch('server-telemetry.php', { method: 'POST', body: { action: 'record_snapshot' } });
            if (d) { toast('success', 'Snapshot Direkam', d.message); loadServerTelemetry(); }
        } catch (err) { toast('error', 'Gagal Rekam Snapshot', err.message); }
    }

    // ==========================================================================
    // TAB 5: CENTRALIZED ERROR LOGS (BULK ACTION + ACTIVE/RESOLVED WORKFLOW)
    // ==========================================================================
    function getSelectedErrIds() {
        return Array.from(document.querySelectorAll('.err-row-check:checked')).map(function (c) { return parseInt(c.value, 10); }).filter(Boolean);
    }

    function updateErrSelectedCount() {
        var cnt = getSelectedErrIds().length;
        setText('errSelectedBadge', cnt + ' dipilih');
    }

    function toggleAllErrChecks(checked) {
        document.querySelectorAll('.err-row-check').forEach(function (c) { c.checked = !!checked; });
        updateErrSelectedCount();
    }

    async function loadErrorLogs() {
        var lvlEl = document.getElementById('errLevelFilter');
        var stEl  = document.getElementById('errStatusFilter');
        var lvl   = lvlEl ? lvlEl.value : 'all';
        var st    = stEl ? stEl.value : 'active';
        var chkAll = document.getElementById('errCheckAll');
        if (chkAll) chkAll.checked = false;

        try {
            var d = await apiFetch('error-logs.php?source=' + encodeURIComponent(state.errSource) + '&level=' + encodeURIComponent(lvl) + '&status=' + encodeURIComponent(st));
            if (!d) return;
            state.errorsCache = d.errors || [];

            if (d.counts) {
                setText('errCountActive', 'Aktif: ' + (d.counts.active || 0));
                setText('errCountResolved', 'Selesai: ' + (d.counts.resolved || 0));
            }
            updateErrSelectedCount();

            var pillBox = document.getElementById('errSourcePills');
            var pills = '<button type="button" class="gc-pill-btn ' + (state.errSource === 'all' ? 'is-active' : '') + '" onclick="GC.selectErrorSource(\'all\')">Semua Aplikasi</button>';
            if (d.sources) {
                Object.keys(d.sources).forEach(function (k) {
                    var s = d.sources[k];
                    pills += '<button type="button" class="gc-pill-btn ' + (state.errSource === k ? 'is-active' : '') + '" onclick="GC.selectErrorSource(\'' + esc(k) + '\')">' + esc(s.label) + ' (' + esc(s.size_human) + ')</button>';
                });
            }
            pillBox.innerHTML = pills;

            var tbody = document.getElementById('errorLogsBody');
            if (!state.errorsCache.length) {
                var emptyText = st === 'active'
                    ? '✓ Bersih! Semua error pada filter ini sudah selesai atau tidak ada error aktif.'
                    : 'Tidak ada catatan error pada filter status ini.';
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:#059669;font-weight:600;">' + emptyText + '</td></tr>';
                return;
            }
            tbody.innerHTML = state.errorsCache.map(function (e, idx) {
                var isRes = Number(e.is_resolved) === 1;
                var bCls = (e.level === 'fatal' || e.level === 'sql') ? 'gc-badge-crit' : (e.level === 'exception' || e.level === 'warning' ? 'gc-badge-warn' : 'gc-badge-info');
                return '<tr data-err-id="' + esc(e.id) + '" style="' + (isRes ? 'opacity:0.68;' : '') + '">' +
                    '<td data-label="Pilih"><input type="checkbox" class="err-row-check" value="' + esc(e.id) + '" onchange="GC.updateErrSelectedCount()"></td>' +
                    '<td data-label="Sumber & Level"><strong>' + esc(e.app_source) + '</strong><div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:3px;"><span class="gc-badge ' + bCls + '">' + esc(e.level) + '</span>' + (isRes ? '<span class="gc-badge gc-badge-ok">SELESAI ✓</span>' : '') + '</div></td>' +
                    '<td data-label="Pesan & Lokasi"><div style="font-weight:600;font-size:0.84rem;' + (isRes ? 'text-decoration:line-through;color:var(--gc-muted);' : '') + '">' + esc(String(e.message || '').split('\n')[0]).slice(0, 140) + '</div>' +
                        (e.file_path ? '<div class="gc-mono" style="font-size:0.74rem;color:var(--gc-muted);">' + esc(e.file_path) + (e.line_num ? ':' + esc(e.line_num) : '') + '</div>' : '') + '</td>' +
                    '<td data-label="Frekuensi"><span class="gc-badge gc-badge-navy">' + esc(e.hit_count) + 'x</span></td>' +
                    '<td data-label="Terakhir">' + esc(e.last_seen_at) + '</td>' +
                    '<td data-label="Aksi" style="display:flex;gap:6px;flex-wrap:wrap;">' +
                        '<button type="button" class="gc-btn gc-btn-ghost gc-btn-sm" onclick="GC.showErrorDetail(' + idx + ')">Trace</button>' +
                        '<button type="button" class="gc-btn ' + (isRes ? 'gc-btn-gold' : 'gc-btn-teal') + ' gc-btn-sm" onclick="GC.toggleErrorResolve(' + e.id + ', ' + (isRes ? 0 : 1) + ', this)">' + (isRes ? 'Buka' : 'Selesai') + '</button>' +
                    '</td></tr>';
            }).join('');
        } catch (err) {
            toast('error', 'Gagal Memuat Error Log', err.message);
        }
    }

    function selectErrorSource(src) {
        state.errSource = src;
        loadErrorLogs();
    }

    function showErrorDetail(idx) {
        var e = state.errorsCache[idx];
        if (!e) return;
        setText('detailModalTitle', 'Error Trace (' + e.app_source + ')');
        setText('detailModalSub', e.level.toUpperCase() + ' · Terjadi ' + e.hit_count + 'x · Terakhir ' + e.last_seen_at);
        document.getElementById('detailModalBody').innerHTML =
            '<div class="gc-mono" style="white-space:pre-wrap;background:#0F1D2F;color:#E2E8F0;padding:14px;border-radius:12px;font-size:0.76rem;max-height:380px;overflow:auto;">' +
            esc(e.message) + '</div>';
        document.getElementById('detailModalFooter').innerHTML = '<button type="button" class="ilm-modal-btn ilm-modal-btn--ghost" onclick="GC.closeModal(\'gcDetailModal\')">Tutup</button>';
        openModal('gcDetailModal');
    }

    async function toggleErrorResolve(id, resolved, btn) {
        var stEl = document.getElementById('errStatusFilter');
        var curSt = stEl ? stEl.value : 'active';
        try {
            var d = await apiFetch('error-logs.php', { method: 'POST', body: { action: 'resolve', id: id, resolved: resolved } });
            if (d) {
                var tr = btn ? btn.closest('tr') : null;
                var shouldSlideOut = (curSt === 'active' && resolved === 1) || (curSt === 'resolved' && resolved === 0);
                if (tr && shouldSlideOut) {
                    tr.classList.add('row-smooth-out');
                    setTimeout(function () { loadErrorLogs(); }, 270);
                } else {
                    if (tr) tr.classList.add('row-flash');
                    loadErrorLogs();
                }
                toast('success', resolved ? 'Ditandai Selesai' : 'Dibuka Kembali', d.message);
                loadOverview();
            }
        } catch (err) { toast('error', 'Gagal', err.message); }
    }

    function bulkErrorAction(action) {
        var ids = getSelectedErrIds();
        if (!ids.length) {
            toast('warning', 'Belum Ada Baris Dipilih', 'Centang minimal 1 error pada kolom paling kiri terlebih dahulu.');
            return;
        }
        var labelMap = {
            bulk_resolve: { title: 'Tandai ' + ids.length + ' Error Selesai?', msg: 'Error terpilih akan dipindahkan dari daftar Aktif ke arsip Sudah Selesai.', btn: 'Ya, Tandai Selesai', type: 'warning' },
            bulk_reopen:  { title: 'Buka Kembali ' + ids.length + ' Error?', msg: 'Error terpilih akan dikembalikan ke daftar Belum Selesai (Aktif).', btn: 'Ya, Buka Kembali', type: 'info' },
            bulk_delete:  { title: 'Hapus ' + ids.length + ' Snapshot Error?', msg: 'Catatan error terpilih akan dihapus dari database Guardian Control (tidak menghapus file log asli).', btn: 'Ya, Hapus Terpilih', type: 'danger' }
        };
        var cfg = labelMap[action] || labelMap.bulk_resolve;
        askConfirm({ title: cfg.title, message: cfg.msg, confirmText: cfg.btn, type: cfg.type }, async function () {
            try {
                var d = await apiFetch('error-logs.php', { method: 'POST', body: { action: action, ids: ids } });
                if (d) {
                    ids.forEach(function (id) {
                        var tr = document.querySelector('tr[data-err-id="' + id + '"]');
                        if (tr) tr.classList.add('row-smooth-out');
                    });
                    toast('success', 'Bulk Action Berhasil', d.message);
                    setTimeout(function () { loadErrorLogs(); loadOverview(); }, 280);
                }
            } catch (err) { toast('error', 'Bulk Action Gagal', err.message); }
        });
    }

    function resolveAllErrors() {
        var lvlEl = document.getElementById('errLevelFilter');
        var lvl = lvlEl ? lvlEl.value : 'all';
        askConfirm({
            title: 'Tandai Semua Error Aktif Selesai?',
            message: 'Seluruh error aktif pada filter aplikasi (' + state.errSource + ') & level (' + lvl + ') akan ditandai selesai sekaligus.',
            confirmText: 'Ya, Selesaikan Semua',
            type: 'warning'
        }, async function () {
            try {
                var d = await apiFetch('error-logs.php', { method: 'POST', body: { action: 'resolve_all', source: state.errSource, level: lvl } });
                if (d) {
                    toast('success', 'Semua Selesai', d.message);
                    loadErrorLogs();
                    loadOverview();
                }
            } catch (err) { toast('error', 'Gagal', err.message); }
        });
    }

    function downloadActiveErrorLog() {
        if (state.errSource === 'all') {
            toast('warning', 'Pilih Satu Aplikasi', 'Pilih salah satu pill aplikasi spesifik terlebih dahulu untuk mengunduh log.');
            return;
        }
        window.location.href = BASE + '/api/error-logs.php?source=' + encodeURIComponent(state.errSource) + '&download=1';
    }

    function truncateActiveErrorLog() {
        if (state.errSource === 'all') {
            toast('warning', 'Pilih Satu Aplikasi', 'Pilih satu aplikasi spesifik yang log-nya ingin diarsipkan & dikosongkan.');
            return;
        }
        askConfirm({
            title: 'Arsipkan & Kosongkan Log ' + state.errSource + '?',
            message: 'Salinan tail log terakhir akan diarsipkan ke folder cache sebelum berkas error_log dikosongkan.',
            confirmText: 'Ya, Bersihkan Log',
            type: 'danger'
        }, async function () {
            try {
                var d = await apiFetch('error-logs.php', { method: 'POST', body: { action: 'truncate', source: state.errSource } });
                if (d) { toast('success', 'Log Dibersihkan', d.message); loadErrorLogs(); }
            } catch (err) { toast('error', 'Gagal Membersihkan Log', err.message); }
        });
    }

    // ==========================================================================
    // TAB 6: UNIQUE DEVICE VISITORS (1 DEVICE = 1) & AI/SEO TRAFFIC
    // ==========================================================================
    function setVisitorRange(r) {
        state.visRange = r;
        document.querySelectorAll('[data-vis-range]').forEach(function (b) {
            b.classList.toggle('is-active', b.getAttribute('data-vis-range') === r);
        });
        loadVisitorsAndBots();
    }

    async function loadVisitorsAndBots() {
        try {
            var results = await Promise.all([
                apiFetch('visitors.php?range=' + encodeURIComponent(state.visRange)),
                apiFetch('bots-seo.php')
            ]);
            var v = results[0];
            var b = results[1];
            if (v) {
                var s = v.summary || {};
                setText('visTodayUniq', s.today_unique || 0);
                setText('visTodayHits', s.today_hits || 0);
                setText('visOnline5m', s.online_5m || 0);
                setText('vis7dUniq', s.d7_unique || 0);
                setText('vis30dUniq', s.d30_unique || 0);

                var subBody = document.getElementById('visSubdomainBody');
                if (v.subdomains && v.subdomains.length) {
                    subBody.innerHTML = v.subdomains.map(function (sd) {
                        return '<tr><td data-label="Subdomain"><strong class="gc-mono">' + esc(sd.domain) + '</strong></td>' +
                            '<td data-label="Unique Devices"><span class="gc-badge gc-badge-ok">' + esc(sd.unique_devices) + '</span></td>' +
                            '<td data-label="Pageviews">' + esc(sd.total_pageviews) + '</td>' +
                            '<td data-label="Online 5m">' + esc(sd.online_5m) + '</td></tr>';
                    }).join('');
                } else {
                    subBody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:18px;color:var(--gc-muted);">Menunggu data kunjungan manusia dari cookie gd_dev_id...</td></tr>';
                }

                var brk = document.getElementById('visBreakdownBox');
                var renderMiniList = function (title, arr, kKey, vKey) {
                    var rows = (arr || []).map(function (x) {
                        return '<div style="display:flex;justify-content:space-between;font-size:0.8rem;padding:4px 0;border-bottom:1px dashed var(--gc-border);"><span>' + esc(x[kKey]) + '</span><strong>' + esc(x[vKey]) + '</strong></div>';
                    }).join('') || '<div style="font-size:0.78rem;color:var(--gc-muted);">Belum ada data</div>';
                    return '<div><div class="gc-kpi-label">' + title + '</div>' + rows + '</div>';
                };
                var topPagesArr = Object.keys((v.drilldown && v.drilldown.pages) || {}).slice(0, 5).map(function (k) { return { name: k, cnt: v.drilldown.pages[k] }; });
                brk.innerHTML =
                    renderMiniList('Perangkat', v.devices, 'device_type', 'cnt') +
                    renderMiniList('Sistem Operasi', v.os, 'os_name', 'cnt') +
                    renderMiniList('Browser', v.browsers, 'browser_name', 'cnt') +
                    renderMiniList('Halaman Terpopuler', topPagesArr, 'name', 'cnt');

                drawLineChart('visTrendCanvas', v.trend || [], [
                    { key: 'unique_devices', label: 'Unique Devices', color: '#0D7A87' },
                    { key: 'total_pageviews', label: 'Pageviews', color: '#52B2DC' }
                ], 'visit_date', null);
            }

            if (b) {
                var bc = b.counts || {};
                setText('botAiCnt', bc.ai_blocked_30d || 0);
                setText('botSeoCnt', bc.verified_seo || 0);
                setText('botFakeCnt', bc.fake_bots_30d || 0);

                var aiBox = document.getElementById('botAiList');
                var aiKeys = Object.keys(b.ai_breakdown || {});
                if (aiKeys.length) {
                    aiBox.innerHTML = aiKeys.map(function (k) {
                        return '<div style="display:flex;justify-content:space-between;padding:7px 8px;border-bottom:1px solid var(--gc-border);"><strong>' + esc(k) + '</strong><span class="gc-badge gc-badge-crit">' + esc(b.ai_breakdown[k]) + ' blokir</span></div>';
                    }).join('');
                } else {
                    aiBox.innerHTML = '<div style="text-align:center;padding:16px;color:var(--gc-muted);">Belum ada blokir AI Scraper dalam 30 hari terakhir.</div>';
                }

                var seoBody = document.getElementById('botSeoBody');
                if (b.verified_seo && b.verified_seo.length) {
                    seoBody.innerHTML = b.verified_seo.map(function (s) {
                        return '<tr><td data-label="IP" class="gc-mono">' + esc(s.ip) + '</td><td data-label="PTR" class="gc-mono">' + esc(s.ptr) + '</td><td data-label="Status"><span class="gc-badge gc-badge-ok">FCrDNS OK</span></td><td data-label="Expired">' + esc(s.expires_at) + '</td></tr>';
                    }).join('');
                } else {
                    seoBody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:16px;color:var(--gc-muted);">Belum ada cache FCrDNS crawler aktif.</td></tr>';
                }
            }
        } catch (err) {
            toast('error', 'Gagal Memuat Analitik', err.message);
        }
    }

    // ==========================================================================
    // TAB 7: FIM SHA-256, HARDENING AUDIT & WEBSHELL SCANNER
    // ==========================================================================
    function renderFimData(d) {
        var tbody = document.getElementById('fimTableBody');
        var rows = d.fim || [];
        tbody.innerHTML = rows.map(function (f) {
            var stCls = f.status === 'ok' ? 'gc-badge-ok' : 'gc-badge-crit';
            var rowStyle = f.status !== 'ok' ? 'background:rgba(239,68,68,0.08);' : '';
            return '<tr style="' + rowStyle + '">' +
                '<td data-label="Aplikasi"><strong>' + esc(f.app_source) + '</strong></td>' +
                '<td data-label="Berkas" class="gc-mono">' + esc(f.file_path) + '</td>' +
                '<td data-label="SHA-256" class="gc-mono" style="font-size:0.72rem;">' + esc(String(f.last_sha256 || '').slice(0, 24)) + '…</td>' +
                '<td data-label="Status"><span class="gc-badge ' + stCls + '">' + esc(f.status.toUpperCase()) + '</span></td>' +
                '<td data-label="Cek Terakhir">' + esc(f.last_checked_at) + '</td></tr>';
        }).join('');

        var hBox = document.getElementById('fimHardeningBox');
        if (!d.hardening || !d.hardening.length) {
            hBox.innerHTML = '<div style="padding:14px;border-radius:12px;background:#ECFDF5;color:#065F46;font-weight:700;">✓ 0 temuan kerentanan hak akses (0777) maupun eksposur .env/.git/.sql.</div>';
        } else {
            hBox.innerHTML = d.hardening.map(function (h) {
                return '<div style="padding:10px 12px;border-radius:12px;background:#FEF2F2;border:1px solid #FECACA;margin-bottom:8px;">' +
                    '<span class="gc-badge gc-badge-crit">' + esc(h.type) + '</span> <strong class="gc-mono">' + esc(h.path) + '</strong>' +
                    '<div style="font-size:0.8rem;color:#7F1D1D;margin-top:4px;">' + esc(h.detail) + '</div></div>';
            }).join('');
        }

        var uBox = document.getElementById('fimUploadsBox');
        var up = d.uploads || { scanned_files: 0, threats: [] };
        if (!up.threats || !up.threats.length) {
            uBox.innerHTML = '<div style="padding:14px;border-radius:12px;background:#ECFDF5;color:#065F46;font-weight:700;">✓ Bersih! Dipindai ' + esc(up.scanned_files) + ' berkas di uploads/ — 0 webshell/polyglot ditemukan.</div>';
        } else {
            uBox.innerHTML = up.threats.map(function (t) {
                return '<div style="padding:10px 12px;border-radius:12px;background:#FEF2F2;border:1px solid #FECACA;margin-bottom:8px;">' +
                    '<span class="gc-badge gc-badge-crit">' + esc(t.type) + '</span> <strong class="gc-mono">' + esc(t.path) + '</strong>' +
                    '<div style="font-size:0.8rem;color:#7F1D1D;margin-top:4px;">' + esc(t.detail) + '</div></div>';
            }).join('');
        }
    }

    async function loadFimScanner() {
        try {
            var d = await apiFetch('fim-scanner.php');
            if (d) renderFimData(d);
        } catch (err) { toast('error', 'Gagal Memuat FIM', err.message); }
    }

    async function runFimScan() {
        toast('info', 'Memindai Integritas...', 'Menghitung SHA-256 & memindai direktori uploads/...');
        try {
            var d = await apiFetch('fim-scanner.php', { method: 'POST', body: { action: 'scan_now' } });
            if (d) { renderFimData(d); loadOverview(); toast('success', 'Pemindaian Selesai', d.message); }
        } catch (err) { toast('error', 'Gagal Scan FIM', err.message); }
    }

    function resetFimBaseline() {
        askConfirm({
            title: 'Perbarui Baseline SHA-256?',
            message: 'Hash berkas saat ini akan disimpan sebagai baseline resmi baru. Lakukan hanya setelah deploy resmi.',
            confirmText: 'Ya, Set Baseline Baru',
            type: 'warning'
        }, async function () {
            try {
                var d = await apiFetch('fim-scanner.php', { method: 'POST', body: { action: 'reset_baseline' } });
                if (d) { toast('success', 'Baseline Diperbarui', d.message); loadFimScanner(); loadOverview(); }
            } catch (err) { toast('error', 'Gagal Reset Baseline', err.message); }
        });
    }

    // ==========================================================================
    // TAB 8: TELEGRAM SENTINEL, CACHE PURGER & AUDIT LOG
    // ==========================================================================
    async function loadMaintenance() {
        try {
            var d = await apiFetch('maintenance.php');
            if (!d) return;

            var s = d.sentinel || {};
            document.getElementById('sentinelStatusBox').innerHTML = s.installed
                ? '<span class="gc-badge gc-badge-ok">Modul Terdeteksi</span> <span class="gc-mono" style="font-size:0.74rem;">' + esc(s.path) + '</span>'
                : '<span class="gc-badge gc-badge-warn">Modul Telegram Belum Ditemukan</span>';

            if (s.prefs) {
                document.getElementById('sentAlertLevel').value = s.prefs.alert_level || 'critical_only';
                document.getElementById('sentNotifyFim').checked = !!s.prefs.notify_fim;
                document.getElementById('sentNotifyDown').checked = !!s.prefs.notify_downtime;
                document.getElementById('sentSslDays').value = s.prefs.notify_ssl_days || 14;
            }

            var ca = d.cache || {};
            document.getElementById('cacheStatsBox').innerHTML =
                '<div style="font-family:Outfit,sans-serif;font-size:1.3rem;font-weight:800;margin-bottom:6px;">' + esc(ca.total_files || 0) + ' berkas · ' + esc(ca.total_human || '0 B') + '</div>' +
                (d.purger && d.purger.at ? '<div style="font-size:0.8rem;color:var(--gc-muted);">Purge terakhir: ' + esc(d.purger.at) + ' — ' + esc(d.purger.summary) + '</div>' : '');

            var aBody = document.getElementById('auditLogBody');
            if (d.audit_log && d.audit_log.length) {
                aBody.innerHTML = d.audit_log.map(function (a) {
                    return '<tr><td data-label="Waktu">' + esc(a.created_at) + '</td>' +
                        '<td data-label="Admin"><strong>' + esc(a.admin_username) + '</strong></td>' +
                        '<td data-label="Aksi"><span class="gc-badge gc-badge-navy">' + esc(a.action_type) + '</span></td>' +
                        '<td data-label="Target" class="gc-mono">' + esc(a.target_ref || '-') + '</td>' +
                        '<td data-label="IP Admin" class="gc-mono">' + esc(a.ip_address) + '</td></tr>';
                }).join('');
            } else {
                aBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;">Belum ada riwayat audit.</td></tr>';
            }
        } catch (err) { toast('error', 'Gagal Memuat Pengaturan', err.message); }
    }

    async function saveSentinelPrefs() {
        try {
            var d = await apiFetch('maintenance.php', {
                method: 'POST',
                body: {
                    action: 'save_sentinel_prefs',
                    alert_level: document.getElementById('sentAlertLevel').value,
                    notify_fim: document.getElementById('sentNotifyFim').checked,
                    notify_downtime: document.getElementById('sentNotifyDown').checked,
                    notify_ssl_days: parseInt(document.getElementById('sentSslDays').value || '14', 10)
                }
            });
            if (d) toast('success', 'Disimpan', d.message);
        } catch (err) { toast('error', 'Gagal Menyimpan', err.message); }
    }

    async function testTelegram() {
        try {
            var d = await apiFetch('maintenance.php', { method: 'POST', body: { action: 'test_telegram' } });
            if (d) toast('success', 'Telegram Sentinel OK', d.message);
        } catch (err) { toast('error', 'Gagal Uji Telegram', err.message); }
    }

    function purgeCache(forceAll) {
        askConfirm({
            title: forceAll ? 'Purge Semua Cache Sementara?' : 'Purge Cache > 7 Hari?',
            message: 'Berkas banned_ips.json & gc-runtime-rules.json tetap dilindungi dan tidak akan dihapus.',
            confirmText: 'Ya, Purge Sekarang',
            type: forceAll ? 'danger' : 'warning'
        }, async function () {
            try {
                var d = await apiFetch('maintenance.php', { method: 'POST', body: { action: 'purge_cache', force_all: !!forceAll } });
                if (d) { toast('success', 'Cache Purger Selesai', d.message); loadMaintenance(); }
            } catch (err) { toast('error', 'Gagal Purge Cache', err.message); }
        });
    }

    // ==========================================================================
    // NATIVE HTML5 <CANVAS> MICRO LINE CHART (ZERO EXTERNAL LIBRARY)
    // ==========================================================================
    function drawLineChart(canvasId, rows, series, labelKey, fixedMax) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || !canvas.getContext) return;
        var rect = canvas.parentElement.getBoundingClientRect();
        var dpr = window.devicePixelRatio || 1;
        var w = Math.max(280, rect.width || 500);
        var h = 210;
        canvas.width = w * dpr;
        canvas.height = h * dpr;
        var ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);
        ctx.clearRect(0, 0, w, h);

        var pad = { t: 22, r: 16, b: 28, l: 38 };
        var cw = w - pad.l - pad.r;
        var ch = h - pad.t - pad.b;

        if (!rows || !rows.length) {
            ctx.fillStyle = '#94A3B8';
            ctx.font = '600 13px "Plus Jakarta Sans", sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Belum ada data historis untuk ditampilkan', w / 2, h / 2);
            return;
        }

        var maxVal = fixedMax || 10;
        if (!fixedMax) {
            rows.forEach(function (r) {
                series.forEach(function (s) {
                    var v = parseFloat(r[s.key]) || 0;
                    if (v > maxVal) maxVal = v;
                });
            });
            maxVal = Math.ceil(maxVal * 1.15);
        }

        // Grid lines
        ctx.strokeStyle = 'rgba(148, 163, 184, 0.25)';
        ctx.lineWidth = 1;
        ctx.fillStyle = '#64748B';
        ctx.font = '11px "JetBrains Mono", monospace';
        ctx.textAlign = 'right';
        for (var g = 0; g <= 4; g++) {
            var y = pad.t + ch - (ch * g / 4);
            ctx.beginPath(); ctx.moveTo(pad.l, y); ctx.lineTo(pad.l + cw, y); ctx.stroke();
            ctx.fillText(String(Math.round(maxVal * g / 4)), pad.l - 6, y + 4);
        }

        // Series lines
        series.forEach(function (s) {
            ctx.strokeStyle = s.color;
            ctx.lineWidth = 2.5;
            ctx.beginPath();
            rows.forEach(function (r, i) {
                var x = pad.l + (rows.length === 1 ? cw / 2 : (cw * i / (rows.length - 1)));
                var y = pad.t + ch - (ch * (Math.min(maxVal, parseFloat(r[s.key]) || 0) / maxVal));
                if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
            });
            ctx.stroke();
        });

        // Legend
        var lx = pad.l;
        ctx.textAlign = 'left';
        ctx.font = '700 11px "Plus Jakarta Sans", sans-serif';
        series.forEach(function (s) {
            ctx.fillStyle = s.color;
            ctx.fillRect(lx, 6, 10, 10);
            ctx.fillStyle = '#64748B';
            ctx.fillText(s.label, lx + 14, 15);
            lx += ctx.measureText(s.label).width + 32;
        });

        // X labels (first, middle, last)
        ctx.fillStyle = '#64748B';
        ctx.font = '10px "JetBrains Mono", monospace';
        ctx.textAlign = 'center';
        var idxs = rows.length === 1 ? [0] : [0, Math.floor(rows.length / 2), rows.length - 1];
        idxs.forEach(function (i) {
            var x = pad.l + (rows.length === 1 ? cw / 2 : (cw * i / (rows.length - 1)));
            ctx.fillText(String(rows[i][labelKey] || ''), x, h - 8);
        });
    }

    // ==========================================================================
    // AMBIENT PARTICLE NETWORK, ENTRY RADIAL WAVE, SIDEBAR & FULL TRANSITIONS
    // ==========================================================================
    function initAmbientParticles() {
        var canvas = document.getElementById('gcParticleCanvas');
        if (!canvas || !canvas.getContext) return;
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        var ctx = canvas.getContext('2d');
        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        resize();
        window.addEventListener('resize', resize);

        var colors = ['#1D3B5E', '#0D7A87', '#52B2DC', '#F59E0B', '#10B981'];
        var pts = [];
        for (var i = 0; i < 24; i++) {
            pts.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                vx: (Math.random() - 0.5) * 0.35,
                vy: (Math.random() - 0.5) * 0.35,
                r: Math.random() * 2.4 + 1,
                c: colors[Math.floor(Math.random() * colors.length)],
                o: Math.random() * 0.35 + 0.15
            });
        }

        function tick() {
            if (!document.hidden) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                for (var a = 0; a < pts.length; a++) {
                    var p1 = pts[a];
                    p1.x += p1.vx; p1.y += p1.vy;
                    if (p1.x < 0 || p1.x > canvas.width) p1.vx *= -1;
                    if (p1.y < 0 || p1.y > canvas.height) p1.vy *= -1;
                    ctx.beginPath();
                    ctx.arc(p1.x, p1.y, p1.r, 0, Math.PI * 2);
                    ctx.fillStyle = p1.c;
                    ctx.globalAlpha = p1.o;
                    ctx.fill();
                    for (var b = a + 1; b < pts.length; b++) {
                        var p2 = pts[b];
                        var dist = Math.hypot(p1.x - p2.x, p1.y - p2.y);
                        if (dist < 110) {
                            ctx.beginPath();
                            ctx.moveTo(p1.x, p1.y);
                            ctx.lineTo(p2.x, p2.y);
                            ctx.strokeStyle = p1.c;
                            ctx.globalAlpha = (1 - dist / 110) * 0.14;
                            ctx.stroke();
                        }
                    }
                }
                ctx.globalAlpha = 1;
            }
            requestAnimationFrame(tick);
        }
        tick();
    }

    function initShell() {
        // 1. Entry radial wave curtain sweep-out (1:1 SSO & Learn)
        var entrySlide = document.getElementById('entrySlide');
        if (entrySlide) {
            requestAnimationFrame(function () {
                setTimeout(function () { entrySlide.classList.add('is-done'); }, 70);
            });
        }

        initAmbientParticles();

        var leftSidebar = document.getElementById('leftSidebar');
        var overlay     = document.getElementById('sidebarOverlay');
        var sideToggle  = document.getElementById('leftSidebarToggle');
        var mainEl      = document.getElementById('mainContent');
        var topHeader   = document.getElementById('topHeader');
        var scrollBtn   = document.getElementById('scrollTopBtn');

        function isDesktop() { return window.innerWidth >= 1024; }
        function closeMobileDrawer() {
            if (leftSidebar) leftSidebar.classList.remove('is-open-mobile');
            if (overlay) overlay.classList.remove('is-open');
        }

        document.querySelectorAll('[data-gc-tab]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                closeMobileDrawer();
                switchTab(el.getAttribute('data-gc-tab'), false);
            });
        });

        var refreshBtn = document.getElementById('gcGlobalRefreshBtn');
        var refreshIcon = document.getElementById('gcRefreshIcon');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                if (refreshIcon) {
                    refreshIcon.classList.add('gc-spin');
                    setTimeout(function () { refreshIcon.classList.remove('gc-spin'); }, 750);
                }
                loadOverview();
                switchTab(state.activeTab, true);
                toast('info', 'Menyegarkan Data', 'Mengambil status terbaru dari server & WAF.');
            });
        }

        if (sideToggle && leftSidebar) {
            sideToggle.addEventListener('click', function () {
                if (isDesktop()) {
                    var collapsed = leftSidebar.classList.toggle('is-collapsed-desktop');
                    try { localStorage.setItem('gc.leftSidebarClosed', collapsed ? 'true' : 'false'); } catch (e) {}
                } else {
                    var openNow = !leftSidebar.classList.contains('is-open-mobile');
                    closeMobileDrawer();
                    if (openNow) {
                        leftSidebar.classList.add('is-open-mobile');
                        if (overlay) overlay.classList.add('is-open');
                    }
                }
            });
        }
        if (overlay) overlay.addEventListener('click', closeMobileDrawer);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeMobileDrawer();
        });

        // Sticky Header Morph + Scroll-to-Top FAB (1:1 SSO)
        if (mainEl) {
            mainEl.addEventListener('scroll', function () {
                var y = mainEl.scrollTop;
                if (topHeader) topHeader.classList.toggle('is-scrolled', y > 45);
                if (scrollBtn) scrollBtn.classList.toggle('is-visible', y > 240);
            }, { passive: true });
        }
        if (scrollBtn && mainEl) {
            scrollBtn.addEventListener('click', function () {
                mainEl.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        var themeBtn = document.getElementById('gcThemeToggleBtn');
        var themeIc  = document.getElementById('gcThemeIcon');
        if (themeIc && document.documentElement.classList.contains('gc-dark')) {
            themeIc.setAttribute('icon', 'solar:sun-bold');
        }
        if (themeBtn) {
            themeBtn.addEventListener('click', function () {
                var isDark = document.documentElement.classList.toggle('gc-dark');
                try { localStorage.setItem('gc_theme_mode', isDark ? 'dark' : 'light'); } catch (e) {}
                if (themeIc) themeIc.setAttribute('icon', isDark ? 'solar:sun-bold' : 'solar:moon-stars-bold');
            });
        }

        // Anti-polling boros: segarkan lembut hanya saat admin kembali ke tab browser
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                loadOverview();
            }
        });

        loadOverview();
        switchTab(state.activeTab, true);
    }

    window.GC = {
        switchTab: switchTab,
        openModal: openModal,
        closeModal: closeModal,
        saveWafPolicy: saveWafPolicy,
        emergencyUnbanAll: emergencyUnbanAll,
        flushRdnsCache: flushRdnsCache,
        loadFirewall: loadFirewall,
        openBanModal: openBanModal,
        submitBanIp: submitBanIp,
        unbanIp: unbanIp,
        bulkUnban: bulkUnban,
        bulkPermanent: bulkPermanent,
        toggleAllFwChecks: function (chk) {
            document.querySelectorAll('.fw-row-check').forEach(function (c) { c.checked = !!chk; });
        },
        openWhitelistModal: openWhitelistModal,
        whitelistMyIp: whitelistMyIp,
        submitWhitelist: submitWhitelist,
        removeWhitelist: removeWhitelist,
        loadIncidents: loadIncidents,
        showIncidentDetail: showIncidentDetail,
        runUptimeCheckNow: runUptimeCheckNow,
        recordServerSnapshot: recordServerSnapshot,
        loadErrorLogs: loadErrorLogs,
        selectErrorSource: selectErrorSource,
        showErrorDetail: showErrorDetail,
        toggleErrorResolve: toggleErrorResolve,
        toggleAllErrChecks: toggleAllErrChecks,
        updateErrSelectedCount: updateErrSelectedCount,
        bulkErrorAction: bulkErrorAction,
        resolveAllErrors: resolveAllErrors,
        downloadActiveErrorLog: downloadActiveErrorLog,
        truncateActiveErrorLog: truncateActiveErrorLog,
        setVisitorRange: setVisitorRange,
        runFimScan: runFimScan,
        resetFimBaseline: resetFimBaseline,
        saveSentinelPrefs: saveSentinelPrefs,
        testTelegram: testTelegram,
        purgeCache: purgeCache
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initShell);
    } else {
        initShell();
    }
})();
