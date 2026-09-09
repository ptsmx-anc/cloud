<?php
/**
 * Emerald Central Hub V11 — Notepad module API (dual mode).
 *
 * 1) PUBLIC MODE (EMERALD_DISPATCH not defined)
 *    Used by /notepad/index.php (V10-compatible public page). Boots the
 *    core stack itself and answers the exact V10 action set:
 *      ?api=list_users | verify_access | list_notes | load_note |
 *             create_note | save_note | delete_note
 *    Mutating actions are gated by verifyUserPassword() or SSO.
 *
 * 2) DISPATCH MODE (EMERALD_DISPATCH defined by core/api.php)
 *    Used by the in-shell Notepad page (?api=notepad&action=...) with a
 *    live session; $action and $current_user are provided by the core.
 *
 * V11: note files live in modules/notepad/data/<user>/*.txt (migrated
 * from public_notepad/ by update.php — original files are never deleted).
 */
if (!defined('EMERALD_DISPATCH')) {
    // ---- public mode bootstrap (auth_start() starts the session with the
    //      correct V11 session name — starting it here breaks SSO) ----
    require_once dirname(__DIR__) . '/../core/config.php';
    require_once CORE_DIR . '/auth.php';
    auth_start();
    $current_user = auth_user();   // null when not logged in (SSO unavailable)
    $raw_action = isset($_GET['api']) ? $_GET['api'] : (isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : ''));
    $action = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$raw_action));
    header('Content-Type: application/json; charset=utf-8');
} else {
    // ---- dispatch mode ----
    if (!isset($current_user)) { $current_user = null; }
}

if (!defined('DIR_NOTEPAD')) {
    $np = MODULES_DIR . '/notepad/data';
    if (!is_dir($np) && is_dir(APP_ROOT . '/public_notepad')) {
        $np = APP_ROOT . '/public_notepad';
    }
    if (!is_dir($np)) { @mkdir($np, 0755, true); }
    define('DIR_NOTEPAD', $np);
}

$users = db_load('users');

/* helper: safe note filename (no path separators) */
function npd_note_path($user, $file) {
    $user = sec_clean_path($user);
    $file = sec_clean_path($file);
    if ($user === '' || $file === '') { return null; }
    if (strpos($user, '/') !== false || strpos($file, '/') !== false) { return null; }
    $dir = DIR_NOTEPAD . '/' . $user;
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    return $dir . '/' . $file;
}

if ($action === 'list_users') {
    $output = array();
    foreach ($users as $uname => $udata) {
        $output[] = array(
            'username' => $uname,
            'avatar' => !empty($udata['avatar']) ? $udata['avatar'] : 'https://ui-avatars.com/api/?name=' . urlencode($uname) . '&background=0ea5e9&color=fff&rounded=true&bold=true',
            'last_active' => isset($udata['last_active']) ? $udata['last_active'] : 0,
        );
    }
    sec_json_out($output);
}

if ($action === 'verify_access') {
    $user = sec_str(isset($_POST['user']) ? $_POST['user'] : '');
    $pass = isset($_POST['password']) ? $_POST['password'] : '';
    if ($user !== '' && verifyUserPassword($user, $pass)) {
        sec_json_out(array('status' => 'success'));
    }
    sec_json_out(array('status' => 'error', 'message' => 'Invalid password.'));
}

if ($action === 'list_notes') {
    $user = sec_str(isset($_POST['user']) ? $_POST['user'] : '');
    $dir = DIR_NOTEPAD . '/' . sec_clean_path($user);
    if ($user === '' || strpos(sec_clean_path($user), '/') !== false) { sec_json_out(array('status' => 'error', 'message' => 'Invalid user.')); }
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    clearstatcache();
    $notes = array();
    foreach (scandir($dir) as $file) {
        if ($file !== '.' && $file !== '..') { $notes[] = $file; }
    }
    sec_json_out(array('status' => 'success', 'notes' => array_values($notes)));
}

if ($action === 'load_note') {
    /* Match V10 public notepad: load does not re-check password */
    $user = sec_str(isset($_POST['user']) ? $_POST['user'] : '');
    $file = sec_str(isset($_POST['file']) ? $_POST['file'] : '');
    $path = npd_note_path($user, $file);
    $content = '';
    if ($path !== null && file_exists($path)) {
        $content = (string)file_get_contents($path);
    } else {
        $alt = APP_ROOT . '/public_notepad/' . sec_clean_path($user) . '/' . sec_clean_path($file);
        if (is_file($alt)) { $content = (string)file_get_contents($alt); }
    }
    sec_json_out(array('status' => 'success', 'content' => $content));
}

if ($action === 'save_note' || $action === 'create_note' || $action === 'delete_note') {
    $user = sec_str(isset($_POST['user']) ? $_POST['user'] : '');
    $file = sec_str(isset($_POST['file']) ? $_POST['file'] : '');
    $pass = isset($_POST['password']) ? $_POST['password'] : '';
    $is_sso = ($current_user !== null && $current_user !== '' && $current_user === $user);
    if (!$is_sso && !verifyUserPassword($user, $pass)) {
        sec_json_out(array('status' => 'error', 'message' => 'Invalid password.'));
    }
    $path = npd_note_path($user, $file);
    if ($path === null) { sec_json_out(array('status' => 'error', 'message' => 'Invalid file name.')); }

    if ($action === 'save_note') {
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        if (@file_put_contents($path, $content) !== false) {
            sec_json_out(array('status' => 'success'));
        }
        sec_json_out(array('status' => 'error', 'message' => 'Could not save note.'));
    }

    if ($action === 'create_note') {
        if (strpos($file, '.txt') === false) {
            $file .= '.txt';
            $path = npd_note_path($user, $file);
        }
        if (!file_exists($path)) { @file_put_contents($path, ''); }
        sec_json_out(array('status' => 'success'));
    }

    if ($action === 'delete_note') {
        if (file_exists($path)) { @unlink($path); }
        sec_json_out(array('status' => 'success'));
    }
}

sec_json_out(array('status' => 'error', 'message' => 'Unknown action.'));
