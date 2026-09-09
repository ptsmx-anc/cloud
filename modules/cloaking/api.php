<?php
/**
 * Emerald Central Hub V11 — Cloaking module API.
 *
 * V11 FIX over V10: save_cloaking now MERGES into the existing entry
 * instead of overwriting it, so the extended Domains vault fields
 * (status, username, uapi_token, cpanel_token, expiry, notes) are
 * preserved when a cloak is updated. Both datasets share one encrypted
 * file (modules/domains/data/domains.json).
 *
 * Actions:
 *   list_cloaking (GET) | save_cloaking | delete_cloaking
 */
if (!defined('EMERALD_DISPATCH')) {
    http_response_code(403);
    exit;
}

if ($action === 'list_cloaking') {
    $cloaks = db_load('cloaking');
    $filtered = array();
    foreach ($cloaks as $cid => $c) {
        $owner = isset($c['owner']) ? $c['owner'] : 'System';
        $type = isset($c['type']) ? $c['type'] : 'personal';
        // only cloak-shaped entries (domain vault entries stay in the Domains view)
        if ($type === 'domain') { continue; }
        if ($type === 'global' || $owner === $current_user || $current_role === 'owner' || $current_role === 'admin') {
            $c['id'] = $cid;
            $c['avatar'] = avatar_for($owner, 'https://ui-avatars.com/api/?name=' . urlencode($owner) . '&background=0ea5e9&color=fff&rounded=true&bold=true');
            $filtered[] = $c;
        }
    }
    sec_json_out(array('status' => 'success', 'cloaks' => array_values($filtered)));
}

if ($action === 'save_cloaking') {
    $cloaks = db_load('cloaking');
    $id = isset($_POST['id']) ? sec_str($_POST['id']) : '';
    if ($id === '' || !isset($cloaks[$id])) {
        $id = generateId();
        $existing_owner = $current_user;
    } else {
        $existing_owner = isset($cloaks[$id]['owner']) ? $cloaks[$id]['owner'] : $current_user;
        if ($existing_owner !== $current_user && $current_role !== 'owner' && $current_role !== 'admin') {
            sec_json_err('Mutlak hanya pemilik asli yang bisa mengubah data ini.', 'forbidden');
        }
    }

    $domain = sec_str(isset($_POST['domain']) ? $_POST['domain'] : '');
    if ($domain === '') { sec_json_err('Domain is required.', 'error'); }

    // MERGE (V11 fix): keep every existing field — including extended vault
    // fields such as status/uapi_token/cpanel_token/expiry/notes — then
    // apply only the cloak-managed fields.
    $entry = isset($cloaks[$id]) ? $cloaks[$id] : array();
    $entry['id'] = $id;
    $entry['domain'] = $domain;
    $entry['path'] = sec_str(isset($_POST['path']) ? $_POST['path'] : (isset($entry['path']) ? $entry['path'] : ''));
    $entry['content'] = isset($_POST['content']) ? $_POST['content'] : (isset($entry['content']) ? $entry['content'] : '');
    $entry['type'] = sec_str(isset($_POST['type']) ? $_POST['type'] : (isset($entry['type']) ? $entry['type'] : 'personal'));
    $entry['owner'] = $existing_owner;
    $entry['timestamp'] = time();
    $cloaks[$id] = $entry;
    db_save('cloaking', $cloaks);
    logActivity($current_user, 'Deployed Cloak for: ' . $domain);
    sec_json_out(array('status' => 'success', 'id' => $id));
}

if ($action === 'delete_cloaking') {
    $cloaks = db_load('cloaking');
    $id = sec_str(isset($_POST['id']) ? $_POST['id'] : '');
    if ($id !== '' && isset($cloaks[$id])) {
        $owner = isset($cloaks[$id]['owner']) ? $cloaks[$id]['owner'] : 'System';
        if ($owner !== 'System' && $owner !== $current_user && $current_role !== 'owner' && $current_role !== 'admin') {
            sec_json_err('Mutlak hanya pemilik asli yang bisa menghapus data ini.', 'forbidden');
        }
        $domain = isset($cloaks[$id]['domain']) ? $cloaks[$id]['domain'] : $id;
        unset($cloaks[$id]);
        db_save('cloaking', $cloaks);
        logActivity($current_user, 'Purged Cloak: ' . $domain);
        sec_json_out(array('status' => 'success'));
    }
    sec_json_err('Cloak not found.', 'error');
}

sec_json_err('Unknown action.', 'notfound');
