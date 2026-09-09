<?php
/**
 * Emerald Central Hub V11 — Dashboard module API.
 * Dispatched by core/api.php (EMERALD_DISPATCH defined). Read-only.
 * Actions: sys_info (GET)
 */
if (!defined('EMERALD_DISPATCH')) {
    http_response_code(403);
    exit;
}

if ($action === 'sys_info') {
    $logs = db_load('login_logs');
    $activity = db_load('activity_logs');
    $disk_free = @disk_free_space('/');
    $disk_total = @disk_total_space('/');
    sec_json_out(array(
        'status' => 'success',
        'stats' => getSystemStats(),
        'extended' => array(
            'disk_free'  => formatSize($disk_free ?: 0),
            'disk_total' => formatSize($disk_total ?: 0),
            'php_sapi'   => php_sapi_name(),
        ),
        'logs' => (is_array($logs) || is_object($logs)) ? array_values((array)$logs) : array(),
        'activity' => (is_array($activity) || is_object($activity)) ? array_values((array)$activity) : array(),
        'firewall_count' => count(db_load('firewall')),
        'users_count'    => count($users),
        'cloaks_count'   => count(db_load('cloaking')),
        'notes_count'    => count(db_load('notes')),
    ));
}

sec_json_err('Unknown action.', 'notfound');
