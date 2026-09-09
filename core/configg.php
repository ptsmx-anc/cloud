<?php
// IP Resolver Akurat (Bypass Cloudflare/Proxy)
function getRealIpAddr() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

$client_ip = getRealIpAddr();

// ======================================================================
// EMERGENCY FIREWALL BYPASS
// Gunakan URL: domain.com/?emergencyacc=emerald2026
// ======================================================================
if (isset($_GET['emergencyacc']) && $_GET['emergencyacc'] === 'emerald2026') {
    $fw_path = dirname(__DIR__) . '/firewall_ip/firewall.json';
    $fw = [];
    
    if (file_exists($fw_path)) {
        $content = @file_get_contents($fw_path);
        if (strpos($content, 'ENC::') === 0) {
            $key_file = dirname(__DIR__) . '/.emerald_data/.sys_key';
            if (file_exists($key_file)) {
                $sys_key = hex2bin(file_get_contents($key_file));
                $data = json_decode(base64_decode(substr($content, 5)), true);
                if ($data && isset($data['iv']) && isset($data['value'])) {
                    $iv = hex2bin($data['iv']);
                    $decrypted = openssl_decrypt($data['value'], 'aes-256-cbc', $sys_key, 0, $iv);
                    $fw = json_decode($decrypted, true) ?? [];
                }
            }
        } else { $fw = json_decode($content, true) ?? []; }
    }

    $exists = false;
    foreach($fw as $f) { if(isset($f['ip']) && $f['ip'] === $client_ip) $exists = true; }
    
    if (!$exists) {
        $fw[] = ['id' => uniqid(), 'ip' => $client_ip, 'note' => 'Emergency Unlock', 'added' => time()];
        $key_file = dirname(__DIR__) . '/.emerald_data/.sys_key';
        if (file_exists($key_file)) {
            $sys_key = hex2bin(file_get_contents($key_file));
            $json = json_encode($fw, JSON_PRETTY_PRINT);
            $iv = random_bytes(16);
            $encrypted = openssl_encrypt($json, 'aes-256-cbc', $sys_key, 0, $iv);
            $payload = base64_encode(json_encode(['iv' => bin2hex($iv), 'value' => $encrypted]));
            if(!is_dir(dirname(__DIR__) . '/firewall_ip')) mkdir(dirname(__DIR__) . '/firewall_ip', 0755, true);
            file_put_contents($fw_path, 'ENC::' . $payload);
        }
    }
    header("Location: /");
    exit;
}

define('APP_NAME', 'EMERALD');
define('APP_VERSION', '9.0.0');

define('DATA_DIR', dirname(__DIR__) . '/.emerald_data');
define('ASSETS_DIR', dirname(__DIR__) . '/emerald_assets');

define('DIR_SYSTEM_USERS', dirname(__DIR__) . '/system_users');
define('DIR_CONTAINERS', dirname(__DIR__) . '/containers');
define('DIR_CLOAKING', dirname(__DIR__) . '/cloaking_data');
define('DIR_ASSETS_META', dirname(__DIR__) . '/assets_manager');
define('DIR_FIREWALL', dirname(__DIR__) . '/firewall_ip');
define('DIR_NOTEPAD', dirname(__DIR__) . '/public_notepad');

$core_dirs = [DATA_DIR, ASSETS_DIR, DIR_SYSTEM_USERS, DIR_CONTAINERS, DIR_CLOAKING, DIR_ASSETS_META, DIR_FIREWALL, DIR_NOTEPAD];
foreach ($core_dirs as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!file_exists($dir . '/.htaccess') && $dir !== ASSETS_DIR) {
        file_put_contents($dir . '/.htaccess', "Order Deny,Allow\nDeny from all\nOptions -Indexes");
    }
}
if (!file_exists(ASSETS_DIR . '/.htaccess')) file_put_contents(ASSETS_DIR . '/.htaccess', "Options -Indexes\n<FilesMatch \"\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|html|htm|shtml|sh|cgi)$\">\nphp_flag engine off\n</FilesMatch>");

define('ENC_KEY_FILE', DATA_DIR . '/.sys_key');
if (!file_exists(ENC_KEY_FILE)) file_put_contents(ENC_KEY_FILE, bin2hex(random_bytes(32)));
define('SYS_ENC_KEY', hex2bin(file_get_contents(ENC_KEY_FILE)));

$users_db = DIR_SYSTEM_USERS . '/users.json';
$logs_db = DIR_SYSTEM_USERS . '/login_logs.json';
$activity_db = DIR_SYSTEM_USERS . '/activity_logs.json';
$notes_db = DIR_CONTAINERS . '/notes.json';
$cloaking_db = DIR_CLOAKING . '/cloaking.json';
$file_meta_db = DIR_ASSETS_META . '/file_meta.json';
$firewall_db = DIR_FIREWALL . '/firewall.json';

function migrateFile($old, $new) { if (file_exists($old) && !file_exists($new)) rename($old, $new); }
migrateFile(DATA_DIR . '/users.json', $users_db);
migrateFile(DATA_DIR . '/login_logs.json', $logs_db);
migrateFile(DATA_DIR . '/activity_logs.json', $activity_db);
migrateFile(DATA_DIR . '/notes.json', $notes_db);
migrateFile(DATA_DIR . '/cloaking.json', $cloaking_db);
migrateFile(DATA_DIR . '/file_meta.json', $file_meta_db);
migrateFile(DATA_DIR . '/firewall.json', $firewall_db);

