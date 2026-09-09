<?php
/**
 * Emerald Central Hub V11 — Users module API.
 * Ported from V10: list_users | add_user | delete_user | update_profile.
 *  - add_user: owner role only
 *  - delete_user: only your own identity; migrate_to moves ownership
 *    (file_meta / notes / cloaks / domains / notepad dir) or purges data.
 *  - update_profile: username / password / avatar.
 */
if (!defined('EMERALD_DISPATCH')) {
    http_response_code(403);
    exit;
}

if ($action === 'list_users') {
    $out = array();
    foreach ($users as $uname => $data) {
        $out[] = array(
            'username' => $uname,
            'role' => isset($data['role']) ? $data['role'] : 'user',
            'avatar' => isset($data['avatar']) ? $data['avatar'] : '',
            'last_active' => isset($data['last_active']) ? $data['last_active'] : 0,
        );
    }
    sec_json_out($out);
}

if ($action === 'add_user') {
    if ($current_role !== 'owner') {
        sec_json_err('Only owners can add users.', 'forbidden');
    }
    $new_user = sec_str(isset($_POST['username']) ? $_POST['username'] : '');
    $new_role = sec_str(isset($_POST['role']) ? $_POST['role'] : 'user');
    if ($new_user === '') { sec_json_err('Username required.', 'error'); }
    if (!in_array($new_role, array('owner', 'admin', 'user', 'guest'), true)) { $new_role = 'user'; }
    if (!isset($users[$new_user])) {
        $users[$new_user] = array(
            'password' => password_hash(isset($_POST['password']) ? $_POST['password'] : '', PASSWORD_DEFAULT),
            'role' => $new_role,
            'avatar' => '',
            'last_active' => time(),
            'sec_q' => '',
            'sec_a' => '',
        );
        db_save('users', $users);
        logActivity($current_user, 'Registered Identity: ' . $new_user);
        sec_json_out(array('status' => 'success'));
    }
    sec_json_err('User exists.', 'error');
}

if ($action === 'delete_user') {
    $target_user = sec_str(isset($_POST['target_user']) ? $_POST['target_user'] : '');
    $migrate_to = sec_str(isset($_POST['migrate_to']) ? $_POST['migrate_to'] : '');

    if ($target_user !== $current_user) {
        sec_json_err('Akses Ditolak: Anda mutlak hanya bisa menghapus identitas akun Anda sendiri.', 'forbidden');
    }
    if (!isset($users[$target_user])) {
        sec_json_err('Target user not found.', 'error');
    }

    if ($migrate_to !== '') {
        if (!isset($users[$migrate_to])) {
            sec_json_err('Migration target user does not exist.', 'error');
        }
        $file_meta = db_load('file_meta');
        foreach ($file_meta as $fname => $owner) { if ($owner === $target_user) { $file_meta[$fname] = $migrate_to; } }
        db_save('file_meta', $file_meta);

        $notes = db_load('notes');
        foreach ($notes as $nid => $ndata) { if (isset($ndata['owner']) && $ndata['owner'] === $target_user) { $notes[$nid]['owner'] = $migrate_to; } }
        db_save('notes', $notes);

        $cloaks = db_load('cloaking');
        foreach ($cloaks as $cid => $cdata) { if (isset($cdata['owner']) && $cdata['owner'] === $target_user) { $cloaks[$cid]['owner'] = $migrate_to; } }
        db_save('cloaking', $cloaks);

        $notepad_dir = MODULES_DIR . '/notepad/data';
        $old_dir = $notepad_dir . '/' . $target_user;
        $new_dir = $notepad_dir . '/' . $migrate_to;
        if (is_dir($old_dir)) {
            if (!is_dir($new_dir)) { @mkdir($new_dir, 0755, true); }
            $items = @scandir($old_dir);
            if (is_array($items)) {
                foreach ($items as $f) {
                    if ($f === '.' || $f === '..') { continue; }
                    @rename($old_dir . '/' . $f, $new_dir . '/' . $f);
                }
            }
            recursiveRemoveDir($old_dir);
        }
        logActivity($current_user, 'Erased User: ' . $target_user . ' (Data migrated to ' . $migrate_to . ')');
    } else {
        $old_dir = MODULES_DIR . '/notepad/data/' . $target_user;
        if (is_dir($old_dir)) { recursiveRemoveDir($old_dir); }
        logActivity($current_user, 'Erased User: ' . $target_user . ' (All data purged)');
    }

    unset($users[$target_user]);
    db_save('users', $users);
    if ($target_user === $current_user) {
        auth_logout();
    }
    sec_json_out(array('status' => 'success'));
}

if ($action === 'update_profile') {
    $old_user = $current_user;
    $new_user = sec_str(isset($_POST['username']) ? $_POST['username'] : '');
    $pass = isset($_POST['password']) ? $_POST['password'] : '';
    $avatar = sec_str(isset($_POST['avatar']) ? $_POST['avatar'] : '');
    if ($new_user === '') { sec_json_err('Username cannot be empty.', 'error'); }
    if ($new_user !== $old_user && isset($users[$new_user])) { sec_json_err('Username already taken.', 'error'); }

    $userData = $users[$old_user];
    if (!empty($pass)) { $userData['password'] = password_hash($pass, PASSWORD_DEFAULT); }
    if (!empty($avatar)) { $userData['avatar'] = $avatar; }

    if ($new_user !== $old_user) {
        unset($users[$old_user]);
        $users[$new_user] = $userData;
        $_SESSION['emerald_user'] = $new_user;
    } else {
        $users[$old_user] = $userData;
    }
    db_save('users', $users);
    logActivity($new_user, 'Updated Profile Identity');
    sec_json_out(array('status' => 'success', 'new_user' => $new_user));
}

sec_json_err('Unknown action.', 'notfound');
