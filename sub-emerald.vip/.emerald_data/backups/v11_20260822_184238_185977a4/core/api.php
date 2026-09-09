<?php
/**
 * Emerald Central Hub V11 — API dispatcher.
 * Included ONLY by index.php when ?api=<module> is present.
 *
 *  - Requires an authenticated session (V10 contract: 403 JSON otherwise)
 *  - CSRF token enforced on every POST
 *  - Guest role is read-only (V10 modifying-action list)
 *  - ?api=heartbeat handled inline (throttled to 1 update / 15 s)
 *  - Dispatches to modules/<module>/api.php with EMERALD_DISPATCH defined
 *
 * PHP 7.0+ compatible.
 */
if (!isset($_GET['api'])) {
    http_response_code(403);
    exit;
}

$module = strtolower((string)$_GET['api']);
$module = preg_replace('/[^a-z0-9_\-]/', '', $module);

if (auth_user() === null) {
    sec_json_err('Authentication required.', 'auth');
}

$current_user = auth_user();
$users = db_load('users');
$current_role = isset($users[$current_user]['role']) ? $users[$current_user]['role'] : 'guest';
$_SESSION['emerald_role'] = $current_role;

$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
$action = isset($_POST['action']) ? (string)$_POST['action'] : (isset($_GET['action']) ? (string)$_GET['action'] : '');
$action = preg_replace('/[^a-z0-9_\-]/', '', strtolower($action));

/* ------------------------------------------------------------------ *
 *  Heartbeat — keep-alive + online presence (throttled 15 s / session)
 * ------------------------------------------------------------------ */
if ($module === 'heartbeat' || $action === 'heartbeat') {
    $last = isset($_SESSION['emerald_hb']) ? (int)$_SESSION['emerald_hb'] : 0;
    $throttled = (time() - $last) < 15;
    $_SESSION['emerald_hb'] = time();
    if (!$throttled) {
        $users[$current_user]['last_active'] = time();
        db_save('users', $users);
    }
    $statuses = array();
    foreach ($users as $uname => $udata) {
        $statuses[$uname] = isset($udata['last_active']) ? $udata['last_active'] : 0;
    }
    sec_json_out(array('status' => 'success', 'throttled' => $throttled, 'online_data' => $statuses));
}

/* ------------------------------------------------------------------ *
 *  CSRF (POST)
 * ------------------------------------------------------------------ */
if ($isPost && !sec_csrf_verify()) {
    sec_json_err('Invalid CSRF token. Refresh the page and try again.', 'csrf');
}

/* Touch last_active (heartbeat already did its own) */
$users[$current_user]['last_active'] = time();
db_save('users', $users);

/* ------------------------------------------------------------------ *
 *  Guest guard — same list as V10 (+ the two new domain actions)
 * ------------------------------------------------------------------ */
$modifying_actions = array(
    'upload', 'create_folder', 'create_file', 'delete_file', 'multi_delete',
    'paste_files', 'save_file', 'save_note', 'delete_note', 'add_user',
    'delete_user', 'save_cloaking', 'delete_cloaking', 'update_profile',
    'zip_file', 'unzip_file', 'add_firewall', 'delete_firewall', 'rename_file',
    'kill_process', 'save_domain', 'delete_domain',
);
if ($current_role === 'guest' && in_array($action, $modifying_actions, true)) {
    sec_json_err('Guest privileges do not allow modifications.', 'forbidden');
}

/* ------------------------------------------------------------------ *
 *  Dispatch to the module
 * ------------------------------------------------------------------ */
$apiFile = MODULES_DIR . '/' . $module . '/api.php';
if (!is_file($apiFile)) {
    sec_json_err('Unknown module.', 'notfound');
}
if (!defined('EMERALD_DISPATCH')) { define('EMERALD_DISPATCH', true); }
require $apiFile;
exit;
