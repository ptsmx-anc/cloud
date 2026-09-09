<?php
/**
 * Emerald Central Hub V11 — Firewall module API.
 * Ported from V10: list_firewall | add_firewall | delete_firewall.
 * delete_firewall: owner must match OR role is 'owner' (V10 rule).
 */
if (!defined('EMERALD_DISPATCH')) {
    http_response_code(403);
    exit;
}

if ($action === 'list_firewall') {
    sec_json_out(array('status' => 'success', 'entries' => array_values(db_load('firewall'))));
}

if ($action === 'add_firewall') {
    $fw = db_load('firewall');
    $ip = sec_str(isset($_POST['ip']) ? $_POST['ip'] : '');
    $note = sec_str(isset($_POST['note']) ? $_POST['note'] : '');
    if ($ip === '' || !preg_match('/^[0-9a-fA-F:.\-]+$/', $ip)) {
        sec_json_err('Invalid IP address.', 'error');
    }
    $fw[] = array('id' => generateId(), 'ip' => $ip, 'note' => $note, 'added' => time(), 'owner' => $current_user);
    db_save('firewall', $fw);
    logActivity($current_user, 'Whitelisted IP: ' . $ip);
    sec_json_out(array('status' => 'success'));
}

if ($action === 'delete_firewall') {
    $fw = db_load('firewall');
    $id = sec_str(isset($_POST['id']) ? $_POST['id'] : '');
    $target_key = null;
    $target_fw = null;
    foreach ($fw as $key => $val) {
        if (isset($val['id']) && $val['id'] === $id) { $target_fw = $val; $target_key = $key; break; }
    }
    if ($target_fw === null) { sec_json_err('Not found.', 'error'); }

    $owner = isset($target_fw['owner']) ? $target_fw['owner'] : 'System';
    if ($owner !== $current_user && $current_role !== 'owner') {
        sec_json_err('Mutlak hanya pemilik asli (' . $owner . ') yang bisa menghapus.', 'forbidden');
    }

    logActivity($current_user, 'Removed IP from Whitelist: ' . $target_fw['ip']);
    unset($fw[$target_key]);
    db_save('firewall', array_values($fw));
    sec_json_out(array('status' => 'success'));
}

sec_json_err('Unknown action.', 'notfound');