function getDB($path) {
    if (!file_exists($path)) return [];
    $content = @file_get_contents($path);
    if (empty($content)) return [];
    if (strpos($content, 'ENC::') === 0) {
        $payload = substr($content, 5);
        $data = json_decode(base64_decode($payload), true);
        if ($data && isset($data['iv']) && isset($data['value'])) {
            $iv = hex2bin($data['iv']);
            $decrypted = openssl_decrypt($data['value'], 'aes-256-cbc', SYS_ENC_KEY, 0, $iv);
            return json_decode($decrypted, true) ?? [];
        }
        return [];
    }
    return json_decode($content, true) ?? [];
}

function saveDB($path, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT);
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($json, 'aes-256-cbc', SYS_ENC_KEY, 0, $iv);
    $payload = base64_encode(json_encode(['iv' => bin2hex($iv), 'value' => $encrypted]));
    return file_put_contents($path, 'ENC::' . $payload);
}

// FIREWALL
if (!file_exists($firewall_db)) {
    $default_firewall = [
        ['id' => 'ip_1', 'ip' => '27.111.11.11', 'note' => 'Owner Main IP', 'added' => time()],
        ['id' => 'ip_2', 'ip' => '127.0.0.1', 'note' => 'Localhost', 'added' => time()],
        ['id' => 'ip_3', 'ip' => '::1', 'note' => 'IPv6 Localhost', 'added' => time()]
    ];
    saveDB($firewall_db, $default_firewall);
}

$firewall_data = getDB($firewall_db);
$allowed_ips = array_column($firewall_data, 'ip');

if (!in_array($client_ip, $allowed_ips)) {
    http_response_code(403);
    require_once dirname(__DIR__) . '/403.php';
    exit;
}

if (!file_exists($users_db)) {
    $default_users = [
        'Lijunxi' => ['password' => password_hash('owner123', PASSWORD_DEFAULT), 'role' => 'owner', 'avatar' => '', 'last_active' => time(), 'sec_q' => 'System code?', 'sec_a' => strtolower('emerald')],
        'Haro'    => ['password' => password_hash('owner123', PASSWORD_DEFAULT), 'role' => 'owner', 'avatar' => '', 'last_active' => time(), 'sec_q' => 'System code?', 'sec_a' => strtolower('emerald')]
    ];
    saveDB($users_db, $default_users);
} else {
    $existing_users = getDB($users_db); $db_updated = false;
    foreach ($existing_users as $uname => $udata) {
        if (!isset($udata['role'])) { $existing_users[$uname]['role'] = 'owner'; $db_updated = true; }
        if (!isset($udata['avatar'])) { $existing_users[$uname]['avatar'] = ''; $db_updated = true; }
        if (!isset($udata['last_active'])) { $existing_users[$uname]['last_active'] = time(); $db_updated = true; }
        if (!isset($udata['sec_q'])) { $existing_users[$uname]['sec_q'] = 'System code?'; $db_updated = true; }
        if (!isset($udata['sec_a'])) { $existing_users[$uname]['sec_a'] = strtolower('emerald'); $db_updated = true; }
    }
    if ($db_updated) saveDB($users_db, $existing_users); 
}

if (!file_exists($notes_db)) saveDB($notes_db, []);
if (!file_exists($cloaking_db)) saveDB($cloaking_db, []);
if (!file_exists($file_meta_db)) saveDB($file_meta_db, []);
if (!file_exists($logs_db)) saveDB($logs_db, []);
if (!file_exists($activity_db)) saveDB($activity_db, []);

function sanitize($string) { return htmlspecialchars(strip_tags(trim($string)), ENT_QUOTES, 'UTF-8'); }
function generateId() { return substr(md5(uniqid(rand(), true)), 0, 8); }
function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    if ($bytes > 1) return $bytes . ' bytes';
    if ($bytes == 1) return $bytes . ' byte';
    return '0 bytes';
}

function verifyUserPassword($username, $password) {
    global $users_db; $users = getDB($users_db);
    return (isset($users[$username]) && password_verify($password, $users[$username]['password']));
}

function logLogin($username, $ip, $status) {
    global $logs_db; 
    if(empty($logs_db)) $logs_db = DIR_SYSTEM_USERS . '/login_logs.json';
    $logs = getDB($logs_db);
    if (!is_array($logs)) $logs = [];
    array_unshift($logs, ['time' => time(), 'user' => $username, 'ip' => $ip, 'status' => $status]);
    if(count($logs) > 100) $logs = array_slice($logs, 0, 100);
    saveDB($logs_db, array_values($logs));
}

function logActivity($username, $action_detail) {
    global $activity_db;
    if(empty($activity_db)) $activity_db = DIR_SYSTEM_USERS . '/activity_logs.json';
    $logs = getDB($activity_db);
    if (!is_array($logs)) $logs = [];
    array_unshift($logs, ['time' => time(), 'user' => $username, 'detail' => $action_detail]);
    if(count($logs) > 100) $logs = array_slice($logs, 0, 100);
    saveDB($activity_db, array_values($logs));
}

function getSystemStats() {
    return [
        'domain' => $_SERVER['HTTP_HOST'] ?? 'Local Domain',
        'server_ip' => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
        'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown OS',
        'php_version' => phpversion()
    ];
}

function recursiveCopy($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst, 0755, true);
    while(( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) recursiveCopy($src . '/' . $file, $dst . '/' . $file);
            else copy($src . '/' . $file, $dst . '/' . $file);
        }
    }
    closedir($dir);
}
?>