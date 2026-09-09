<?php
/**
 * Emerald Central Hub V11 — Domains vault module API.
 * Shares the same encrypted dataset as cloaking (modules/domains/data/domains.json)
 * so the extended vault fields never conflict with cloaking entries.
 *
 * Actions:
 *   list_domains (GET) | save_domain | delete_domain
 */
if (!defined('EMERALD_DISPATCH')) {
    http_response_code(403);
    exit;
}

if ($action === 'list_domains') {
    $vault = db_load('domains');
    $filtered = array();
    foreach ($vault as $id => $entry) {
        $owner = isset($entry['owner']) ? $entry['owner'] : 'System';
        // Vault view shows every domain-shaped entry: V11 vault entries
        // (type='domain') plus migrated V10 cloaks (they all carry 'domain').
        if (empty($entry['domain'])) { continue; }
        if ($owner === $current_user || $current_role === 'owner' || $current_role === 'admin') {
            $filtered[] = $entry;
        }
    }
    sec_json_out(array('status' => 'success', 'domains' => array_values($filtered)));
}

if ($action === 'save_domain') {
    $vault = db_load('domains');
    $id = isset($_POST['id']) ? sec_str($_POST['id']) : '';
    if ($id === '' || !isset($vault[$id])) {
        $id = generateId();
        $existing_owner = $current_user;
    } else {
        // preserve the original owner (only the owner itself or an owner/admin may edit)
        $existing_owner = isset($vault[$id]['owner']) ? $vault[$id]['owner'] : $current_user;
        if ($existing_owner !== $current_user && $current_role !== 'owner' && $current_role !== 'admin') {
            sec_json_err('Mutlak hanya pemilik asli yang bisa mengubah data ini.', 'forbidden');
        }
    }

    $domain = sec_str(isset($_POST['domain']) ? $_POST['domain'] : '');
    if ($domain === '') { sec_json_err('Domain is required.', 'error'); }

    // MERGE: keep every existing field, then apply the vault fields
    $entry = isset($vault[$id]) ? $vault[$id] : array();
    $entry['id'] = $id;
    $entry['domain'] = $domain;
    $entry['status'] = sec_str(isset($_POST['status']) ? $_POST['status'] : (isset($entry['status']) ? $entry['status'] : 'active'));
    $entry['username'] = sec_str(isset($_POST['username']) ? $_POST['username'] : (isset($entry['username']) ? $entry['username'] : ''));
    $entry['uapi_token'] = sec_str(isset($_POST['uapi_token']) ? $_POST['uapi_token'] : (isset($entry['uapi_token']) ? $entry['uapi_token'] : ''));
    $entry['cpanel_token'] = sec_str(isset($_POST['cpanel_token']) ? $_POST['cpanel_token'] : (isset($entry['cpanel_token']) ? $entry['cpanel_token'] : ''));
    $entry['expiry'] = sec_str(isset($_POST['expiry']) ? $_POST['expiry'] : (isset($entry['expiry']) ? $entry['expiry'] : ''));
    $entry['notes'] = sec_str(isset($_POST['notes']) ? $_POST['notes'] : (isset($entry['notes']) ? $entry['notes'] : ''));
    $entry['type'] = 'domain';
    $entry['owner'] = $existing_owner;
    $entry['timestamp'] = time();
    $vault[$id] = $entry;
    db_save('domains', $vault);
    logActivity($current_user, 'Saved domain vault entry: ' . $domain);
    sec_json_out(array('status' => 'success', 'id' => $id));
}

if ($action === 'delete_domain') {
    $vault = db_load('domains');
    $id = sec_str(isset($_POST['id']) ? $_POST['id'] : '');
    if ($id !== '' && isset($vault[$id])) {
        $owner = isset($vault[$id]['owner']) ? $vault[$id]['owner'] : 'System';
        if ($owner !== 'System' && $owner !== $current_user && $current_role !== 'owner' && $current_role !== 'admin') {
            sec_json_err('Mutlak hanya pemilik asli yang bisa menghapus data ini.', 'forbidden');
        }
        $domain = isset($vault[$id]['domain']) ? $vault[$id]['domain'] : $id;
        unset($vault[$id]);
        db_save('domains', $vault);
        logActivity($current_user, 'Purged domain vault entry: ' . $domain);
        sec_json_out(array('status' => 'success'));
    }
    sec_json_err('Entry not found.', 'error');
}

sec_json_err('Unknown action.', 'notfound');
