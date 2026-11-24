# 🚀 GUARDIAN CONTROL — IMPLEMENTATION PLAN (FASE 0 S/D FASE 6)

> **Rencana Pembangunan Bertahap Panel Kendali Guardian Dome WAF, Monitoring Menyeluruh Ekosistem & Unique Device Analytics**  
> **Target Workspace:** `C:\laragon\www\guardian control\` → **Produksi:** `https://guardian-dome.ilmuna.my.id`  
> **Lokasi Engine Guardian Dome:** `../guardian-dome` (`/home/uofruaju/guardian-dome` · lokal `C:\laragon\Guardian Dome`)

---

## FASE 0 — FONDASI PROYEK, PARITAS TEMA & AUTENTIKASI SSO ADMIN

**Tujuan:** Menyiapkan struktur proyek, aset visual 100% identik Lumina Gold & Nusantara, skema DB auto-migration, dan login eksklusif Admin via Margo One SSO.

### Checklist Deliverable
- [ ] Salin aset inti 1:1 dari `C:\laragon\www\SSO\` & `C:\laragon\Guardian Dome\`:
  - `assets/css/lumina-lms.css`, `ilmuna-unified-responsive.css`, `margo-shell.css`
  - `assets/js/alert.js` (`window.Ilmuna`)
  - `assets/img/gunungan.png`, `assets/images/Guardian_Dome.png`, `assets/images/logo.ico`
  - `includes/modal-template.php`, `table-template.php`, `calendar-template.php`
  - `sdk/ilmuna-sso-client.php`
- [ ] Buat `config.php` (kompatibel PHP 7.4–8.3, Timezone `Asia/Jakarta`, kredensial DB `uofruaju_guardian_control` + fallback lokal Laragon `root`, konfigurasi path absolut Guardian Dome & daftar preset `error_log` tiap aplikasi).
- [ ] Buat `database.php` untuk auto-migrasi **9 tabel `gc_*`** (`gc_incidents`, `gc_ip_rules`, `gc_server_metrics`, `gc_uptime_checks`, `gc_error_snapshots`, `gc_fim_hashes`, `gc_settings`, `gc_admin_audit`, **`gc_unique_visitors`**).
- [ ] Buat `auth.php`, `login.php` (Split-Panel Lumina Gold), `sso-callback.php` (wajib role `admin`), `sso-logout-webhook.php`, dan `logout.php` (Global SLO).
- [ ] Buat `.htaccess` dengan Clean URLs, security headers ketat, dan pengecualian `auto_prepend_file none` untuk `sso-callback.php` & `sso-logout-webhook.php`.

---

## FASE 1 — ENGINE KOLEKTOR TELEMETRI & JEMBATAN ATOMIK WAF

**Tujuan:** Membangun mesin backend murni PHP (100% kompatibel cPanel tanpa `exec()`) untuk membaca status WAF, RAM/CPU/Disk/MySQL, error log lintas aplikasi, dan menulis policy atomik.

### Checklist Deliverable
- [ ] Buat `includes/waf-bridge.php`:
  - Fungsi `gc_waf_read_policy()` & `gc_waf_write_policy(array $policy)` (menulis atomik ke `gc-runtime-rules.json` via `.tmp` + `rename()`).
  - Fungsi `gc_waf_read_banned_ips()`, `gc_waf_ban_ip()`, `gc_waf_unban_ip()`, `gc_waf_read_rdns_cache()`, dan `gc_waf_sync_incidents_to_db(PDO $pdo)`.
- [ ] Perbarui `../guardian-dome/guardian-dome-waf.php` (lokal `C:\laragon\Guardian Dome\guardian-dome-waf.php`):
  - Baca override dari `cache/gc-runtime-rules.json` bila file tersedia (fallback mulus ke konstanta bawaan).
  - Append ringkasan setiap blokir/ban ke `cache/incidents.jsonl` (rotasi otomatis saat > 5MB).
  - Tambahkan **Unique Device Visitor Tracker (`gd_dev_id` cookie `.ilmuna.my.id`)**: catat `device_hash` (SHA-256) + `domain` + `device_type` + `os` + `browser` ke `cache/visitors_YYYY-MM-DD.jsonl` khusus pengunjung manusia (mengecualikan bot SEO/AI, aset statis, API, cron) agar **1 device = 1 pengunjung** lintas seluruh web `*.ilmuna.my.id`.
- [ ] Buat `includes/server-collector.php`:
  - `gc_collect_ram_stats()` (`/proc/meminfo` Linux + fallback PHP memory di Windows).
  - `gc_collect_cpu_stats()` (`sys_getloadavg()` + jumlah core `/proc/cpuinfo`).
  - `gc_collect_disk_stats()` (`disk_total_space` / `disk_free_space` + ukuran folder cache/log).
  - `gc_collect_php_opcache_stats()` (`PHP_VERSION`, ekstensi aktif, `opcache_get_status(false)`).
  - `gc_collect_mysql_stats(PDO $pdo)` (`SHOW GLOBAL STATUS` + ukuran DB `INFORMATION_SCHEMA`).
  - `gc_check_ecosystem_uptime()` (HTTP status, latency ms, & sisa hari sertifikat SSL untuk `ilmuna.my.id`, `margo-one.ilmuna.my.id`, `learn.ilmuna.my.id`).
- [ ] Buat `includes/log-reader.php`:
  - `gc_tail_error_log($sourceKey, $maxBytes = 204800)` dengan whitelist key ketat (anti-LFI).
  - Parser klasifikasi `fatal`, `exception`, `warning`, `notice`, `sql` + deduplikasi fingerprint.
  - Fungsi `gc_truncate_error_log($sourceKey)` dengan arsip opsional & pencatatan audit.

---

## FASE 2 — DASHBOARD KOMANDO SEC-OPS, WAF & FIREWALL IP

**Tujuan:** Membangun shell antarmuka **Lumina Gold Data-First** (Zero Title, Segmented Tabs `.curriculum-main-tabs`, Zero Page Reload) serta kontrol langsung WAF & Firewall IP.

### Checklist Deliverable
- [ ] Buat template shell `includes/header.php`, `includes/sidebar.php`, `includes/menu.php`, `includes/footer.php` dan `assets/css/guardian-control.css`.
- [ ] Buat `dashboard.php` dengan **Segmented Tabs** + counter real-time:
  - Tab 1: **WAF & Policy** (`api/waf-policy.php`) — saklar toggle real-time (Under-Attack Mode, Pre-Flight Scan, Shield v3 + slider Min Score, Anti-AI Scraper, Honeypot Form, SEO/AdSense FCrDNS Pass), tombol *Emergency Unban All*.
  - Tab 2: **Firewall IP & Whitelist** (`api/firewall.php`) — tabel responsif `.ilmuna-table-stack`, pencarian IP/Ray-ID, modal Ban IP/CIDR manual, tombol Unban 1-klik, Bulk Unban, dan manajemen Whitelist IP Admin/Sekolah.
  - Tab 3: **Log Insiden & Serangan** (`api/incidents.php`) — filter kategori (SQLi/XSS/RCE/AI-Scraper/Shield-v3/Honeypot/OOB/CSRF/Flood), filter subdomain & tanggal, modal inspeksi detail payload, tombol aksi cepat Ban/Whitelist, dan tombol Export CSV/JSON.

---

## FASE 3 — MONITORING SERVER (RAM/CPU/DISK/MYSQL), ERROR LOG & AI/SEO TRAFFIC

**Tujuan:** Menyediakan visibilitas penuh atas kesehatan server, error aplikasi lintas subdomain, dan analitik bot AI vs SEO/AdSense.

### Checklist Deliverable
- [ ] Tab 4: **Kesehatan Server & Uptime** (`api/server-telemetry.php`):
  - Meteran visual bersih (Lumina Teal/Sky/Gold/Rose) untuk **RAM**, **CPU Load (1m/5m/15m)**, **Disk**, **OPcache Hit Rate**, dan **MySQL Connections / DB Size**.
  - Grafik tren historis ringan berbasis HTML5 `<canvas>` murni (tanpa Chart.js berat) dari tabel `gc_server_metrics` (24 jam terakhir).
  - Tabel status **Uptime & SSL Expiry** untuk `ilmuna.my.id`, `margo-one.ilmuna.my.id`, dan `learn.ilmuna.my.id` dengan tombol **Uji Sekarang**.
- [ ] Tab 5: **Monitor Error Log** (`api/error-logs.php`):
  - Pill switcher sumber log (`Semua`, `WordPress`, `Margo One SSO`, `Ilmuna Learn`, `Guardian Dome`).
  - Badge severity (`FATAL`, `EXCEPTION`, `WARNING`, `SQL`), jumlah kemunculan, tombol **Tandai Selesai**, **Unduh**, dan **Bersihkan Log**.
- [ ] Tab 6: **Pengunjung Unik (1 Device = 1) & AI/SEO Traffic** (`api/visitors.php` & `api/bots-seo.php`):
  - Ringkasan **Unique Device Ekosistem** (`COUNT(DISTINCT device_hash)` lintas seluruh `*.ilmuna.my.id`), **Online Aktif (5 menit)**, grafik tren harian/mingguan/bulanan (Canvas native), tabel rincian otomatis per subdomain (`ilmuna.my.id`, `margo-one.ilmuna.my.id`, `learn.ilmuna.my.id`, dll. — terpisah antara *Unique Devices* vs *Total Pageviews*), serta breakdown OS/Device/Browser.
  - Perbandingan jumlah blokir bot AI (ChatGPT/GPTBot, ClaudeBot, PerplexityBot, Bytespider, Google-Extended, dll.) vs crawler resmi terverifikasi FCrDNS (Googlebot, AdSense, Site Kit, Bingbot) + tabel cache FCrDNS aktif.

---

## FASE 4 — FILE INTEGRITY MONITOR (FIM), TELEGRAM SENTINEL & CRON TELEMETRY

**Tujuan:** Menambahkan deteksi perubahan file tak sah (anti-backdoor/defacement), pemindaian webshell `uploads/`, kendali Telegram Sentinel, dan cron pengumpul metrik otomatis.

### Checklist Deliverable
- [ ] Tab 7: **File Integrity (FIM) & Malware Scan** (`api/fim-scanner.php`):
  - Hitung & bandingkan hash **SHA-256** seluruh file kritis lintas aplikasi.
  - Highlight merah mencolok bila ada file berstatus `MODIFIED` atau `MISSING`, tombol **Pindai Sekarang** dan **Perbarui Baseline SHA-256**.
  - Pemindai kebersihan folder `uploads/` (deteksi `.php`/`.phtml`/`.phar` atau tag `<?php` tersembunyi).
- [ ] Tab 8: **Sentinel, Cache & Audit Admin** (`api/maintenance.php`):
  - Status & tombol **Kirim Uji Alert Telegram Sentinel**.
  - Informasi ukuran & jumlah file cache Guardian Dome + tombol **Purge Cache Sekarang**.
  - Tabel riwayat **Audit Tindakan Admin SEC-OPS** (`gc_admin_audit`).
- [ ] Buat `cron/collect-telemetry.php` (CLI-only, untuk dijalankan tiap 5 menit di cPanel Cron Jobs):
  - Catat snapshot RAM/CPU/Disk/MySQL ke `gc_server_metrics` (auto-prune data > 30 hari).
  - Jalankan uptime & SSL check ke `gc_uptime_checks` (kirim alert Telegram bila ada subdomain down atau SSL < 14 hari!).
  - Sinkronkan `incidents.jsonl` → `gc_incidents` dan jalankan pengecekan FIM berkala.

---

## FASE 5 — PENGUJIAN KEAMANAN, KOMPATIBILITAS PHP 7.4–8.3 & RESPONSIVITAS

**Tujuan:** Memastikan aplikasi bebas bug, kebal eksploitasi, dan mulus di semua ukuran layar.

### 12 Skenario Pengujian Wajib
1. **PHP Syntax Lint (`php -l`)**: 0 error pada seluruh file `.php` di `guardian control/`.
2. **PHP 7.4 Compatibility Audit**: Pastikan tidak ada sintaks PHP 8-only (`match`, `?->`, union types, `readonly`).
3. **SSO Admin-Only Enforcement**: Login akun role `guru` / `siswa` via Margo One SSO wajib ditolak `403 Forbidden`.
4. **Global SLO Sync**: Logout di Margo One SSO otomatis memutus sesi Guardian Control via `sso-logout-webhook.php`.
5. **CSRF Guard**: Semua `POST /api/*.php` tanpa `X-CSRF-Token` valid wajib ditolak `403`.
6. **Anti-LFI Error Log Reader**: Uji `error-logs.php?source=../../etc/passwd` wajib ditolak (hanya terima whitelist preset).
7. **Atomic Policy Bridge Test**: Toggle Shield v3 / Under-Attack Mode di UI → verifikasi `gc-runtime-rules.json` terupdate valid JSON dan terbaca oleh `guardian-dome-waf.php`.
8. **IP Ban & Unban Round-Trip**: Ban IP uji → cek masuk `banned_ips.json` & `gc_ip_rules` → Unban 1-klik → verifikasi hilang bersih.
9. **Native Telemetry Fallback**: Verifikasi `server-telemetry.php` berjalan mulus tanpa error baik di Windows Laragon maupun Linux cPanel (tanpa `exec()`).
10. **FIM Tamper Detection**: Ubah 1 byte file uji → jalankan scan FIM → status langsung berubah ke `MODIFIED`.
11. **Zero Page Reload & `window.Ilmuna`**: Seluruh aksi mutasi berjalan in-place dengan `.row-flash` / `.row-smooth-out` dan tanpa `alert()`/`confirm()` browser.
12. **Mobile `360px` Audit**: Zero horizontal overflow, semua tabel responsif menjadi card stack, tombol touch ≥ `44px`.

---

## FASE 6 — DEPLOYMENT CPANEL & GO-LIVE

1. Buat subdomain **`guardian-dome.ilmuna.my.id`** di cPanel (sejajar dengan folder **`../guardian-dome`** / `/home/uofruaju/guardian-dome`) + aktifkan SSL AutoSSL/Let's Encrypt.
2. Buat database MySQL **`uofruaju_guardian_control`** + user MySQL berhak `ALL PRIVILEGES`, masukkan kredensial ke `config.local.php` (tidak di-commit ke Git).
3. Daftarkan klien baru di **Margo One Admin (`https://margo-one.ilmuna.my.id/dashboard/admin/clients`)**:
   - **Slug**: `guardian-control`
   - **App URL**: `https://guardian-dome.ilmuna.my.id`
   - **Redirect URI**: `https://guardian-dome.ilmuna.my.id/sso-callback`
   - **Logout Webhook**: `https://guardian-dome.ilmuna.my.id/sso-logout-webhook`
   - **Allowed Roles**: **`admin`** (hanya admin!)
   - Salin `Public Key` & `Private Key` ke `guardian control/config.local.php`.
4. Pasang 1 Cron Job di cPanel (setiap 5 menit):
   ```bash
   */5 * * * * /usr/local/bin/php -q /home/uofruaju/guardian-dome.ilmuna.my.id/cron/collect-telemetry.php >/dev/null 2>&1
   ```
5. Login via Margo One SSO → buka `https://guardian-dome.ilmuna.my.id` → klik **Set Baseline FIM Awal** & **Kirim Uji Telegram Sentinel** → **Go-Live!**
