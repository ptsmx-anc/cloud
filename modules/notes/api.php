<?php
/**
 * Emerald Central Hub V11 — Notes module API (system containers).
 * Ported from V10: list_notes | save_note | delete_note.
 * Owner / System only for modification (guest guard lives in core/api.php).
 */
if (!defined('EMERALD_DISPATCH')) {
    http_response_code(403);
    exit;
}

if ($action === 'list_notes') {
    $notes = db_load('notes');
    $out = array();
    foreach ($notes as $nid => $note) {
        if (!is_array($note)) { continue; }
        $owner = isset($note['owner']) ? $note['owner'] : 'System';
        $note['id'] = $nid;
        $note['avatar'] = avatar_for($owner, 'https://ui-avatars.com/api/?name=' . urlencode($owner) . '&background=0ea5e9&color=fff&rounded=true&bold=true');
        $out[] = $note;
    }
    sec_json_out(array('status' => 'success', 'notes' => $out));
}

if ($action === 'save_note') {
    $notes = db_load('notes');
    $id = isset($_POST['id']) ? sec_str($_POST['id']) : '';
    if ($id === '') { $id = generateId(); }
    $raw_list = explode("\n", isset($_POST['text_list']) ? $_POST['text_list'] : '');
    $parsed_list = array();
    foreach ($raw_list as $line) {
        $cl = trim($line);
        if ($cl === '') { continue; }
        $parsed_list[] = preg_replace('/^-->\s*/', '', $cl);
    }
    $title = sec_str(isset($_POST['title']) ? $_POST['title'] : 'Untitled');
    $data = array(
        'auth' => array(
            'host' => sec_str(isset($_POST['host']) ? $_POST['host'] : ''),
            'user' => sec_str(isset($_POST['user']) ? $_POST['user'] : ''),
            'pass' => isset($_POST['pass']) ? $_POST['pass'] : '',
            'dir'  => sec_str(isset($_POST['dir']) ? $_POST['dir'] : ''),
        ),
        'list'   => implode("\n", $parsed_list),
        'status' => sec_str(isset($_POST['status']) ? $_POST['status'] : (isset($notes[$id]['data']['status']) ? $notes[$id]['data']['status'] : 'active')),
    );

    $existing_owner = isset($notes[$id]) && isset($notes[$id]['owner']) ? $notes[$id]['owner'] : $current_user;
    $notes[$id] = array(
        'id' => $id,
        'title' => $title,
        'owner' => $existing_owner,
        'timestamp' => time(),
        'data' => json_encode($data),
    );
    db_save('notes', $notes);
    logActivity($current_user, 'Configured Container: ' . $title);
    sec_json_out(array('status' => 'success', 'id' => $id));
}

if ($action === 'delete_note') {
    $notes = db_load('notes');
    $id = sec_str(isset($_POST['id']) ? $_POST['id'] : '');
    if ($id !== '' && isset($notes[$id])) {
        $owner = isset($notes[$id]['owner']) ? $notes[$id]['owner'] : 'System';
        if ($owner !== 'System' && $owner !== $current_user) {
            sec_json_err('Mutlak hanya pemilik asli yang bisa menghapus.', 'forbidden');
        }
        $title = isset($notes[$id]['title']) ? $notes[$id]['title'] : $id;
        unset($notes[$id]);
        db_save('notes', $notes);
        logActivity($current_user, 'Purged Container: ' . $title);
        sec_json_out(array('status' => 'success'));
    }
    sec_json_err('Note not found.', 'error');
}

sec_json_err('Unknown action.', 'notfound');
