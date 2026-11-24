<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL API — SECURITY INCIDENT & ATTACK LOG EXPLORER
 * ==============================================================================
 */
require_once dirname(__DIR__) . '/auth.php';
require_once BASE_PATH . '/includes/waf-bridge.php';

requireAdminApiAuth(false);
$pdo = getDBConnection();

// Sinkronisasi inkremental dari incidents.jsonl
gc_waf_sync_incidents_to_db($pdo);

$category = trim((string)($_GET['category'] ?? 'all'));
$domain   = trim((string)($_GET['domain'] ?? ''));
$q        = trim((string)($_GET['q'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo   = trim((string)($_GET['date_to'] ?? ''));
$export   = strtolower(trim((string)($_GET['export'] ?? '')));
$limit    = min(500, max(10, (int)($_GET['limit'] ?? 100)));

$where  = [];
$params = [];

if ($category !== '' && $category !== 'all') {
    if ($category === 'sqli_xss') {
        $where[] = "category IN ('sqli','xss','lfi')";
    } elseif ($category === 'shield_hp') {
        $where[] = "category IN ('shield_v3','honeypot')";
    } elseif ($category === 'oob_ssrf') {
        $where[] = "category IN ('oob','ssrf','jwt','csrf')";
    } else {
        $where[] = "category = :cat";
        $params[':cat'] = $category;
    }
}

if ($domain !== '' && $domain !== 'all') {
    $where[] = "domain = :dom";
    $params[':dom'] = $domain;
}

if ($q !== '') {
    $where[] = "(ip_address LIKE :q1 OR ray_id LIKE :q2 OR request_uri LIKE :q3 OR reason LIKE :q4)";
    $like = '%' . $q . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
    $params[':q4'] = $like;
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = "occurred_at >= :df";
    $params[':df'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = "occurred_at <= :dt";
    $params[':dt'] = $dateTo . ' 23:59:59';
}

$whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));
$sql = "SELECT id, ray_id, domain, ip_address, category, severity, action_taken, http_method, request_uri, user_agent, reason, shield_v3_score, meta_json, occurred_at
        FROM gc_incidents
        {$whereSql}
        ORDER BY occurred_at DESC, id DESC
        LIMIT {$limit}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Export CSV atau JSON
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="guardian-incidents-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Ray-ID', 'Domain', 'IP Address', 'Category', 'Severity', 'Action', 'Method', 'URI', 'Shield v3 Score', 'Reason', 'Occurred At']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['ray_id'], $r['domain'], $r['ip_address'],
            $r['category'], $r['severity'], $r['action_taken'], $r['http_method'],
            $r['request_uri'], $r['shield_v3_score'], $r['reason'], $r['occurred_at'],
        ]);
    }
    fclose($out);
    exit;
}

if ($export === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="guardian-incidents-' . date('Ymd-His') . '.json"');
    echo json_encode(['exported_at' => date('Y-m-d H:i:s'), 'count' => count($rows), 'incidents' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$domains = $pdo->query("SELECT DISTINCT domain FROM gc_incidents ORDER BY domain ASC")->fetchAll(PDO::FETCH_COLUMN);
$catCounts = $pdo->query("SELECT category, COUNT(*) AS cnt FROM gc_incidents WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY category")->fetchAll(PDO::FETCH_KEY_PAIR);

gc_json_response([
    'success'         => true,
    'count'           => count($rows),
    'domains'         => $domains,
    'category_counts' => $catCounts,
    'incidents'       => $rows,
]);
