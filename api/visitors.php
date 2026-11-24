<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL API — UNIQUE DEVICE VISITOR ANALYTICS (1 DEVICE = 1 VISITOR)
 * ==============================================================================
 * Lintas seluruh ekosistem web *.ilmuna.my.id tanpa terkecuali.
 * Terpisah total antara Unique Devices vs Total Pageviews, 100% bebas bot/crawler.
 * ==============================================================================
 */
require_once dirname(__DIR__) . '/auth.php';
require_once BASE_PATH . '/includes/waf-bridge.php';

requireAdminApiAuth(false);
$pdo = getDBConnection();

// Sinkronkan visitors_YYYY-MM-DD.jsonl terbaru ke DB
gc_waf_sync_visitors_to_db($pdo);

$range  = trim((string)($_GET['range'] ?? '7d'));
$domain = trim((string)($_GET['domain'] ?? 'all'));

$days = 7;
if ($range === '1d' || $range === 'today') { $days = 1; }
elseif ($range === '30d') { $days = 30; }

$domWhere = ($domain !== '' && $domain !== 'all') ? ' AND domain = :dom ' : '';
$domParams = ($domain !== '' && $domain !== 'all') ? [':dom' => $domain] : [];

// 1. Ringkasan Global Ekosistem (COUNT DISTINCT device_hash lintas semua subdomain!)
$qToday = $pdo->prepare("SELECT COUNT(DISTINCT device_hash) AS uniq, COALESCE(SUM(hits_count), 0) AS hits FROM gc_unique_visitors WHERE visit_date = CURDATE() {$domWhere}");
$qToday->execute($domParams);
$todayRow = $qToday->fetch(PDO::FETCH_ASSOC);

$q7d = $pdo->prepare("SELECT COUNT(DISTINCT device_hash) AS uniq, COALESCE(SUM(hits_count), 0) AS hits FROM gc_unique_visitors WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) {$domWhere}");
$q7d->execute($domParams);
$d7Row = $q7d->fetch(PDO::FETCH_ASSOC);

$q30d = $pdo->prepare("SELECT COUNT(DISTINCT device_hash) AS uniq, COALESCE(SUM(hits_count), 0) AS hits FROM gc_unique_visitors WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) {$domWhere}");
$q30d->execute($domParams);
$d30Row = $q30d->fetch(PDO::FETCH_ASSOC);

$qOnline = $pdo->prepare("SELECT COUNT(DISTINCT device_hash) FROM gc_unique_visitors WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) {$domWhere}");
$qOnline->execute($domParams);
$online5m = (int)$qOnline->fetchColumn();

// 2. Rincian Otomatis Per Subdomain (*.ilmuna.my.id auto-discover)
$subSql = "SELECT domain,
                  COUNT(DISTINCT device_hash) AS unique_devices,
                  COALESCE(SUM(hits_count), 0) AS total_pageviews,
                  COUNT(DISTINCT CASE WHEN last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN device_hash END) AS online_5m,
                  MAX(last_seen_at) AS last_activity
           FROM gc_unique_visitors
           WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL " . ($days - 1) . " DAY)
           GROUP BY domain
           ORDER BY unique_devices DESC";
$subdomains = $pdo->query($subSql)->fetchAll(PDO::FETCH_ASSOC);

// 3. Tren Harian (untuk grafik Canvas native)
$trendStmt = $pdo->prepare(
    "SELECT visit_date,
            COUNT(DISTINCT device_hash) AS unique_devices,
            COALESCE(SUM(hits_count), 0) AS total_pageviews
     FROM gc_unique_visitors
     WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL " . ($days - 1) . " DAY) {$domWhere}
     GROUP BY visit_date
     ORDER BY visit_date ASC"
);
$trendStmt->execute($domParams);
$trend = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Breakdown Device Type, OS, Browser
$devStmt = $pdo->prepare(
    "SELECT device_type, COUNT(DISTINCT device_hash) AS cnt
     FROM gc_unique_visitors
     WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL " . ($days - 1) . " DAY) {$domWhere}
     GROUP BY device_type ORDER BY cnt DESC"
);
$devStmt->execute($domParams);
$devices = $devStmt->fetchAll(PDO::FETCH_ASSOC);

$osStmt = $pdo->prepare(
    "SELECT os_name, COUNT(DISTINCT device_hash) AS cnt
     FROM gc_unique_visitors
     WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL " . ($days - 1) . " DAY) {$domWhere}
     GROUP BY os_name ORDER BY cnt DESC LIMIT 8"
);
$osStmt->execute($domParams);
$osList = $osStmt->fetchAll(PDO::FETCH_ASSOC);

$brwStmt = $pdo->prepare(
    "SELECT browser_name, COUNT(DISTINCT device_hash) AS cnt
     FROM gc_unique_visitors
     WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL " . ($days - 1) . " DAY) {$domWhere}
     GROUP BY browser_name ORDER BY cnt DESC LIMIT 8"
);
$brwStmt->execute($domParams);
$browsers = $brwStmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Drill-down hari ini (trafik per jam, halaman terpopuler, referer)
$drill = gc_get_setting($pdo, 'visitors_drilldown_today', [
    'date'     => date('Y-m-d'),
    'hourly'   => array_fill(0, 24, 0),
    'pages'    => [],
    'referers' => [],
]);

gc_json_response([
    'success'    => true,
    'range'      => $range,
    'domain'     => $domain,
    'summary'    => [
        'today_unique' => (int)($todayRow['uniq'] ?? 0),
        'today_hits'   => (int)($todayRow['hits'] ?? 0),
        'd7_unique'    => (int)($d7Row['uniq'] ?? 0),
        'd7_hits'      => (int)($d7Row['hits'] ?? 0),
        'd30_unique'   => (int)($d30Row['uniq'] ?? 0),
        'd30_hits'     => (int)($d30Row['hits'] ?? 0),
        'online_5m'    => $online5m,
    ],
    'subdomains' => $subdomains,
    'trend'      => $trend,
    'devices'    => $devices,
    'os'         => $osList,
    'browsers'   => $browsers,
    'drilldown'  => $drill,
]);
