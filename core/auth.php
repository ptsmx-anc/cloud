<?php
/**
 * Emerald Central Hub — V11 authentication
 *  - Login with bcrypt verification (V10-compatible) + master-key fallback
 *  - Emergency account (?emergencyacc=...) that whitelists the client IP
 *  - Security-question password reset (preserved from V10)
 *  - Session expiry + login rate limiting
 * PHP 7.0+ compatible.
 */
if (!function_exists('auth_start')) {

    function auth_start() {
        sec_start_session();
        // Session expiry (matches V10: 2700s)
        if (!empty($_SESSION['emerald_last']) && (time() - (int)$_SESSION['emerald_last']) > SESSION_EXPIRE) {
            $_SESSION = array();
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            @session_destroy();
            @session_start();
            sec_csrf_token();
        }
        $_SESSION['emerald_last'] = time();
        return true;
    }

    function auth_user() {
        return isset($_SESSION['emerald_user']) ? $_SESSION['emerald_user'] : null;
    }

    function auth_role() {
        $u = auth_user();
        if ($u === null) { return 'guest'; }
        $users = db_load('users');
        return isset($users[$u]['role']) ? $users[$u]['role'] : 'user';
    }

    function auth_require() {
        auth_start();
        $u = auth_user();
        if ($u === null) {
            header('Location: index.php');
            exit;
        }
        return $u;
    }

    /* ------------------------------------------------------------------ *
     *  Login
     *  NOTE: password verification lives in core/db.php as
     *  verifyUserPassword($username, $password) — V10-compatible (bcrypt,
     *  legacy md5 and master key Lk7w1fvntg1). Do not redeclare it here.
     * ------------------------------------------------------------------ */
    function auth_login($uname, $password) {
        auth_start();
        $uname = sec_str($uname);
        $ip = sec_client_ip();

        if (sec_rate_blocked($uname) || sec_rate_blocked('')) {
            db_log_login($uname, $ip, 'Blocked (rate limit)');
            return array('status' => 'error', 'message' => 'Too many failed attempts. Please wait a few minutes.');
        }

        $users = db_load('users');
        $ok = ($uname !== '' && verifyUserPassword($uname, $password));

        if (!$ok) {
            sec_rate_hit($uname);
            sec_rate_hit('');
            db_log_login($uname, $ip, 'Failed');
            return array('status' => 'error', 'message' => 'Invalid username or password.');
        }

        sec_rate_clear($uname);
        sec_rate_clear('');
        session_regenerate_id(true);
        $_SESSION['emerald_user'] = $uname;
        $_SESSION['emerald_role'] = isset($users[$uname]['role']) ? $users[$uname]['role'] : 'user';
        $_SESSION['csrf'] = sec_csrf_token();

        $users[$uname]['last_active'] = time();
        db_save('users', $users);
        db_log_login($uname, $ip, 'Success');
        db_log_activity($uname, 'Logged in');

        return array('status' => 'ok', 'user' => $uname, 'role' => $_SESSION['emerald_role']);
    }

    function auth_logout() {
        auth_start();
        $u = auth_user();
        if ($u !== null) { db_log_activity($u, 'Logged out'); }
        $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        @session_destroy();
    }

    /* ------------------------------------------------------------------ *
     *  Security-question password reset (V10-compatible)
     * ------------------------------------------------------------------ */
    function auth_get_sec_q($uname) {
        $users = db_load('users');
        if (!isset($users[$uname])) { return null; }
        return array('uname' => $uname, 'q' => isset($users[$uname]['sec_q']) ? $users[$uname]['sec_q'] : '');
    }

    function auth_reset_pass($uname, $secA, $newPass) {
        if (strlen((string)$newPass) < 6) {
            return array('status' => 'error', 'message' => 'New password must be at least 6 characters.');
        }
        $users = db_load('users');
        if (!isset($users[$uname])) {
            return array('status' => 'error', 'message' => 'User not found.');
        }
        $expected = isset($users[$uname]['sec_a']) ? (string)$users[$uname]['sec_a'] : '';
        if ($expected === '') { $expected = 'emerald'; }   // V10 default answer
        if (strtolower(trim((string)$secA)) !== strtolower(trim($expected))) {
            return array('status' => 'error', 'message' => 'Security answer is incorrect.');
        }
        $users[$uname]['password'] = password_hash((string)$newPass, PASSWORD_DEFAULT);
        $users[$uname]['last_active'] = time();
        db_save('users', $users);
        db_log_activity($uname, 'Password reset via security question');
        return array('status' => 'ok', 'message' => 'Password updated. You can now log in.');
    }

    /* ------------------------------------------------------------------ *
     *  Emergency account (?emergencyacc=emerald2026)
     *  V10 behaviour: whitelist client IP in firewall + log in as owner.
     * ------------------------------------------------------------------ */
    function auth_emergency($token) {
        if ($token !== 'emerald2026') { return null; }
        $ip = sec_client_ip();
        // Persist client IP into the firewall (V10 behaviour)
        $fw = db_load('firewall');
        if (!is_array($fw)) { $fw = array(); }
        $exists = false;
        foreach ($fw as $e) {
            if (isset($e['ip']) && $e['ip'] === $ip) { $exists = true; break; }
        }
        if (!$exists) {
            $fw[] = array('id' => 'emrg' . substr(md5($ip . time()), 0, 6), 'ip' => $ip, 'note' => 'Emergency access', 'added' => time(), 'owner' => 'System');
            db_save('firewall', $fw);
        }
        // Log in as first owner account (fallback: Lijunxi/Haro)
        $users = db_load('users');
        $target = null;
        foreach (array('Lijunxi', 'Haro') as $cand) {
            if (isset($users[$cand])) { $target = $cand; break; }
        }
        if ($target === null) { $keys = array_keys($users); $target = count($keys) ? $keys[0] : 'Lijunxi'; }
        session_regenerate_id(true);
        $_SESSION['emerald_user'] = $target;
        $_SESSION['emerald_role'] = isset($users[$target]['role']) ? $users[$target]['role'] : 'owner';
        $_SESSION['emerald_last'] = time();
        $_SESSION['csrf'] = sec_csrf_token();
        db_log_activity($target, 'Emergency access (' . $ip . ')');
        return $target;
    }
}
