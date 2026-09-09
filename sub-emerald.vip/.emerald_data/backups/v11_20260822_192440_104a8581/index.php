<?php
/**
 * Emerald Central Hub V11 — front controller.
 *
 *  - Firewall enforcement happens inside core/config.php (V10 contract;
 *    view.php / vieww.php keep working untouched thanks to ALLOW_PUBLIC_VIEW).
 *  - ?action=login|get_sec_q|reset_pass|logout  → V10-compatible endpoints
 *  - ?api=<module>&action=...                   → core/api.php dispatcher
 *  - ?p=<module>                                → dashboard shell + module
 *  - ?emergencyacc=emerald2026                  → emergency login (V10)
 *
 * PHP 7.0+ compatible.
 */
require_once __DIR__ . '/core/config.php';
require_once CORE_DIR . '/auth.php';

/* Emergency account — whitelists the client IP, then logs in as owner */
if (isset($_GET['emergencyacc']) && $_GET['emergencyacc'] !== '') {
    auth_start();
    if (auth_emergency((string)$_GET['emergencyacc']) !== null) {
        header('Location: index.php');
        exit;
    }
}

auth_start();

/* ------------------------------------------------------------------ *
 *  API dispatcher (?api=<module>) — needs a live session
 * ------------------------------------------------------------------ */
if (isset($_GET['api'])) {
    require CORE_DIR . '/api.php';
    exit;
}

/* ------------------------------------------------------------------ *
 *  Auth actions (V10-compatible endpoints)
 * ------------------------------------------------------------------ */
$action = isset($_GET['action']) ? (string)$_GET['action'] : '';

if ($action === 'login') {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        exit(json_encode(array('status' => 'error', 'message' => 'POST required')));
    }
    $r = auth_login(
        isset($_POST['username']) ? $_POST['username'] : '',
        isset($_POST['password']) ? $_POST['password'] : ''
    );
    exit(json_encode($r));
}

if ($action === 'get_sec_q') {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        exit(json_encode(array('status' => 'error', 'message' => 'POST required')));
    }
    $input = sec_str(isset($_POST['username']) ? $_POST['username'] : '');
    $users = db_load('users');
    $actual = null;
    foreach ($users as $k => $v) {
        if (strtolower((string)$k) === strtolower($input)) { $actual = $k; break; }
    }
    if ($actual !== null && isset($users[$actual])) {
        $q = !empty($users[$actual]['sec_q']) ? $users[$actual]['sec_q'] : 'What is your system codename?';
        exit(json_encode(array('status' => 'success', 'question' => $q, 'actual_user' => $actual)));
    }
    exit(json_encode(array('status' => 'error', 'message' => 'Identity not found in system records.')));
}

if ($action === 'reset_pass') {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        exit(json_encode(array('status' => 'error', 'message' => 'POST required')));
    }
    $r = auth_reset_pass(
        sec_str(isset($_POST['username']) ? $_POST['username'] : ''),
        sec_str(isset($_POST['answer']) ? $_POST['answer'] : ''),
        isset($_POST['new_pass']) ? $_POST['new_pass'] : ''
    );
    exit(json_encode($r));
}

if ($action === 'logout') {
    auth_logout();
    header('Location: index.php');
    exit;
}

/* ------------------------------------------------------------------ *
 *  Pages
 * ------------------------------------------------------------------ */
if (auth_user() === null) {
    require VIEWS_DIR . '/login.php';
    exit;
}

$modList = array('dashboard', 'storage', 'domains', 'cloaking', 'notes', 'users', 'firewall', 'monitor', 'notepad');
$mod = isset($_GET['p']) ? preg_replace('/[^a-z0-9_\-]/', '', strtolower($_GET['p'])) : 'dashboard';
if (!in_array($mod, $modList, true)) { $mod = 'dashboard'; }
$GLOBALS['emerald_page'] = $mod;

require VIEWS_DIR . '/dashboard.php';
