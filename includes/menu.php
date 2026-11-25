<?php
/**
 * ==============================================================================
 * GUARDIAN CONTROL — SIDEBAR MENU STRUCTURE (ICONIFY SOLAR ICONS ONLY)
 * ==============================================================================
 */
if (!defined('BASE_PATH')) {
    exit;
}

$gcSidebarGroups = [
    [
        'key'      => 'secops',
        'title'    => 'Pertahanan WAF & Firewall',
        'icon'     => 'solar:shield-check-bold',
        'is_admin' => true,
        'items'    => [
            [
                'tab'   => 'waf',
                'label' => 'WAF & Policy Bridge',
                'icon'  => 'solar:shield-star-bold',
                'badge_id' => 'sideBadgeWaf',
            ],
            [
                'tab'   => 'firewall',
                'label' => 'Firewall IP & Whitelist',
                'icon'  => 'solar:forbidden-circle-bold',
                'badge_id' => 'sideBadgeBans',
            ],
            [
                'tab'   => 'incidents',
                'label' => 'Log Insiden & Serangan',
                'icon'  => 'solar:danger-triangle-bold',
                'badge_id' => 'sideBadgeIncidents',
            ],
            [
                'tab'   => 'maintenance',
                'label' => 'Maintenance Website',
                'icon'  => 'solar:tuning-square-2-bold',
                'badge_id' => 'sideBadgeMaint',
            ],
        ],
    ],
    [
        'key'      => 'monitoring',
        'title'    => 'Monitoring Akar Rumput',
        'icon'     => 'solar:cpu-bolt-bold',
        'is_admin' => false,
        'items'    => [
            [
                'tab'   => 'server',
                'label' => 'Kesehatan Server & Uptime',
                'icon'  => 'solar:server-square-bold',
                'badge_id' => 'sideBadgeServer',
            ],
            [
                'tab'   => 'errors',
                'label' => 'Monitor Error Log',
                'icon'  => 'solar:bug-bold',
                'badge_id' => 'sideBadgeErrors',
            ],
            [
                'tab'   => 'visitors',
                'label' => 'Pengunjung 1-Device & AI/SEO',
                'icon'  => 'solar:users-group-two-rounded-bold',
                'badge_id' => 'sideBadgeVisitors',
            ],
        ],
    ],
    [
        'key'      => 'integrity',
        'title'    => 'Integritas & Sentinel',
        'icon'     => 'solar:lock-keyhole-bold',
        'is_admin' => false,
        'items'    => [
            [
                'tab'   => 'fim',
                'label' => 'FIM SHA-256 & Webshell',
                'icon'  => 'solar:file-check-bold',
                'badge_id' => 'sideBadgeFim',
            ],
            [
                'tab'   => 'settings',
                'label' => 'Sentinel, Cache & Audit',
                'icon'  => 'solar:settings-bold',
                'badge_id' => '',
            ],
        ],
    ],
];
