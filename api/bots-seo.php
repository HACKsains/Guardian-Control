<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL API — AI SCRAPER vs VERIFIED FCrDNS SEO/ADSENSE INSPECTOR
 * ==============================================================================
 */
require_once dirname(__DIR__) . '/auth.php';
require_once BASE_PATH . '/includes/waf-bridge.php';

requireAdminApiAuth(false);
$pdo = getDBConnection();

gc_waf_sync_incidents_to_db($pdo);

// 1. Blocked AI / LLM Scrapers (30 hari)
$aiIncidents = $pdo->query(
    "SELECT id, ray_id, domain, ip_address, user_agent, request_uri, reason, occurred_at
     FROM gc_incidents
     WHERE category = 'ai_scraper' AND occurred_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     ORDER BY occurred_at DESC
     LIMIT 100"
)->fetchAll(PDO::FETCH_ASSOC);

// 2. Blocked Fake Bots (PTR Mismatch / Spoofed Googlebot/AdSense)
$fakeBots = $pdo->query(
    "SELECT id, ray_id, domain, ip_address, user_agent, request_uri, reason, occurred_at
     FROM gc_incidents
     WHERE category = 'fake_bot' AND occurred_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     ORDER BY occurred_at DESC
     LIMIT 100"
)->fetchAll(PDO::FETCH_ASSOC);

// 3. Cache FCrDNS Resmi (rdns_bot_*.json)
$rdnsEntries = gc_waf_read_rdns_cache();
$verifiedSeo = array_values(array_filter($rdnsEntries, function ($e) {
    return !empty($e['verified']);
}));
$failedRdns = array_values(array_filter($rdnsEntries, function ($e) {
    return empty($e['verified']);
}));

// Agregasi nama AI bot
$aiBreakdown = [];
$knownAi = ['GPTBot', 'ChatGPT-User', 'OAI-SearchBot', 'ClaudeBot', 'PerplexityBot', 'Bytespider', 'Google-Extended', 'Meta-ExternalAgent', 'CCBot', 'Amazonbot', 'Firecrawl', 'Jina'];
foreach ($aiIncidents as $inc) {
    $ua = (string)($inc['user_agent'] ?? '') . ' ' . (string)($inc['reason'] ?? '');
    $matched = 'AI/LLM Scraper Lainnya';
    foreach ($knownAi as $name) {
        if (stripos($ua, $name) !== false) {
            $matched = $name;
            break;
        }
    }
    $aiBreakdown[$matched] = (int)($aiBreakdown[$matched] ?? 0) + 1;
}
arsort($aiBreakdown);

gc_json_response([
    'success'       => true,
    'counts'        => [
        'ai_blocked_30d'   => count($aiIncidents),
        'verified_seo'     => count($verifiedSeo),
        'fake_bots_30d'    => count($fakeBots) + count($failedRdns),
    ],
    'ai_breakdown'  => $aiBreakdown,
    'ai_incidents'  => $aiIncidents,
    'verified_seo'  => $verifiedSeo,
    'fake_bots'     => $fakeBots,
    'rdns_all'      => $rdnsEntries,
]);
