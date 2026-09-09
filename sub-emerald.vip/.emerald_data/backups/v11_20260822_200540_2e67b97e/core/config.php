<?php
/**
 * Emerald Central Hub — V11 configuration
 * Generated/restructured by update.php (V10 → V11).
 *
 * IMPORTANT compatibility contract (kept identical to V10):
 *  - ASSETS_DIR still points to the user file vault (emerald_assets/)
 *  - ALLOW_PUBLIC_VIEW=true (set by view.php/vieww.php) bypasses the firewall
 *  - Firewall is enforced the same way (403 unless IP whitelisted or public view)
 *  - Missing firewall/users DBs are auto-seeded with the V10 defaults
 * PHP 7.0+ compatible.
 */
if (!defined('APP_VERSION')) {

    /* ------------------------------------------------------------------ *
     *  Identity
     * ------------------------------------------------------------------ */
    define('APP_NAME', 'Emerald Central Hub');
    define('APP_VERSION', '11.0.0');
    define('APP_TAGLINE', 'Domains, Storage, Cloaking & Notes — one vault.');

    /* ------------------------------------------------------------------ *
     *  Paths (relative to this file: app_root/core/)
     * ------------------------------------------------------------------ */
    define('APP_ROOT',    dirname(__DIR__));
    define('CORE_DIR',    APP_ROOT . '/core');
    define('VIEWS_DIR',   APP_ROOT . '/views');
    define('MODULES_DIR', APP_ROOT . '/modules');
    define('ASSETS_DIR',  APP_ROOT . '/emerald_assets');   // user file vault (V10)
    define('DATA_DIR',    APP_ROOT . '/.emerald_data');
    define('BACKUP_DIR',  DATA_DIR . '/backups');
    define('ENC_PREFIX',  'ENC::');

    /* ------------------------------------------------------------------ *
     *  Firewall defaults (same as V10)
     * ------------------------------------------------------------------ */
    define('FIREWALL_DEFAULT_IPS', '27.111.11.11,127.0.0.1,::1');

    /* ------------------------------------------------------------------ *
     *  Sessions / hardening
     * ------------------------------------------------------------------ */
    define('SESSION_NAME',   'emerald_sid');
    define('SESSION_EXPIRE', 2700);                 // seconds (matches V10)
    define('RATE_LIMIT_MAX', 6);                    // failed login attempts
    define('RATE_LIMIT_WINDOW', 300);               // seconds

    /* ------------------------------------------------------------------ *
     *  Public view (single-file .txt previews) — set by view.php/vieww.php
     * ------------------------------------------------------------------ */
    if (!defined('ALLOW_PUBLIC_VIEW')) { define('ALLOW_PUBLIC_VIEW', false); }

    /* ------------------------------------------------------------------ *
     *  Dataset path registry
     *  - update.php writes .emerald_data/v11_paths.php (auto-generated).
     *  - Keys match the dataset names used across core/db.php.
     * ------------------------------------------------------------------ */
    $__v11paths = array();
    $__reg = DATA_DIR . '/v11_paths.php';
    if (is_file($__reg)) {
        $__loaded = @include($__reg);
        if (is_array($__loaded)) { $__v11paths = $__loaded; }
        unset($__loaded);
    }
    unset($__reg);
    if (empty($__v11paths)) {
        $__v11paths = array(
            'users'         => 'modules/users/data/users.json',
            'login_logs'    => 'modules/users/data/login_logs.json',
            'activity_logs' => 'modules/users/data/activity_logs.json',
            'notes'         => 'modules/notes/data/notes.json',
            'cloaking'      => 'modules/domains/data/domains.json',
            'domains'       => 'modules/domains/data/domains.json',   // shared vault with cloaking
            'file_meta'     => 'modules/storage/data/file_meta.json',
            'firewall'      => 'modules/firewall/data/firewall.json',
        );
    }
    foreach ($__v11paths as $__k => $__rel) {
        if (!defined('DB_' . strtoupper($__k))) {
            define('DB_' . strtoupper($__k), APP_ROOT . '/' . $__rel);
        }
    }
    unset($__v11paths, $__k, $__rel);

    /* ------------------------------------------------------------------ *
     *  Encryption key — hex2bin is whitespace-sensitive on PHP 8.x,
     *  so the key file is ALWAYS trimmed before use.
     * ------------------------------------------------------------------ */
    function v11_load_key() {
        $f = DATA_DIR . '/.sys_key';
        if (is_file($f)) {
            $raw = trim((string)file_get_contents($f));
            if (preg_match('/^[0-9a-fA-F]{32,128}$/', $raw)) { return $raw; }
        }
        if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0755, true); }
        $bytes = function_exists('random_bytes') ? random_bytes(32)
               : (function_exists('openssl_random_pseudo_bytes') ? openssl_random_pseudo_bytes(32)
               : md5(mt_rand() . microtime() . uniqid('', true), true) . md5(uniqid('', true) . microtime() . mt_rand(), true));
        $hex = bin2hex($bytes);
        @file_put_contents($f, $hex . PHP_EOL);
        return $hex;
    }

    define('MASTER_KEY_HEX', v11_load_key());

    /* ------------------------------------------------------------------ *
     *  Security question fallback (used by password reset)
     * ------------------------------------------------------------------ */
    define('SEC_QUESTIONS', serialize(array(
        'What is your mother\'s maiden name?',
        'What was the name of your first pet?',
        'What city were you born in?',
        'What is your favorite teacher\'s name?',
        'What is the name of your first school?',
    )));

    /* ------------------------------------------------------------------ *
     *  Core services (db + security) — both guarded against re-inclusion
     * ------------------------------------------------------------------ */
    require_once CORE_DIR . '/security.php';
    require_once CORE_DIR . '/db.php';

    /* ------------------------------------------------------------------ *
     *  Data directory protection (V10 behaviour)
     * ------------------------------------------------------------------ */
    foreach (array(DATA_DIR, CORE_DIR, VIEWS_DIR, MODULES_DIR, BACKUP_DIR) as $__d) {
        if (!is_dir($__d)) { @mkdir($__d, 0755, true); }
    }

    /* ------------------------------------------------------------------ *
     *  Auto-seed missing datasets with V10 defaults (never touches existing)
     * ------------------------------------------------------------------ */
    if (!is_file(DB_FIREWALL) || filesize(DB_FIREWALL) === 0) {
        if (count(db_load('firewall')) === 0) {
            $now = time();
            $def = array();
            foreach (explode(',', FIREWALL_DEFAULT_IPS) as $i => $ip) {
                $def[] = array('id' => 'ip_' . ($i + 1), 'ip' => trim($ip), 'note' => ($i === 0 ? 'Owner Main IP' : 'Localhost'), 'added' => $now, 'owner' => 'System');
            }
            db_save('firewall', $def);
        }
    }
    if (!is_file(DB_USERS) || filesize(DB_USERS) === 0) {
        if (count(db_load('users')) === 0) {
            $now = time();
            $du = array(
                'Lijunxi' => array('password' => password_hash('owner123', PASSWORD_DEFAULT), 'role' => 'owner', 'avatar' => '', 'last_active' => $now, 'sec_q' => 'System code?', 'sec_a' => 'emerald'),
                'Haro'    => array('password' => password_hash('owner123', PASSWORD_DEFAULT), 'role' => 'owner', 'avatar' => '', 'last_active' => $now, 'sec_q' => 'System code?', 'sec_a' => 'emerald'),
            );
            db_save('users', $du);
        }
    }

    /* ------------------------------------------------------------------ *
     *  Firewall enforcement (identical behaviour to V10)
     *  view.php/vieww.php set ALLOW_PUBLIC_VIEW=true BEFORE including this
     *  file, which lets public .txt previews bypass the IP check.
     * ------------------------------------------------------------------ */
    function getRealIpAddr() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) { return $_SERVER['HTTP_CF_CONNECTING_IP']; }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }

    $fwData = db_load('firewall');
    $allowedIps = array();
    foreach ($fwData as $__f) {
        if (isset($__f['ip']) && $__f['ip'] !== '') { $allowedIps[] = $__f['ip']; }
    }
    unset($__f);
    $clientIp = getRealIpAddr();
    if (!in_array($clientIp, $allowedIps)) {
        if (ALLOW_PUBLIC_VIEW !== true) {
            http_response_code(403);
            $__f403 = APP_ROOT . '/403.php';
            if (is_file($__f403)) { require_once $__f403; }
            exit;
        }
    }
    unset($fwData, $allowedIps, $clientIp);
}
