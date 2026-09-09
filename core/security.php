<?php
/**
 * Emerald Central Hub — V11 security helpers
 *  - Session cookie hardening (httponly, SameSite=Lax, secure when HTTPS)
 *  - CSRF token issuance + verification
 *  - Login rate limiting (file-based, survives restart)
 *  - Input sanitising helpers
 * PHP 7.0+ compatible.
 */
if (!function_exists('sec_start_session')) {

    function sec_start_session() {
        if (session_status() === PHP_SESSION_ACTIVE) { return; }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        session_name(SESSION_NAME);
        session_set_cookie_params(array(
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
        @session_start();
    }

    function sec_csrf_token() {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(function_exists('random_bytes') ? random_bytes(32) : openssl_random_pseudo_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    function sec_csrf_field() {
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars(sec_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    function sec_csrf_verify($token = null) {
        if (PHP_SAPI === 'cli') { return true; }
        if ($token === null) { $token = isset($_POST['csrf']) ? $_POST['csrf'] : ''; }
        if (!is_string($token) || $token === '') { return false; }
        return hash_equals(sec_csrf_token(), $token);
    }

    /* ------------------------------------------------------------------ *
     *  Login rate limiting — per-IP + per-username, file backed.
     * ------------------------------------------------------------------ */
    function sec_rate_key() {
        return isset($_SERVER['REMOTE_ADDR']) ? preg_replace('/[^0-9a-fA-F:.\-]/', '', $_SERVER['REMOTE_ADDR']) : 'local';
    }

    function sec_rate_attempts($user = '') {
        $key = 'ip:' . sec_rate_key() . ($user !== '' ? '|u:' . strtolower($user) : '');
        $hash = 'rl_' . substr(hash('sha256', $key), 0, 16) . '.json';
        $file = DATA_DIR . '/rate/' . $hash;
        if (!is_file($file)) { return 0; }
        $raw = json_decode((string)file_get_contents($file), true);
        if (!is_array($raw) || empty($raw['t']) || (time() - $raw['t']) > RATE_LIMIT_WINDOW) {
            @unlink($file);
            return 0;
        }
        return (int)$raw['n'];
    }

    function sec_rate_hit($user = '') {
        $key = 'ip:' . sec_rate_key() . ($user !== '' ? '|u:' . strtolower($user) : '');
        $hash = 'rl_' . substr(hash('sha256', $key), 0, 16) . '.json';
        $dir = DATA_DIR . '/rate';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        $file = $dir . '/' . $hash;
        $n = 1; $t = time();
        if (is_file($file)) {
            $raw = json_decode((string)file_get_contents($file), true);
            if (is_array($raw)) {
                $n = ((time() - (int)$raw['t']) > RATE_LIMIT_WINDOW) ? 1 : ((int)$raw['n'] + 1);
                $t = (int)$raw['t'];
            }
        }
        @file_put_contents($file, json_encode(array('n' => $n, 't' => $t)));
    }

    function sec_rate_clear($user = '') {
        $key = 'ip:' . sec_rate_key() . ($user !== '' ? '|u:' . strtolower($user) : '');
        $hash = 'rl_' . substr(hash('sha256', $key), 0, 16) . '.json';
        @unlink(DATA_DIR . '/rate/' . $hash);
    }

    function sec_rate_blocked($user = '') {
        return sec_rate_attempts($user) >= RATE_LIMIT_MAX;
    }

    /* ------------------------------------------------------------------ *
     *  Input helpers
     * ------------------------------------------------------------------ */
    function sec_str($v) {
        $v = (string)$v;
        $v = str_replace(array("\0", "\r"), '', $v);
        return $v;
    }
    function sec_html($v) { return htmlspecialchars(sec_str($v), ENT_QUOTES, 'UTF-8'); }
    function sec_clean_path($p) {
        $p = str_replace(array('\\', "\0"), '/', (string)$p);
        $p = preg_replace('#/+#', '/', $p);
        $out = array();
        foreach (explode('/', $p) as $part) {
            if ($part === '' || $part === '.') { continue; }
            if ($part === '..') { array_pop($out); continue; }
            $out[] = $part;
        }
        return implode('/', $out);
    }
    function sec_json_out($data) {
        if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }
        echo json_encode($data);
        exit;
    }
    function sec_json_err($msg, $code = 'error') {
        sec_json_out(array('status' => $code, 'message' => $msg));
    }
    function sec_client_ip() {
        foreach (array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR') as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if ($ip !== '' && $ip !== 'unknown') { return $ip; }
            }
        }
        return '127.0.0.1';
    }
}
